<?php

namespace App\Modules\Selloff\Payment\Models;

use App\Modules\Selloff\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPlanCategoryLimit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_active_listings' => 'integer',
        ];
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
