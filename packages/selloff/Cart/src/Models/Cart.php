<?php

namespace App\Modules\Selloff\Cart\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'shipping_cost' => 'decimal:2',
            'shipping_data' => 'array',
            'shipping_cost_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
