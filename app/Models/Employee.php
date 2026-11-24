<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToStore, HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'branch_id',
        'user_id',
        'name',
        'phone',
        'whatsapp',
        'job_title',
        'salary_type',
        'base_salary',
        'commission_percent',
        'opening_balance',
        'current_balance',
        'hired_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:4',
        'commission_percent' => 'decimal:2',
        'opening_balance' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'hired_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EmployeeTransaction::class);
    }
}
