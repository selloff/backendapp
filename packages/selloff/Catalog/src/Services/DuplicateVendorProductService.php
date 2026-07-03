<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DuplicateVendorProductService
{
    public function duplicate(Product $source, User $vendor): ProductResource
    {
        abort_unless((int) $source->vendor_id === (int) $vendor->id, 403);

        return DB::transaction(function () use ($source, $vendor) {
            $source->load([
                'translations',
                'images',
                'options.values',
                'variants.optionValues',
                'tags',
                'customFieldProducts',
                'digitalFiles',
            ]);

            $suffix = (string) time();
            $attributes = collect($source->getAttributes())
                ->except(['id', 'created_at', 'updated_at'])
                ->all();

            $attributes['vendor_id'] = $vendor->id;
            $attributes['slug'] = $this->uniqueSlug($source->slug.'-'.$suffix);
            $attributes['sku'] = $source->sku ? $source->sku.'-'.$suffix : null;
            $attributes['status'] = 'draft';
            $attributes['is_draft'] = true;
            $attributes['is_active'] = false;
            $attributes['is_verified'] = false;
            $attributes['is_affiliate'] = false;
            $attributes['is_promoted'] = false;
            $attributes['is_sold'] = false;
            $attributes['is_deleted'] = false;
            $attributes['is_edited'] = false;
            $attributes['pageviews'] = 0;
            $attributes['promoted_until'] = null;
            $attributes['promoted_at'] = null;

            /** @var Product $duplicate */
            $duplicate = Product::query()->create($attributes);

            foreach ($source->translations as $translation) {
                $duplicate->translations()->create($translation->only([
                    'locale',
                    'title',
                    'description',
                    'short_description',
                ]));
            }

            foreach ($source->images as $image) {
                $duplicate->images()->create($image->only([
                    'path',
                    'disk',
                    'is_primary',
                    'sort_order',
                    'variant_paths',
                ]));
            }

            foreach ($source->options as $option) {
                $newOption = $duplicate->options()->create($option->only(['name', 'sort_order']));
                foreach ($option->values as $value) {
                    $newOption->values()->create($value->only(['value', 'sort_order']));
                }
            }

            foreach ($source->tags as $tag) {
                $duplicate->tags()->attach($tag->id);
            }

            foreach ($source->customFieldProducts as $field) {
                $duplicate->customFieldProducts()->create($field->only([
                    'custom_field_id',
                    'field_value',
                    'product_filter_key',
                    'custom_field_option_id',
                ]));
            }

            foreach ($source->digitalFiles as $file) {
                $duplicate->digitalFiles()->create($file->only([
                    'file_name',
                    'storage',
                    'user_id',
                ]));
            }

            $duplicate->load(['translations', 'images', 'category.translations', 'brand']);

            return new ProductResource($duplicate);
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'product-'.time();
        $candidate = $slug;
        $counter = 1;

        while (Product::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
