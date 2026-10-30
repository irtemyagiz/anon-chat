<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sipariş, indirme hakkı, kupon, komisyon ve creator hakediş tabloları.
     *
     * Creator kazançları puan cüzdanından tamamen ayrıdır ve USD sent
     * cinsinden parasal hakediş olarak tutulur.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('discount_type', 10);
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['creator_id', 'code']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('total_points');
            $table->string('status', 20)->index();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('license_id')->constrained()->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_title', 160);
            $table->unsignedInteger('list_price_points');
            $table->unsignedInteger('discount_points')->default(0);
            $table->unsignedInteger('price_points');
            $table->unsignedSmallInteger('commission_bps');
            $table->unsignedInteger('creator_amount_cents');
            $table->unsignedInteger('platform_amount_cents');
            $table->string('status', 20)->index();
            $table->timestamps();

            $table->index(['creator_id', 'created_at']);
        });

        Schema::create('purchase_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('license_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'license_id']);
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_entitlement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20)->index();
            $table->foreignId('creator_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('rate_bps');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('creator_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->unique()->constrained('users')->restrictOnDelete();
            $table->bigInteger('pending_cents')->default(0);
            $table->bigInteger('available_cents')->default(0);
            $table->bigInteger('reserved_cents')->default(0);
            $table->bigInteger('paid_cents')->default(0);
            $table->bigInteger('reversed_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('creator_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('method', 30);
            $table->text('payout_details');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('creator_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_payout_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->char('paid_currency', 3);
            $table->unsignedBigInteger('paid_amount_minor');
            $table->unsignedBigInteger('exchange_rate_micros')->nullable();
            $table->string('reference', 191)->nullable();
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('creator_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('gross_cents');
            $table->unsignedSmallInteger('commission_bps');
            $table->unsignedInteger('platform_cents');
            $table->unsignedInteger('net_cents');
            $table->string('status', 20)->index();
            $table->timestamp('available_at')->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('creator_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['creator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_earnings');
        Schema::dropIfExists('creator_payouts');
        Schema::dropIfExists('creator_payout_requests');
        Schema::dropIfExists('creator_balances');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('purchase_entitlements');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
    }
};
