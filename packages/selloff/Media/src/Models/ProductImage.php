<?php

namespace App\Modules\Selloff\Media\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variant_paths' => 'array',
            'is_primary' => 'boolean',
        ];
    }
}
