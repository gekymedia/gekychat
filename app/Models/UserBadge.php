<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserBadge extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'icon',
        'color',
        'description',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get users who have this badge
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badge_assignments', 'badge_id', 'user_id')
            ->withPivot(['assigned_at', 'assigned_by', 'assignment_notes'])
            ->orderBy('assigned_at', 'desc');
    }

    /**
     * Scope for active badges only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    /**
     * Assign badge to user
     */
    public function assignTo(User $user, ?User $assignedBy = null, ?string $notes = null): void
    {
        if (!$user->hasBadge($this->name)) {
            $user->badges()->attach($this->id, [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy?->id,
                'assignment_notes' => $notes,
            ]);
        }
    }

    /**
     * Remove badge from user
     */
    public function removeFrom(User $user): void
    {
        $user->badges()->detach($this->id);
    }
}
