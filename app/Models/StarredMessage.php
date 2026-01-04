<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarredMessage extends Model
{
    protected $fillable = [
        'user_id',
        'message_id',
        'group_message_id',
        'starred_at',
    ];

    protected $casts = [
        'starred_at' => 'datetime',
    ];

    /**
     * User who starred the message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Regular message (if from conversation)
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Group message (if from group)
     */
    public function groupMessage(): BelongsTo
    {
        return $this->belongsTo(GroupMessage::class);
    }

    /**
     * Get the actual message (either regular or group)
     */
    public function getActualMessage()
    {
        if ($this->message_id) {
            return $this->message;
        } elseif ($this->group_message_id) {
            return $this->groupMessage;
        }
        return null;
    }
}

