<?php

namespace App\Modules\Selloff\Payment\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

class MembershipPlanEntitlementValidator
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'nullable';

        return [
            'visibility_multiplier' => [$required, 'numeric', 'min:1', 'max:1000'],
            'global_listing_limit' => ['nullable', 'integer', 'min:-1'],
            'auto_bump_interval_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'top_credits_per_period' => [$required, 'integer', 'min:0', 'max:10000'],
            'top_badge_label' => ['nullable', 'string', 'max:100'],
            'top_rank_weight' => [$required, 'integer', 'min:0', 'max:100000'],
            'allow_website_link' => ['nullable', 'boolean'],
            'allow_social_links' => ['nullable', 'boolean'],
            'allow_whatsapp_link' => ['nullable', 'boolean'],
            'hide_seller_feedback' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
            'marketing_benefits' => ['nullable', 'array'],
            'marketing_benefits.*' => ['string', 'max:500'],
            'category_limits' => ['nullable', 'array'],
            'category_limits.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_limits.*.max_active_listings' => ['required', 'integer', 'min:-1'],
        ];
    }

    public static function validateRootCategories(Validator $validator): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $limits = $validator->getData()['category_limits'] ?? null;
        if (! is_array($limits)) {
            return;
        }

        foreach ($limits as $index => $limit) {
            if (! is_array($limit)) {
                continue;
            }

            $categoryId = (int) ($limit['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $parentId = DB::table('categories')->where('id', $categoryId)->value('parent_id');
            if ($parentId !== null) {
                $validator->errors()->add(
                    "category_limits.{$index}.category_id",
                    'Category limits must use root categories only.',
                );
            }
        }
    }
}
