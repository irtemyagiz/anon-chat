<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creator profili ve satıcı doğrulama tabloları.
     */
    public function up(): void
    {
        Schema::create('creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('designer_name', 80);
            $table->string('store_name', 80);
            $table->string('store_slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('social_links')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('terms_accepted_at');
            $table->timestamps();
        });

        Schema::create('creator_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_profile_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name', 160);
            $table->string('entity_type', 20);
            $table->text('tax_number')->nullable();
            $table->string('tax_office', 120)->nullable();
            $table->text('identity_number')->nullable();
            $table->char('country_code', 2);
            $table->text('address')->nullable();
            $table->string('document_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_verifications');
        Schema::dropIfExists('creator_profiles');
    }
};
