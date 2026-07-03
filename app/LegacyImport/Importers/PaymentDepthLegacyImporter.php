<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\LegacyImport\Support\MembershipLegacyImportMapper;
use App\Modules\Selloff\Payment\Services\MembershipLegacyEntitlementMapper;
use App\Modules\Selloff\Payment\Services\MembershipPlanFeatureResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentDepthLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
        private readonly MembershipPlanFeatureResolver $membershipPlanFeatures,
        private readonly MembershipLegacyEntitlementMapper $membershipLegacyEntitlements,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return [
            'taxes',
            'bank_transfers',
            'membership_plans',
            'membership_transactions',
            'users_membership_plans',
            'promoted_transactions',
            'payment_settings',
            'payment_gateways',
        ];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importTaxes($context, $reader);
        $this->importBankTransfers($context, $reader);
        $this->importMembershipPlans($context, $reader);
        $this->importMembershipTransactions($context, $reader);
        $this->importUserMembershipPlans($context, $reader);
        $this->importPromotedTransactions($context, $reader);
        $this->importPaymentSettings($context, $reader);
        $this->importPaymentGateways($context, $reader);
    }

    private function importTaxes(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('taxes') || ! $reader->hasTable('taxes')) {
            return;
        }

        foreach ($reader->rows('taxes') as $row) {
            $context->notePlanned('taxes');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('taxes');

                continue;
            }

            $name = LegacyValueCoercer::localizedLabel($row['name_data'] ?? $row['name'] ?? null, 'Tax '.$legacyId);
            $countryIds = LegacyValueCoercer::phpSerializedArray($row['country_ids'] ?? null);
            $stateIds = LegacyValueCoercer::phpSerializedArray($row['state_ids'] ?? null);
            $countryId = is_array($countryIds) ? (int) (reset($countryIds) ?: 0) : 0;
            $stateId = is_array($stateIds) ? (int) (reset($stateIds) ?: 0) : 0;

            $payload = [
                'id' => $legacyId,
                'name' => $name,
                'rate' => LegacyValueCoercer::decimal($row['tax_rate'] ?? $row['rate'] ?? 0, 4),
                'country_id' => $countryId > 0 ? $context->resolveId('location_countries', $countryId) : null,
                'state_id' => $stateId > 0 ? $context->resolveId('location_states', $stateId) : null,
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('tax_rules')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'taxes', $legacyId, 'tax_rules', $legacyId);
            $context->noteImported('taxes');
        }
    }

    private function importBankTransfers(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('bank_transfers') || ! $reader->hasTable('bank_transfers')) {
            return;
        }

        foreach ($reader->rows('bank_transfers') as $row) {
            $context->notePlanned('bank_transfers');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('bank_transfers');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'order_number' => isset($row['order_number']) ? (int) $row['order_number'] : null,
                'user_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'payment_note' => $row['payment_note'] ?? null,
                'receipt_path' => $row['receipt_path'] ?? null,
                'status' => $row['status'] ?? 'pending',
                'ip_address' => $row['ip_address'] ?? null,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('bank_transfer_requests')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'bank_transfers', $legacyId, 'bank_transfer_requests', $legacyId);
            $context->noteImported('bank_transfers');
        }
    }

    private function importMembershipPlans(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('membership_plans') || ! $reader->hasTable('membership_plans')) {
            return;
        }

        foreach ($reader->rows('membership_plans') as $row) {
            $context->notePlanned('membership_plans');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('membership_plans');

                continue;
            }

            $title = LegacyValueCoercer::localizedLabel(
                $row['title_array'] ?? $row['title_data'] ?? $row['title'] ?? null,
                'Plan '.$legacyId,
            );

            $payload = [
                'id' => $legacyId,
                'title' => $title,
                'description' => $row['description'] ?? null,
                'price' => LegacyValueCoercer::decimal($row['price'] ?? 0),
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'duration_days' => (int) ($row['number_of_days'] ?? $row['duration_days'] ?? 30),
                'is_active' => LegacyValueCoercer::bool($row['status'] ?? $row['is_active'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (Schema::hasColumn('membership_plans', 'plan_order')) {
                $payload['plan_order'] = (int) ($row['plan_order'] ?? 1);
            }

            if (Schema::hasColumn('membership_plans', 'is_popular')) {
                $payload['is_popular'] = LegacyValueCoercer::bool($row['is_popular'] ?? 0);
            }

            if (Schema::hasColumn('membership_plans', 'features')) {
                $features = $this->membershipPlanFeatures->fromLegacyFeaturesArray(
                    $row['features_array'] ?? null,
                );
                $payload['features'] = $features === [] ? null : json_encode($features);
            }

            $payload = array_merge($payload, $this->membershipEntitlementColumns($row));

            if (! $context->dryRun) {
                DB::table('membership_plans')->updateOrInsert(['id' => $legacyId], $payload);

                $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()->find($legacyId);
                if ($plan !== null) {
                    $this->membershipLegacyEntitlements->syncCategoryLimitsForPlan($plan);
                }
            }

            $this->maps->remember($context, 'membership_plans', $legacyId, 'membership_plans', $legacyId);
            $context->noteImported('membership_plans');
        }
    }

    private function importMembershipTransactions(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('membership_transactions') || ! $reader->hasTable('membership_transactions')) {
            return;
        }

        foreach ($reader->rows('membership_transactions') as $row) {
            $context->notePlanned('membership_transactions');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('membership_transactions');

                continue;
            }

            $planLegacyId = (int) ($row['plan_id'] ?? 0);
            $planDurationDays = $this->membershipPlanDurationDays($reader, $planLegacyId);

            $payload = [
                'id' => $legacyId,
                'user_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'membership_plan_id' => $planLegacyId > 0
                    ? $context->resolveId('membership_plans', $planLegacyId)
                    : null,
                'amount' => LegacyValueCoercer::decimal($row['payment_amount'] ?? $row['amount'] ?? 0),
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'payment_method' => $row['payment_method'] ?? null,
                'payment_reference' => $row['payment_id'] ?? null,
                'status' => LegacyValueCoercer::servicePaymentStatus($row['payment_status'] ?? $row['status'] ?? 'pending'),
                'checkout_token' => $row['checkout_token'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'metadata' => LegacyValueCoercer::jsonb($row['global_taxes_data'] ?? null),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            $payload = array_merge(
                $payload,
                MembershipLegacyImportMapper::transactionParityColumns($row, $planDurationDays),
            );

            if (! $context->dryRun) {
                DB::table('membership_transactions')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'membership_transactions', $legacyId, 'membership_transactions', $legacyId);
            $context->noteImported('membership_transactions');
        }
    }

    private function importUserMembershipPlans(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('users_membership_plans') || ! $reader->hasTable('users_membership_plans')) {
            return;
        }

        foreach ($reader->rows('users_membership_plans') as $row) {
            $context->notePlanned('users_membership_plans');

            $legacyId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            $planId = $context->resolveId('membership_plans', (int) ($row['plan_id'] ?? 0));
            if ($legacyId <= 0 || $userId === null || $planId === null) {
                $context->noteSkipped('users_membership_plans');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'membership_plan_id' => $planId,
                'starts_at' => LegacyValueCoercer::date($row['plan_start_date'] ?? $row['starts_at'] ?? null),
                'expires_at' => LegacyValueCoercer::date($row['plan_end_date'] ?? $row['expires_at'] ?? null),
                'is_active' => LegacyValueCoercer::bool($row['plan_status'] ?? $row['is_active'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            $payload = array_merge(
                $payload,
                MembershipLegacyImportMapper::subscriptionParityColumns($row),
            );

            if (! $context->dryRun) {
                DB::table('user_membership_plans')->updateOrInsert(['id' => $legacyId], $payload);

                $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()->find($planId);
                if ($plan !== null && Schema::hasColumn('user_membership_plans', 'entitlements_snapshot')) {
                    $snapshot = $this->membershipLegacyEntitlements->snapshotForImportedSubscription($plan);
                    $topCredits = max(0, (int) ($snapshot['top_credits_per_period'] ?? 0));

                    DB::table('user_membership_plans')->where('id', $legacyId)->update([
                        'entitlements_snapshot' => json_encode($snapshot),
                        'top_credits_remaining' => $topCredits,
                        'top_credits_period_started_at' => $payload['starts_at'],
                        'top_credits_period_ends_at' => $payload['expires_at'],
                    ]);
                }
            }

            $this->maps->remember($context, 'users_membership_plans', $legacyId, 'user_membership_plans', $legacyId);
            $context->noteImported('users_membership_plans');
        }
    }

    private function importPromotedTransactions(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('promoted_transactions') || ! $reader->hasTable('promoted_transactions')) {
            return;
        }

        foreach ($reader->rows('promoted_transactions') as $row) {
            $context->notePlanned('promoted_transactions');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('promoted_transactions');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'user_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'product_id' => $context->resolveId('products', (int) ($row['product_id'] ?? 0)),
                'payment_method' => $row['payment_method'] ?? null,
                'payment_reference' => $row['payment_id'] ?? null,
                'amount' => LegacyValueCoercer::decimal($row['payment_amount'] ?? $row['amount'] ?? 0),
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'status' => LegacyValueCoercer::servicePaymentStatus($row['payment_status'] ?? $row['status'] ?? 'pending'),
                'checkout_token' => $row['checkout_token'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'purchased_plan' => $row['purchased_plan'] ?? null,
                'day_count' => isset($row['day_count']) ? (int) $row['day_count'] : null,
                'metadata' => LegacyValueCoercer::jsonb($row['global_taxes_data'] ?? null),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('promotion_transactions')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'promoted_transactions', $legacyId, 'promotion_transactions', $legacyId);
            $context->noteImported('promoted_transactions');
        }
    }

    private function importPaymentSettings(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('payment_settings') || ! $reader->hasTable('payment_settings')) {
            return;
        }

        $rows = $reader->rows('payment_settings');
        $row = $rows[0] ?? null;
        if ($row === null) {
            return;
        }

        $context->notePlanned('payment_settings');

        $settings = [
            'payment_wallet_enabled' => LegacyValueCoercer::bool($row['wallet_enabled'] ?? $row['pay_with_wallet_balance'] ?? 1),
            'payment_wallet_deposit_enabled' => LegacyValueCoercer::bool($row['wallet_deposit'] ?? 1),
            'payment_wallet_min_deposit' => LegacyValueCoercer::decimal($row['wallet_min_deposit'] ?? 0, 2),
            'payment_bank_transfer_enabled' => LegacyValueCoercer::bool($row['bank_transfer_enabled'] ?? 1),
            'payment_bank_transfer_instructions' => (string) ($row['bank_transfer_accounts'] ?? ''),
            'payment_cod_enabled' => LegacyValueCoercer::bool($row['cash_on_delivery_enabled'] ?? 1),
            'default_currency' => (string) ($row['default_currency'] ?? 'NGN'),
            'currency_converter' => LegacyValueCoercer::bool($row['currency_converter'] ?? 0),
            'auto_update_exchange_rates' => LegacyValueCoercer::bool($row['auto_update_exchange_rates'] ?? 0),
            'currency_converter_api' => (string) ($row['currency_converter_api'] ?? 'fixer'),
            'currency_converter_api_key' => (string) ($row['currency_converter_api_key'] ?? ''),
            'allow_all_currencies_for_classified' => LegacyValueCoercer::bool($row['allow_all_currencies_for_classied'] ?? $row['allow_all_currencies_for_classified'] ?? 0),
            'commission_rate' => LegacyValueCoercer::decimal($row['commission_rate'] ?? 0, 2),
            'vat_status' => LegacyValueCoercer::bool($row['vat_status'] ?? 0),
            'cart_location_selection' => LegacyValueCoercer::bool($row['cart_location_selection'] ?? 1),
            'cash_on_delivery_debt_limit' => LegacyValueCoercer::decimal($row['cash_on_delivery_debt_limit'] ?? 0, 2),
        ];

        if (! $context->dryRun) {
            foreach ($settings as $key => $value) {
                DB::table('platform_settings')->updateOrInsert(
                    ['key' => $key],
                    [
                        'value' => json_encode($value),
                        'group' => 'payment',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        $context->noteImported('payment_settings');
    }

    private function importPaymentGateways(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('payment_gateways') || ! $reader->hasTable('payment_gateways')) {
            return;
        }

        $context->notePlanned('payment_gateways');

        $stripe = null;
        foreach ($reader->rows('payment_gateways') as $row) {
            $nameKey = (string) ($row['name_key'] ?? '');
            if ($nameKey === 'stripe' || str_contains($nameKey, 'stripe')) {
                $stripe = $row;

                break;
            }
        }

        if ($stripe !== null && ! $context->dryRun) {
            DB::table('platform_settings')->updateOrInsert(
                ['key' => 'payment_stripe_enabled'],
                [
                    'value' => json_encode(LegacyValueCoercer::bool($stripe['status'] ?? 0)),
                    'group' => 'payment',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            if (! empty($stripe['public_key'])) {
                DB::table('platform_settings')->updateOrInsert(
                    ['key' => 'payment_stripe_public_key'],
                    [
                        'value' => json_encode((string) $stripe['public_key']),
                        'group' => 'payment',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        if (! $context->dryRun) {
            $gateways = $reader->rows('payment_gateways');
            DB::table('platform_settings')->updateOrInsert(
                ['key' => 'legacy_payment_gateways'],
                [
                    'value' => json_encode($gateways),
                    'group' => 'payment',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $context->noteImported('payment_gateways');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function membershipEntitlementColumns(array $row): array
    {
        $mapped = $this->membershipLegacyEntitlements->planPayloadFromLegacyRow($row);
        $columns = [];

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
            if (! Schema::hasColumn('membership_plans', $column) || ! array_key_exists($column, $mapped)) {
                continue;
            }

            $columns[$column] = $mapped[$column];
        }

        return $columns;
    }

    private function membershipPlanDurationDays(MySqlDumpReader $reader, int $planLegacyId): ?int
    {
        if ($planLegacyId <= 0 || ! $reader->hasTable('membership_plans')) {
            return null;
        }

        foreach ($reader->rows('membership_plans') as $planRow) {
            if ((int) ($planRow['id'] ?? 0) !== $planLegacyId) {
                continue;
            }

            $days = (int) ($planRow['number_of_days'] ?? $planRow['duration_days'] ?? 0);

            return $days > 0 ? $days : null;
        }

        return null;
    }
}
