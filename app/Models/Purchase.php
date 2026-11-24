<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'branch_id',
        'supplier_id',
        'user_id',
        'parent_purchase_id',
        'shift_id',
        'type',
        'reference_number',
        'subtotal',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'credit_amount',
        'payment_method',
        'payment_source',
        'purchased_at',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'purchased_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentPurchase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_purchase_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
