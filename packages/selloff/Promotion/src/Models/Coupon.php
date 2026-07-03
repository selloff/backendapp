<?php

namespace App\Modules\Selloff\Promotion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'category_ids' => 'array',
            'expires_at' => 'datetime',
            'minimum_order_amount' => 'decimal:2',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Selloff\Catalog\Models\Product::class,
            'coupon_products',
            'coupon_id',
            'product_id',
        );
    }
}
