<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_images')) {
            return;
        }

        Schema::create('blog_images', function (Blueprint $table): void {
            $table->id();
            $table->string('image_path');
            $table->string('image_path_thumb')->nullable();
            $table->string('storage', 32)->default('public');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestampsTz();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_images');
    }
};
