<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30); // sale, purchase, purchase_return, return_restock, return_damaged, adjustment_in, adjustment_out, stocktake
            $table->decimal('quantity_delta', 12, 3);
            $table->decimal('balance_before', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_negative_alert')->default(false);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'product_id', 'created_at']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->string('category', 100);
            $table->decimal('amount', 19, 4);
            $table->string('payment_source', 30); // cash_drawer, owner_pocket, bank
            $table->timestamp('spent_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('stock_movements');
    }
};
