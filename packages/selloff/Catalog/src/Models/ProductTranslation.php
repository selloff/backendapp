<?php

namespace App\Modules\Selloff\Catalog\Models;

use App\Support\LegacyTextNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    protected $guarded = [];

    protected function description(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value) => LegacyTextNormalizer::normalizeImportedText($value),
        );
    }

    protected function shortDescription(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value) => LegacyTextNormalizer::normalizeImportedText($value),
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
