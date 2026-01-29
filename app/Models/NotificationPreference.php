<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        // Push notifications
        'push_messages',
        'push_group_messages',
        'push_calls',
        'push_status_updates',
        'push_reactions',
        'push_mentions',
        // Email notifications
        'email_messages',
        'email_weekly_digest',
        'email_security_alerts',
        'email_marketing',
        // In-app notifications
        'show_message_preview',
        'notification_sound',
        'vibrate',
        'led_notification',
        // Quiet hours
        'quiet_hours_start',
        'quiet_hours_end',
        'quiet_hours_enabled',
    ];

    protected $casts = [
        'push_messages' => 'boolean',
        'push_group_messages' => 'boolean',
        'push_calls' => 'boolean',
        'push_status_updates' => 'boolean',
        'push_reactions' => 'boolean',
        'push_mentions' => 'boolean',
        'email_messages' => 'boolean',
        'email_weekly_digest' => 'boolean',
        'email_security_alerts' => 'boolean',
        'email_marketing' => 'boolean',
        'show_message_preview' => 'boolean',
        'notification_sound' => 'boolean',
        'vibrate' => 'boolean',
        'led_notification' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
    ];

    /**
     * Get the user that owns the notification preferences
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if currently in quiet hours
     */
    public function isQuietHours(): bool
    {
        if (!$this->quiet_hours_enabled || !$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = Carbon::now()->format('H:i:s');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // Handle overnight quiet hours (e.g., 22:00 to 07:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        // Handle same-day quiet hours (e.g., 13:00 to 14:00)
        return $now >= $start && $now <= $end;
    }

    /**
     * Should send push notification for message
     */
    public function shouldSendPushForMessage(bool $isGroupMessage = false): bool
    {
        if ($this->isQuietHours()) {
            return false;
        }

        return $isGroupMessage ? $this->push_group_messages : $this->push_messages;
    }
}
