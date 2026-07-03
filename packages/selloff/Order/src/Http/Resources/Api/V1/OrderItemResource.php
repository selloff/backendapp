<?php

namespace App\Modules\Selloff\Order\Http\Resources\Api\V1;

use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Services\Media\MediaUploadService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exposeDigital = (bool) $request->attributes->get('expose_digital_downloads', false);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'seller_id' => $this->seller_id,
            'product_title' => $this->product_title,
            'product_sku' => $this->product_sku,
            'product_type' => $this->product_type,
            'product_slug' => $this->whenLoaded('product', fn () => $this->product?->slug),
            'product_image_url' => $this->productImageUrl(),
            'product_options_summary' => $this->product_options_summary,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'product_vat' => $this->product_vat,
            'product_vat_rate' => $this->product_vat_rate,
            'seller_shipping_cost' => $this->seller_shipping_cost,
            'order_status' => $this->order_status,
            'is_approved' => $this->is_approved,
            'shipping_method' => $this->shipping_method,
            'shipping_tracking_number' => $this->shipping_tracking_number,
            'shipping_tracking_url' => $this->shipping_tracking_url,
            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'id' => $this->seller->id,
                'username' => $this->seller->username ?? $this->seller->slug,
                'slug' => $this->seller->slug,
                'name' => $this->seller->name,
            ] : null),
            'digital_downloads' => $this->when(
                $exposeDigital && $this->product_type === 'digital',
                fn () => DigitalSale::query()
                    ->where('order_id', $this->order_id)
                    ->where('product_id', $this->product_id)
                    ->get(['id', 'license_key', 'purchase_code'])
                    ->map(fn (DigitalSale $sale) => [
                        'id' => $sale->id,
                        'license_key' => $sale->license_key,
                        'purchase_code' => $sale->purchase_code,
                    ])
                    ->values()
                    ->all(),
            ),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }

    private function productImageUrl(): ?string
    {
        if ($this->relationLoaded('product') && $this->product) {
            $image = $this->product->relationLoaded('images')
                ? ($this->product->images->firstWhere('is_primary', true) ?? $this->product->images->first())
                : null;
            if ($image) {
                return app(MediaUploadService::class)->urlForProductImage($image->path, $image->disk, 'small');
            }
        }

        $imageData = $this->product_image_data;
        if (is_array($imageData)) {
            $path = $imageData['image_default']
                ?? $imageData['path']
                ?? $imageData['image']
                ?? null;
            if (is_string($path) && $path !== '') {
                return MediaUrl::resolve($path);
            }
        }

        return null;
    }
}
