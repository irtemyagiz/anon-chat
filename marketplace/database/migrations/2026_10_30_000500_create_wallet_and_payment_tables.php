<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Puan cüzdanı (çift taraflı defter), puan paketleri ve ödeme tabloları.
     *
     * Puanlar her zaman tamsayıdır. Para tutarları ilgili para biriminin
     * en küçük biriminde (sent/kuruş) tamsayı olarak tutulur.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('system_code', 40)->nullable()->unique();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('pending_balance')->default(0);
            $table->unsignedBigInteger('total_loaded')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->string('type', 30)->index();
            $table->string('description')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'id']);
        });

        Schema::create('wallet_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('points');
            $table->string('reason', 500);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('point_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->unsignedInteger('points');
            $table->unsignedInteger('price_usd_cents');
            $table->unsignedInteger('bonus_points')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->string('mode', 10)->default('sandbox');
            $table->boolean('is_enabled')->default(false);
            $table->json('supported_currencies');
            $table->json('supported_countries')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('point_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('point_package_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('points');
            $table->unsignedInteger('usd_cents');
            $table->char('charge_currency', 3);
            $table->unsignedBigInteger('charge_amount_minor');
            $table->unsignedBigInteger('exchange_rate_micros')->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->index();
            $table->string('provider_reference', 191)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_purchase_id')->constrained()->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('provider_transaction_id', 191);
            $table->string('type', 20);
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id', 'type'], 'payment_tx_provider_ref_type_unique');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 191);
            $table->string('event_type', 80)->nullable();
            $table->boolean('signature_valid');
            $table->string('status', 20)->default('received')->index();
            $table->longText('payload');
            $table->text('processing_error')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('point_purchase_id')->constrained()->restrictOnDelete();
            $table->string('provider_refund_id', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('points_reversed');
            $table->string('status', 20)->index();
            $table->string('reason', 500)->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_purchase_id')->constrained()->restrictOnDelete();
            $table->string('provider_dispute_id', 191);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason', 120)->nullable();
            $table->string('status', 20)->index();
            $table->unsignedInteger('points_reversed')->default(0);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['point_purchase_id', 'provider_dispute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('point_purchases');
        Schema::dropIfExists('payment_providers');
        Schema::dropIfExists('point_packages');
        Schema::dropIfExists('wallet_adjustments');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
