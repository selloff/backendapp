<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages')) {
            Schema::table('pages', function (Blueprint $table): void {
                if (! Schema::hasColumn('pages', 'location')) {
                    $table->string('location', 50)->default('information')->after('locale');
                }
                if (! Schema::hasColumn('pages', 'is_custom')) {
                    $table->boolean('is_custom')->default(true)->after('is_active');
                }
            });
        }

        if (Schema::hasTable('blog_categories')) {
            Schema::table('blog_categories', function (Blueprint $table): void {
                if (! Schema::hasColumn('blog_categories', 'lang_id')) {
                    $table->unsignedTinyInteger('lang_id')->default(1)->after('name');
                }
                if (! Schema::hasColumn('blog_categories', 'description')) {
                    $table->string('description', 500)->nullable()->after('slug');
                }
                if (! Schema::hasColumn('blog_categories', 'keywords')) {
                    $table->string('keywords', 500)->nullable()->after('description');
                }
                if (! Schema::hasColumn('blog_categories', 'category_order')) {
                    $table->unsignedInteger('category_order')->default(1)->after('keywords');
                }
            });
        }

        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('blog_posts', 'lang_id')) {
                    $table->unsignedTinyInteger('lang_id')->default(1)->after('user_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            Schema::table('pages', function (Blueprint $table): void {
                foreach (['location', 'is_custom'] as $column) {
                    if (Schema::hasColumn('pages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('blog_categories')) {
            Schema::table('blog_categories', function (Blueprint $table): void {
                foreach (['lang_id', 'description', 'keywords', 'category_order'] as $column) {
                    if (Schema::hasColumn('blog_categories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table): void {
                if (Schema::hasColumn('blog_posts', 'lang_id')) {
                    $table->dropColumn('lang_id');
                }
            });
        }
    }
};
