<?php

namespace App\Modules\Selloff\Catalog\Http\Resources\Api\V1;

use App\Modules\Selloff\Catalog\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomField */
class CustomFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_type' => $this->field_type,
            'label' => $this->label,
            'is_required' => $this->is_required,
            'status' => $this->status,
            'field_order' => $this->field_order,
            'is_product_filter' => $this->is_product_filter,
            'product_filter_key' => $this->product_filter_key,
            'sort_options' => $this->sort_options,
            'where_to_display' => $this->where_to_display,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'option_key' => $option->option_key,
                'label' => $option->label,
            ])),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->values()),
            'created_at' => $this->created_at,
        ];
    }
}
