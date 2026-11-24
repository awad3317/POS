<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'branch_id',
        'terminal_id',
        'user_id',
        'client_uuid',
        'opened_at',
        'closed_at',
        'opening_cash',
        'cash_sales_total',
        'cash_returns_total',
        'cash_in_total',
        'cash_out_total',
        'expected_cash',
        'actual_cash',
        'difference',
        'status',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:4',
        'cash_sales_total' => 'decimal:4',
        'cash_returns_total' => 'decimal:4',
        'cash_in_total' => 'decimal:4',
        'cash_out_total' => 'decimal:4',
        'expected_cash' => 'decimal:4',
        'actual_cash' => 'decimal:4',
        'difference' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function employeeTransactions(): HasMany
    {
        return $this->hasMany(EmployeeTransaction::class);
    }
}
