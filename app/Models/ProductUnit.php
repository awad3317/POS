<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'unit_name',
        'conversion_factor',
        'barcode',
        'purchase_price',
        'sale_price',
        'is_default',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:3',
        'purchase_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
