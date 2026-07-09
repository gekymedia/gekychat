<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnalyticsEvent extends Model
{
    protected $fillable = [
        'session_uuid',
        'user_id',
        'event_name',
        'feature_key',
        'action_key',
        'properties',
        'platform',
        'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
