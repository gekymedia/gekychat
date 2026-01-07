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
        'allow_download', // PHASE 1: Download permission flag
    ];

    protected $casts = [
        'font_size' => 'integer',
        'duration' => 'integer',
        'view_count' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'allow_download' => 'boolean', // PHASE 1: Cast allow_download to boolean
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
     * 
     * PHASE 1: Enhanced to respect StatusPrivacySetting rules
     */
    public function scopeVisibleTo($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            // User can always see their own statuses
            $q->where('user_id', $userId)
              // Or status owner's privacy allows this user to see
              ->orWhere(function ($subQ) use ($userId) {
                  // This subquery will be filtered by canViewStatus method in controller
                  // For now, keep contact-based filtering as fallback
                  $subQ->whereIn('user_id', function ($contactSub) use ($userId) {
                      $contactSub->select('contact_user_id')
                          ->from('contacts')
                          ->where('user_id', $userId)
                          ->where('is_deleted', false)
                          ->whereNotNull('contact_user_id');
                  });
              });
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
     * PHASE 1: Check if a user can view this status based on privacy settings
     * 
     * @param int $viewerId
     * @return bool
     */
    public function canBeViewedBy(int $viewerId): bool
    {
        // Owner can always view
        if ($this->user_id === $viewerId) {
            return true;
        }
        
        // Get privacy settings for status owner
        $privacySettings = StatusPrivacySetting::where('user_id', $this->user_id)->first();
        
        if (!$privacySettings) {
            // Default: contacts only (backward compatibility)
            return Contact::where('user_id', $this->user_id)
                ->where('contact_user_id', $viewerId)
                ->where('is_deleted', false)
                ->exists();
        }
        
        // Use privacy settings to determine visibility
        return $privacySettings->canView($viewerId, $this->user_id);
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
