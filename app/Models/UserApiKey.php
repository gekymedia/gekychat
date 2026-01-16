<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'client_secret',
        'client_secret_plain',
        'last_used_at',
        'last_used_ip',
        'is_active',
        'webhook_url',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'client_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all messages sent using this API key
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'user_api_key_id');
    }

    /**
     * Generate a new client secret
     */
    public static function generateSecret(): string
    {
        return 'sk_' . Str::random(48);
    }

    /**
     * Create a new API key for a user
     */
    public static function createForUser(int $userId, string $name): self
    {
        $plainSecret = self::generateSecret();
        
        return self::create([
            'user_id' => $userId,
            'name' => $name,
            'client_secret' => Hash::make($plainSecret),
            'client_secret_plain' => $plainSecret, // Store plain text temporarily
            'is_active' => true,
        ]);
    }

    /**
     * Verify a client secret
     */
    public function verifySecret(string $plainSecret): bool
    {
        return Hash::check($plainSecret, $this->client_secret);
    }

    /**
     * Record usage
     */
    public function recordUsage(?string $ip = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }
}
