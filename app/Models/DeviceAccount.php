<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 2: Device Account Model
 * 
 * Tracks multiple user accounts on a single device (mobile/desktop only).
 */
class DeviceAccount extends Model
{
    protected $fillable = [
        'device_id',
        'device_type',
        'user_id',
        'account_label',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set this account as active and deactivate others on the same device
     */
    public function activate(): void
    {
        // Deactivate all accounts on this device
        static::where('device_id', $this->device_id)
            ->where('device_type', $this->device_type)
            ->update(['is_active' => false]);

        // Activate this account
        $this->update([
            'is_active' => true,
            'last_used_at' => now(),
        ]);
    }
}


