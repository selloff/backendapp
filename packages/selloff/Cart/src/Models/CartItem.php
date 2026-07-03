<?php

namespace App\Modules\Selloff\Cart\Models;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'unit_price_base' => 'decimal:2',
            'total_price' => 'decimal:2',
            'product_vat' => 'decimal:2',
            'product_vat_rate' => 'decimal:4',
            'product_image_data' => 'array',
            'extra_options' => 'array',
            'product_options_snapshot' => 'array',
            'is_stock_available' => 'boolean',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
