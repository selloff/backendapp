<?php

namespace App\Modules\Selloff\Escrow\Models;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscrowTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_amount' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'metadata' => 'array',
            'buyer_agreed' => 'boolean',
            'seller_agreed' => 'boolean',
            'payment_link_sent' => 'boolean',
            'payment_received' => 'boolean',
            'seller_shipped_item' => 'boolean',
            'buyer_confirmed_item_delivery' => 'boolean',
            'seller_received_payment' => 'boolean',
            'transaction_complete' => 'boolean',
            'funded_at' => 'datetime',
            'shipped_at' => 'datetime',
            'accepted_at' => 'datetime',
            'released_at' => 'datetime',
            'release_scheduled_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(EscrowEvent::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EscrowLedgerEntry::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
