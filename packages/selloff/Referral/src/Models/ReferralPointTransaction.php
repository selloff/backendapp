<?php

namespace App\Modules\Selloff\Referral\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralPointTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'wallet_amount' => 'decimal:2',
            'money_per_point' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
