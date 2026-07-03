<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('pageviews')->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->after('country_id')->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('state_id')->constrained('cities')->nullOnDelete();
            $table->string('address', 500)->nullable()->after('city_id');
            $table->string('zip_code', 50)->nullable()->after('address');
            $table->jsonb('shipping_dimensions')->nullable()->after('zip_code');
            $table->string('video_path', 500)->nullable()->after('shipping_dimensions');
            $table->string('video_disk', 50)->nullable()->after('video_path');
            $table->string('audio_path', 500)->nullable()->after('video_disk');
            $table->string('audio_disk', 50)->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn([
                'address',
                'zip_code',
                'shipping_dimensions',
                'video_path',
                'video_disk',
                'audio_path',
                'audio_disk',
            ]);
        });
    }
};
