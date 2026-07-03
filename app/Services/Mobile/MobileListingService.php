<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Media\Models\ProductImage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileListingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createListing(User $vendor, array $data): Product
    {
        if (! $vendor->can('products')) {
            throw ValidationException::withMessages([
                'permission' => ['Not permitted to add listings.'],
            ]);
        }

        $title = (string) ($data['title'] ?? $data['product_title'] ?? '');
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['The title field is required.'],
            ]);
        }

        $price = $data['price'] ?? $data['product_price'] ?? null;
        if ($price === null || ! is_numeric($price)) {
            throw ValidationException::withMessages([
                'price' => ['The price field is required.'],
            ]);
        }

        $product = Product::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'slug' => $data['slug'] ?? Str::slug($title).'-'.Str::lower(Str::random(4)),
            'type' => $data['type'] ?? 'physical',
            'listing_type' => $data['listing_type'] ?? 'sell_on_site',
            'status' => $data['status'] ?? 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'price' => $price,
            'price_discounted' => $data['price_discounted'] ?? null,
            'currency_code' => $data['currency_code'] ?? 'NGN',
            'stock' => (int) ($data['stock'] ?? $data['product_quantity'] ?? 1),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'en',
            'title' => $title,
            'description' => $data['description'] ?? $data['product_description'] ?? null,
            'short_description' => $data['short_description'] ?? null,
        ]);

        $images = $data['images'] ?? [];
        if (is_string($images)) {
            $images = array_filter([['path' => $images]]);
        }

        foreach (array_values($images) as $index => $image) {
            $path = is_array($image) ? ($image['path'] ?? $image['image_path'] ?? null) : null;
            if (! $path) {
                continue;
            }

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'disk' => is_array($image) ? ($image['disk'] ?? 'public') : 'public',
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }

        return $product->load(['translations', 'vendor.vendorProfile', 'category.translations', 'images']);
    }
}
