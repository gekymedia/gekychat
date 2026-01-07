<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 2: Email Message Model
 * 
 * Links email metadata to messages in conversations.
 */
class EmailMessage extends Model
{
    protected $fillable = [
        'message_id',
        'email_thread_id',
        'message_id_header',
        'in_reply_to',
        'references',
        'from_email',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'html_body',
        'text_body',
        'status',
        'delivered_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'from_email' => 'array',
        'to_emails' => 'array',
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Message in conversation
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Email thread
     */
    public function emailThread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class);
    }
}
