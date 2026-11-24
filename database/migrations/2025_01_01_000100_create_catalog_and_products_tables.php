<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'name']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->string('type', 20)->default('simple'); // simple, variant, weighted
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('scale_plu_code', 10)->nullable();
            $table->decimal('purchase_price', 19, 4)->default(0);
            $table->decimal('sale_price', 19, 4)->default(0);
            $table->decimal('wholesale_price', 19, 4)->nullable();
            $table->string('cost_currency', 10)->nullable();
            $table->decimal('foreign_cost_price', 19, 4)->nullable();
            $table->decimal('profit_margin_percent', 8, 2)->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('min_stock_alert', 12, 3)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_quick_pick')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'barcode']);
            $table->index(['store_id', 'scale_plu_code']);
            $table->index(['store_id', 'is_quick_pick']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('size', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('attribute_label');
            $table->string('barcode', 64)->nullable();
            $table->decimal('purchase_price', 19, 4)->default(0);
            $table->decimal('sale_price', 19, 4)->default(0);
            $table->decimal('quantity', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'barcode']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('unit_name', 50);
            $table->decimal('conversion_factor', 12, 3)->default(1);
            $table->string('barcode', 64)->nullable();
            $table->decimal('purchase_price', 19, 4)->default(0);
            $table->decimal('sale_price', 19, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'barcode']);
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->date('expiry_date');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'expiry_date']);
        });

        Schema::create('global_barcode_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('category_hint', 100)->nullable();
            $table->string('default_unit', 50)->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_barcode_catalog');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
