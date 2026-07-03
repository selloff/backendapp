<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;
use App\Support\Gtm\GtmEventFactory;
use Illuminate\Http\Request;

class ProductContactService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
        private readonly VendorListingMetricsRecorder $listingMetrics,
        private readonly GtmEventFactory $gtmFactory,
    ) {}

    /**
     * @return array{phone_number: string, gtm_events: list<array<string, mixed>>}
     */
    public function revealPhone(Product $product, ?User $viewer, Request $request): array
    {
        $this->assertContactRevealAllowed($product, $viewer);

        $vendor = $product->vendor;
        abort_if($vendor === null, 404, 'Seller not found.');

        $phone = $this->resolveVendorPhone($vendor);
        abort_if($phone === null, 404, 'Seller phone is not available.');

        $this->listingMetrics->recordContactView($product, $viewer);

        return [
            'phone_number' => $phone,
            'gtm_events' => [
                $this->buildGtmEvent('view_contact', $product, $vendor, $viewer, $request),
            ],
        ];
    }

    /**
     * @return array{gtm_events: list<array<string, mixed>>}
     */
    public function trackClickToCall(Product $product, ?User $viewer, Request $request): array
    {
        $this->assertContactRevealAllowed($product, $viewer);

        $vendor = $product->vendor;
        abort_if($vendor === null, 404, 'Seller not found.');

        return [
            'gtm_events' => [
                $this->buildGtmEvent('click_to_call', $product, $vendor, $viewer, $request),
            ],
        ];
    }

    private function assertContactRevealAllowed(Product $product, ?User $viewer): void
    {
        abort_if(
            in_array($product->listing_type, ['sell_on_site', 'license_key'], true),
            422,
            'Contact reveal is only available for classified listings.',
        );

        $settings = $this->platformSettings->all();
        $guestsAllowed = $this->platformBool($settings, 'show_vendor_contact_info_guests', false);

        abort_if(
            $viewer === null && ! $guestsAllowed,
            401,
            'Sign in to view seller contact information.',
        );
    }

    private function resolveVendorPhone(User $vendor): ?string
    {
        $settings = $this->platformSettings->all();

        if (! $this->platformBool($settings, 'show_vendor_contact_information', true)) {
            return null;
        }

        $phone = trim((string) ($vendor->phone_number ?? ''));

        return $phone !== '' ? $phone : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function platformBool(array $settings, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGtmEvent(
        string $eventName,
        Product $product,
        User $vendor,
        ?User $viewer,
        Request $request,
    ): array {
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();

        $category = $product->category;
        $categoryTranslation = $category?->translations->firstWhere('locale', 'en')
            ?? $category?->translations->first();
        $parent = $category?->parent;
        $parentTranslation = $parent?->translations->firstWhere('locale', 'en')
            ?? $parent?->translations->first();

        $price = (float) ($product->price_discounted ?? $product->price);
        $location = $vendor->relationLoaded('state') ? ($vendor->state?->name ?? '') : '';

        $eventData = [
            'item_id' => (string) $product->id,
            'item_title' => (string) ($translation?->title ?? ''),
            'item_category_id' => (string) ($product->category_id ?? ''),
            'item_category' => (string) ($parentTranslation?->name ?? $categoryTranslation?->name ?? ''),
            'item_sub_category' => (string) ($parent ? ($categoryTranslation?->name ?? '') : ''),
            'item_price' => $price,
            'item_location' => $location,
            'seller_id' => (string) $vendor->id,
            'seller_name' => trim($vendor->first_name.' '.$vendor->last_name),
            'seller_username' => (string) ($vendor->username ?? $vendor->slug ?? ''),
            'seller_phone' => (string) ($vendor->phone_number ?? ''),
            'seller_email' => (string) $vendor->email,
            'viewer_id' => $viewer ? (string) $viewer->id : '',
            'viewer_name' => $viewer ? trim($viewer->first_name.' '.$viewer->last_name) : '',
            'viewer_username' => $viewer ? (string) ($viewer->username ?? $viewer->slug ?? '') : '',
            'viewer_phone' => $viewer ? (string) ($viewer->phone_number ?? '') : '',
            'viewer_email' => $viewer ? (string) ($viewer->email ?? '') : '',
            'viewer_ip' => (string) $request->ip(),
            'contact_method' => 'web',
        ];

        return $this->gtmFactory->make($eventName, $eventData);
    }
}
