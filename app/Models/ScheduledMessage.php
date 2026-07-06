<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'group_id',
        'user_id',
        'body',
        'attachments',
        'reply_to_id',
        'scheduled_for',
        'status',
        'sent_message_id',
        'error_message',
    ];

    protected $casts = [
        'attachments' => 'array',
        'scheduled_for' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
