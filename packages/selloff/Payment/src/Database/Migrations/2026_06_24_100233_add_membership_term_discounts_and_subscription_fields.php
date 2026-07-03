<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_DISCOUNTS = [
        ['months' => 1, 'discount_percent' => 0],
        ['months' => 3, 'discount_percent' => 15],
        ['months' => 6, 'discount_percent' => 20],
        ['months' => 12, 'discount_percent' => 25],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('membership_term_discounts')) {
            Schema::create('membership_term_discounts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('months')->unique();
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            $now = now();
            foreach (self::DEFAULT_DISCOUNTS as $discount) {
                DB::table('membership_term_discounts')->insert([
                    'months' => $discount['months'],
                    'discount_percent' => $discount['discount_percent'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('user_membership_plans')) {
            Schema::table('user_membership_plans', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_membership_plans', 'last_paid_amount')) {
                    $table->decimal('last_paid_amount', 13, 2)->nullable()->after('expires_at');
                }
                if (! Schema::hasColumn('user_membership_plans', 'term_months')) {
                    $table->unsignedSmallInteger('term_months')->nullable()->after('last_paid_amount');
                }
                if (! Schema::hasColumn('user_membership_plans', 'expiry_notified_at')) {
                    $table->timestampTz('expiry_notified_at')->nullable()->after('term_months');
                }
            });
        }

        if (Schema::hasTable('membership_transactions')) {
            Schema::table('membership_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('membership_transactions', 'term_months')) {
                    $table->unsignedSmallInteger('term_months')->nullable()->after('amount');
                }
                if (! Schema::hasColumn('membership_transactions', 'purchase_type')) {
                    $table->string('purchase_type', 20)->nullable()->after('term_months');
                }
                if (! Schema::hasColumn('membership_transactions', 'gross_amount')) {
                    $table->decimal('gross_amount', 13, 2)->nullable()->after('purchase_type');
                }
                if (! Schema::hasColumn('membership_transactions', 'discount_amount')) {
                    $table->decimal('discount_amount', 13, 2)->nullable()->after('gross_amount');
                }
                if (! Schema::hasColumn('membership_transactions', 'credit_amount')) {
                    $table->decimal('credit_amount', 13, 2)->nullable()->after('discount_amount');
                }
                if (! Schema::hasColumn('membership_transactions', 'amount_charged')) {
                    $table->decimal('amount_charged', 13, 2)->nullable()->after('credit_amount');
                }
                if (! Schema::hasColumn('membership_transactions', 'monthly_price_at_purchase')) {
                    $table->decimal('monthly_price_at_purchase', 13, 2)->nullable()->after('amount_charged');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('membership_transactions')) {
            Schema::table('membership_transactions', function (Blueprint $table): void {
                foreach ([
                    'monthly_price_at_purchase',
                    'amount_charged',
                    'credit_amount',
                    'discount_amount',
                    'gross_amount',
                    'purchase_type',
                    'term_months',
                ] as $column) {
                    if (Schema::hasColumn('membership_transactions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('user_membership_plans')) {
            Schema::table('user_membership_plans', function (Blueprint $table): void {
                foreach (['expiry_notified_at', 'term_months', 'last_paid_amount'] as $column) {
                    if (Schema::hasColumn('user_membership_plans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('membership_term_discounts');
    }
};
