<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierAccountService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ShiftService $shiftService,
    ) {}

    public function recordPurchase(array $data, User $actor): Purchase
    {
        return DB::transaction(function () use ($data, $actor): Purchase {
            $store = Store::query()->findOrFail($actor->store_id);
            $branch = Branch::withoutStoreScope()
                ->where('store_id', $store->id)
                ->findOrFail($data['branch_id'] ?? $actor->branch_id);

            $supplier = null;
            if (! empty($data['supplier_id'])) {
                $supplier = Supplier::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->lockForUpdate()
                    ->find($data['supplier_id']);

                if (! $supplier) {
                    throw ValidationException::withMessages(['supplier_id' => 'Invalid supplier for this store.']);
                }
            }

            $rawItems = $data['items'] ?? [];
            if (! is_array($rawItems) || count($rawItems) === 0) {
                throw ValidationException::withMessages(['items' => 'Purchase must contain at least one item.']);
            }

            $discountAmount = MoneyMath::money((string) ($data['discount_amount'] ?? '0'));
            if (MoneyMath::isNegative($discountAmount)) {
                throw ValidationException::withMessages(['discount_amount' => 'Negative purchase discount is forbidden.']);
            }

            $subtotal = '0.0000';
            $preparedItems = [];

            foreach ($rawItems as $idx => $item) {
                $product = Product::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->lockForUpdate()
                    ->find($item['product_id'] ?? null);

                if (! $product) {
                    throw ValidationException::withMessages(["items.{$idx}.product_id" => 'Invalid product for this store.']);
                }

                $variant = null;
                if (! empty($item['variant_id'])) {
                    $variant = ProductVariant::withoutStoreScope()
                        ->where('store_id', $store->id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->find($item['variant_id']);

                    if (! $variant) {
                        throw ValidationException::withMessages(["items.{$idx}.variant_id" => 'Invalid product variant.']);
                    }
                }

                $unit = null;
                if (! empty($item['unit_id'])) {
                    $unit = ProductUnit::withoutStoreScope()
                        ->where('store_id', $store->id)
                        ->where('product_id', $product->id)
                        ->find($item['unit_id']);
                }

                $qty = MoneyMath::qty((string) ($item['quantity'] ?? '0'));
                if (! MoneyMath::isPositive($qty)) {
                    throw ValidationException::withMessages(["items.{$idx}.quantity" => 'Purchase quantity must be positive.']);
                }

                $conversionFactor = $unit
                    ? MoneyMath::qty($unit->conversion_factor)
                    : MoneyMath::qty((string) ($item['conversion_factor'] ?? '1.000'));

                $unitCost = MoneyMath::money((string) ($item['unit_cost'] ?? '0'));
                if (MoneyMath::isNegative($unitCost)) {
                    throw ValidationException::withMessages(["items.{$idx}.unit_cost" => 'Unit cost cannot be negative.']);
                }

                $lineNet = MoneyMath::mul($unitCost, $qty);
                $subtotal = MoneyMath::add($subtotal, $lineNet);

                $baseQty = MoneyMath::mul($qty, $conversionFactor, 3);
                $baseUnitCost = MoneyMath::div($unitCost, $conversionFactor, 4);

                $preparedItems[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'unit' => $unit,
                    'quantity' => $qty,
                    'conversion_factor' => $conversionFactor,
                    'base_quantity' => $baseQty,
                    'unit_cost' => $unitCost,
                    'base_unit_cost' => $baseUnitCost,
                    'net_total' => $lineNet,
                ];
            }

            $totalAmount = MoneyMath::sub($subtotal, $discountAmount);
            if (MoneyMath::isNegative($totalAmount)) {
                throw ValidationException::withMessages(['discount_amount' => 'Discount cannot exceed purchase subtotal.']);
            }

            $paidAmount = MoneyMath::money((string) ($data['paid_amount'] ?? '0'));
            $creditAmount = isset($data['credit_amount'])
                ? MoneyMath::money((string) $data['credit_amount'])
                : MoneyMath::sub($totalAmount, $paidAmount);

            if (MoneyMath::isNegative($paidAmount) || MoneyMath::isNegative($creditAmount)) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid and credit amounts must be non-negative.']);
            }

            if (MoneyMath::cmp(MoneyMath::add($paidAmount, $creditAmount), $totalAmount) !== 0) {
                throw ValidationException::withMessages(['paid_amount' => 'Purchase paid_amount + credit_amount must equal total_amount.']);
            }

            if (MoneyMath::isPositive($creditAmount) && ! $supplier) {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier is required for credit purchases.']);
            }

            $paymentSource = $data['payment_source'] ?? null;
            $shiftId = $data['shift_id'] ?? null;

            if (MoneyMath::isPositive($paidAmount) && $paymentSource === 'cash_drawer') {
                if (! $shiftId) {
                    throw ValidationException::withMessages(['shift_id' => 'Shift is required when paying from cash_drawer.']);
                }
                $lockedShift = Shift::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->lockForUpdate()
                    ->findOrFail($shiftId);
                $lockedShift->cash_out_total = MoneyMath::add($lockedShift->cash_out_total, $paidAmount);
                $this->shiftService->recalculateExpectedCash($lockedShift);
                $lockedShift->save();
            }

            $purchase = Purchase::withoutStoreScope()->create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'supplier_id' => $supplier?->id,
                'user_id' => $actor->id,
                'parent_purchase_id' => null,
                'shift_id' => $shiftId,
                'type' => 'purchase',
                'reference_number' => $data['reference_number'] ?? ('PUR-' . now()->format('YmdHis') . '-' . random_int(100, 999)),
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'credit_amount' => $creditAmount,
                'payment_method' => $data['payment_method'] ?? (MoneyMath::isPositive($paidAmount) ? 'cash' : null),
                'payment_source' => $paymentSource,
                'purchased_at' => $data['purchased_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($preparedItems as $pi) {
                PurchaseItem::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'purchase_id' => $purchase->id,
                    'product_id' => $pi['product']->id,
                    'variant_id' => $pi['variant']?->id,
                    'unit_id' => $pi['unit']?->id,
                    'quantity' => $pi['quantity'],
                    'conversion_factor' => $pi['conversion_factor'],
                    'unit_cost' => $pi['unit_cost'],
                    'net_total' => $pi['net_total'],
                ]);

                // Update Weighted Average Cost BEFORE applying incoming movement
                if ($pi['variant'] !== null) {
                    $lockedVar = ProductVariant::withoutStoreScope()->lockForUpdate()->findOrFail($pi['variant']->id);
                    $newWac = $this->inventoryService->calculateWeightedAverageCost(
                        $lockedVar->quantity,
                        $lockedVar->purchase_price,
                        $pi['base_quantity'],
                        $pi['base_unit_cost']
                    );
                    $lockedVar->purchase_price = $newWac;
                    $lockedVar->save();
                } else {
                    $lockedProd = Product::withoutStoreScope()->lockForUpdate()->findOrFail($pi['product']->id);
                    $newWac = $this->inventoryService->calculateWeightedAverageCost(
                        $lockedProd->quantity,
                        $lockedProd->purchase_price,
                        $pi['base_quantity'],
                        $pi['base_unit_cost']
                    );
                    $lockedProd->purchase_price = $newWac;
                    $lockedProd->save();
                }

                $this->inventoryService->applyMovement(
                    store: $store,
                    branch: $branch,
                    product: $pi['product'],
                    variant: $pi['variant'],
                    actor: $actor,
                    type: 'purchase',
                    quantityDelta: $pi['base_quantity'],
                    unitCost: $pi['base_unit_cost'],
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    note: "Purchase {$purchase->reference_number}",
                );
            }

            if ($supplier && MoneyMath::isPositive($creditAmount)) {
                $supplier->current_balance = MoneyMath::add($supplier->current_balance, $creditAmount);
                $supplier->save();
            }

            return $purchase->load('items');
        });
    }

    public function recordPurchaseReturn(Purchase $parentPurchase, array $data, User $actor): Purchase
    {
        if ((int) $parentPurchase->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Purchase belongs to another store.');
        }

        return DB::transaction(function () use ($parentPurchase, $data, $actor): Purchase {
            $store = Store::query()->findOrFail($actor->store_id);
            $lockedParent = Purchase::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($parentPurchase->id);

            $branch = Branch::withoutStoreScope()->findOrFail($data['branch_id'] ?? $lockedParent->branch_id);
            $supplier = $lockedParent->supplier_id
                ? Supplier::withoutStoreScope()->where('store_id', $store->id)->lockForUpdate()->findOrFail($lockedParent->supplier_id)
                : null;

            $rawItems = $data['items'] ?? [];
            if (! is_array($rawItems) || count($rawItems) === 0) {
                throw ValidationException::withMessages(['items' => 'Purchase return must contain at least one item.']);
            }

            $subtotal = '0.0000';
            $preparedItems = [];

            foreach ($rawItems as $idx => $item) {
                $origItem = $lockedParent->items->first(fn (PurchaseItem $pi) => (int) $pi->product_id === (int) ($item['product_id'] ?? 0)
                    && (int) ($pi->variant_id ?? 0) === (int) ($item['variant_id'] ?? 0));

                if (! $origItem) {
                    throw ValidationException::withMessages(["items.{$idx}" => 'Item not found in original purchase.']);
                }

                $qty = MoneyMath::qty((string) ($item['quantity'] ?? '0'));
                if (! MoneyMath::isPositive($qty) || MoneyMath::isGreaterThan($qty, $origItem->quantity)) {
                    throw ValidationException::withMessages(["items.{$idx}.quantity" => 'Invalid purchase return quantity.']);
                }

                $unitCost = isset($item['unit_cost']) ? MoneyMath::money((string) $item['unit_cost']) : $origItem->unit_cost;
                $lineNet = MoneyMath::mul($unitCost, $qty);
                $subtotal = MoneyMath::add($subtotal, $lineNet);

                $preparedItems[] = [
                    'orig_item' => $origItem,
                    'quantity' => $qty,
                    'base_quantity' => MoneyMath::mul($qty, $origItem->conversion_factor, 3),
                    'unit_cost' => $unitCost,
                    'base_unit_cost' => MoneyMath::div($unitCost, $origItem->conversion_factor, 4),
                    'net_total' => $lineNet,
                ];
            }

            $totalAmount = $subtotal;
            $paidRefund = MoneyMath::money((string) ($data['paid_amount'] ?? '0'));
            $creditReduction = isset($data['credit_amount'])
                ? MoneyMath::money((string) $data['credit_amount'])
                : MoneyMath::sub($totalAmount, $paidRefund);

            if (MoneyMath::cmp(MoneyMath::add($paidRefund, $creditReduction), $totalAmount) !== 0) {
                throw ValidationException::withMessages(['credit_amount' => 'Purchase return refund + credit reduction must equal total_amount.']);
            }

            $returnPurchase = Purchase::withoutStoreScope()->create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'supplier_id' => $supplier?->id,
                'user_id' => $actor->id,
                'parent_purchase_id' => $lockedParent->id,
                'shift_id' => $data['shift_id'] ?? null,
                'type' => 'purchase_return',
                'reference_number' => $data['reference_number'] ?? ('PRET-' . now()->format('YmdHis') . '-' . random_int(100, 999)),
                'subtotal' => $subtotal,
                'discount_amount' => '0.0000',
                'total_amount' => $totalAmount,
                'paid_amount' => $paidRefund,
                'credit_amount' => $creditReduction,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_source' => $data['payment_source'] ?? null,
                'purchased_at' => $data['purchased_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($preparedItems as $pi) {
                /** @var PurchaseItem $orig */
                $orig = $pi['orig_item'];
                PurchaseItem::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'purchase_id' => $returnPurchase->id,
                    'product_id' => $orig->product_id,
                    'variant_id' => $orig->variant_id,
                    'unit_id' => $orig->unit_id,
                    'quantity' => $pi['quantity'],
                    'conversion_factor' => $orig->conversion_factor,
                    'unit_cost' => $pi['unit_cost'],
                    'net_total' => $pi['net_total'],
                ]);

                $product = Product::withoutStoreScope()->withTrashed()->findOrFail($orig->product_id);
                $variant = $orig->variant_id ? ProductVariant::withoutStoreScope()->withTrashed()->find($orig->variant_id) : null;

                $this->inventoryService->applyMovement(
                    store: $store,
                    branch: $branch,
                    product: $product,
                    variant: $variant,
                    actor: $actor,
                    type: 'purchase_return',
                    quantityDelta: MoneyMath::negate($pi['base_quantity'], 3),
                    unitCost: $pi['base_unit_cost'],
                    referenceType: Purchase::class,
                    referenceId: $returnPurchase->id,
                    note: "Purchase return {$returnPurchase->reference_number}",
                );
            }

            if ($supplier && MoneyMath::isPositive($creditReduction)) {
                $supplier->current_balance = MoneyMath::sub($supplier->current_balance, $creditReduction);
                $supplier->save();
            }

            return $returnPurchase->load('items');
        });
    }

    /**
     * @return array{status: string, supplier_payment: SupplierPayment}
     */
    public function recordSupplierPayment(Supplier $supplier, array $data, User $actor): array
    {
        if ((int) $supplier->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Supplier belongs to another store.');
        }

        $clientUuid = isset($data['client_uuid']) ? (string) $data['client_uuid'] : (string) Str::uuid();
        $existing = SupplierPayment::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant supplier payment UUID conflict.');
            }

            return ['status' => 'duplicate_ok', 'supplier_payment' => $existing];
        }

        return DB::transaction(function () use ($supplier, $data, $actor, $clientUuid): array {
            $amount = MoneyMath::money((string) ($data['amount'] ?? '0'));
            if (! MoneyMath::isPositive($amount)) {
                throw ValidationException::withMessages(['amount' => 'Supplier payment amount must be positive.']);
            }

            $lockedSupplier = Supplier::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->lockForUpdate()
                ->findOrFail($supplier->id);

            $balanceBefore = MoneyMath::money($lockedSupplier->current_balance);
            $balanceAfter = MoneyMath::sub($balanceBefore, $amount);

            $lockedSupplier->current_balance = $balanceAfter;
            $lockedSupplier->save();

            $paymentSource = (string) ($data['payment_source'] ?? 'owner_pocket');
            $shiftId = $data['shift_id'] ?? null;
            $branchId = (int) ($data['branch_id'] ?? $actor->branch_id);

            if ($paymentSource === 'cash_drawer') {
                if (! $shiftId) {
                    throw ValidationException::withMessages(['shift_id' => 'Shift is required when paying supplier from cash_drawer.']);
                }

                $lockedShift = Shift::withoutStoreScope()
                    ->where('store_id', $actor->store_id)
                    ->lockForUpdate()
                    ->findOrFail($shiftId);

                $branchId = (int) $lockedShift->branch_id;
                $lockedShift->cash_out_total = MoneyMath::add($lockedShift->cash_out_total, $amount);
                $this->shiftService->recalculateExpectedCash($lockedShift);
                $lockedShift->save();
            }

            $payment = SupplierPayment::withoutStoreScope()->create([
                'store_id' => $actor->store_id,
                'branch_id' => $branchId,
                'supplier_id' => $lockedSupplier->id,
                'purchase_id' => $data['purchase_id'] ?? null,
                'shift_id' => $shiftId,
                'user_id' => $actor->id,
                'client_uuid' => $clientUuid,
                'method' => (string) ($data['method'] ?? 'cash'),
                'payment_source' => $paymentSource,
                'reference_number' => $data['reference_number'] ?? null,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['status' => 'created', 'supplier_payment' => $payment];
        });
    }

    /**
     * @return array{supplier_id: int, opening_balance: string, entries: array<int, array<string, mixed>>, closing_balance: string}
     */
    public function getSupplierStatement(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        $purchases = Purchase::withoutStoreScope()
            ->where('store_id', $supplier->store_id)
            ->where('supplier_id', $supplier->id)
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get();

        $payments = SupplierPayment::withoutStoreScope()
            ->where('store_id', $supplier->store_id)
            ->where('supplier_id', $supplier->id)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $rawEvents = [];

        foreach ($purchases as $p) {
            if (MoneyMath::isZero($p->credit_amount)) {
                continue;
            }
            $signed = $p->type === 'purchase'
                ? MoneyMath::money($p->credit_amount)
                : MoneyMath::negate($p->credit_amount);

            $rawEvents[] = [
                'timestamp' => $p->purchased_at?->getTimestamp() ?? 0,
                'sort_key' => '1-' . str_pad((string) $p->id, 10, '0', STR_PAD_LEFT),
                'date' => $p->purchased_at?->toIso8601String(),
                'type' => $p->type,
                'reference' => $p->reference_number,
                'amount' => MoneyMath::money($p->credit_amount),
                'signed_amount' => $signed,
            ];
        }

        foreach ($payments as $pay) {
            $rawEvents[] = [
                'timestamp' => $pay->paid_at?->getTimestamp() ?? 0,
                'sort_key' => '2-' . str_pad((string) $pay->id, 10, '0', STR_PAD_LEFT),
                'date' => $pay->paid_at?->toIso8601String(),
                'type' => 'supplier_payment',
                'reference' => $pay->reference_number ?? ('PAY-' . $pay->id),
                'amount' => MoneyMath::money($pay->amount),
                'signed_amount' => MoneyMath::negate($pay->amount),
            ];
        }

        usort($rawEvents, fn (array $a, array $b) => ($a['timestamp'] <=> $b['timestamp']) ?: ($a['sort_key'] <=> $b['sort_key']));

        $fromTs = $from ? strtotime($from) : null;
        $toTs = $to ? strtotime($to) : null;

        $openingBalance = MoneyMath::money($supplier->opening_balance);
        $runningBalance = $openingBalance;
        $entries = [];

        foreach ($rawEvents as $ev) {
            if ($fromTs !== null && $ev['timestamp'] < $fromTs) {
                $openingBalance = MoneyMath::add($openingBalance, $ev['signed_amount']);
                $runningBalance = $openingBalance;

                continue;
            }

            if ($toTs !== null && $ev['timestamp'] > $toTs) {
                continue;
            }

            $runningBalance = MoneyMath::add($runningBalance, $ev['signed_amount']);
            $entries[] = [
                'date' => $ev['date'],
                'type' => $ev['type'],
                'reference' => $ev['reference'],
                'amount' => $ev['amount'],
                'signed_amount' => $ev['signed_amount'],
                'running_balance' => $runningBalance,
            ];
        }

        return [
            'supplier_id' => $supplier->id,
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'closing_balance' => $runningBalance,
        ];
    }
}
