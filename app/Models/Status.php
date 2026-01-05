<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Status extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'text',
        'media_url',
        'thumbnail_url',
        'background_color',
        'text_color',
        'font_size',
        'font_family',
        'duration',
        'expires_at',
        'view_count',
    ];

    protected $casts = [
        'font_size' => 'integer',
        'duration' => 'integer',
        'view_count' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['viewed'];

    /**
     * The user who created this status
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Users who have viewed this status
     */
    public function views(): HasMany
    {
        return $this->hasMany(StatusView::class);
    }

    /**
     * Comments on this status
     */
    public function comments(): HasMany
    {
        return $this->hasMany(StatusComment::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get full media URL
     */
    public function getMediaUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If already a full URL, return as is (but force HTTPS if request is HTTPS)
        if (str_starts_with($value, 'http')) {
            // Force HTTPS if the current request is over HTTPS
            if (request()->secure() || request()->header('X-Forwarded-Proto') === 'https') {
                $value = str_replace('http://', 'https://', $value);
            }
            return $value;
        }

        // Otherwise, generate URL from storage (force HTTPS if request is HTTPS)
        return \App\Helpers\UrlHelper::secureStorageUrl($value, 'public');
    }

    /**
     * Get full thumbnail URL
     */
    public function getThumbnailUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If already a full URL, return as is (but force HTTPS if request is HTTPS)
        if (str_starts_with($value, 'http')) {
            // Force HTTPS if the current request is over HTTPS
            if (request()->secure() || request()->header('X-Forwarded-Proto') === 'https') {
                $value = str_replace('http://', 'https://', $value);
            }
            return $value;
        }

        // Otherwise, generate URL from storage (force HTTPS if request is HTTPS)
        return \App\Helpers\UrlHelper::secureStorageUrl($value, 'public');
    }

    /**
     * Check if status is expired
     */
    public function isExpired(): bool
    {
        if ($this->expires_at) {
            return $this->expires_at->isPast();
        }

        // Fallback to duration-based calculation
        return $this->created_at->addSeconds($this->duration)->isPast();
    }

    /**
     * Check if current user has viewed this status
     */
    public function getViewedAttribute(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->views()
            ->where('user_id', auth()->id())
            ->exists();
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Scope to get only non-expired statuses
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get statuses visible to a user based on privacy settings
     */
    public function scopeVisibleTo($query, int $userId)
    {
        // Check if the status owner is in the current user's contacts
        // Contact: user_id = current user, contact_user_id = status owner
        return $query->whereIn('user_id', function ($subQuery) use ($userId) {
            // Get all users who are in the current user's contact list
            $subQuery->select('contact_user_id')
                ->from('contacts')
                ->where('user_id', $userId)
                ->where('is_deleted', false)
                ->whereNotNull('contact_user_id');
        })
        ->where(function ($q) use ($userId) {
            // Exclude muted users
            $q->whereNotIn('user_id', function ($subQuery) use ($userId) {
                $subQuery->select('muted_user_id')
                    ->from('status_mutes')
                    ->where('user_id', $userId);
            });
        })
        ->where('expires_at', '>', now()); // Only show non-expired statuses
    }

    /**
     * Boot method to set expires_at automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($status) {
            if (!$status->expires_at) {
                $status->expires_at = now()->addHours(24);
            }
        });
    }
}
