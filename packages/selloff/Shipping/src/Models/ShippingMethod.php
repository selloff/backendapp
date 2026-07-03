<?php

namespace App\Modules\Selloff\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'flat_rate' => 'decimal:2',
            'free_shipping_min_amount' => 'decimal:2',
            'local_pickup_cost' => 'decimal:2',
            'shipping_flat_cost' => 'decimal:2',
            'flat_rate_costs' => 'array',
            'status' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
