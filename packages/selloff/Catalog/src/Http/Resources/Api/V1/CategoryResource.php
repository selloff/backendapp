<?php

namespace App\Modules\Selloff\Catalog\Http\Resources\Api\V1;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $translation?->name,
            'description' => $translation?->description,
            'parent_id' => $this->parent_id,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'show_on_main_menu' => $this->show_on_main_menu,
            'show_products_on_index' => $this->show_products_on_index,
            'image_path' => $this->image_path,
            'image_url' => MediaUrl::resolve($this->image_path),
            'ads_count' => (int) ($this->ads_count ?? 0),
            'has_children' => isset($this->children_count)
                ? (int) $this->children_count > 0
                : ($this->relationLoaded('children') ? $this->children->isNotEmpty() : false),
            'featured_order' => $this->featured_order,
            'homepage_order' => $this->homepage_order,
            'is_commission_set' => $this->is_commission_set,
            'commission_rate' => $this->is_commission_set ? (float) $this->commission_rate : null,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
