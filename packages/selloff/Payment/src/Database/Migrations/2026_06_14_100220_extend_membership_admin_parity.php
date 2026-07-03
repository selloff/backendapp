<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('membership_plans')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_plans', 'plan_order')) {
                $table->unsignedInteger('plan_order')->default(1)->after('duration_days');
            }
            if (! Schema::hasColumn('membership_plans', 'is_popular')) {
                $table->boolean('is_popular')->default(false)->after('plan_order');
            }
            if (! Schema::hasColumn('membership_plans', 'features')) {
                $table->json('features')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('membership_plans')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            foreach (['plan_order', 'is_popular', 'features'] as $column) {
                if (Schema::hasColumn('membership_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
