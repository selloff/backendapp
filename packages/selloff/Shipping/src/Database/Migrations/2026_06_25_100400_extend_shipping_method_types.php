<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_methods')) {
            return;
        }

        Schema::table('shipping_methods', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipping_methods', 'method_type')) {
                $table->string('method_type', 50)->default('flat_rate')->after('name');
            }
            if (! Schema::hasColumn('shipping_methods', 'free_shipping_min_amount')) {
                $table->decimal('free_shipping_min_amount', 13, 2)->nullable()->after('flat_rate');
            }
            if (! Schema::hasColumn('shipping_methods', 'local_pickup_cost')) {
                $table->decimal('local_pickup_cost', 13, 2)->nullable()->after('free_shipping_min_amount');
            }
            if (! Schema::hasColumn('shipping_methods', 'cost_calculation_type')) {
                $table->string('cost_calculation_type', 50)->nullable()->after('local_pickup_cost');
            }
            if (! Schema::hasColumn('shipping_methods', 'shipping_flat_cost')) {
                $table->decimal('shipping_flat_cost', 13, 2)->nullable()->after('cost_calculation_type');
            }
            if (! Schema::hasColumn('shipping_methods', 'flat_rate_costs')) {
                $table->json('flat_rate_costs')->nullable()->after('shipping_flat_cost');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_methods')) {
            return;
        }

        Schema::table('shipping_methods', function (Blueprint $table): void {
            foreach ([
                'flat_rate_costs',
                'shipping_flat_cost',
                'cost_calculation_type',
                'local_pickup_cost',
                'free_shipping_min_amount',
                'method_type',
            ] as $column) {
                if (Schema::hasColumn('shipping_methods', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
