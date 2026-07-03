<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_jobs', function (Blueprint $table) {
                    $table->id();
                    $table->string('to_email');
                    $table->string('subject')->nullable();
                    $table->text('body')->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->timestampTz('sent_at')->nullable();
                    $table->jsonb('metadata')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('newsletter_subscribers', function (Blueprint $table) {
                    $table->id();
                    $table->string('email')->unique();
                    $table->boolean('is_active')->default(true);
                    $table->string('token')->nullable()->index();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');

        Schema::dropIfExists('email_jobs');
    }
};
