<?php

namespace App\Modules\Selloff\Content\Models;

use App\Models\User;
use App\Support\LegacyTextNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogPost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value) => LegacyTextNormalizer::restoreLineBreaks($value),
        );
    }

    protected function summary(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value) => LegacyTextNormalizer::restoreLineBreaks($value),
        );
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_post_category');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(BlogTag::class, 'blog_post_id');
    }
}
