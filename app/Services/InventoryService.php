<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Support\MoneyMath;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function applyMovement(
        Store $store,
        Branch $branch,
        Product $product,
        ?ProductVariant $variant,
        User $actor,
        string $type,
        string $quantityDelta,
        string $unitCost = '0.0000',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $store,
            $branch,
            $product,
            $variant,
            $actor,
            $type,
            $quantityDelta,
            $unitCost,
            $referenceType,
            $referenceId,
            $note
        ): StockMovement {
            $lockedProduct = Product::withoutStoreScope()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->findOrFail($product->id);

            $delta = MoneyMath::qty($quantityDelta);
            $normalizedCost = MoneyMath::money($unitCost);

            if ($variant !== null) {
                $lockedVariant = ProductVariant::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->where('product_id', $lockedProduct->id)
                    ->lockForUpdate()
                    ->findOrFail($variant->id);

                $balanceBefore = MoneyMath::qty($lockedVariant->quantity);
                $balanceAfter = MoneyMath::add($balanceBefore, $delta, 3);

                $lockedVariant->quantity = $balanceAfter;
                $lockedVariant->save();

                $lockedProduct->quantity = MoneyMath::add($lockedProduct->quantity, $delta, 3);
                $lockedProduct->save();
            } else {
                $balanceBefore = MoneyMath::qty($lockedProduct->quantity);
                $balanceAfter = MoneyMath::add($balanceBefore, $delta, 3);

                $lockedProduct->quantity = $balanceAfter;
                $lockedProduct->save();
            }

            $isNegativeAlert = MoneyMath::isNegative($balanceAfter);

            return StockMovement::withoutStoreScope()->create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'product_id' => $lockedProduct->id,
                'variant_id' => $variant?->id,
                'user_id' => $actor->id,
                'type' => $type,
                'quantity_delta' => $delta,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'unit_cost' => $normalizedCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'is_negative_alert' => $isNegativeAlert,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }

    public function calculateWeightedAverageCost(
        string $currentQty,
        string $currentCost,
        string $incomingQty,
        string $incomingCost
    ): string {
        if (! MoneyMath::isPositive($incomingQty)) {
            return MoneyMath::money($currentCost);
        }

        if (! MoneyMath::isPositive($currentQty)) {
            return MoneyMath::money($incomingCost);
        }

        $existingValue = BigDecimal::of($currentQty)->multipliedBy(BigDecimal::of($currentCost));
        $incomingValue = BigDecimal::of($incomingQty)->multipliedBy(BigDecimal::of($incomingCost));
        $totalQty = BigDecimal::of($currentQty)->plus(BigDecimal::of($incomingQty));

        return $existingValue
            ->plus($incomingValue)
            ->dividedBy($totalQty, 4, RoundingMode::HALF_UP)
            ->__toString();
    }
}
