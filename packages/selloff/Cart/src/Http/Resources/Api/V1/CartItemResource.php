<?php

namespace App\Modules\Selloff\Cart\Http\Resources\Api\V1;

use App\Modules\Selloff\Cart\Models\CartItem;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = null;
        if ($this->relationLoaded('product') && $this->product) {
            $image = $this->product->relationLoaded('images')
                ? ($this->product->images->firstWhere('is_primary', true) ?? $this->product->images->first())
                : null;
            if ($image) {
                $imageUrl = app(MediaUploadService::class)->urlForProductImage($image->path, $image->disk, 'small');
            }
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'seller_id' => $this->seller_id,
            'product_title' => $this->product_title,
            'product_sku' => $this->product_sku,
            'product_type' => $this->product_type,
            'listing_type' => $this->listing_type,
            'product_options_summary' => $this->product_options_summary,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'is_stock_available' => $this->is_stock_available,
            'product_slug' => $this->product?->slug,
            'product_image_url' => $imageUrl,
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'username' => $this->seller->username ?? $this->seller->slug,
                'slug' => $this->seller->slug,
                'shop_name' => $this->seller->vendorProfile?->shop_name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'slug' => $this->product->slug,
                'stock' => $this->product->stock,
                'type' => $this->product->type,
            ]),
        ];
    }
}
