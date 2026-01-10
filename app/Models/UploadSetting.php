<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global Upload Settings Model
 * 
 * Stores admin-configurable upload limits for the system.
 * These are default limits that apply to all users unless overridden.
 */
class UploadSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'value' => 'string', // We'll cast based on type when retrieving
    ];

    /**
     * Get a setting value by key with type casting
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        // Cast based on type
        switch ($setting->type) {
            case 'integer':
                return (int) $setting->value;
            case 'float':
                return (float) $setting->value;
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            default:
                return $setting->value;
        }
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'description' => $description,
            ]
        );
    }

    /**
     * Get all settings as key-value pairs
     */
    public static function getAllAsArray(): array
    {
        return static::all()->mapWithKeys(function ($setting) {
            return [$setting->key => static::getValue($setting->key)];
        })->toArray();
    }

    /**
     * Get World Feed max duration (in seconds)
     */
    public static function getWorldFeedMaxDuration(): int
    {
        return static::getValue('world_feed_max_duration', 180);
    }

    /**
     * Get Chat video max size (in bytes)
     */
    public static function getChatVideoMaxSize(): int
    {
        return static::getValue('chat_video_max_size', 10485760); // 10 MB
    }

    /**
     * Get Status max duration (in seconds)
     */
    public static function getStatusMaxDuration(): int
    {
        return static::getValue('status_max_duration', 180);
    }
}
