<?php

namespace App\Modules\Selloff\Affiliate\Models;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateEarning extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:4',
            'earned_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
