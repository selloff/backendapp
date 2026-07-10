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
            'template_data' => 'array',
            'sent_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'skipped_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
