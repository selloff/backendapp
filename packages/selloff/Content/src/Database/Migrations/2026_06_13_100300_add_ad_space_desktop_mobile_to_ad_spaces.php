<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_spaces', function (Blueprint $table) {
            $table->text('ad_code_desktop')->nullable()->after('ad_code');
            $table->text('ad_code_mobile')->nullable()->after('ad_code_desktop');
        });

        DB::table('ad_spaces')
            ->whereNotNull('ad_code')
            ->whereNull('ad_code_desktop')
            ->update(['ad_code_desktop' => DB::raw('ad_code')]);
    }

    public function down(): void
    {
        Schema::table('ad_spaces', function (Blueprint $table) {
            $table->dropColumn(['ad_code_desktop', 'ad_code_mobile']);
        });
    }
};
