<?php

namespace App\Modules\Selloff\Notification\Models;

use Illuminate\Database\Eloquent\Model;

class EmailJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
