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
     * Scope blocks for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('blocker_id', $userId);
    }

    /**
     * Scope blocks targeting a specific user
     */
    public function scopeTargetingUser($query, $userId)
    {
        return $query->where('blocked_user_id', $userId);
    }
}