<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listing_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('traffic')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('contact_views')->default(0);
            $table->unsignedInteger('chats')->default(0);
            $table->decimal('promotion_spend', 13, 2)->default(0);
            $table->timestampsTz();

            $table->unique(['vendor_id', 'metric_date']);
            $table->index(['vendor_id', 'metric_date']);
        });

        Schema::create('vendor_listing_metric_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('visitor_key', 120);
            $table->timestampsTz();

            $table->unique(['vendor_id', 'metric_date', 'visitor_key']);
        });

        Schema::create('vendor_listing_contact_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('viewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_contact_views');
        Schema::dropIfExists('vendor_listing_metric_visitors');
        Schema::dropIfExists('vendor_listing_daily_metrics');
    }
};
