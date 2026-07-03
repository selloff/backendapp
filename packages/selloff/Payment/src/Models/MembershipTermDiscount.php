<?php

namespace App\Modules\Selloff\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipTermDiscount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
