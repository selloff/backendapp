<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE referral_profiles DROP CONSTRAINT IF EXISTS referral_profiles_no_self_referral');
        DB::statement('ALTER TABLE referral_profiles ADD CONSTRAINT referral_profiles_no_self_referral CHECK (referral_user_id IS NULL OR referral_user_id <> user_id)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE referral_profiles DROP CONSTRAINT IF EXISTS referral_profiles_no_self_referral');
    }
};
