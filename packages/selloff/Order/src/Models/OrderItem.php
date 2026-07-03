<?php

namespace App\Modules\Selloff\Order\Models;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'product_vat' => 'decimal:2',
            'product_vat_rate' => 'decimal:4',
            'seller_shipping_cost' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'is_approved' => 'boolean',
            'product_image_data' => 'array',
            'product_options_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
