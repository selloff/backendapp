<?php

namespace App\Modules\Selloff\Content\Models;

use Illuminate\Database\Eloquent\Model;

class AdSpace extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
