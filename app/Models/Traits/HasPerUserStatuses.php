<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasPerUserStatuses
{
    /**
     * Your model MUST define:
     *   - a `statuses()` relationship (hasMany to the proper *Status model)
     *   - a static `statusClass(): string` that returns that Status class name
     *     (e.g. MessageStatus::class or GroupMessageStatus::class)
     */

    /** Append computed attr automatically */
    public function initializeHasPerUserStatuses(): void
    {
        if (property_exists($this, 'appends')) {
            $this->appends = array_values(array_unique(array_merge($this->appends, ['read_count_others'])));
        }
    }

    /** Hide items “deleted for me” by this user (uses statuses.deleted_at) */
    public function scopeVisibleTo(Builder $q, int $userId): Builder
    {
        return $q->whereDoesntHave('statuses', function ($s) use ($userId) {
            $s->where('user_id', $userId)->whereNotNull('deleted_at');
        });
    }

    /** Add DB-side counters: read_count & delivered_count */
    public function scopeWithStatusCounters(Builder $q): Builder
    {
        $status = static::statusClass();

        return $q->withCount([
            'statuses as read_count' => fn ($s) => $s->where('status', $status::STATUS_READ),
            'statuses as delivered_count' => fn ($s) =>
                $s->whereIn('status', [$status::STATUS_DELIVERED, $status::STATUS_READ]),
        ]);
    }

    /** Computed: readers excluding the sender, derived from read_count (+/- if we know sender’s row) */
    public function getReadCountOthersAttribute(): int
    {
        $read = (int) ($this->read_count ?? 0);

        // If statuses are loaded, try subtracting the sender's read row precisely:
        if (method_exists($this, 'relationLoaded') && $this->relationLoaded('statuses') && isset($this->sender_id)) {
            $status = static::statusClass();
            $senderRow = $this->statuses->firstWhere('user_id', $this->sender_id);
            $senderRead = $senderRow && $senderRow->status === $status::STATUS_READ ? 1 : 0;
            return max($read - $senderRead, 0);
        }

        // Fallback (no relation loaded): just return DB count
        return $read;
    }

    /** Per-user helpers */
    public function markAsReadFor(int $userId): void
    {
        $status = static::statusClass();
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['status' => $status::STATUS_READ, 'updated_at' => now()]
        );
    }

    public function markAsDeliveredFor(int $userId): void
    {
        $status = static::statusClass();
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['status' => $status::STATUS_DELIVERED, 'updated_at' => now()]
        );
    }

    public function deleteForUser(int $userId): void
    {
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['deleted_at' => now(), 'updated_at' => now()]
        );
    }

    /** Each model implements this to point to its Status class */
    abstract public static function statusClass(): string;
}
