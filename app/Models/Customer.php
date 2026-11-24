<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToStore, HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'client_uuid',
        'name',
        'phone',
        'whatsapp',
        'address',
        'opening_balance',
        'credit_limit',
        'current_balance',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'credit_limit' => 'decimal:4',
        'current_balance' => 'decimal:4',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
