<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para birimi ve döviz kuru tabloları.
     *
     * Kurlar float yerine milyonda bir hassasiyetli tamsayı (rate_micros)
     * olarak saklanır: 1 USD = 40.123456 TRY => 40123456.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('minor_units')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->unsignedBigInteger('rate_micros');
            $table->string('source', 40);
            $table->boolean('is_manual')->default(false);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'fetched_at']);
        });

        Schema::create('exchange_rate_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->boolean('succeeded');
            $table->unsignedBigInteger('rate_micros')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_logs');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
