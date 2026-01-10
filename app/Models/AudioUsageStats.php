<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioUsageStats extends Model
{
    protected $table = 'audio_usage_stats';
    
    protected $fillable = [
        'audio_library_id',
        'date',
        'hour',
        'usage_count',
        'unique_users',
        'total_plays',
    ];
    
    protected $casts = [
        'audio_library_id' => 'integer',
        'date' => 'date',
        'hour' => 'integer',
        'usage_count' => 'integer',
        'unique_users' => 'integer',
        'total_plays' => 'integer',
    ];
    
    /**
     * Get the audio library entry
     */
    public function audio(): BelongsTo
    {
        return $this->belongsTo(AudioLibrary::class, 'audio_library_id');
    }
}
