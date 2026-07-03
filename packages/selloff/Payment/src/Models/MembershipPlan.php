<?php

namespace App\Modules\Selloff\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'is_free' => 'boolean',
            'features' => 'array',
            'marketing_benefits' => 'array',
            'visibility_multiplier' => 'decimal:2',
            'allow_website_link' => 'boolean',
            'allow_social_links' => 'boolean',
            'allow_whatsapp_link' => 'boolean',
            'hide_seller_feedback' => 'boolean',
        ];
    }

    public function categoryLimits(): HasMany
    {
        return $this->hasMany(MembershipPlanCategoryLimit::class);
    }
}
