<?php

namespace App\Modules\Selloff\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'status' => 'boolean',
            'is_product_filter' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(CustomFieldOption::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'custom_field_category', 'custom_field_id', 'category_id');
    }
}
