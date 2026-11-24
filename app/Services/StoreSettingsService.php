<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Throwable;

class StoreSettingsService
{
    public function cacheKey(int $storeId = 1): string
    {
        return "store_settings_{$storeId}";
    }

    /**
     * Load store settings from permanent cache without running Schema::hasTable() per request.
     *
     * @return array<string, mixed>
     */
    public function all(int $storeId = 1): array
    {
        try {
            return Cache::rememberForever($this->cacheKey($storeId), function () use ($storeId): array {
                $store = Store::query()->find($storeId);
                if (! $store) {
                    return $this->defaults();
                }

                return [
                    'site_name' => $store->name,
                    'business_type' => $store->business_type,
                    'currency_symbol' => $store->currency_symbol,
                    'currency_code' => $store->currency_code,
                    'decimal_places' => $store->decimal_places,
                    'secondary_currency_code' => $store->secondary_currency_code,
                    'exchange_rate' => $store->exchange_rate,
                    'feature_flags' => $store->feature_flags ?? [],
                    'receipt_settings' => $store->receipt_settings ?? [],
                ];
            });
        } catch (Throwable) {
            return $this->defaults();
        }
    }

    public function get(string $key, mixed $default = null, int $storeId = 1): mixed
    {
        $settings = $this->all($storeId);

        return $settings[$key] ?? $default ?? ($this->defaults()[$key] ?? null);
    }

    public function clearCache(int $storeId = 1): void
    {
        Cache::forget($this->cacheKey($storeId));
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'site_name' => 'Yemen POS',
            'business_type' => 'general',
            'currency_symbol' => 'ر.ي',
            'currency_code' => 'YER',
            'decimal_places' => 0,
            'feature_flags' => [],
            'receipt_settings' => [],
        ];
    }
}
