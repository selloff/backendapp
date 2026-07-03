<?php

namespace App\Modules\Selloff\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogImage extends Model
{
    protected $fillable = [
        'image_path',
        'image_path_thumb',
        'storage',
        'user_id',
        'legacy_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
