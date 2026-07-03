<?php

namespace App\Modules\Selloff\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorListingDailyMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'promotion_spend' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
