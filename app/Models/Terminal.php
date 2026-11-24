<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'branch_id',
        'code',
        'name',
        'last_invoice_seq',
        'sanctum_token_id',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'last_invoice_seq' => 'integer',
        'sanctum_token_id' => 'integer',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
