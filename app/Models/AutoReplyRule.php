<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AUTO-REPLY: Auto-reply rule model
 * 
 * Users can create rules that automatically reply to messages containing keywords
 * Rules only apply to one-to-one private chats (not groups, channels, etc.)
 */
class AutoReplyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'keyword',
        'reply_text',
        'delay_seconds',
        'is_active',
    ];

    protected $casts = [
        'delay_seconds' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * User who owns this rule
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cooldown records for this rule
     */
    public function cooldowns(): HasMany
    {
        return $this->hasMany(AutoReplyCooldown::class, 'rule_id');
    }

    /**
     * Check if keyword matches message body (case-insensitive)
     */
    public function matches(string $messageBody): bool
    {
        if (empty($messageBody)) {
            return false;
        }

        return stripos($messageBody, $this->keyword) !== false;
    }

    /**
     * Scope to only active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
