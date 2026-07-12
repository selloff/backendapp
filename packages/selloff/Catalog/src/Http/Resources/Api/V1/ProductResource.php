<?php

namespace App\Modules\Selloff\Catalog\Http\Resources\Api\V1;

use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ProductController;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\ProductCustomFieldValuePresenter;
use App\Modules\Selloff\Catalog\Support\ProductCommissionResolver;
use App\Modules\Selloff\Payment\Services\MembershipProductDetailPerksService;
use App\Services\Media\MediaUploadService;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\User\Models\Follower;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** @return array<int, string> */
    public static function listEagerLoads(): array
    {
        return [
            'translations',
            'vendor.vendorProfile',
            'vendor.state',
            'vendor.city',
            'state',
            'city',
            'category.translations',
            'brand',
            'images',
        ];
    }

    public function toArray(Request $request): array
    {
        $translation = $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();

        $price = (float) $this->price;
        $discounted = $this->price_discounted !== null ? (float) $this->price_discounted : null;
        $discountRate = null;
        if ($discounted !== null && $discounted > 0 && $discounted < $price) {
            $discountRate = (int) round((($price - $discounted) / $price) * 100);
        }

        $platformSettings = app(PlatformSettingsService::class)->all();
        $contactAvailableToGuests = $this->platformBool($platformSettings, 'show_vendor_contact_info_guests', false);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'title' => $translation?->title,
            'description' => $translation?->description,
            'short_description' => $translation?->short_description,
            'type' => $this->type,
            'listing_type' => $this->listing_type,
            'show_sku' => $this->platformBool($platformSettings, 'marketplace_sku', true),
            'contact_available_to_guests' => $contactAvailableToGuests,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'is_active' => $this->is_active,
            'is_verified' => $this->is_verified,
            'is_edited' => (bool) $this->is_edited,
            'is_affiliate' => (bool) $this->is_affiliate,
            'is_commission_set' => (bool) ($this->is_commission_set ?? $this->category?->is_commission_set ?? false),
            'commission_rate' => ($this->is_commission_set ?? $this->category?->is_commission_set)
                ? ($this->commission_rate ?? $this->category?->commission_rate)
                : null,
            'is_promoted' => (bool) $this->is_promoted,
            'is_top_boosted' => $this->topBoostIsActive(),
            'top_badge_label' => $this->topBoostIsActive() ? $this->top_boost_badge_label : null,
            'top_boost_expires_at' => $this->topBoostIsActive() ? $this->top_boost_expires_at : null,
            'top_boost_stack_count' => (int) ($this->top_boost_stack_count ?? 0),
            'is_free' => (bool) ($this->is_free_product ?? false) || ($price <= 0 && ($discounted === null || $discounted <= 0)),
            'is_free_product' => (bool) ($this->is_free_product ?? false),
            'vat_rate' => $this->vat_rate,
            'delivery_time_option_id' => $this->delivery_time_option_id,
            'effective_commission_rate' => app(ProductCommissionResolver::class)->resolveForProduct($this->resource, $this->category_id),
            'discount_rate' => $discountRate,
            'has_options' => ($this->options_count ?? 0) > 0,
            'price' => $this->price,
            'price_discounted' => $this->price_discounted,
            'currency_code' => $this->currency_code,
            'stock' => $this->stock,
            'pageviews' => $this->pageviews,
            'listing_stats' => $this->when(
                $this->listing_stats !== null,
                fn () => $this->listing_stats,
            ),
            'is_sold' => (bool) $this->is_sold,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'zip_code' => $this->zip_code,
            'shipping_dimensions' => $this->shipping_dimensions,
            'video_path' => $this->video_path,
            'video_disk' => $this->video_disk,
            'audio_path' => $this->audio_path,
            'audio_disk' => $this->audio_disk,
            'video_url' => $this->when(
                $this->video_path,
                fn () => app(MediaUploadService::class)->urlFor($this->video_path, $this->video_disk ?? 'public'),
            ),
            'audio_url' => $this->when(
                $this->audio_path,
                fn () => app(MediaUploadService::class)->urlFor($this->audio_path, $this->audio_disk ?? 'public'),
            ),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('tag')->values()),
            'country_name' => $this->whenLoaded('country', fn () => $this->country?->name),
            'state_name' => $this->whenLoaded('state', fn () => $this->state?->name),
            'city_name' => $this->whenLoaded('city', fn () => $this->city?->name),
            'vendor_id' => $this->vendor_id,
            'vendor' => $this->whenLoaded('vendor', function () use ($request, $contactAvailableToGuests) {
                $settings = app(PlatformSettingsService::class)->all();
                $showContact = $this->platformBool($settings, 'show_vendor_contact_information', true);
                $guestCanViewContact = $contactAvailableToGuests || $request->user() !== null;
                $phone = trim((string) ($this->vendor->phone_number ?? ''));
                $email = trim((string) ($this->vendor->email ?? ''));
                $vendorPayload = [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                    'slug' => $this->vendor->slug,
                    'username' => $this->vendor->username ?? $this->vendor->slug,
                    'shop_name' => $this->vendor->vendorProfile?->shop_name,
                    'avatar' => $this->vendor->avatar,
                    'is_verified_seller' => (bool) ($this->vendor->vendorProfile?->is_verified_seller ?? false),
                    'state_name' => $this->vendor->relationLoaded('state') ? $this->vendor->state?->name : null,
                    'city_name' => $this->vendor->relationLoaded('city') ? $this->vendor->city?->name : null,
                    'created_at' => $this->vendor->created_at,
                    'email' => $showContact && $email !== '' ? $email : null,
                    'show_phone_contact' => $showContact && $guestCanViewContact && $phone !== '',
                    'show_email_contact' => $showContact && $guestCanViewContact && $email !== '',
                ];

                if ($this->exposesMembershipDetailPerks($request)) {
                    $perksService = app(MembershipProductDetailPerksService::class);
                    $vendorPayload['membership_detail_perks'] = $perksService->detailPerks($this->vendor);
                    $vendorPayload['social_links'] = $perksService->publicSocialLinks($this->vendor);
                    $vendorPayload['hide_seller_feedback'] = $perksService->shouldHideSellerFeedback($this->vendor);
                }

                $viewer = $request->user();
                if ($viewer && (int) $viewer->id !== (int) $this->vendor->id) {
                    $vendorPayload['is_following'] = Follower::query()
                        ->where('user_id', $this->vendor->id)
                        ->where('follower_id', $viewer->id)
                        ->exists();
                }

                return $vendorPayload;
            }),
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'brand' => $this->whenLoaded('brand', fn () => new BrandResource($this->brand)),
            'images' => $this->whenLoaded('images', function () {
                $media = app(MediaUploadService::class);

                return $this->images->sortBy('sort_order')->values()->map(function ($image) use ($media) {
                    $variantPaths = is_array($image->variant_paths) ? $image->variant_paths : null;

                    return [
                        'id' => $image->id,
                        'path' => $image->path,
                        'url' => $media->urlForProductImageWithVariants($image->path, $image->disk, 'default', $variantPaths),
                        'urls' => [
                            'small' => $media->urlForProductImageWithVariants($image->path, $image->disk, 'small', $variantPaths),
                            'default' => $media->urlForProductImageWithVariants($image->path, $image->disk, 'default', $variantPaths),
                            'big' => $media->urlForProductImageWithVariants($image->path, $image->disk, 'big', $variantPaths),
                        ],
                        'variant_paths' => $variantPaths,
                        'is_primary' => $image->is_primary,
                        'sort_order' => $image->sort_order,
                    ];
                })->values();
            }),
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'sort_order' => $option->sort_order,
                'values' => $option->relationLoaded('values')
                    ? $option->values->map(fn ($value) => [
                        'id' => $value->id,
                        'value' => $value->value,
                        'sort_order' => $value->sort_order,
                    ])->values()
                    : [],
            ])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(function ($variant) {
                $discounted = $variant->price_discounted !== null ? (float) $variant->price_discounted : null;
                $price = (float) $variant->price;
                $discountRate = null;
                if ($discounted !== null && $discounted > 0 && $discounted < $price) {
                    $discountRate = (int) round((($price - $discounted) / $price) * 100);
                }

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'price_discounted' => $variant->price_discounted,
                    'discount_rate' => $discountRate,
                    'stock' => $variant->stock,
                    'is_default' => (bool) $variant->is_default,
                    'option_value_ids' => $variant->relationLoaded('optionValues')
                        ? $variant->optionValues->pluck('id')->values()->all()
                        : [],
                ];
            })->values()),
            'digital_files' => $this->whenLoaded('digitalFiles', fn () => $this->digitalFiles->map(fn ($file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'storage' => $file->storage,
            ])->values()),
            'license_keys' => $this->whenLoaded('licenseKeys', fn () => $this->licenseKeys->map(fn ($key) => [
                'id' => $key->id,
                'license_key' => $key->license_key,
                'is_used' => (bool) $key->is_used,
            ])->values()),
            'custom_fields' => $this->whenLoaded('customFieldProducts', fn () => $this->customFieldProducts->map(fn ($row) => [
                'custom_field_id' => $row->custom_field_id,
                'field_value' => $row->field_value,
                'custom_field_option_id' => $row->custom_field_option_id,
            ])->values()),
            'custom_field_values' => $this->when(
                $this->relationLoaded('customFieldProducts'),
                fn () => app(ProductCustomFieldValuePresenter::class)->forProduct($this->resource),
            ),
            'category_breadcrumb' => $this->when(
                $this->relationLoaded('category') && $this->category,
                fn () => $this->buildCategoryBreadcrumb(),
            ),
            'safety_tips' => $this->resolveSafetyTips($platformSettings),
            'viewer_digital_purchase' => $this->when(
                $this->exposesMembershipDetailPerks($request),
                fn () => $this->resolveViewerDigitalPurchase($request),
            ),
            'has_pending_changes' => is_array($this->pending_changes) && $this->pending_changes !== [],
            'pending_changes' => $this->when(
                $this->canViewPendingChanges($request),
                fn () => $this->pending_changes,
            ),
            'pending_submitted_at' => $this->when(
                $this->canViewPendingChanges($request) && $this->pending_submitted_at !== null,
                fn () => $this->pending_submitted_at?->toIso8601String(),
            ),
            'approved_snapshot' => $this->when(
                $this->canViewPendingChanges($request),
                fn () => $this->approved_snapshot,
            ),
            'last_edit_reject_reason' => $this->when(
                $this->canViewPendingChanges($request) && filled($this->last_edit_reject_reason),
                fn () => $this->last_edit_reject_reason,
            ),
            'last_edit_rejected_at' => $this->when(
                $this->canViewPendingChanges($request) && $this->last_edit_rejected_at !== null,
                fn () => $this->last_edit_rejected_at?->toIso8601String(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function canViewPendingChanges(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('admin_panel')) {
            return true;
        }

        return (int) $user->id === (int) $this->vendor_id;
    }

    /** @return list<array{id: int, slug: string|null, name: string|null}> */
    private function buildCategoryBreadcrumb(): array
    {
        $trail = [];
        $category = $this->category;

        while ($category) {
            $translation = $category->translations->firstWhere('locale', 'en')
                ?? $category->translations->first();

            $trail[] = [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $translation?->name,
            ];

            if (! $category->parent_id) {
                break;
            }

            $category = $category->relationLoaded('parent')
                ? $category->parent
                : $category->parent()->with('translations')->first();
        }

        return array_reverse($trail);
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
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function resolveSafetyTips(array $settings): array
    {
        $tips = $settings['product_safety_tips'] ?? config('selloff.platform_settings.product_safety_tips', []);

        if (is_string($tips)) {
            $decoded = json_decode($tips, true);
            $tips = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($tips)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($tip) => is_string($tip) ? trim($tip) : '',
            $tips,
        )));
    }

    /** @return array{id: int, purchase_code: string}|null */
    private function resolveViewerDigitalPurchase(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null || $this->type !== 'digital' || (bool) ($this->is_free_product ?? false)) {
            return null;
        }

        $sale = DigitalSale::query()
            ->where('buyer_id', $user->id)
            ->where('product_id', $this->id)
            ->first();

        if ($sale === null) {
            return null;
        }

        return [
            'id' => (int) $sale->id,
            'purchase_code' => (string) $sale->purchase_code,
        ];
    }

    private function exposesMembershipDetailPerks(Request $request): bool
    {
        $route = $request->route();
        if ($route === null) {
            return false;
        }

        return $route->getActionMethod() === 'show'
            && $route->getControllerClass() === ProductController::class;
    }
}
