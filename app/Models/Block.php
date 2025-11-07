<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocker_id',
        'blocked_user_id',
        'reason',
        'blocked_by_admin',
        'expires_at',
    ];

    protected $casts = [
        'blocked_by_admin' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user who created the block
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Get the user who is being blocked
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }

    /**
     * Get the admin who created the block (if applicable)
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Check if the block is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the block is permanent
     */
    public function isPermanent(): bool
    {
        return is_null($this->expires_at);
    }

    /**
     * Scope active blocks (not expired)
     */
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope admin blocks
     */
    public function scopeAdminBlocks($query)
    {
        return $query->where('blocked_by_admin', true);
    }

    /**
     * Scope user blocks
     */
    public function scopeUserBlocks($query)
    {
        return $query->where('blocked_by_admin', false);
    }
}