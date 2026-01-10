<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioLibrary extends Model
{
    protected $table = 'audio_library';
    
    protected $fillable = [
        'freesound_id',
        'freesound_username',
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
     * Scope for trending audio
     */
    public function scopeTrending($query, int $days = 7)
    {
        $startDate = now()->subDays($days)->toDateString();
        
        return $query->select('audio_library.*')
            ->join('audio_usage_stats', 'audio_library.id', '=', 'audio_usage_stats.audio_library_id')
            ->where('audio_usage_stats.date', '>=', $startDate)
            ->where('audio_library.is_active', true)
            ->groupBy('audio_library.id')
            ->orderByRaw('SUM(audio_usage_stats.usage_count) DESC');
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
