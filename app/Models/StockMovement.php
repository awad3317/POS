<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToStore, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'branch_id',
        'product_id',
        'variant_id',
        'user_id',
        'type',
        'quantity_delta',
        'balance_before',
        'balance_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'is_negative_alert',
        'note',
        'created_at',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:3',
        'balance_before' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'is_negative_alert' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
