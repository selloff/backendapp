<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_point_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('referral_point_transactions', 'money_per_point')) {
                $table->decimal('money_per_point', 13, 2)->nullable()->after('points');
            }

            if (! Schema::hasColumn('referral_point_transactions', 'points_remaining')) {
                $table->unsignedInteger('points_remaining')->nullable()->after('money_per_point');
            }
        });

        $defaultRate = (float) (config('selloff.referral_program.money_per_point') ?? 10);

        DB::table('referral_point_transactions')
            ->where('type', 'earn')
            ->whereNull('money_per_point')
            ->update(['money_per_point' => $defaultRate]);

        DB::table('referral_point_transactions')
            ->where('type', 'earn')
            ->whereNull('points_remaining')
            ->update(['points_remaining' => DB::raw('points')]);

        $userIds = DB::table('referral_point_transactions')
            ->where('type', 'redeem')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->replayRedemptionsForUser((int) $userId);
        }
    }

    public function down(): void
    {
        Schema::table('referral_point_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('referral_point_transactions', 'points_remaining')) {
                $table->dropColumn('points_remaining');
            }

            if (Schema::hasColumn('referral_point_transactions', 'money_per_point')) {
                $table->dropColumn('money_per_point');
            }
        });
    }

    private function replayRedemptionsForUser(int $userId): void
    {
        $earnLots = DB::table('referral_point_transactions')
            ->where('user_id', $userId)
            ->where('type', 'earn')
            ->orderBy('id')
            ->get(['id', 'points']);

        foreach ($earnLots as $lot) {
            DB::table('referral_point_transactions')
                ->where('id', $lot->id)
                ->update(['points_remaining' => $lot->points]);
        }

        $redeems = DB::table('referral_point_transactions')
            ->where('user_id', $userId)
            ->where('type', 'redeem')
            ->orderBy('id')
            ->pluck('points');

        foreach ($redeems as $pointsToRedeem) {
            $remaining = (int) $pointsToRedeem;

            foreach ($earnLots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $available = (int) DB::table('referral_point_transactions')
                    ->where('id', $lot->id)
                    ->value('points_remaining');

                if ($available <= 0) {
                    continue;
                }

                $take = min($available, $remaining);
                DB::table('referral_point_transactions')
                    ->where('id', $lot->id)
                    ->decrement('points_remaining', $take);

                $remaining -= $take;
            }
        }
    }
};
