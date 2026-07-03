<?php

namespace App\Modules\Selloff\Catalog\Http\Resources\Api\V1;

use App\Modules\Selloff\Catalog\Models\Brand;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Brand */
class AdminBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_path' => $this->image_path,
            'image_url' => MediaUrl::resolve($this->image_path),
            'storage' => $this->storage,
            'show_on_slider' => (bool) $this->show_on_slider,
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->values()->all()),
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(function ($category) {
                    $translation = $category->translations->firstWhere('locale', 'en')
                        ?? $category->translations->first();

                    return [
                        'id' => $category->id,
                        'name' => $translation?->name ?? $category->slug,
                    ];
                })->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
