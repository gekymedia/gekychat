<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldFeedAudio extends Model
{
    protected $table = 'world_feed_audio';
    
    public $timestamps = false;
    
    protected $fillable = [
        'world_feed_post_id',
        'audio_library_id',
        'volume_level',
        'audio_start_time',
        'loop_audio',
        'fade_in_duration',
        'fade_out_duration',
        'license_snapshot',
        'attribution_displayed',
        'attached_by',
        'attached_at',
    ];
    
    protected $casts = [
        'world_feed_post_id' => 'integer',
        'audio_library_id' => 'integer',
        'volume_level' => 'integer',
        'audio_start_time' => 'decimal:2',
        'loop_audio' => 'boolean',
        'fade_in_duration' => 'decimal:2',
        'fade_out_duration' => 'decimal:2',
        'license_snapshot' => 'array',
        'attached_by' => 'integer',
        'attached_at' => 'datetime',
    ];
    
    /**
     * Get the world feed post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'world_feed_post_id');
    }
    
    /**
     * Get the audio library entry
     */
    public function audio(): BelongsTo
    {
        return $this->belongsTo(AudioLibrary::class, 'audio_library_id');
    }
    
    /**
     * Get the user who attached the audio
     */
    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by');
    }
}
