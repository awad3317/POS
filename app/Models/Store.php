<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    public const SUBSCRIPTION_BLOCKED_MESSAGE = 'انتهى اشتراك المتجر أو تم إيقافه، يرجى التواصل مع الإدارة لتجديد الاشتراك.';

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'phone',
        'address',
        'tax_number',
        'business_type',
        'currency_symbol',
        'currency_code',
        'decimal_places',
        'secondary_currency_code',
        'exchange_rate',
        'feature_flags',
        'receipt_settings',
        'subscription_status',
        'subscription_ends_at',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'exchange_rate' => 'decimal:4',
        'feature_flags' => 'array',
        'receipt_settings' => 'array',
        'subscription_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isSubscriptionBlocked(): bool
    {
        if ($this->subscription_status === 'suspended') {
            return true;
        }

        if ($this->subscription_ends_at !== null && $this->subscription_ends_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isPast();
    }

    public function businessTypeLabel(): string
    {
        return match ($this->business_type) {
            'grocery' => 'بقالة',
            'clothing' => 'ملابس',
            'perfumes' => 'عطور',
            'general' => 'تجزئة',
            default => $this->business_type ?? 'تجزئة',
        };
    }
}
