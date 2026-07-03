<?php

namespace App\Modules\Selloff\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'status' => 'boolean',
        ];
    }
}
