<?php

namespace App\Modules\Selloff\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'space_money_symbol' => 'boolean',
            'exchange_rate' => 'decimal:6',
        ];
    }
}
