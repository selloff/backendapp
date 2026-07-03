<?php

namespace App\Modules\Selloff\Order\Models;

use App\Models\User;
use App\Modules\Selloff\Cart\Models\Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckoutSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'grand_total_base' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'cart_totals_data' => 'array',
            'shipping_data' => 'array',
            'shipping_cost_data' => 'array',
            'service_data' => 'array',
            'service_tax_data' => 'array',
            'has_physical_product' => 'boolean',
            'has_digital_product' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutItem::class);
    }
}
