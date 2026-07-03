<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_maps', function (Blueprint $table) {
                    $table->id();
                    $table->string('legacy_table');
                    $table->unsignedBigInteger('legacy_id');
                    $table->string('new_table');
                    $table->unsignedBigInteger('new_id');
                    $table->timestampTz('imported_at');
                    $table->index(['legacy_table', 'legacy_id']);
                    $table->index(['new_table', 'new_id']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_maps');
    }
};
