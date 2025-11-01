<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickReply extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'title', 'message', 
        'shortcut', 'order', 'usage_count', 'last_used_at', 'is_global'
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_global' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuickReplyCategory::class);
    }
}
