<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->string('language_code', 20)->default('en-US')->after('code');
            $table->string('text_direction', 10)->default('ltr')->after('language_code');
            $table->unsignedTinyInteger('language_order')->default(1)->after('text_direction');
            $table->string('text_editor_lang', 20)->default('en')->after('language_order');
            $table->string('flag_path', 500)->nullable()->after('text_editor_lang');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn([
                'language_code',
                'text_direction',
                'language_order',
                'text_editor_lang',
                'flag_path',
            ]);
        });
    }
};
