<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyUniqueValueResolver;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\Models\User;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Services\Auth\RolePermissionSync;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UsersLegacyImporter implements LegacyImporter
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
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('users')) {
            return;
        }

        $this->ensureRolesExist();

        $uniqueValues = new LegacyUniqueValueResolver();

        foreach ($reader->rows('users') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'slug' => $uniqueValues->uniqueUserSlug($row['slug'] ?? null, $legacyId),
                'username' => $uniqueValues->uniqueUsername($row['username'] ?? null, $legacyId),
                'email' => $uniqueValues->uniqueEmail($row['email'] ?? null, $legacyId),
                'password' => $row['password'] ?? bcrypt('password'),
                'wallet_balance' => LegacyValueCoercer::decimal($row['balance'] ?? 0),
                'is_banned' => LegacyValueCoercer::bool($row['banned'] ?? 0),
                'phone_number' => $row['phone_number'] ?? null,
                'about_me' => $row['about_me'] ?? null,
                'show_rss_feeds' => LegacyValueCoercer::bool($row['show_rss_feeds'] ?? 0),
                'country_id' => $this->resolveLocationId($context, 'location_countries', $row['country_id'] ?? null),
                'state_id' => $this->resolveLocationId($context, 'location_states', $row['state_id'] ?? null),
                'city_id' => $this->resolveLocationId($context, 'location_cities', $row['city_id'] ?? null),
                'address' => isset($row['address']) ? LegacyValueCoercer::stringMax($row['address'], 500) : null,
                'zip_code' => isset($row['zip_code']) ? LegacyValueCoercer::stringMax($row['zip_code'], 50) : null,
                'last_seen_at' => LegacyValueCoercer::date($row['last_seen'] ?? $row['last_seen_at'] ?? null),
                'avatar' => $row['avatar'] ?? null,
                'storage_avatar' => LegacyValueCoercer::stringMax($row['storage_avatar'] ?? 'local', 50, 'local'),
                'shop_opening_status' => (int) ($row['is_active_shop_request'] ?? 0),
                'vendor_documents' => LegacyValueCoercer::jsonb(
                    LegacyValueCoercer::phpSerializedArray($row['vendor_documents'] ?? null)
                        ?? LegacyValueCoercer::json($row['vendor_documents'] ?? null),
                ),
                'shop_request_date' => LegacyValueCoercer::date($row['shop_request_date'] ?? null),
                'shop_opening_rejection_reason' => isset($row['shop_request_reject_reason'])
                    ? LegacyValueCoercer::stringMax($row['shop_request_reject_reason'], 2000)
                    : null,
                'social_media_data' => LegacyValueCoercer::jsonb(
                    LegacyValueCoercer::phpSerializedArray($row['social_media_data'] ?? null)
                        ?? LegacyValueCoercer::json($row['social_media_data'] ?? null),
                ),
                'facebook_id' => $row['facebook_id'] ?? null,
                'google_id' => $row['google_id'] ?? null,
                'vk_id' => $row['vkontakte_id'] ?? $row['vk_id'] ?? null,
                'email_verified_at' => LegacyValueCoercer::bool($row['email_status'] ?? 0) ? LegacyValueCoercer::date($row['created_at'] ?? now()) : null,
                'account_delete_requested_at' => LegacyValueCoercer::bool($row['account_delete_req'] ?? 0)
                    ? (LegacyValueCoercer::date($row['account_delete_req_date'] ?? null)
                        ?? LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? null))
                    : null,
                'is_enable_login' => true,
                'is_disable' => false,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()),
            ];

            if (! $context->dryRun) {
                DB::table('users')->updateOrInsert(['id' => $legacyId], $payload);

                $roleName = $this->roleForLegacyRow($row, $context);
                $user = User::query()->find($legacyId);
                $user?->syncRoles([$roleName]);

                if ($this->shouldCreateVendorProfile($row, $roleName)) {
                    VendorProfile::query()->updateOrCreate(
                        ['user_id' => $legacyId],
                        [
                            'shop_name' => (string) ($row['shop_name'] ?? $row['username'] ?? 'Shop '.$legacyId),
                            'slug' => $uniqueValues->uniqueVendorSlug($row['slug'] ?? null, $legacyId),
                            'cover_path' => $row['cover_image'] ?? null,
                            'is_verified_seller' => LegacyValueCoercer::bool($row['is_verified_seller'] ?? 0),
                            'is_commission_set' => LegacyValueCoercer::bool($row['is_commission_set'] ?? 0),
                            'commission_rate' => LegacyValueCoercer::decimal($row['commission_rate'] ?? 0),
                            'vacation_mode' => LegacyValueCoercer::bool($row['vacation_mode'] ?? 0),
                            'vacation_message' => $row['vacation_message'] ?? null,
                            'payout_info' => LegacyValueCoercer::phpSerializedArray($row['payout_info'] ?? null),
                            'vat_rates_data' => LegacyValueCoercer::phpSerializedArray($row['vat_rates_data'] ?? null),
                            'vat_rates_by_state' => LegacyValueCoercer::phpSerializedArray($row['vat_rates_data_state'] ?? null),
                            'is_fixed_vat' => LegacyValueCoercer::bool($row['is_fixed_vat'] ?? 0),
                            'fixed_vat_rate' => LegacyValueCoercer::decimal($row['fixed_vat_rate'] ?? 0),
                            'social_media_data' => LegacyValueCoercer::phpSerializedArray($row['social_media_data'] ?? null),
                            'legacy_id' => $legacyId,
                        ],
                    );
                }
            }

            $this->maps->remember($context, 'users', $legacyId, 'users', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    private function ensureRolesExist(): void
    {
        app(RolePermissionSync::class)->sync();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function roleForLegacyRow(array $row, LegacyImportContext $context): string
    {
        $roleId = isset($row['role_id']) ? (int) $row['role_id'] : 0;

        if ($roleId > 0) {
            $spatieRoleId = $context->resolveId('roles_permissions', $roleId);
            if ($spatieRoleId !== null) {
                $role = Role::query()->find($spatieRoleId);
                if ($role !== null) {
                    return $role->name;
                }
            }
        }

        if ($roleId === 1 || LegacyValueCoercer::bool($row['super_admin'] ?? 0)) {
            return 'super-admin';
        }

        if ($roleId === 2 || ! empty($row['shop_name'])) {
            return 'vendor';
        }

        if (in_array($roleId, [4, 5, 6], true)) {
            return 'admin';
        }

        return 'member';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function shouldCreateVendorProfile(array $row, string $roleName): bool
    {
        if ($roleName === 'vendor') {
            return true;
        }

        return ! empty($row['shop_name']);
    }

    private function resolveLocationId(LegacyImportContext $context, string $legacyTable, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId <= 0) {
            return null;
        }

        return $context->resolveId($legacyTable, (int) $legacyId);
    }
}
