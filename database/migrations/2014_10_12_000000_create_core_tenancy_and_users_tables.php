<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('business_type', 30)->default('general'); // grocery, clothing, perfumes, general
            $table->string('currency_symbol', 20)->default('ر.ي');
            $table->string('currency_code', 10)->default('YER');
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->string('secondary_currency_code', 10)->nullable();
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->json('feature_flags')->nullable();
            $table->json('receipt_settings')->nullable();
            $table->string('subscription_status', 30)->default('trial'); // active, trial, suspended
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 50)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('pin_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->unsignedBigInteger('last_invoice_seq')->default(0);
            $table->unsignedBigInteger('sanctum_token_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('stores');
    }
};
