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

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION referral_profiles_prevent_referrer_change()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.referral_user_id IS NOT NULL
        AND NEW.referral_user_id IS DISTINCT FROM OLD.referral_user_id THEN
        RAISE EXCEPTION 'referral_user_id cannot be changed once set';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS referral_profiles_prevent_referrer_change ON referral_profiles;

CREATE TRIGGER referral_profiles_prevent_referrer_change
    BEFORE UPDATE ON referral_profiles
    FOR EACH ROW
    EXECUTE FUNCTION referral_profiles_prevent_referrer_change();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS referral_profiles_prevent_referrer_change ON referral_profiles;
DROP FUNCTION IF EXISTS referral_profiles_prevent_referrer_change();
SQL);
    }
};
