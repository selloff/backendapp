<?php

namespace App\Modules\Selloff\Payout\Models;

use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorEarning extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'earned_amount' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'is_refunded' => 'boolean',
            'affiliate_data' => 'array',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Selloff\Escrow\Models\EscrowTransaction::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
