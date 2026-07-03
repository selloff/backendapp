<?php

namespace App\Modules\Selloff\Content\Http\Resources\Api\V1;

use App\Modules\Selloff\Content\Models\Slider;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Slider */
class SliderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => MediaUrl::resolve($this->image_path),
            'image_mobile_path' => $this->image_mobile_path,
            'image_mobile_url' => MediaUrl::resolve($this->image_mobile_path),
            'link' => $this->link,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'lang_id' => $this->lang_id,
            'button_text' => $this->button_text,
            'text_color' => $this->text_color,
            'button_color' => $this->button_color,
            'button_text_color' => $this->button_text_color,
            'animation_title' => $this->animation_title,
            'animation_description' => $this->animation_description,
            'animation_button' => $this->animation_button,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
