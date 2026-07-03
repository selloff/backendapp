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
            if (! Schema::hasColumn('membership_plans', 'visibility_multiplier')) {
                $table->decimal('visibility_multiplier', 8, 2)->default(1)->after('features');
            }
            if (! Schema::hasColumn('membership_plans', 'global_listing_limit')) {
                $table->unsignedInteger('global_listing_limit')->nullable()->after('visibility_multiplier');
            }
            if (! Schema::hasColumn('membership_plans', 'auto_bump_interval_hours')) {
                $table->unsignedInteger('auto_bump_interval_hours')->nullable()->after('global_listing_limit');
            }
            if (! Schema::hasColumn('membership_plans', 'top_credits_per_period')) {
                $table->unsignedInteger('top_credits_per_period')->default(0)->after('auto_bump_interval_hours');
            }
            if (! Schema::hasColumn('membership_plans', 'top_badge_label')) {
                $table->string('top_badge_label')->nullable()->after('top_credits_per_period');
            }
            if (! Schema::hasColumn('membership_plans', 'top_rank_weight')) {
                $table->unsignedInteger('top_rank_weight')->default(0)->after('top_badge_label');
            }
            if (! Schema::hasColumn('membership_plans', 'allow_website_link')) {
                $table->boolean('allow_website_link')->default(false)->after('top_rank_weight');
            }
            if (! Schema::hasColumn('membership_plans', 'allow_social_links')) {
                $table->boolean('allow_social_links')->default(false)->after('allow_website_link');
            }
            if (! Schema::hasColumn('membership_plans', 'allow_whatsapp_link')) {
                $table->boolean('allow_whatsapp_link')->default(false)->after('allow_social_links');
            }
            if (! Schema::hasColumn('membership_plans', 'hide_seller_feedback')) {
                $table->boolean('hide_seller_feedback')->default(false)->after('allow_whatsapp_link');
            }
            if (! Schema::hasColumn('membership_plans', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('hide_seller_feedback');
            }
            if (! Schema::hasColumn('membership_plans', 'marketing_benefits')) {
                $table->json('marketing_benefits')->nullable()->after('is_free');
            }
        });

        if (! Schema::hasTable('membership_plan_category_limits')) {
            Schema::create('membership_plan_category_limits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->integer('max_active_listings');
                $table->timestampsTz();
                $table->unique(['membership_plan_id', 'category_id']);
            });
        }

        if (Schema::hasTable('user_membership_plans')) {
            Schema::table('user_membership_plans', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_membership_plans', 'entitlements_snapshot')) {
                    $table->jsonb('entitlements_snapshot')->nullable()->after('expiry_notified_at');
                }
                if (! Schema::hasColumn('user_membership_plans', 'top_credits_remaining')) {
                    $table->unsignedInteger('top_credits_remaining')->default(0)->after('entitlements_snapshot');
                }
                if (! Schema::hasColumn('user_membership_plans', 'top_credits_period_started_at')) {
                    $table->timestampTz('top_credits_period_started_at')->nullable()->after('top_credits_remaining');
                }
                if (! Schema::hasColumn('user_membership_plans', 'top_credits_period_ends_at')) {
                    $table->timestampTz('top_credits_period_ends_at')->nullable()->after('top_credits_period_started_at');
                }
            });
        }

        if (! Schema::hasTable('membership_top_applications')) {
            Schema::create('membership_top_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_membership_plan_id')->constrained('user_membership_plans')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('credits_consumed')->default(1);
                $table->timestampTz('applied_at');
                $table->timestampTz('expires_at')->nullable();
                $table->timestampsTz();
                $table->index(['product_id', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_top_applications');

        if (Schema::hasTable('user_membership_plans')) {
            Schema::table('user_membership_plans', function (Blueprint $table): void {
                foreach ([
                    'entitlements_snapshot',
                    'top_credits_remaining',
                    'top_credits_period_started_at',
                    'top_credits_period_ends_at',
                ] as $column) {
                    if (Schema::hasColumn('user_membership_plans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('membership_plan_category_limits');

        if (! Schema::hasTable('membership_plans')) {
            return;
        }

        Schema::table('membership_plans', function (Blueprint $table): void {
            foreach ([
                'visibility_multiplier',
                'global_listing_limit',
                'auto_bump_interval_hours',
                'top_credits_per_period',
                'top_badge_label',
                'top_rank_weight',
                'allow_website_link',
                'allow_social_links',
                'allow_whatsapp_link',
                'hide_seller_feedback',
                'is_free',
                'marketing_benefits',
            ] as $column) {
                if (Schema::hasColumn('membership_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
