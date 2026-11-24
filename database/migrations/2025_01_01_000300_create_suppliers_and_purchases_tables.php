<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->decimal('opening_balance', 19, 4)->default(0);
            $table->decimal('current_balance', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'name']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->string('type', 30)->default('purchase'); // purchase, purchase_return
            $table->string('reference_number', 100)->nullable();
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_source', 30)->nullable(); // cash_drawer, owner_pocket, bank
            $table->timestamp('purchased_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'purchased_at']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('product_units')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('conversion_factor', 12, 3)->default(1);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('net_total', 19, 4);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->string('method', 30); // cash, kuraimi, jawali, floosak, onecash, bank_transfer
            $table->string('payment_source', 30); // cash_drawer, owner_pocket, bank
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 19, 4);
            $table->decimal('balance_before', 19, 4);
            $table->decimal('balance_after', 19, 4);
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'supplier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
