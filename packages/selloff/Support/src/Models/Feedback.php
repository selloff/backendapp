<?php

namespace App\Modules\Selloff\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(FeedbackReply::class)->orderBy('id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(FeedbackDispute::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('moderation_status', 'approved');
    }
}
