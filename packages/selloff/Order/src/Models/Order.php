<?php

namespace App\Modules\Selloff\Order\Models;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_subtotal' => 'decimal:2',
            'price_vat' => 'decimal:2',
            'price_shipping' => 'decimal:2',
            'price_total' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'transaction_fee' => 'decimal:2',
            'transaction_fee_rate' => 'decimal:4',
            'price_total_base' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'shipping_snapshot' => 'array',
            'global_taxes_data' => 'array',
            'affiliate_data' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }
}
