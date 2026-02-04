<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DraftMessage Model
 * 
 * Stores draft messages for conversations (WhatsApp-style auto-save).
 * One draft per user per conversation.
 */
class DraftMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'content',
        'media_urls_json',
        'reply_to_id',
        'mentions_json',
        'saved_at',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this draft
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation this draft belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the message being replied to (if any)
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Get media URLs as array
     */
    public function getMediaUrlsAttribute()
    {
        return $this->media_urls_json ? json_decode($this->media_urls_json, true) : [];
    }

    /**
     * Set media URLs from array
     */
    public function setMediaUrlsAttribute($value)
    {
        $this->attributes['media_urls_json'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Get mentions as array
     */
    public function getMentionsAttribute()
    {
        return $this->mentions_json ? json_decode($this->mentions_json, true) : [];
    }

    /**
     * Set mentions from array
     */
    public function setMentionsAttribute($value)
    {
        $this->attributes['mentions_json'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Update saved_at timestamp
     */
    public function touchSavedAt()
    {
        $this->saved_at = now();
        $this->save();
    }
}
