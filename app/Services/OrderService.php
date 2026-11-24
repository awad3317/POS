<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ShiftService $shiftService,
    ) {}

    /**
     * @return array{status: string, order: Order}
     */
    public function createSale(array $data, User $actor): array
    {
        $clientUuid = (string) ($data['client_uuid'] ?? Str::uuid());

        $existing = Order::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant order UUID conflict.');
            }

            return [
                'status' => 'duplicate_ok',
                'order' => $existing->load(['items', 'payments']),
            ];
        }

        return DB::transaction(function () use ($data, $actor, $clientUuid): array {
            $store = Store::query()->findOrFail($actor->store_id);

            $lockedShift = Shift::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->find($data['shift_id'] ?? null);

            if (! $lockedShift) {
                throw ValidationException::withMessages(['shift_id' => 'Valid store shift is required for sales.']);
            }

            $branch = Branch::withoutStoreScope()
                ->where('store_id', $store->id)
                ->findOrFail($data['branch_id'] ?? $lockedShift->branch_id);

            $lockedTerminal = Terminal::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->findOrFail($data['terminal_id'] ?? $lockedShift->terminal_id);

            $rawItems = $data['items'] ?? [];
            if (! is_array($rawItems) || count($rawItems) === 0) {
                throw ValidationException::withMessages(['items' => 'Order must contain at least one item.']);
            }

            $orderDiscount = MoneyMath::money((string) ($data['order_discount_amount'] ?? '0'));
            if (MoneyMath::isNegative($orderDiscount)) {
                throw ValidationException::withMessages(['order_discount_amount' => 'Negative order discount is forbidden.']);
            }

            $preparedLines = [];
            $subtotal = '0.0000';
            $itemDiscountTotal = '0.0000';
            $taxTotal = '0.0000';
            $totalCostFloor = '0.0000';
            $requiresCostOverride = false;

            foreach ($rawItems as $index => $line) {
                $product = Product::withoutStoreScope()
                    ->withTrashed()
                    ->where('store_id', $store->id)
                    ->find($line['product_id'] ?? null);

                if (! $product) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'Product does not belong to this store.']);
                }

                $variant = null;
                if (! empty($line['variant_id'])) {
                    $variant = ProductVariant::withoutStoreScope()
                        ->withTrashed()
                        ->where('store_id', $store->id)
                        ->where('product_id', $product->id)
                        ->find($line['variant_id']);

                    if (! $variant) {
                        throw ValidationException::withMessages(["items.{$index}.variant_id" => 'Invalid product variant.']);
                    }
                }

                $unit = null;
                if (! empty($line['unit_id'])) {
                    $unit = ProductUnit::withoutStoreScope()
                        ->where('store_id', $store->id)
                        ->where('product_id', $product->id)
                        ->find($line['unit_id']);

                    if (! $unit) {
                        throw ValidationException::withMessages(["items.{$index}.unit_id" => 'Invalid product unit.']);
                    }
                }

                $qty = MoneyMath::qty((string) ($line['quantity'] ?? '0'));
                if (! MoneyMath::isPositive($qty)) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'Item quantity must be greater than zero.']);
                }

                $conversionFactor = $unit
                    ? MoneyMath::qty($unit->conversion_factor)
                    : MoneyMath::qty((string) ($line['conversion_factor'] ?? '1.000'));

                if (! MoneyMath::isPositive($conversionFactor)) {
                    throw ValidationException::withMessages(["items.{$index}.conversion_factor" => 'Conversion factor must be positive.']);
                }

                $unitCost = $variant
                    ? MoneyMath::money($variant->purchase_price)
                    : ($unit && MoneyMath::isPositive($unit->purchase_price)
                        ? MoneyMath::money($unit->purchase_price)
                        : MoneyMath::mul($product->purchase_price, $conversionFactor));

                $defaultUnitPrice = $variant?->sale_price ?? $unit?->sale_price ?? $product->sale_price;
                $unitPrice = isset($line['unit_price'])
                    ? MoneyMath::money((string) $line['unit_price'])
                    : MoneyMath::money($defaultUnitPrice);

                if (MoneyMath::isNegative($unitPrice)) {
                    throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Unit price cannot be negative.']);
                }

                $lineDiscount = MoneyMath::money((string) ($line['discount_amount'] ?? '0'));
                if (MoneyMath::isNegative($lineDiscount)) {
                    throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'Negative item discount is forbidden.']);
                }

                $lineGross = MoneyMath::mul($unitPrice, $qty);
                if (MoneyMath::isGreaterThan($lineDiscount, $lineGross)) {
                    throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'Item discount cannot exceed line gross amount.']);
                }

                $lineAfterDiscount = MoneyMath::sub($lineGross, $lineDiscount);
                $taxRate = isset($line['tax_rate'])
                    ? MoneyMath::add((string) $line['tax_rate'], '0', 2)
                    : MoneyMath::add($product->tax_rate, '0', 2);

                if (MoneyMath::isNegative($taxRate)) {
                    throw ValidationException::withMessages(["items.{$index}.tax_rate" => 'Negative tax rate is forbidden.']);
                }

                $lineTax = MoneyMath::percent($lineAfterDiscount, $taxRate);
                $lineNet = MoneyMath::add($lineAfterDiscount, $lineTax);

                $lineCostTotal = MoneyMath::mul($unitCost, $qty);
                if (MoneyMath::isLessThan($lineAfterDiscount, $lineCostTotal)) {
                    $requiresCostOverride = true;
                }

                $subtotal = MoneyMath::add($subtotal, $lineGross);
                $itemDiscountTotal = MoneyMath::add($itemDiscountTotal, $lineDiscount);
                $taxTotal = MoneyMath::add($taxTotal, $lineTax);
                $totalCostFloor = MoneyMath::add($totalCostFloor, $lineCostTotal);

                $preparedLines[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'unit' => $unit,
                    'product_name' => (string) ($line['product_name'] ?? ($variant ? "{$product->name} ({$variant->attribute_label})" : $product->name)),
                    'barcode_snapshot' => $line['barcode_snapshot'] ?? $variant?->barcode ?? $unit?->barcode ?? $product->barcode,
                    'unit_name_snapshot' => $line['unit_name_snapshot'] ?? $unit?->unit_name ?? 'حبة',
                    'conversion_factor' => $conversionFactor,
                    'quantity' => $qty,
                    'base_quantity' => MoneyMath::mul($qty, $conversionFactor, 3),
                    'unit_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'net_total' => $lineNet,
                ];
            }

            $netRevenueBeforeTax = MoneyMath::sub(MoneyMath::sub($subtotal, $itemDiscountTotal), $orderDiscount);
            if (MoneyMath::isNegative($netRevenueBeforeTax)) {
                throw ValidationException::withMessages(['order_discount_amount' => 'Order discount exceeds subtotal after item discounts.']);
            }

            if (MoneyMath::isLessThan($netRevenueBeforeTax, $totalCostFloor)) {
                $requiresCostOverride = true;
            }

            $discountOverrideBy = null;
            if ($requiresCostOverride) {
                $discountOverrideBy = $this->resolveCostFloorOverride($store, $actor, $data['manager_override_pin'] ?? null);
            }

            $totalAmount = MoneyMath::add($netRevenueBeforeTax, $taxTotal);

            $actor->loadMissing(['roles.permissions', 'permissions']);

            $rawPayments = $data['payments'] ?? [];
            $tenderedTotal = '0.0000';
            $cashTendered = '0.0000';
            $preparedPayments = [];

            foreach ($rawPayments as $pIdx => $p) {
                $pAmount = MoneyMath::money((string) ($p['amount'] ?? '0'));
                if (! MoneyMath::isPositive($pAmount)) {
                    throw ValidationException::withMessages(["payments.{$pIdx}.amount" => 'Payment amount must be greater than zero.']);
                }
                $method = (string) ($p['method'] ?? 'cash');
                $tenderedTotal = MoneyMath::add($tenderedTotal, $pAmount);
                if ($method === 'cash') {
                    $cashTendered = MoneyMath::add($cashTendered, $pAmount);
                }
                $preparedPayments[] = [
                    'client_uuid' => (string) ($p['client_uuid'] ?? Str::uuid()),
                    'method' => $method,
                    'reference_number' => $p['reference_number'] ?? null,
                    'amount' => $pAmount,
                    'paid_at' => $p['paid_at'] ?? $data['sold_at'] ?? now(),
                    'notes' => $p['notes'] ?? null,
                ];
            }

            $changeAmount = MoneyMath::money((string) ($data['change_amount'] ?? '0'));
            if (MoneyMath::isNegative($changeAmount)) {
                throw ValidationException::withMessages(['change_amount' => 'Change amount cannot be negative.']);
            }
            if (MoneyMath::isGreaterThan($changeAmount, $cashTendered)) {
                throw ValidationException::withMessages(['change_amount' => 'Change amount cannot exceed cash tendered.']);
            }

            $paidAmount = MoneyMath::sub($tenderedTotal, $changeAmount);
            $creditAmount = MoneyMath::money((string) ($data['credit_amount'] ?? '0'));
            if (MoneyMath::isNegative($creditAmount)) {
                throw ValidationException::withMessages(['credit_amount' => 'Credit amount cannot be negative.']);
            }

            if (MoneyMath::cmp(MoneyMath::add($paidAmount, $creditAmount), $totalAmount) !== 0) {
                throw ValidationException::withMessages([
                    'payments' => sprintf(
                        'Unbalanced invoice: paid_amount (%s) + credit_amount (%s) must equal total_amount (%s).',
                        $paidAmount,
                        $creditAmount,
                        $totalAmount
                    ),
                ]);
            }

            // Customer & Credit Handling
            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->lockForUpdate()
                    ->find($data['customer_id']);

                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'Customer does not belong to this store.']);
                }
            }

            if (MoneyMath::isPositive($creditAmount)) {
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'Customer is required when credit_amount > 0.']);
                }

                $newCustomerBalance = MoneyMath::add($customer->current_balance, $creditAmount);
                $isOfflineSync = (bool) ($data['is_offline_sync'] ?? false);

                if (
                    MoneyMath::isPositive($customer->credit_limit)
                    && MoneyMath::isGreaterThan($newCustomerBalance, $customer->credit_limit)
                    && ! $isOfflineSync
                    && ! $discountOverrideBy
                    && ! $actor->can('pos.override_discounts')
                ) {
                    throw ValidationException::withMessages(['credit_amount' => 'Customer credit limit exceeded.']);
                }

                $customer->current_balance = $newCustomerBalance;
                $customer->save();
            }

            // Invoice Number
            $invoiceNumber = $data['invoice_number'] ?? null;
            if (! $invoiceNumber) {
                $lockedTerminal->last_invoice_seq = ((int) $lockedTerminal->last_invoice_seq) + 1;
                $lockedTerminal->save();
                $invoiceNumber = sprintf('INV-%s-%s-%06d', $lockedTerminal->code, now()->format('Y'), $lockedTerminal->last_invoice_seq);
            } else {
                if (preg_match('/(\d+)$/', (string) $invoiceNumber, $m)) {
                    $seq = (int) $m[1];
                    if ($seq > (int) $lockedTerminal->last_invoice_seq) {
                        $lockedTerminal->last_invoice_seq = $seq;
                        $lockedTerminal->save();
                    }
                }
            }

            $soldAt = $data['sold_at'] ?? now();

            $order = Order::withoutStoreScope()->create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'terminal_id' => $lockedTerminal->id,
                'shift_id' => $lockedShift->id,
                'user_id' => $actor->id,
                'customer_id' => $customer?->id,
                'parent_order_id' => null,
                'client_uuid' => $clientUuid,
                'invoice_number' => $invoiceNumber,
                'type' => 'sale',
                'status' => 'completed',
                'subtotal' => $subtotal,
                'item_discount_total' => $itemDiscountTotal,
                'order_discount_amount' => $orderDiscount,
                'tax_total' => $taxTotal,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'credit_amount' => $creditAmount,
                'change_amount' => $changeAmount,
                'discount_override_by' => $discountOverrideBy,
                'notes' => $data['notes'] ?? null,
                'sold_at' => $soldAt,
                'synced_at' => ! empty($data['is_offline_sync']) ? now() : null,
            ]);

            foreach ($preparedLines as $pl) {
                OrderItem::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'order_id' => $order->id,
                    'product_id' => $pl['product']->id,
                    'variant_id' => $pl['variant']?->id,
                    'unit_id' => $pl['unit']?->id,
                    'product_name' => $pl['product_name'],
                    'barcode_snapshot' => $pl['barcode_snapshot'],
                    'unit_name_snapshot' => $pl['unit_name_snapshot'],
                    'conversion_factor' => $pl['conversion_factor'],
                    'quantity' => $pl['quantity'],
                    'unit_cost' => $pl['unit_cost'],
                    'unit_price' => $pl['unit_price'],
                    'discount_amount' => $pl['discount_amount'],
                    'tax_rate' => $pl['tax_rate'],
                    'tax_amount' => $pl['tax_amount'],
                    'net_total' => $pl['net_total'],
                ]);

                $this->inventoryService->applyMovement(
                    store: $store,
                    branch: $branch,
                    product: $pl['product'],
                    variant: $pl['variant'],
                    actor: $actor,
                    type: 'sale',
                    quantityDelta: MoneyMath::negate($pl['base_quantity'], 3),
                    unitCost: $pl['unit_cost'],
                    referenceType: Order::class,
                    referenceId: $order->id,
                    note: "Sale {$order->invoice_number}",
                );
            }

            foreach ($preparedPayments as $pp) {
                $netPaymentAmount = $pp['amount'];
                // Record exact applied payment amount if cash had change
                if ($pp['method'] === 'cash' && MoneyMath::isPositive($changeAmount)) {
                    $deductChange = MoneyMath::isGreaterThan($changeAmount, $netPaymentAmount) ? $netPaymentAmount : $changeAmount;
                    $netPaymentAmount = MoneyMath::sub($netPaymentAmount, $deductChange);
                }

                if (MoneyMath::isPositive($netPaymentAmount)) {
                    Payment::withoutStoreScope()->create([
                        'store_id' => $store->id,
                        'branch_id' => $branch->id,
                        'shift_id' => $lockedShift->id,
                        'order_id' => $order->id,
                        'customer_id' => $customer?->id,
                        'user_id' => $actor->id,
                        'client_uuid' => $pp['client_uuid'],
                        'payment_type' => 'order_payment',
                        'method' => $pp['method'],
                        'reference_number' => $pp['reference_number'],
                        'amount' => $netPaymentAmount,
                        'paid_at' => $pp['paid_at'],
                        'notes' => $pp['notes'],
                    ]);
                }
            }

            $netCashReceived = MoneyMath::sub($cashTendered, $changeAmount);
            if (MoneyMath::isPositive($netCashReceived)) {
                $lockedShift->cash_sales_total = MoneyMath::add($lockedShift->cash_sales_total, $netCashReceived);
                $this->shiftService->recalculateExpectedCash($lockedShift);
                $lockedShift->save();
            }

            if ($discountOverrideBy !== null) {
                activity()
                    ->performedOn($order)
                    ->causedBy($actor)
                    ->withProperties(['discount_override_by' => $discountOverrideBy, 'invoice_number' => $order->invoice_number])
                    ->event('discount_override')
                    ->log('Sale below cost approved via manager override.');
            }

            return [
                'status' => 'created',
                'order' => $order->load(['items', 'payments']),
            ];
        });
    }

    protected function resolveCostFloorOverride(Store $store, User $actor, ?string $managerPin): int
    {
        $actor->loadMissing(['roles.permissions', 'permissions']);

        if ($managerPin !== null && $managerPin !== '') {
            $candidates = User::query()
                ->with(['roles.permissions', 'permissions'])
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->whereNotNull('pin_hash')
                ->get();

            foreach ($candidates as $candidate) {
                if (
                    ($candidate->can('pos.override_discounts') || $candidate->hasAnyRole(['owner', 'manager']))
                    && Hash::check($managerPin, $candidate->pin_hash)
                ) {
                    return (int) $candidate->id;
                }
            }

            throw ValidationException::withMessages([
                'manager_override_pin' => 'Invalid manager override PIN.',
            ]);
        }

        if ($actor->can('pos.override_discounts')) {
            return (int) $actor->id;
        }

        throw ValidationException::withMessages([
            'discount' => 'Selling below purchase cost is forbidden without manager override.',
        ]);
    }
}
