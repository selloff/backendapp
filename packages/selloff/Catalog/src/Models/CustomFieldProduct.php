<?php

namespace App\Modules\Selloff\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldProduct extends Model
{
    protected $table = 'custom_field_product';

    public $timestamps = false;

    protected $guarded = [];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(CustomFieldOption::class, 'custom_field_option_id');
    }
}
