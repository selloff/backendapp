<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->string('label')->nullable()->after('field_type');
        });

        Schema::table('custom_field_options', function (Blueprint $table) {
            $table->string('label')->nullable()->after('option_key');
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_options', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
