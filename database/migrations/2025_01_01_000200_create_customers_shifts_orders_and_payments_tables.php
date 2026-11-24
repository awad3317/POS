<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->decimal('opening_balance', 19, 4)->default(0);
            $table->decimal('credit_limit', 19, 4)->default(0);
            $table->decimal('current_balance', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'phone']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('client_uuid', 36)->unique();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 19, 4)->default(0);
            $table->decimal('cash_sales_total', 19, 4)->default(0);
            $table->decimal('cash_returns_total', 19, 4)->default(0);
            $table->decimal('cash_in_total', 19, 4)->default(0);
            $table->decimal('cash_out_total', 19, 4)->default(0);
            $table->decimal('expected_cash', 19, 4)->default(0);
            $table->decimal('actual_cash', 19, 4)->nullable();
            $table->decimal('difference', 19, 4)->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'terminal_id', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->char('client_uuid', 36)->unique();
            $table->string('invoice_number', 50);
            $table->string('type', 20)->default('sale'); // sale, return
            $table->string('status', 30)->default('completed'); // completed, partially_refunded, refunded
            $table->decimal('subtotal', 19, 4);
            $table->decimal('item_discount_total', 19, 4)->default(0);
            $table->decimal('order_discount_amount', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->decimal('change_amount', 19, 4)->default(0);
            $table->foreignId('discount_override_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('sold_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'invoice_number']);
            $table->index(['store_id', 'sold_at']);
            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'shift_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('product_units')->restrictOnDelete();
            $table->string('product_name');
            $table->string('barcode_snapshot', 64)->nullable();
            $table->string('unit_name_snapshot', 50)->nullable();
            $table->decimal('conversion_factor', 12, 3)->default(1);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('net_total', 19, 4);
            $table->string('return_condition', 20)->nullable(); // restock, damaged
            $table->timestamps();

            $table->index(['store_id', 'order_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('client_uuid', 36)->unique();
            $table->string('payment_type', 30); // order_payment, credit_settlement, refund_payout
            $table->string('method', 30); // cash, kuraimi, jawali, floosak, onecash, card, bank_transfer
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 19, 4);
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('customers');
    }
};
