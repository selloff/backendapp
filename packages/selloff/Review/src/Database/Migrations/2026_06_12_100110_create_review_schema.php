<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->unsignedTinyInteger('rating')->default(0);
                    $table->text('review')->nullable();
                    $table->boolean('is_approved')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('comments', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
                    $table->text('comment')->nullable();
                    $table->boolean('is_approved')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('abuse_reports', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('report_type', 50)->nullable();
                    $table->text('description')->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('abuse_reports');

        Schema::dropIfExists('comments');

        Schema::dropIfExists('product_reviews');
    }
};
