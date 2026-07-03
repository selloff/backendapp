<?php

namespace App\Modules\Selloff\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => 'boolean',
            'language_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LanguageTranslation::class);
    }
}
