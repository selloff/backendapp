<?php

namespace App\Modules\Selloff\Review\Http\Resources\Api\V1;

use App\Modules\Selloff\Review\Models\ProductComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductComment */
class ProductCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'comment' => $this->comment,
            'is_approved' => $this->is_approved,
            'vendor_reply' => $this->vendor_reply,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'product' => $this->whenLoaded('product', function () {
                $translation = $this->product?->relationLoaded('translations')
                    ? ($this->product->translations->firstWhere('locale', 'en') ?? $this->product->translations->first())
                    : null;

                return [
                    'id' => $this->product->id,
                    'slug' => $this->product->slug,
                    'title' => $translation?->title,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
