<?php

namespace App\Modules\Selloff\Content\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'title_active' => 'boolean',
        ];
    }
}
