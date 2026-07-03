<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AbuseReportsListService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AdminPlatformController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly AbuseReportsListService $abuseReports,
    ) {}

    public function cacheSettings(): JsonResponse
    {
        $stored = $this->settings->all();

        return ApiResponse::success([
            'cache_system' => $this->bool($stored, 'cache_system'),
            'refresh_cache_database_changes' => $this->bool($stored, 'refresh_cache_database_changes'),
            'cache_refresh_time' => (int) ($stored['cache_refresh_time'] ?? 60),
            'static_cache_system' => $this->bool($stored, 'static_cache_system'),
            'category_cache_system' => $this->bool($stored, 'category_cache_system'),
        ]);
    }

    public function updateCacheSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cache_system' => ['sometimes', 'boolean'],
            'refresh_cache_database_changes' => ['sometimes', 'boolean'],
            'cache_refresh_time' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'static_cache_system' => ['sometimes', 'boolean'],
            'category_cache_system' => ['sometimes', 'boolean'],
        ]);

        $this->settings->upsertMany($data, 'preferences');

        return $this->cacheSettings();
    }

    public function resetCache(Request $request): JsonResponse
    {
        $scope = $request->string('scope')->toString();

        if ($scope === 'static') {
            $this->settings->flushCache();

            return ApiResponse::success(['reset' => true, 'scope' => 'static'], message: 'Static cache cleared.');
        }

        Artisan::call('cache:clear');
        $this->settings->flushCache();

        return ApiResponse::success(['reset' => true, 'scope' => 'all'], message: 'Application cache cleared.');
    }

    public function preferences(): JsonResponse
    {
        $stored = $this->settings->all();

        return ApiResponse::success([
            'system' => [
                'physical_products_enabled' => $this->bool($stored, 'physical_products_enabled', true),
                'digital_products_enabled' => $this->bool($stored, 'digital_products_enabled', true),
                'marketplace_enabled' => $this->bool($stored, 'marketplace_enabled', true),
                'classified_ads_enabled' => $this->bool($stored, 'classified_ads_enabled', true),
                'bidding_enabled' => $this->bool($stored, 'bidding_enabled', true),
                'license_keys_enabled' => $this->bool($stored, 'license_keys_enabled', false),
                'multi_vendor_system' => $this->bool($stored, 'multi_vendor_system', true),
                'timezone' => (string) ($stored['timezone'] ?? 'Africa/Lagos'),
            ],
            'general' => [
                'multilingual_system' => $this->bool($stored, 'multilingual_system', false),
                'rss_enabled' => $this->bool($stored, 'rss_enabled', true),
                'vendor_verification_system' => $this->bool($stored, 'vendor_verification_system', false),
                'show_vendor_contact_information' => $this->bool($stored, 'show_vendor_contact_information', true),
                'show_vendor_contact_info_guests' => $this->bool($stored, 'show_vendor_contact_info_guests', false),
                'guest_checkout_enabled' => $this->bool($stored, 'guest_checkout_enabled', true),
                'location_search_header' => $this->bool($stored, 'location_search_header', true),
                'pwa_enabled' => $this->bool($stored, 'pwa_enabled', false),
                'pwa_logo_md_url' => (string) ($stored['pwa_logo_md_url'] ?? ''),
            ],
            'products' => [
                'approve_before_publishing' => $this->bool($stored, 'approve_before_publishing', false),
                'approve_after_editing' => (int) ($stored['approve_after_editing'] ?? 0),
                'promoted_products' => $this->bool($stored, 'promoted_products', true),
                'vendor_bulk_product_upload' => $this->bool($stored, 'vendor_bulk_product_upload', false),
                'show_sold_products' => $this->bool($stored, 'show_sold_products', true),
                'product_link_structure' => (string) ($stored['product_link_structure'] ?? 'slug-id'),
                'product_reviews_enabled' => $this->bool($stored, 'product_reviews_enabled', true),
                'product_comments_enabled' => $this->bool($stored, 'product_comments_enabled', true),
                'blog_comments_enabled' => $this->bool($stored, 'blog_comments_enabled', true),
                'comment_approval_system' => $this->bool($stored, 'comment_approval_system', true),
            ],
            'shop' => [
                'refund_system' => $this->bool($stored, 'refund_system', true),
                'profile_number_of_sales' => $this->bool($stored, 'profile_number_of_sales', true),
                'vendors_change_shop_name' => $this->bool($stored, 'vendors_change_shop_name', true),
                'show_customer_email_seller' => $this->bool($stored, 'show_customer_email_seller', true),
                'show_customer_phone_seller' => $this->bool($stored, 'show_customer_phone_seller', true),
                'auto_approve_orders' => $this->bool($stored, 'auto_approve_orders', false),
                'auto_approve_orders_days' => (int) ($stored['auto_approve_orders_days'] ?? 7),
                'request_documents_vendors' => $this->bool($stored, 'request_documents_vendors', true),
                'explanation_documents_vendors' => (string) ($stored['explanation_documents_vendors'] ?? ''),
            ],
            'wallet' => [
                'wallet_status' => $this->bool($stored, 'wallet_status', true),
                'wallet_deposit' => $this->bool($stored, 'wallet_deposit', true),
                'pay_with_wallet_balance' => $this->bool($stored, 'pay_with_wallet_balance', true),
                'wallet_min_deposit' => (float) ($stored['wallet_min_deposit'] ?? 100),
            ],
            'file_upload' => [
                'image_file_format' => (string) ($stored['image_file_format'] ?? 'WEBP'),
                'is_product_image_required' => $this->bool($stored, 'is_product_image_required', true),
                'product_image_limit' => (int) ($stored['product_image_limit'] ?? 10),
                'max_file_size_image' => (float) ($stored['max_file_size_image'] ?? 10),
                'max_file_size_video' => (float) ($stored['max_file_size_video'] ?? 50),
                'max_file_size_audio' => (float) ($stored['max_file_size_audio'] ?? 20),
            ],
            'storage' => $this->storagePayload($stored),
            'ai_writer' => [
                'status' => $this->bool($stored, 'ai_writer_status', false),
                'api_key' => (string) ($stored['ai_writer_api_key'] ?? ''),
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => ['sometimes', 'string', 'in:system,general,products,shop,wallet,file_upload'],
            'settings' => ['required', 'array'],
            'settings.physical_products_enabled' => ['sometimes', 'boolean'],
            'settings.digital_products_enabled' => ['sometimes', 'boolean'],
            'settings.marketplace_enabled' => ['sometimes', 'boolean'],
            'settings.classified_ads_enabled' => ['sometimes', 'boolean'],
            'settings.bidding_enabled' => ['sometimes', 'boolean'],
            'settings.license_keys_enabled' => ['sometimes', 'boolean'],
            'settings.multi_vendor_system' => ['sometimes', 'boolean'],
            'settings.timezone' => ['sometimes', 'string', 'max:64'],
            'settings.multilingual_system' => ['sometimes', 'boolean'],
            'settings.rss_enabled' => ['sometimes', 'boolean'],
            'settings.vendor_verification_system' => ['sometimes', 'boolean'],
            'settings.show_vendor_contact_information' => ['sometimes', 'boolean'],
            'settings.show_vendor_contact_info_guests' => ['sometimes', 'boolean'],
            'settings.guest_checkout_enabled' => ['sometimes', 'boolean'],
            'settings.location_search_header' => ['sometimes', 'boolean'],
            'settings.pwa_enabled' => ['sometimes', 'boolean'],
            'settings.pwa_logo_md_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'settings.pwa_logo_paths' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'settings.approve_before_publishing' => ['sometimes', 'boolean'],
            'settings.approve_after_editing' => ['sometimes', 'integer', 'in:0,1,2'],
            'settings.promoted_products' => ['sometimes', 'boolean'],
            'settings.vendor_bulk_product_upload' => ['sometimes', 'boolean'],
            'settings.show_sold_products' => ['sometimes', 'boolean'],
            'settings.product_link_structure' => ['sometimes', 'string', 'in:slug-id,id-slug'],
            'settings.product_reviews_enabled' => ['sometimes', 'boolean'],
            'settings.product_comments_enabled' => ['sometimes', 'boolean'],
            'settings.blog_comments_enabled' => ['sometimes', 'boolean'],
            'settings.comment_approval_system' => ['sometimes', 'boolean'],
            'settings.refund_system' => ['sometimes', 'boolean'],
            'settings.profile_number_of_sales' => ['sometimes', 'boolean'],
            'settings.vendors_change_shop_name' => ['sometimes', 'boolean'],
            'settings.show_customer_email_seller' => ['sometimes', 'boolean'],
            'settings.show_customer_phone_seller' => ['sometimes', 'boolean'],
            'settings.auto_approve_orders' => ['sometimes', 'boolean'],
            'settings.auto_approve_orders_days' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'settings.request_documents_vendors' => ['sometimes', 'boolean'],
            'settings.explanation_documents_vendors' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'settings.wallet_status' => ['sometimes', 'boolean'],
            'settings.wallet_deposit' => ['sometimes', 'boolean'],
            'settings.pay_with_wallet_balance' => ['sometimes', 'boolean'],
            'settings.wallet_min_deposit' => ['sometimes', 'numeric', 'min:0'],
            'settings.image_file_format' => ['sometimes', 'string', 'in:JPG,WEBP,PNG,original'],
            'settings.is_product_image_required' => ['sometimes', 'boolean'],
            'settings.product_image_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'settings.max_file_size_image' => ['sometimes', 'numeric', 'min:1', 'max:512'],
            'settings.max_file_size_video' => ['sometimes', 'numeric', 'min:1', 'max:5120'],
            'settings.max_file_size_audio' => ['sometimes', 'numeric', 'min:1', 'max:5120'],
        ]);

        if (($data['tab'] ?? null) === 'system') {
            $physical = (bool) ($data['settings']['physical_products_enabled'] ?? false);
            $digital = (bool) ($data['settings']['digital_products_enabled'] ?? false);
            abort_unless($physical || $digital, 422, 'At least one product type must be enabled.');
        }

        $this->settings->upsertMany($data['settings'], 'preferences');

        return $this->preferences();
    }

    public function updateStorageSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'storage' => ['required', 'string', 'in:local,aws_s3,cloudflare_r2,backblaze_b2'],
            'aws_key' => ['nullable', 'string', 'max:255'],
            'aws_secret' => ['nullable', 'string', 'max:255'],
            'aws_bucket' => ['nullable', 'string', 'max:255'],
            'aws_region' => ['nullable', 'string', 'max:255'],
            'r2_key' => ['nullable', 'string', 'max:255'],
            'r2_secret' => ['nullable', 'string', 'max:255'],
            'r2_bucket' => ['nullable', 'string', 'max:255'],
            'r2_endpoint_url' => ['nullable', 'string', 'max:2048'],
            'r2_public_url' => ['nullable', 'string', 'max:2048'],
            'b2_key' => ['nullable', 'string', 'max:255'],
            'b2_secret' => ['nullable', 'string', 'max:255'],
            'b2_bucket' => ['nullable', 'string', 'max:255'],
            'b2_endpoint_url' => ['nullable', 'string', 'max:2048'],
            'b2_public_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $this->settings->upsertMany($data, 'preferences');

        return ApiResponse::success($this->storagePayload($this->settings->all()));
    }

    public function updateAiWriterSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'boolean'],
            'api_key' => ['required', 'string', 'max:500'],
        ]);

        $this->settings->upsertMany([
            'ai_writer_status' => $data['status'],
            'ai_writer_api_key' => $data['api_key'],
        ], 'preferences');

        return ApiResponse::success([
            'status' => (bool) $data['status'],
            'api_key' => $data['api_key'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function storagePayload(array $stored): array
    {
        return [
            'storage' => (string) ($stored['storage'] ?? 'local'),
            'aws_key' => (string) ($stored['aws_key'] ?? ''),
            'aws_secret' => (string) ($stored['aws_secret'] ?? ''),
            'aws_bucket' => (string) ($stored['aws_bucket'] ?? ''),
            'aws_region' => (string) ($stored['aws_region'] ?? ''),
            'r2_key' => (string) ($stored['r2_key'] ?? ''),
            'r2_secret' => (string) ($stored['r2_secret'] ?? ''),
            'r2_bucket' => (string) ($stored['r2_bucket'] ?? ''),
            'r2_endpoint_url' => (string) ($stored['r2_endpoint_url'] ?? ''),
            'r2_public_url' => (string) ($stored['r2_public_url'] ?? ''),
            'b2_key' => (string) ($stored['b2_key'] ?? ''),
            'b2_secret' => (string) ($stored['b2_secret'] ?? ''),
            'b2_bucket' => (string) ($stored['b2_bucket'] ?? ''),
            'b2_endpoint_url' => (string) ($stored['b2_endpoint_url'] ?? ''),
            'b2_public_url' => (string) ($stored['b2_public_url'] ?? ''),
        ];
    }

    public function abuseReports(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);

        return ApiResponse::success($this->abuseReports->paginate($perPage));
    }

    public function updateAbuseReport(Request $request, int $abuseReport): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,reviewed,dismissed,resolved'],
        ]);

        $updated = DB::table('abuse_reports')
            ->where('id', $abuseReport)
            ->update([
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        abort_unless($updated > 0, 404);

        return ApiResponse::success([
            'id' => $abuseReport,
            'status' => $data['status'],
        ]);
    }

    public function destroyAbuseReport(int $abuseReport): JsonResponse
    {
        $deleted = DB::table('abuse_reports')->where('id', $abuseReport)->delete();
        abort_unless($deleted > 0, 404);

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function bool(array $stored, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $stored)) {
            return $default;
        }

        $value = $stored[$key];

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
