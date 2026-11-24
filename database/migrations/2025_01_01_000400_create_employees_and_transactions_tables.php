<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('salary_type', 30)->default('monthly'); // monthly, weekly, daily, commission_only
            $table->decimal('base_salary', 19, 4)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('opening_balance', 19, 4)->default(0);
            $table->decimal('current_balance', 19, 4)->default(0);
            $table->date('hired_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('employee_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->string('type', 30); // salary_accrual, bonus, advance, salary_payout, deduction, shift_shortage
            $table->string('payment_source', 30)->nullable(); // cash_drawer, owner_pocket, kuraimi, jawali, floosak, bank
            $table->decimal('amount', 19, 4);
            $table->decimal('signed_amount', 19, 4);
            $table->decimal('balance_before', 19, 4);
            $table->decimal('balance_after', 19, 4);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('transaction_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'employee_id', 'transaction_date'], 'emp_tx_store_emp_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_transactions');
        Schema::dropIfExists('employees');
    }
};
