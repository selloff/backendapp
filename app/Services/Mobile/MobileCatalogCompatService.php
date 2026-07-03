<?php

namespace App\Services\Mobile;

use App\Http\Resources\Api\V1\Mobile\MobileProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Media\Models\ProductImage;
use App\Support\MediaUrl;
use Illuminate\Support\Collection;

class MobileCatalogCompatService
{
    /**
     * @return array{product_id: int, images: list<array<string, mixed>>}
     */
    public function productImages(int $productId): array
    {
        Product::query()->findOrFail($productId);

        $images = ProductImage::query()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'product_id' => $image->product_id,
                'image_default' => $image->is_primary ? '1' : '0',
                'image_path' => $image->path,
                'image_url' => MediaUrl::resolve($image->path),
                'storage' => $image->disk ?? 'public',
            ])
            ->values()
            ->all();

        return [
            'product_id' => $productId,
            'images' => $images,
        ];
    }

    /** @return Collection<int, Product> */
    public function relatedProducts(int $productId, int $limit): Collection
    {
        $product = Product::query()->findOrFail($productId);
        $limit = min(max($limit, 1), 20);

        $query = Product::query()
            ->with(['translations', 'vendor.vendorProfile', 'category.translations', 'images', 'state', 'city'])
            ->where('status', 'published')
            ->where('visibility', 'visible')
            ->where('is_active', true)
            ->where('id', '!=', $productId);

        if ($product->category_id) {
            $query->where('category_id', $product->category_id);
        } else {
            $query->where('vendor_id', $product->vendor_id);
        }

        return $query->latest()->limit($limit)->get();
    }

    /** @return list<array<string, mixed>> */
    public function relatedProductPayload(int $productId, int $limit): array
    {
        return MobileProductResource::collection(
            $this->relatedProducts($productId, $limit),
        )->resolve();
    }
}
