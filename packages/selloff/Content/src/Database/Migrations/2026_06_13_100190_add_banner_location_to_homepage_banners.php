<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_banners')) {
            return;
        }

        if (! Schema::hasColumn('homepage_banners', 'banner_location')) {
            Schema::table('homepage_banners', function (Blueprint $table) {
                $table->string('banner_location', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('homepage_banners', 'banner_width')) {
            Schema::table('homepage_banners', function (Blueprint $table) {
                $table->unsignedTinyInteger('banner_width')->default(50);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('homepage_banners')) {
            return;
        }

        Schema::table('homepage_banners', function (Blueprint $table) {
            if (Schema::hasColumn('homepage_banners', 'banner_width')) {
                $table->dropColumn('banner_width');
            }
            if (Schema::hasColumn('homepage_banners', 'banner_location')) {
                $table->dropColumn('banner_location');
            }
        });
    }
};
