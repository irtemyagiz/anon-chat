<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parça parça (chunked) ve kaldığı yerden devam ettirilebilen büyük dosya yüklemeleri.
     * Parçalar yerel diskte birleştirilir; tamamlanınca dosya normal güvenlik
     * kontrollerinden geçirilip ürüne eklenir ve oturum silinir.
     */
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
