<?php

namespace App\Modules\Selloff\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationRead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function readByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by_user_id');
    }
}
