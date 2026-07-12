<?php

namespace App\Modules\Selloff\Catalog\Http\Resources\Api\V1;

use App\Modules\Selloff\Catalog\Services\ProductEditStagingService;
use Illuminate\Http\Request;

/** @mixin \App\Modules\Selloff\Catalog\Models\Product */
class ProductAdminResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->relationLoaded('customFieldProducts')) {
            $data['custom_fields'] = $this->customFieldProducts->map(fn ($row) => [
                'custom_field_id' => $row->custom_field_id,
                'label' => $row->customField?->label,
                'field_value' => $row->field_value,
                'custom_field_option_id' => $row->custom_field_option_id,
                'option_value' => $row->option?->value,
            ])->values();
        }

        return array_merge($data, [
            'reject_reason' => $this->reject_reason,
            'is_rejected' => filled($this->reject_reason) && ! $this->is_verified,
            'is_edited' => (bool) $this->is_edited,
            'is_deleted' => (bool) $this->is_deleted,
            'is_draft' => (bool) $this->is_draft,
            'is_special_offer' => (bool) $this->is_special_offer,
            'special_offer_at' => $this->special_offer_at?->toIso8601String(),
            'has_discounted_price' => $this->price_discounted !== null
                && (float) $this->price_discounted > 0
                && (float) $this->price_discounted < (float) $this->price,
            'promote_plan' => $this->promote_plan,
            'promoted_at' => $this->promoted_at,
            'promoted_until' => $this->promoted_until,
            'promotion_remaining_days' => $this->promotionRemainingDays(),
            'commission_rate' => $this->whenLoaded(
                'category',
                fn () => $this->category?->is_commission_set ? (float) $this->category->commission_rate : null,
            ),
            'moderation_diff' => $this->when(
                (bool) $this->is_edited,
                fn () => app(ProductEditStagingService::class)->buildModerationDiff($this->resource),
            ),
            'moderation_snapshot_warning' => $this->when(
                (bool) $this->is_edited
                    && (
                        ! is_array($this->approved_snapshot)
                        || $this->approved_snapshot === []
                        || ($this->pending_submitted_at !== null
                            && $this->updated_at !== null
                            && $this->pending_submitted_at->lt($this->updated_at))
                    ),
                true,
            ),
        ]);
    }

    private function promotionRemainingDays(): ?int
    {
        if (! $this->is_promoted || $this->promoted_until === null) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->promoted_until, false));
    }
}
