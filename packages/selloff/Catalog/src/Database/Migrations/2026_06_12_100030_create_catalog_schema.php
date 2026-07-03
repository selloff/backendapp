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
                    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
                    $table->string('slug')->nullable()->index();
                    $table->unsignedSmallInteger('min_images')->default(2);
                    $table->decimal('commission_rate', 13, 2)->default(0);
                    $table->decimal('escrow_commission_rate', 13, 2)->default(0);
                    $table->string('icon')->nullable();
                    $table->unsignedInteger('category_order')->default(0);
                    $table->unsignedInteger('featured_order')->default(0);
                    $table->unsignedInteger('homepage_order')->default(0);
                    $table->boolean('status')->default(true);
                    $table->boolean('is_featured')->default(false);
                    $table->boolean('show_on_main_menu')->default(true);
                    $table->boolean('show_image_on_main_menu')->default(false);
                    $table->boolean('show_products_on_index')->default(false);
                    $table->boolean('show_subcategory_products')->default(false);
                    $table->string('storage')->default('local');
                    $table->string('image_path')->nullable();
                    $table->string('image_path_alt')->nullable();
                    $table->boolean('show_description')->default(false);
                    $table->boolean('is_commission_set')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                    $table->timestampsTz();
                });
        
                Schema::create('category_translations', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                    $table->string('locale', 10);
                    $table->string('name');
                    $table->text('description')->nullable();
                    $table->timestampsTz();
                    $table->unique(['category_id', 'locale']);
                });
        
                Schema::create('category_paths', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                    $table->foreignId('ancestor_id')->constrained('categories')->cascadeOnDelete();
                    $table->unsignedInteger('depth')->default(0);
                    $table->unique(['category_id', 'ancestor_id']);
                });
        
                Schema::create('brands', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->index();
                    $table->string('image_path')->nullable();
                    $table->string('storage')->default('local');
                    $table->boolean('show_on_slider')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                    $table->timestampsTz();
                });
        
                Schema::create('brand_translations', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                    $table->string('locale', 10);
                    $table->string('name');
                    $table->timestampsTz();
                    $table->unique(['brand_id', 'locale']);
                });
        
                Schema::create('brand_category', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                    $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                    $table->unique(['brand_id', 'category_id']);
                });
        
                Schema::create('tags', function (Blueprint $table) {
                    $table->id();
                    $table->string('tag')->unique();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('products', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                    $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
                    $table->string('slug')->nullable()->index();
                    $table->string('sku')->nullable()->index();
                    $table->string('type', 30)->default('physical');
                    $table->string('listing_type', 30)->nullable();
                    $table->string('status', 30)->default('draft');
                    $table->string('visibility', 30)->default('visible');
                    $table->boolean('is_active')->default(true);
                    $table->boolean('is_sold')->default(false);
                    $table->boolean('is_verified')->default(false);
                    $table->boolean('multiple_sale')->default(false);
                    $table->decimal('price', 13, 2)->default(0);
                    $table->decimal('price_discounted', 13, 2)->nullable();
                    $table->string('currency_code', 10)->nullable();
                    $table->unsignedInteger('stock')->default(0);
                    $table->unsignedInteger('pageviews')->default(0);
                    $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                    $table->timestampsTz();
                    $table->index(['vendor_id', 'status']);
                });
        
                Schema::create('product_translations', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->string('locale', 10);
                    $table->string('title');
                    $table->text('description')->nullable();
                    $table->text('short_description')->nullable();
                    $table->timestampsTz();
                    $table->unique(['product_id', 'locale']);
                });
        
                Schema::create('product_options', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->string('name');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('product_option_values', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();
                    $table->string('value');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('product_variants', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->string('sku')->nullable()->index();
                    $table->string('variant_hash')->nullable()->index();
                    $table->decimal('price', 13, 2)->default(0);
                    $table->decimal('price_discounted', 13, 2)->nullable();
                    $table->unsignedInteger('stock')->default(0);
                    $table->boolean('is_default')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('product_variant_option_values', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                    $table->foreignId('product_option_value_id')->constrained('product_option_values')->cascadeOnDelete();
                    $table->unique(['product_variant_id', 'product_option_value_id'], 'product_variant_option_value_unique');
                });
        
                Schema::create('custom_fields', function (Blueprint $table) {
                    $table->id();
                    $table->string('field_type', 30)->nullable();
                    $table->boolean('is_required')->default(false);
                    $table->boolean('status')->default(true);
                    $table->unsignedInteger('field_order')->default(1);
                    $table->boolean('is_product_filter')->default(false);
                    $table->string('product_filter_key')->nullable()->index();
                    $table->string('sort_options', 30)->default('alphabetically');
                    $table->unsignedTinyInteger('where_to_display')->default(2);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('custom_field_options', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
                    $table->string('option_key');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                    $table->index(['custom_field_id', 'option_key']);
                });
        
                Schema::create('custom_field_category', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
                    $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                    $table->unique(['custom_field_id', 'category_id']);
                });
        
                Schema::create('custom_field_product', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->string('product_filter_key')->nullable()->index();
                    $table->string('field_value', 1000)->nullable();
                    $table->foreignId('custom_field_option_id')->nullable()->constrained('custom_field_options')->nullOnDelete();
                    $table->index(['product_id', 'custom_field_id']);
                });
        
                Schema::create('product_tag', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                    $table->unique(['product_id', 'tag_id']);
                });
        
                Schema::create('product_license_keys', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->string('license_key');
                    $table->boolean('is_used')->default(false);
                    $table->unsignedBigInteger('order_id')->nullable()->index();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('wishlists', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->timestampsTz();
                    $table->unique(['user_id', 'product_id']);
                });
        
                Schema::create('digital_files', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('file_name');
                    $table->string('storage')->default('local');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_files');

        Schema::dropIfExists('wishlists');

        Schema::dropIfExists('product_license_keys');

        Schema::dropIfExists('product_tag');

        Schema::dropIfExists('custom_field_product');

        Schema::dropIfExists('custom_field_category');

        Schema::dropIfExists('custom_field_options');

        Schema::dropIfExists('custom_fields');

        Schema::dropIfExists('product_variant_option_values');

        Schema::dropIfExists('product_variants');

        Schema::dropIfExists('product_option_values');

        Schema::dropIfExists('product_options');

        Schema::dropIfExists('product_translations');

        Schema::dropIfExists('products');

        Schema::dropIfExists('tags');

        Schema::dropIfExists('brand_category');

        Schema::dropIfExists('brand_translations');

        Schema::dropIfExists('brands');

        Schema::dropIfExists('category_paths');

        Schema::dropIfExists('category_translations');

        Schema::dropIfExists('categories');
    }
};
