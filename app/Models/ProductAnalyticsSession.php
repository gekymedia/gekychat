<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAnalyticsSession extends Model
{
    protected $fillable = [
        'session_uuid',
        'user_id',
        'platform',
        'app_version',
        'device_type',
        'os_version',
        'locale',
        'started_at',
        'ended_at',
        'last_heartbeat_at',
        'duration_seconds',
        'screen_views_count',
        'events_count',
        'is_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductAnalyticsEvent::class, 'session_uuid', 'session_uuid');
    }
}
