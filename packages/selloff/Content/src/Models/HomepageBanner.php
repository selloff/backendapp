<?php

namespace App\Modules\Selloff\Content\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageBanner extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
