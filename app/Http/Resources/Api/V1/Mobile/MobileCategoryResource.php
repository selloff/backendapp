<?php

namespace App\Http\Resources\Api\V1\Mobile;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class MobileCategoryResource extends JsonResource
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
            'is_featured' => $this->is_featured,
            'image_path' => $this->image_path,
            'image_url' => MediaUrl::resolve($this->image_path),
        ];
    }
}
