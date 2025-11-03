<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuickReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'title', 
        'message', 
        'order', 
        'usage_count', 
        'last_used_at'
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
        'order' => 'integer'
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::creating(function ($quickReply) {
            // Set default order if not provided
            if (empty($quickReply->order)) {
                $maxOrder = static::where('user_id', $quickReply->user_id)->max('order') ?? 0;
                $quickReply->order = $maxOrder + 1;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include quick replies for a given user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to order by custom order then by creation date.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to only include frequently used replies.
     */
    public function scopeFrequentlyUsed($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    /**
     * Increment usage count and update last used timestamp.
     */
    public function recordUsage()
    {
        $this->increment('usage_count');
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * Get the display title (fallback to message excerpt if no title).
     */
    public function getDisplayTitleAttribute()
    {
        return $this->title ?: str($this->message)->limit(30)->toString();
    }

    /**
     * Get a short preview of the message.
     */
    public function getMessagePreviewAttribute()
    {
        return str($this->message)->limit(50)->toString();
    }

    /**
     * Check if the quick reply was used recently (within last 7 days).
     */
    public function getUsedRecentlyAttribute()
    {
        return $this->last_used_at && $this->last_used_at->gt(now()->subDays(7));
    }
}