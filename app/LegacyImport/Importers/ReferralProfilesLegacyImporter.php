<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class ReferralProfilesLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'users';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('referral_profiles') && ! $context->shouldImportTable('users')) {
            return;
        }

        if (! $reader->hasTable('users')) {
            return;
        }

        foreach ($reader->rows('users') as $row) {
            $context->notePlanned('referral_profiles');

            $legacyUserId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', $legacyUserId);
            if ($legacyUserId <= 0 || $userId === null) {
                $context->noteSkipped('referral_profiles');

                continue;
            }

            $referralCode = $row['referral_code'] ?? null;
            if ($referralCode === null || $referralCode === '') {
                $referralCode = 'REF-'.$legacyUserId;
            }

            $referralUserId = isset($row['referral_user_id']) && (int) $row['referral_user_id'] > 0
                ? $context->resolveId('users', (int) $row['referral_user_id'])
                : null;

            $referredByCode = $row['referred_by_code'] ?? null;
            if (($referredByCode === null || $referredByCode === '') && $referralUserId !== null) {
                $referredByCode = DB::table('referral_profiles')
                    ->where('user_id', $referralUserId)
                    ->value('referral_code');
            }

            $payload = [
                'user_id' => $userId,
                'referral_code' => (string) $referralCode,
                'referral_user_id' => $referralUserId,
                'referred_by_code' => $referredByCode ? (string) $referredByCode : null,
                'referral_points' => (int) ($row['referral_points'] ?? 0),
                'referral_point_balance' => (int) ($row['referral_point_balance'] ?? 0),
                'affiliate_commission_rate' => LegacyValueCoercer::decimal($row['affiliate_commission_rate'] ?? 0),
                'affiliate_discount_rate' => LegacyValueCoercer::decimal($row['affiliate_discount_rate'] ?? 0),
                'vendor_affiliate_status' => (int) ($row['vendor_affiliate_status'] ?? 0),
                'legacy_id' => $legacyUserId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('referral_profiles')->updateOrInsert(['user_id' => $userId], $payload);
            }

            $newId = $context->dryRun
                ? $legacyUserId
                : (int) DB::table('referral_profiles')->where('user_id', $userId)->value('id');

            $this->maps->remember($context, 'referral_profiles', $legacyUserId, 'referral_profiles', $newId);
            $context->noteImported('referral_profiles');
        }
    }
}
