<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sistem ayarı, denetim kaydı, destek, içerik ve SEO tabloları.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80)->index();
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 80)->index();
            $table->string('route', 160)->nullable();
            $table->string('method', 10)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->index();
            $table->string('severity', 10)->index();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 160);
            $table->string('category', 30);
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_staff_reply')->default(false);
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 30)->index();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->json('link_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('slug', 160);
            $table->string('locale', 8);
            $table->string('title', 190);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'slug', 'locale']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8);
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('route_name', 120);
            $table->string('locale', 8);
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();
            $table->unique(['route_name', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
    }
};
