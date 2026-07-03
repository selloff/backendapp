<?php

namespace App\Modules\Selloff\Order\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
