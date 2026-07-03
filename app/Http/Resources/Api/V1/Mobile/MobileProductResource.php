<?php

namespace App\Http\Resources\Api\V1\Mobile;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class MobileProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();

        $price = $this->price_discounted ?? $this->price;
        $primaryImage = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : null;
        $imagePath = $primaryImage?->path;
        $imageUrl = MediaUrl::resolve($imagePath);

        return [
            'id' => $this->id,
            'title' => $translation?->title,
            'slug' => $this->slug,
            'description' => $translation?->description,
            'short_description' => $translation?->short_description,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'price_final' => (float) $price,
            'price_discounted' => $this->price_discounted !== null ? (float) $this->price_discounted : null,
            'currency' => $this->currency_code,
            'currency_code' => $this->currency_code,
            'stock' => $this->stock,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'verified' => $this->is_verified,
            'category_id' => $this->category_id,
            'user_id' => $this->vendor_id,
            'shop_name' => $this->vendor?->vendorProfile?->shop_name,
            'user_slug' => $this->vendor?->slug,
            'image' => $imagePath,
            'image_url' => $imageUrl,
            'created_at' => $this->created_at,
        ];
    }
}
