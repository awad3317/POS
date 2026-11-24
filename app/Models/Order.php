<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'branch_id',
        'terminal_id',
        'shift_id',
        'user_id',
        'customer_id',
        'parent_order_id',
        'client_uuid',
        'invoice_number',
        'type',
        'status',
        'subtotal',
        'item_discount_total',
        'order_discount_amount',
        'tax_total',
        'total_amount',
        'paid_amount',
        'credit_amount',
        'change_amount',
        'discount_override_by',
        'notes',
        'sold_at',
        'synced_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:4',
        'item_discount_total' => 'decimal:4',
        'order_discount_amount' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'change_amount' => 'decimal:4',
        'sold_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function discountOverrideUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_override_by');
    }

    public function getCustomerName(): string
    {
        return $this->customer?->name ?? 'عميل نقدي';
    }
}
