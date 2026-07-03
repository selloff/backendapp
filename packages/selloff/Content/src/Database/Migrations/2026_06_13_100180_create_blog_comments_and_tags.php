<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('tag');
            $table->string('tag_slug')->index();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestampsTz();
            $table->index(['tag_slug', 'blog_post_id']);
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('comment');
            $table->string('status', 20)->default('pending');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestampsTz();
            $table->index(['blog_post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_tags');
    }
};
