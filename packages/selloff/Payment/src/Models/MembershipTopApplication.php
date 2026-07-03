<?php

namespace App\Modules\Selloff\Payment\Models;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipTopApplication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credits_consumed' => 'integer',
            'applied_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function userMembershipPlan(): BelongsTo
    {
        return $this->belongsTo(UserMembershipPlan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
