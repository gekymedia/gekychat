<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $setting = static::firstOrNew(['key' => $key]);
        
        $setting->value = is_array($value) ? json_encode($value) : (string) $value;
        $setting->type = $type;
        
        if ($description) {
            $setting->description = $description;
        }
        
        $setting->save();
    }

    /**
     * Check if LLM is enabled
     */
    public static function isLlmEnabled(): bool
    {
        return static::get('use_llm', false);
    }

    /**
     * Get LLM provider
     */
    public static function getLlmProvider(): string
    {
        return static::get('llm_provider', 'ollama');
    }

    /**
     * Get Ollama configuration
     */
    public static function getOllamaConfig(): array
    {
        return [
            'api_url' => static::get('ollama_api_url', 'http://localhost:11434'),
            'model' => static::get('ollama_model', 'llama3.2'),
            'temperature' => (float) static::get('llm_temperature', '0.7'),
            'max_tokens' => (int) static::get('llm_max_tokens', '500'),
        ];
    }
}
