<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AUTO-REPLY: Cooldown tracking model
 * 
 * Anti-loop protection: Tracks last auto-reply per conversation per rule
 * Default cooldown: 24 hours
 */
class AutoReplyCooldown extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'rule_id',
        'last_auto_reply_at',
    ];

    protected $casts = [
        'last_auto_reply_at' => 'datetime',
    ];

    /**
     * Conversation where auto-reply was sent
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Rule that was triggered
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutoReplyRule::class);
    }

    /**
     * Check if cooldown period has passed (default: 24 hours)
     */
    public function isExpired(int $cooldownHours = 24): bool
    {
        if (!$this->last_auto_reply_at) {
            return true;
        }

        return $this->last_auto_reply_at->addHours($cooldownHours)->isPast();
    }

    /**
     * Update or create cooldown record
     */
    public static function updateOrCreateCooldown(int $conversationId, int $ruleId): self
    {
        return static::updateOrCreate(
            [
                'conversation_id' => $conversationId,
                'rule_id' => $ruleId,
            ],
            [
                'last_auto_reply_at' => now(),
            ]
        );
    }

    /**
     * Check if cooldown allows auto-reply (default: 24 hours)
     */
    public static function canAutoReply(int $conversationId, int $ruleId, int $cooldownHours = 24): bool
    {
        $cooldown = static::where('conversation_id', $conversationId)
            ->where('rule_id', $ruleId)
            ->first();

        if (!$cooldown) {
            return true; // No cooldown record = allowed
        }

        return $cooldown->isExpired($cooldownHours);
    }
}
