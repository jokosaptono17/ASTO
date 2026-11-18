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
            $table->string('name');
            $table->string('icon', 60)->default('ri-apps-2-line');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            $table->string('tagline', 160)->nullable();
            $table->string('url', 500);
            $table->string('icon_type', 10)->default('remix'); // remix | image
            $table->string('icon', 255)->default('ri-apps-2-fill');
            $table->string('color', 9)->default('#3BFF8A');
            $table->string('placement', 12)->default('list'); // featured | grid | list
            $table->string('badge', 20)->nullable();
            $table->boolean('new_tab')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('health_check')->default(true);
            $table->string('health_status', 10)->default('unknown'); // up | down | unknown
            $table->unsignedSmallInteger('health_ms')->nullable();
            $table->timestamp('health_checked_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('subtitle', 200)->nullable();
            $table->string('format', 10)->default('card'); // card (4:5) | banner (2:1)
            $table->string('image_path');
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 40)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->string('level', 10)->default('info'); // info | penting | darurat
            $table->string('link_url', 500)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('app_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'app_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('mentari_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 160);
            $table->string('keywords', 300);
            $table->text('answer');
            $table->string('link_label', 40)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('description', 255);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'mentari_faqs', 'settings', 'favorites', 'app_visits', 'announcements', 'campaign_clicks', 'campaigns', 'apps', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
