<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Email Log Model
 * 
 * Tracks email processing attempts for admin review.
 * Does NOT store email content for privacy.
 */
class EmailLog extends Model
{
    protected $fillable = [
        'from_email',
        'from_name',
        'to_emails',
        'subject',
        'message_id_header',
        'status',
        'routed_to_username',
        'routed_to_user_id',
        'conversation_id',
        'message_id',
        'failure_reason',
        'error_details',
        'processed_at',
    ];

    protected $casts = [
        'to_emails' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * User this email was routed to
     */
    public function routedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'routed_to_user_id');
    }

    /**
     * Conversation created from this email
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Message created from this email
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Scope for failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for successful emails
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for ignored emails
     */
    public function scopeIgnored($query)
    {
        return $query->where('status', 'ignored');
    }
}
