<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_settings', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('key');
                    $table->jsonb('value')->nullable();
                    $table->timestampsTz();
                    $table->unique(['user_id', 'key']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_settings');
    }
};
