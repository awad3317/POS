<?php

namespace App\Traits;

use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder): void {
            if (StoreContext::isBypassed()) {
                return;
            }

            $storeId = StoreContext::id();
            if ($storeId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('store_id'), $storeId);
            }
        });

        static::creating(function ($model): void {
            if (StoreContext::isBypassed()) {
                return;
            }

            $storeId = StoreContext::id();
            if ($storeId !== null) {
                $model->store_id = $storeId;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeWithoutStoreScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('store');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->withoutGlobalScope('store')->where($this->qualifyColumn('store_id'), $storeId);
    }
}
