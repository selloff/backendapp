<?php

namespace App\Modules\Selloff\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldOption extends Model
{
    protected $guarded = [];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
