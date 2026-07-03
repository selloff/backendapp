<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'top_boost_active')) {
                $table->boolean('top_boost_active')->default(false)->after('is_promoted');
            }
            if (! Schema::hasColumn('products', 'top_boost_expires_at')) {
                $table->timestampTz('top_boost_expires_at')->nullable()->after('top_boost_active');
            }
            if (! Schema::hasColumn('products', 'top_boost_weight')) {
                $table->unsignedInteger('top_boost_weight')->default(0)->after('top_boost_expires_at');
            }
            if (! Schema::hasColumn('products', 'last_bumped_at')) {
                $table->timestampTz('last_bumped_at')->nullable()->after('top_boost_weight');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach (['top_boost_active', 'top_boost_expires_at', 'top_boost_weight', 'last_bumped_at'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
