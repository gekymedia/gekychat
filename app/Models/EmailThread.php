<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PHASE 2: Email Thread Model
 * 
 * Maps email threads to conversations for Email-Chat integration.
 */
class EmailThread extends Model
{
    protected $fillable = [
        'conversation_id',
        'thread_id',
        'subject',
        'participants',
        'last_email_at',
        'email_count',
    ];

    protected $casts = [
        'participants' => 'array',
        'last_email_at' => 'datetime',
        'email_count' => 'integer',
    ];

    /**
     * Conversation this email thread maps to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Email messages in this thread
     */
    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }
}
