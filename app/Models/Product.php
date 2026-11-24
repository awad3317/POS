<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToStore, HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'type',
        'name',
        'sku',
        'barcode',
        'scale_plu_code',
        'purchase_price',
        'sale_price',
        'wholesale_price',
        'cost_currency',
        'foreign_cost_price',
        'profit_margin_percent',
        'quantity',
        'min_stock_alert',
        'tax_rate',
        'image_path',
        'is_quick_pick',
        'has_expiry',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'wholesale_price' => 'decimal:4',
        'foreign_cost_price' => 'decimal:4',
        'profit_margin_percent' => 'decimal:2',
        'quantity' => 'decimal:3',
        'min_stock_alert' => 'decimal:3',
        'tax_rate' => 'decimal:2',
        'is_quick_pick' => 'boolean',
        'has_expiry' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImageUrl(): string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return asset('img/img-placeholder.jpg');
    }
}
