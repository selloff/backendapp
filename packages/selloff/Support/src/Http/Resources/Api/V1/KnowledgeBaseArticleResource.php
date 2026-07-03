<?php

namespace App\Modules\Selloff\Support\Http\Resources\Api\V1;

use App\Modules\Selloff\Support\Models\KnowledgeBaseArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KnowledgeBaseArticle */
class KnowledgeBaseArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledge_base_category_id' => $this->knowledge_base_category_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
