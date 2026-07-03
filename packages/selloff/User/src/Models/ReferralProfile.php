<?php

namespace App\Modules\Selloff\User\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'affiliate_commission_rate' => 'decimal:2',
            'affiliate_discount_rate' => 'decimal:2',
            'vendor_affiliate_status' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referral_user_id');
    }
}
