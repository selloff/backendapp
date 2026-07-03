<?php

namespace App\Modules\Selloff\Catalog\Http\Resources\Api\V1;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class AdminCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();

        $hasSubcategory = $this->children_count ?? null;
        if ($hasSubcategory === null && $this->relationLoaded('children')) {
            $hasSubcategory = $this->children->isNotEmpty();
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $translation?->name,
            'description' => $translation?->description,
            'meta_title' => $translation?->meta_title,
            'meta_description' => $translation?->meta_description,
            'meta_keywords' => $translation?->meta_keywords,
            'parent_id' => $this->parent_id,
            'category_order' => $this->category_order,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'is_commission_set' => $this->is_commission_set,
            'commission_rate' => (float) $this->commission_rate,
            'show_on_main_menu' => $this->show_on_main_menu,
            'show_image_on_main_menu' => $this->show_image_on_main_menu,
            'show_description' => $this->show_description,
            'show_products_on_index' => $this->show_products_on_index,
            'image_path' => $this->image_path,
            'image_url' => MediaUrl::resolve($this->image_path),
            'storage' => $this->storage,
            'featured_order' => $this->featured_order,
            'homepage_order' => $this->homepage_order,
            'has_subcategory' => (bool) ($hasSubcategory ?? false),
            'parent_chain' => $this->when(isset($this->parent_chain), $this->parent_chain),
            'translations' => $this->whenLoaded('translations', function () {
                return $this->translations->map(fn ($row) => [
                    'locale' => $row->locale,
                    'name' => $row->name,
                    'description' => $row->description,
                    'meta_title' => $row->meta_title,
                    'meta_description' => $row->meta_description,
                    'meta_keywords' => $row->meta_keywords,
                ])->values();
            }),
            'children' => AdminCategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
