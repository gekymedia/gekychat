<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageStatus extends Model
{
    use HasFactory, SoftDeletes; // ✅ ADD SoftDeletes trait if using per-user deletion

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'message_id',
        'user_id',
        'status',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'deleted_at' => 'datetime', // ✅ ADD if using SoftDeletes
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    /**
     * Get the message that owns the status.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user that the status applies to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Unknown User',
            'avatar_url' => asset('images/default-avatar.png')
        ]);
    }

    /**
     * Scope a query to only include sent statuses.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope a query to only include delivered statuses.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope a query to only include read statuses.
     */
    public function scopeRead($query)
    {
        return $query->where('status', self::STATUS_READ);
    }

    /**
     * Scope a query to only include failed statuses.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include active (not deleted) statuses.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Mark status as sent.
     */
    public function markAsSent()
    {
        $this->update(['status' => self::STATUS_SENT]);
        return $this;
    }

    /**
     * Mark status as delivered.
     */
    public function markAsDelivered()
    {
        $this->update(['status' => self::STATUS_DELIVERED]);
        return $this;
    }

    /**
     * Mark status as read.
     */
    public function markAsRead()
    {
        $this->update(['status' => self::STATUS_READ]);
        return $this;
    }

    /**
     * Mark status as failed.
     */
    public function markAsFailed()
    {
        $this->update(['status' => self::STATUS_FAILED]);
        return $this;
    }

    /**
     * Check if status is sent.
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if status is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if status is read.
     */
    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }

    /**
     * Check if status is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get the status as a human-readable string.
     */
    public function getDisplayStatusAttribute(): string
    {
        return match($this->status) {
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_READ => 'Read',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }
}