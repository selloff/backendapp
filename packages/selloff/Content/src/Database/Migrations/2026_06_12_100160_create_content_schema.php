<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
                    $table->id();
                    $table->string('slug')->unique();
                    $table->string('title');
                    $table->text('content')->nullable();
                    $table->string('locale', 10)->default('en');
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('blog_categories', function (Blueprint $table) {
                    $table->id();
                    $table->string('slug')->nullable()->index();
                    $table->string('name');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('blog_posts', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('slug')->nullable()->index();
                    $table->string('title');
                    $table->text('summary')->nullable();
                    $table->text('content')->nullable();
                    $table->string('image_path')->nullable();
                    $table->boolean('is_published')->default(false);
                    $table->timestampTz('published_at')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('blog_post_category', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
                    $table->foreignId('blog_category_id')->constrained('blog_categories')->cascadeOnDelete();
                    $table->unique(['blog_post_id', 'blog_category_id']);
                });
        
                Schema::create('sliders', function (Blueprint $table) {
                    $table->id();
                    $table->string('title')->nullable();
                    $table->string('image_path')->nullable();
                    $table->string('link')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('homepage_banners', function (Blueprint $table) {
                    $table->id();
                    $table->string('title')->nullable();
                    $table->string('image_path')->nullable();
                    $table->string('link')->nullable();
                    $table->string('banner_location', 64)->nullable();
                    $table->unsignedTinyInteger('banner_width')->default(50);
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('ad_spaces', function (Blueprint $table) {
                    $table->id();
                    $table->string('ad_space_key')->index();
                    $table->string('title')->nullable();
                    $table->text('ad_code')->nullable();
                    $table->string('url')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spaces');

        Schema::dropIfExists('homepage_banners');

        Schema::dropIfExists('sliders');

        Schema::dropIfExists('blog_post_category');

        Schema::dropIfExists('blog_posts');

        Schema::dropIfExists('blog_categories');

        Schema::dropIfExists('pages');
    }
};
