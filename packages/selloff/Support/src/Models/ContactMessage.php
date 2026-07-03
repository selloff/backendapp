<?php

namespace App\Modules\Selloff\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    protected $guarded = [];

    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class)->orderBy('id');
    }
}
