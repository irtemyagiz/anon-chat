<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Önizlemenin creator tarafından mı yüklendiği, yoksa satılan STL'den otomatik
     * (sadeleştirilerek) mi üretildiği.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('preview_auto_generated')->default(false)->after('preview_model_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('preview_auto_generated');
        });
    }
};
