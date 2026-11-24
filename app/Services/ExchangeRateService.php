<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeRateService
{
    public function __construct(
        protected StoreSettingsService $settingsService,
    ) {}

    public function updateStoreExchangeRate(Store $store, string $newRate, User $actor): int
    {
        if ((int) $store->id !== (int) $actor->store_id) {
            throw new AuthorizationException('Cannot update exchange rate for another store.');
        }

        $normalizedRate = MoneyMath::money($newRate);
        if (! MoneyMath::isPositive($normalizedRate)) {
            throw ValidationException::withMessages(['exchange_rate' => 'Exchange rate must be greater than zero.']);
        }

        return DB::transaction(function () use ($store, $normalizedRate, $actor): int {
            $lockedStore = Store::query()->lockForUpdate()->findOrFail($store->id);
            $oldRate = $lockedStore->exchange_rate;

            $lockedStore->exchange_rate = $normalizedRate;
            $lockedStore->save();

            $query = Product::withoutStoreScope()
                ->where('store_id', $lockedStore->id)
                ->whereNotNull('foreign_cost_price')
                ->lockForUpdate();

            if ($lockedStore->secondary_currency_code) {
                $query->where('cost_currency', $lockedStore->secondary_currency_code);
            }

            $products = $query->get();
            $updatedCount = 0;

            foreach ($products as $product) {
                if (! MoneyMath::isPositive((string) $product->foreign_cost_price)) {
                    continue;
                }

                $newPurchasePrice = MoneyMath::mul((string) $product->foreign_cost_price, $normalizedRate);
                $product->purchase_price = $newPurchasePrice;

                if ($product->profit_margin_percent !== null) {
                    $marginAmount = MoneyMath::percent($newPurchasePrice, (string) $product->profit_margin_percent);
                    $product->sale_price = MoneyMath::add($newPurchasePrice, $marginAmount);
                }

                $product->save();

                // Sync child variants if product is variant-based
                if ($product->type === 'variant') {
                    ProductVariant::withoutStoreScope()
                        ->where('store_id', $lockedStore->id)
                        ->where('product_id', $product->id)
                        ->update([
                            'purchase_price' => $product->purchase_price,
                            'sale_price' => $product->sale_price,
                        ]);
                }

                $updatedCount++;
            }

            $this->settingsService->clearCache($lockedStore->id);

            activity()
                ->performedOn($lockedStore)
                ->causedBy($actor)
                ->withProperties([
                    'old_rate' => $oldRate,
                    'new_rate' => $normalizedRate,
                    'updated_products_count' => $updatedCount,
                ])
                ->event('exchange_rate_updated')
                ->log("Exchange rate updated to {$normalizedRate} ({$updatedCount} products repriced).");

            return $updatedCount;
        });
    }
}
