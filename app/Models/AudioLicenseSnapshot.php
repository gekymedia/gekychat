<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioLicenseSnapshot extends Model
{
    protected $table = 'audio_license_snapshots';
    
    public $timestamps = false;
    
    protected $fillable = [
        'audio_library_id',
        'world_feed_post_id',
        'license_type',
        'license_url',
        'license_full_text',
        'freesound_metadata',
        'validated_at',
        'validated_by',
        'validation_notes',
        'is_compliant',
        'compliance_issues',
    ];
    
    protected $casts = [
        'audio_library_id' => 'integer',
        'world_feed_post_id' => 'integer',
        'freesound_metadata' => 'array',
        'validated_at' => 'datetime',
        'is_compliant' => 'boolean',
        'compliance_issues' => 'array',
    ];
    
    /**
     * Get the audio library entry
     */
    public function audio(): BelongsTo
    {
        return $this->belongsTo(AudioLibrary::class, 'audio_library_id');
    }
    
    /**
     * Get the world feed post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'world_feed_post_id');
    }
}
