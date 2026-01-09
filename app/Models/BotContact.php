<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_number',
        'name',
        'code',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Generate the next bot number
     * Format: 0000000000, 0000000001, 0000000002, etc.
     */
    public static function generateNextBotNumber(): string
    {
        $lastBot = self::orderBy('bot_number', 'desc')->first();
        
        if (!$lastBot) {
            // First bot - use 0000000000 (default GekyBot)
            return '0000000000';
        }
        
        // Extract number from last bot number
        $lastNumber = (int) $lastBot->bot_number;
        $nextNumber = $lastNumber + 1;
        
        // Format as 10-digit string with leading zeros
        return str_pad((string) $nextNumber, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random 6-digit code
     */
    public static function generateCode(): string
    {
        return str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if a phone number is a bot number
     */
    public static function isBotNumber(string $phone): bool
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        return self::where('bot_number', $normalizedPhone)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get bot by phone number
     */
    public static function getByPhone(string $phone): ?self
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        return self::where('bot_number', $normalizedPhone)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Verify bot code
     */
    public function verifyCode(string $code): bool
    {
        return $this->code === $code && $this->is_active;
    }

    /**
     * Get or create the associated User for this bot
     */
    public function getOrCreateUser(): \App\Models\User
    {
        $user = \App\Models\User::where('phone', $this->bot_number)->first();
        
        if (!$user) {
            $user = \App\Models\User::create([
                'phone' => $this->bot_number,
                'name' => $this->name,
                'email' => 'bot_' . $this->bot_number . '@gekychat.com',
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                'phone_verified_at' => now(), // Bots don't need phone verification
            ]);
        } else {
            // Update bot name if it changed
            if ($user->name !== $this->name) {
                $user->update(['name' => $this->name]);
            }
            // Ensure phone is verified
            if (!$user->phone_verified_at) {
                $user->markPhoneAsVerified();
            }
        }
        
        return $user;
    }
}
