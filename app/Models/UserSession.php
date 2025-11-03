<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'location',
        'is_current',
        'last_activity'
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'is_current' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper method to get device icon
    public function getDeviceIcon()
    {
        return match($this->device_type) {
            'mobile' => '📱',
            'tablet' => '📟',
            'desktop' => '💻',
            default => '🖥️'
        };
    }

    // Helper method to get platform icon
    public function getPlatformIcon()
    {
        return match(strtolower($this->platform)) {
            'windows' => '🪟',
            'macos', 'mac' => '🍎',
            'linux' => '🐧',
            'android' => '🤖',
            'ios' => '📱',
            default => '💻'
        };
    }

    // Check if session is active (within last 30 minutes)
    public function getIsActiveAttribute()
    {
        return $this->last_activity->gt(now()->subMinutes(30));
    }

    // Get session age in human readable format
    public function getLastActivityHumanAttribute()
    {
        return $this->last_activity->diffForHumans();
    }
}