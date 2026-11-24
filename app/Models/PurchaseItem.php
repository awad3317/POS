<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'purchase_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity',
        'conversion_factor',
        'unit_cost',
        'net_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'conversion_factor' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'net_total' => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}
