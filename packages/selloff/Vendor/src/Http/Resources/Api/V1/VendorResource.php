<?php

namespace App\Modules\Selloff\Vendor\Http\Resources\Api\V1;

use App\Models\User;
use App\Modules\Selloff\User\Models\Follower;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwnProfile = $request->user()?->id === $this->id;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'avatar' => $this->avatar,
            'shop_name' => $this->vendorProfile?->shop_name,
            'shop_slug' => $this->vendorProfile?->slug,
            'about_me' => $this->about_me,
            'shop_policies' => $this->vendorProfile?->shop_policies,
            'social_media_data' => $this->vendorProfile?->social_media_data,
            'vacation_mode' => (bool) ($this->vendorProfile?->vacation_mode ?? false),
            'vacation_message' => $this->vendorProfile?->vacation_message,
            'is_verified_seller' => (bool) $this->vendorProfile?->is_verified_seller,
            'products' => $this->whenLoaded('products'),
        ];

        $viewer = $request->user();
        if ($viewer && ! $isOwnProfile) {
            $data['is_following'] = Follower::query()
                ->where('user_id', $this->id)
                ->where('follower_id', $viewer->id)
                ->exists();
        }

        if ($isOwnProfile) {
            $platform = app(PlatformSettingsService::class)->all();

            $data = array_merge($data, [
                'show_rss_feeds' => (bool) $this->show_rss_feeds,
                'is_fixed_vat' => (bool) ($this->vendorProfile?->is_fixed_vat ?? false),
                'fixed_vat_rate' => $this->vendorProfile?->fixed_vat_rate,
                'vat_rates_data' => $this->normalizeVatRates($this->vendorProfile?->vat_rates_data),
                'vat_rates_by_state' => $this->normalizeVatRates($this->vendorProfile?->vat_rates_by_state),
                'can_edit_shop_name' => $this->canEditShopName($request, $platform),
            ]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $platform
     */
    private function canEditShopName(Request $request, array $platform): bool
    {
        if ($request->user()?->can('admin_panel')) {
            return true;
        }

        $value = $platform['vendors_change_shop_name'] ?? true;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, string>
     */
    private function normalizeVatRates(mixed $rates): array
    {
        if (! is_array($rates)) {
            return [];
        }

        $normalized = [];
        foreach ($rates as $locationId => $rate) {
            if ($rate === null || $rate === '') {
                continue;
            }

            $normalized[(string) $locationId] = (string) $rate;
        }

        return $normalized;
    }
}
