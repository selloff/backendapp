<?php

namespace App\Modules\Selloff\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLicenseKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
