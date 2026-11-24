<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'branch_id',
        'shift_id',
        'user_id',
        'client_uuid',
        'category',
        'amount',
        'payment_source',
        'spent_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'spent_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
