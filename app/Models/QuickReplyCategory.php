<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickReplyCategory extends Model
{
    protected $fillable = ['name', 'icon', 'order', 'is_active'];

    public function quickReplies(): HasMany
    {
        return $this->hasMany(QuickReply::class);
    }
}
