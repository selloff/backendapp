<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_categories', function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('knowledge_base_articles', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('knowledge_base_category_id')->nullable()->constrained('knowledge_base_categories')->nullOnDelete();
                    $table->string('slug')->nullable()->index();
                    $table->string('title');
                    $table->text('content')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('support_tickets', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('subject');
                    $table->string('status', 30)->default('open');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('support_messages', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->text('message')->nullable();
                    $table->boolean('is_admin')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('contact_messages', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->nullable();
                    $table->string('email')->nullable();
                    $table->string('subject')->nullable();
                    $table->text('message')->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('feedbacks', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->unsignedTinyInteger('rating')->nullable();
                    $table->text('feedback')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');

        Schema::dropIfExists('contact_messages');

        Schema::dropIfExists('support_messages');

        Schema::dropIfExists('support_tickets');

        Schema::dropIfExists('knowledge_base_articles');

        Schema::dropIfExists('knowledge_base_categories');
    }
};
