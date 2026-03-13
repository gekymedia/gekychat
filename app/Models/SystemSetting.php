<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Cache duration in seconds (1 hour)
     */
    const CACHE_TTL = 3600;

    /**
     * Get a setting value by key (alias for getValue for compatibility)
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getValue($key, $default);
    }

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "system_setting:{$key}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }
            
            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, mixed $value, ?string $type = null, ?string $group = null): bool
    {
        $setting = self::firstOrNew(['key' => $key]);
        
        $setting->value = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
        
        if ($type) {
            $setting->type = $type;
        }
        
        if ($group) {
            $setting->group = $group;
        }
        
        $result = $setting->save();
        
        // Clear cache
        Cache::forget("system_setting:{$key}");
        Cache::forget("system_settings_group:{$setting->group}");
        
        return $result;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = "system_settings_group:{$group}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            $settings = self::where('group', $group)->get();
            
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->key] = [
                    'value' => self::castValue($setting->value, $setting->type),
                    'type' => $setting->type,
                    'description' => $setting->description,
                ];
            }
            
            return $result;
        });
    }

    /**
     * Cast value to appropriate type
     */
    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Bulk update settings (creates or updates each key).
     * For priority_bank_api_token: do not pass in $settings if you want to preserve existing; caller should omit or preserve.
     */
    public static function bulkUpdate(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $type = match (true) {
                is_int($value) => 'integer',
                is_bool($value) => 'boolean',
                is_array($value) || is_object($value) => 'json',
                is_float($value) => 'float',
                default => 'string',
            };
            $storedValue = match ($type) {
                'json' => json_encode($value),
                'boolean' => $value ? '1' : '0',
                default => (string) $value,
            };
            $group = str_starts_with($key, 'priority_bank_') ? 'priority_bank' : 'general';
            self::setValue($key, $storedValue, $type, $group);
        }
    }

    /**
     * Clear all cached settings
     */
    public static function clearCache(): void
    {
        $settings = self::all();

        foreach ($settings as $setting) {
            Cache::forget("system_setting:{$setting->key}");
        }

        $groups = self::distinct()->pluck('group');
        foreach ($groups as $group) {
            Cache::forget("system_settings_group:{$group}");
        }
    }
}
