<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori, etiket, lisans ve ürün tabloları.
     *
     * Fiyatlar USD sent cinsinden tamsayı tutulur. 1 USD = 100 POINT
     * olduğundan sent değeri aynı zamanda puan karşılığıdır.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug', 120)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('image_path')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->json('summary')->nullable();
            $table->boolean('allows_commercial_use')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('file_formats', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 10)->unique();
            $table->string('label', 60);
            $table->json('mime_types');
            $table->boolean('is_archive')->default(false);
            $table->boolean('is_previewable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 190)->unique();
            $table->string('short_description', 300)->nullable();
            $table->longText('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0)->index();
            $table->boolean('is_free')->default(false)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('moderation_note')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('preview_model_path')->nullable();
            $table->string('preview_video_url')->nullable();
            $table->string('current_version', 20)->default('1.0');
            $table->json('print_settings')->nullable();
            $table->boolean('requires_supports')->nullable();
            $table->unsignedInteger('print_time_minutes')->nullable();
            $table->string('filament_type', 60)->nullable();
            $table->string('printer_compatibility', 255)->nullable();
            $table->string('print_technology', 20)->nullable()->index();
            $table->decimal('dimension_x_mm', 10, 2)->nullable();
            $table->decimal('dimension_y_mm', 10, 2)->nullable();
            $table->decimal('dimension_z_mm', 10, 2)->nullable();
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['creator_id', 'status']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('product_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('price_cents');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'license_id']);
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->text('changelog')->nullable();
            $table->timestamp('released_at');
            $table->timestamps();
            $table->unique(['product_id', 'version']);
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 20)->default('pending')->index();
            $table->timestamp('scanned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->json('variants')->nullable();
            $table->string('alt_text', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 30)->index();
            $table->text('details')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reports');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('product_licenses');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('products');
        Schema::dropIfExists('file_formats');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
