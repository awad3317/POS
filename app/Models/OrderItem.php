<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'order_id',
        'product_id',
        'variant_id',
        'unit_id',
        'product_name',
        'barcode_snapshot',
        'unit_name_snapshot',
        'conversion_factor',
        'quantity',
        'unit_cost',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'net_total',
        'return_condition',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:3',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'net_total' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
