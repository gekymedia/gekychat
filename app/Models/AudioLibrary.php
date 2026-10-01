<?php

namespace App\Models;

use App\Helpers\UrlHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AudioLibrary extends Model
{
    protected $table = 'audio_library';
    
    protected $fillable = [
        'freesound_id',
        'freesound_username',
        'source',
        'name',
        'description',
        'duration',
        'file_size',
        'preview_url',
        'download_url',
        'local_path',
        'license_type',
        'license_url',
        'license_snapshot',
        'attribution_required',
        'attribution_text',
        'tags',
        'category',
        'usage_count',
        'last_used_at',
        'cached_at',
        'cache_expires_at',
        'is_active',
        'validation_status',
    ];
    
    protected $casts = [
        'duration' => 'decimal:2',
        'file_size' => 'integer',
        'license_snapshot' => 'array',
        'attribution_required' => 'boolean',
        'tags' => 'array',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
        'cached_at' => 'datetime',
        'cache_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Prefer locally hosted file URL when available.
     */
    public function getPreviewUrlAttribute($value): ?string
    {
        $local = $this->attributes['local_path'] ?? null;
        if ($local) {
            return UrlHelper::secureStorageUrl($local, 'public');
        }

        return $value;
    }
    
    /**
     * Get the world feed audio associations
     */
    public function worldFeedAudio(): HasMany
    {
        return $this->hasMany(WorldFeedAudio::class);
    }
    
    /**
     * Get the usage stats
     */
    public function usageStats(): HasMany
    {
        return $this->hasMany(AudioUsageStats::class);
    }
    
    /**
     * Get the license snapshots
     */
    public function licenseSnapshots(): HasMany
    {
        return $this->hasMany(AudioLicenseSnapshot::class);
    }
    
    /**
     * Scope for active audio
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('validation_status', 'approved');
    }
    
    /**
     * Scope for trending audio.
     *
     * Uses a subquery aggregate so MySQL ONLY_FULL_GROUP_BY is satisfied
     * (selecting audio_library.* with GROUP BY id alone is rejected).
     */
    public function scopeTrending($query, int $days = 7)
    {
        $startDate = now()->subDays($days)->toDateString();

        $ranked = DB::table('audio_usage_stats')
            ->select('audio_library_id')
            ->selectRaw('SUM(usage_count) as period_usage')
            ->where('date', '>=', $startDate)
            ->groupBy('audio_library_id');

        return $query->select('audio_library.*')
            ->joinSub($ranked, 'trending', function ($join) {
                $join->on('audio_library.id', '=', 'trending.audio_library_id');
            })
            ->where('audio_library.is_active', true)
            ->where('audio_library.validation_status', 'approved')
            ->orderByDesc('trending.period_usage');
    }
    
    /**
     * Check if audio is safe to use
     */
    public function isSafe(): bool
    {
        return $this->is_active 
            && $this->validation_status === 'approved'
            && in_array($this->license_type, ['Creative Commons 0', 'Attribution']);
    }
    
    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
    
    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }
}
