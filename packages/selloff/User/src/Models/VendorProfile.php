<?php

namespace App\Modules\Selloff\User\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_verified_seller' => 'boolean',
            'is_commission_set' => 'boolean',
            'vacation_mode' => 'boolean',
            'is_fixed_vat' => 'boolean',
            'commission_rate' => 'decimal:2',
            'fixed_vat_rate' => 'decimal:2',
            'payout_info' => 'array',
            'vat_rates_data' => 'array',
            'vat_rates_by_state' => 'array',
            'social_media_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
