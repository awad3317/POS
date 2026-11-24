<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ShiftService $shiftService,
    ) {}

    /**
     * @return array{status: string, order: Order}
     */
    public function createReturn(Order $parentOrder, array $data, User $actor): array
    {
        if ((int) $parentOrder->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Cannot return an order from another store.');
        }

        $clientUuid = (string) ($data['client_uuid'] ?? Str::uuid());
        $existing = Order::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant return UUID conflict.');
            }

            return [
                'status' => 'duplicate_ok',
                'order' => $existing->load(['items', 'payments']),
            ];
        }

        return DB::transaction(function () use ($parentOrder, $data, $actor, $clientUuid): array {
            $store = Store::query()->findOrFail($actor->store_id);

            $lockedParent = Order::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($parentOrder->id);

            if ($lockedParent->type !== 'sale') {
                throw ValidationException::withMessages(['parent_order_id' => 'Returns can only be created against sale orders.']);
            }

            $lockedShift = Shift::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->findOrFail($data['shift_id'] ?? $lockedParent->shift_id);

            $branch = Branch::withoutStoreScope()
                ->where('store_id', $store->id)
                ->findOrFail($data['branch_id'] ?? $lockedShift->branch_id);

            $lockedTerminal = Terminal::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->findOrFail($data['terminal_id'] ?? $lockedShift->terminal_id);

            $previousReturns = Order::withoutStoreScope()
                ->where('parent_order_id', $lockedParent->id)
                ->where('type', 'return')
                ->with('items')
                ->get();

            $alreadyReturnedByItemKey = [];
            foreach ($previousReturns as $retOrder) {
                foreach ($retOrder->items as $retItem) {
                    $key = $this->itemMatchKey($retItem->product_id, $retItem->variant_id, $retItem->unit_id);
                    $alreadyReturnedByItemKey[$key] = MoneyMath::add($alreadyReturnedByItemKey[$key] ?? '0.000', $retItem->quantity, 3);
                }
            }

            $requestedItems = $data['items'] ?? [];
            if (! is_array($requestedItems) || count($requestedItems) === 0) {
                throw ValidationException::withMessages(['items' => 'Return must include at least one item.']);
            }

            $preparedReturnLines = [];
            $returnSubtotal = '0.0000';
            $returnItemDiscount = '0.0000';
            $returnTaxTotal = '0.0000';
            $returnLineNetSum = '0.0000';

            foreach ($requestedItems as $idx => $req) {
                $parentItem = ! empty($req['order_item_id'])
                    ? $lockedParent->items->firstWhere('id', (int) $req['order_item_id'])
                    : $lockedParent->items->first(fn (OrderItem $i) => (int) $i->product_id === (int) ($req['product_id'] ?? 0)
                        && (int) ($i->variant_id ?? 0) === (int) ($req['variant_id'] ?? 0)
                        && (int) ($i->unit_id ?? 0) === (int) ($req['unit_id'] ?? 0));

                if (! $parentItem) {
                    throw ValidationException::withMessages(["items.{$idx}" => 'Item was not found on original sale order.']);
                }

                $returnQty = MoneyMath::qty((string) ($req['quantity'] ?? '0'));
                if (! MoneyMath::isPositive($returnQty)) {
                    throw ValidationException::withMessages(["items.{$idx}.quantity" => 'Return quantity must be positive.']);
                }

                $key = $this->itemMatchKey($parentItem->product_id, $parentItem->variant_id, $parentItem->unit_id);
                $alreadyReturned = $alreadyReturnedByItemKey[$key] ?? '0.000';
                $remainingQty = MoneyMath::sub($parentItem->quantity, $alreadyReturned, 3);

                if (MoneyMath::isGreaterThan($returnQty, $remainingQty)) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.quantity" => "Return quantity ({$returnQty}) exceeds remaining returnable quantity ({$remainingQty}).",
                    ]);
                }

                $alreadyReturnedByItemKey[$key] = MoneyMath::add($alreadyReturned, $returnQty, 3);

                $isFullLineReturn = MoneyMath::cmp($returnQty, $parentItem->quantity) === 0;
                $lineGross = MoneyMath::mul($parentItem->unit_price, $returnQty);
                $lineDiscount = $isFullLineReturn
                    ? MoneyMath::money($parentItem->discount_amount)
                    : MoneyMath::div(MoneyMath::mul($parentItem->discount_amount, $returnQty, 8), $parentItem->quantity, 4);
                $lineAfterDiscount = MoneyMath::sub($lineGross, $lineDiscount);
                $lineTax = $isFullLineReturn
                    ? MoneyMath::money($parentItem->tax_amount)
                    : MoneyMath::percent($lineAfterDiscount, $parentItem->tax_rate);
                $lineNet = MoneyMath::add($lineAfterDiscount, $lineTax);

                $condition = (string) ($req['return_condition'] ?? 'restock');
                if (! in_array($condition, ['restock', 'damaged'], true)) {
                    throw ValidationException::withMessages(["items.{$idx}.return_condition" => 'Invalid return condition.']);
                }

                $returnSubtotal = MoneyMath::add($returnSubtotal, $lineGross);
                $returnItemDiscount = MoneyMath::add($returnItemDiscount, $lineDiscount);
                $returnTaxTotal = MoneyMath::add($returnTaxTotal, $lineTax);
                $returnLineNetSum = MoneyMath::add($returnLineNetSum, $lineNet);

                $preparedReturnLines[] = [
                    'parent_item' => $parentItem,
                    'quantity' => $returnQty,
                    'base_quantity' => MoneyMath::mul($returnQty, $parentItem->conversion_factor, 3),
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'net_total' => $lineNet,
                    'return_condition' => $condition,
                ];
            }

            // Pro-rate header order_discount_amount if parent order had an order-level discount
            $parentLineNetSum = MoneyMath::sum($lockedParent->items->pluck('net_total')->all());
            $returnOrderDiscount = '0.0000';
            if (MoneyMath::isPositive($lockedParent->order_discount_amount) && MoneyMath::isPositive($parentLineNetSum)) {
                if (MoneyMath::cmp($returnLineNetSum, $parentLineNetSum) === 0) {
                    $returnOrderDiscount = MoneyMath::money($lockedParent->order_discount_amount);
                } else {
                    $returnOrderDiscount = MoneyMath::div(
                        MoneyMath::mul($lockedParent->order_discount_amount, $returnLineNetSum, 8),
                        $parentLineNetSum,
                        4
                    );
                }
            }

            $totalRefundAmount = MoneyMath::sub($returnLineNetSum, $returnOrderDiscount);

            // Determine refund split (cash/wallet payout vs customer credit reduction)
            $refundMethod = (string) ($data['refund_method'] ?? 'cash');
            $creditReduction = isset($data['credit_amount'])
                ? MoneyMath::money((string) $data['credit_amount'])
                : ($refundMethod === 'credit' ? $totalRefundAmount : '0.0000');

            $payoutAmount = isset($data['paid_amount'])
                ? MoneyMath::money((string) $data['paid_amount'])
                : MoneyMath::sub($totalRefundAmount, $creditReduction);

            if (MoneyMath::cmp(MoneyMath::add($payoutAmount, $creditReduction), $totalRefundAmount) !== 0) {
                throw ValidationException::withMessages(['refund' => 'Refund payout + credit reduction must equal total return amount.']);
            }

            $customerId = $data['customer_id'] ?? $lockedParent->customer_id;
            if (MoneyMath::isPositive($creditReduction)) {
                if (! $customerId) {
                    throw ValidationException::withMessages(['customer_id' => 'Customer is required for credit balance refund.']);
                }
                $lockedCustomer = Customer::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->lockForUpdate()
                    ->findOrFail($customerId);
                $lockedCustomer->current_balance = MoneyMath::sub($lockedCustomer->current_balance, $creditReduction);
                $lockedCustomer->save();
            }

            $invoiceNumber = $data['invoice_number'] ?? null;
            if (! $invoiceNumber) {
                $lockedTerminal->last_invoice_seq = ((int) $lockedTerminal->last_invoice_seq) + 1;
                $lockedTerminal->save();
                $invoiceNumber = sprintf('RET-%s-%s-%06d', $lockedTerminal->code, now()->format('Y'), $lockedTerminal->last_invoice_seq);
            }

            $returnOrder = Order::withoutStoreScope()->create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'terminal_id' => $lockedTerminal->id,
                'shift_id' => $lockedShift->id,
                'user_id' => $actor->id,
                'customer_id' => $customerId,
                'parent_order_id' => $lockedParent->id,
                'client_uuid' => $clientUuid,
                'invoice_number' => $invoiceNumber,
                'type' => 'return',
                'status' => 'completed',
                'subtotal' => $returnSubtotal,
                'item_discount_total' => $returnItemDiscount,
                'order_discount_amount' => $returnOrderDiscount,
                'tax_total' => $returnTaxTotal,
                'total_amount' => $totalRefundAmount,
                'paid_amount' => $payoutAmount,
                'credit_amount' => $creditReduction,
                'change_amount' => '0.0000',
                'notes' => $data['notes'] ?? null,
                'sold_at' => $data['sold_at'] ?? now(),
                'synced_at' => ! empty($data['is_offline_sync']) ? now() : null,
            ]);

            foreach ($preparedReturnLines as $rl) {
                /** @var OrderItem $pi */
                $pi = $rl['parent_item'];

                OrderItem::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'order_id' => $returnOrder->id,
                    'product_id' => $pi->product_id,
                    'variant_id' => $pi->variant_id,
                    'unit_id' => $pi->unit_id,
                    'product_name' => $pi->product_name,
                    'barcode_snapshot' => $pi->barcode_snapshot,
                    'unit_name_snapshot' => $pi->unit_name_snapshot,
                    'conversion_factor' => $pi->conversion_factor,
                    'quantity' => $rl['quantity'],
                    'unit_cost' => $pi->unit_cost,
                    'unit_price' => $pi->unit_price,
                    'discount_amount' => $rl['discount_amount'],
                    'tax_rate' => $pi->tax_rate,
                    'tax_amount' => $rl['tax_amount'],
                    'net_total' => $rl['net_total'],
                    'return_condition' => $rl['return_condition'],
                ]);

                $product = Product::withoutStoreScope()->withTrashed()->findOrFail($pi->product_id);
                $variant = $pi->variant_id ? ProductVariant::withoutStoreScope()->withTrashed()->find($pi->variant_id) : null;

                if ($rl['return_condition'] === 'restock') {
                    $this->inventoryService->applyMovement(
                        store: $store,
                        branch: $branch,
                        product: $product,
                        variant: $variant,
                        actor: $actor,
                        type: 'return_restock',
                        quantityDelta: $rl['base_quantity'],
                        unitCost: $pi->unit_cost,
                        referenceType: Order::class,
                        referenceId: $returnOrder->id,
                        note: "Return restock {$returnOrder->invoice_number}",
                    );
                } else {
                    $this->inventoryService->applyMovement(
                        store: $store,
                        branch: $branch,
                        product: $product,
                        variant: $variant,
                        actor: $actor,
                        type: 'return_damaged',
                        quantityDelta: '0.000',
                        unitCost: $pi->unit_cost,
                        referenceType: Order::class,
                        referenceId: $returnOrder->id,
                        note: "Damaged return ({$rl['base_quantity']} units not restocked) {$returnOrder->invoice_number}",
                    );
                }
            }

            if (MoneyMath::isPositive($payoutAmount)) {
                $payoutMethod = $refundMethod === 'credit' ? 'cash' : $refundMethod;
                Payment::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'branch_id' => $branch->id,
                    'shift_id' => $lockedShift->id,
                    'order_id' => $returnOrder->id,
                    'customer_id' => $customerId,
                    'user_id' => $actor->id,
                    'client_uuid' => (string) ($data['payment_client_uuid'] ?? Str::uuid()),
                    'payment_type' => 'refund_payout',
                    'method' => $payoutMethod,
                    'amount' => $payoutAmount,
                    'paid_at' => $data['sold_at'] ?? now(),
                    'notes' => "Refund for {$lockedParent->invoice_number}",
                ]);

                if ($payoutMethod === 'cash') {
                    $lockedShift->cash_returns_total = MoneyMath::add($lockedShift->cash_returns_total, $payoutAmount);
                    $this->shiftService->recalculateExpectedCash($lockedShift);
                    $lockedShift->save();
                }
            }

            // Update parent order status (partially_refunded vs refunded)
            $allFullyReturned = true;
            foreach ($lockedParent->items as $origItem) {
                $k = $this->itemMatchKey($origItem->product_id, $origItem->variant_id, $origItem->unit_id);
                $returnedSoFar = $alreadyReturnedByItemKey[$k] ?? '0.000';
                if (MoneyMath::isLessThan($returnedSoFar, $origItem->quantity)) {
                    $allFullyReturned = false;
                    break;
                }
            }

            $lockedParent->status = $allFullyReturned ? 'refunded' : 'partially_refunded';
            $lockedParent->save();

            return [
                'status' => 'created',
                'order' => $returnOrder->load(['items', 'payments']),
            ];
        });
    }

    protected function itemMatchKey(int $productId, ?int $variantId, ?int $unitId): string
    {
        return "{$productId}:" . ($variantId ?? 0) . ':' . ($unitId ?? 0);
    }
}
