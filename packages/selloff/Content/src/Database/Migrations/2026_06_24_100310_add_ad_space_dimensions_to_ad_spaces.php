<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_spaces', function (Blueprint $table) {
            $table->unsignedSmallInteger('desktop_width')->default(728)->after('ad_code_mobile');
            $table->unsignedSmallInteger('desktop_height')->default(90)->after('desktop_width');
            $table->unsignedSmallInteger('mobile_width')->default(300)->after('desktop_height');
            $table->unsignedSmallInteger('mobile_height')->default(250)->after('mobile_width');
        });
    }

    public function down(): void
    {
        Schema::table('ad_spaces', function (Blueprint $table) {
            $table->dropColumn([
                'desktop_width',
                'desktop_height',
                'mobile_width',
                'mobile_height',
            ]);
        });
    }
};
