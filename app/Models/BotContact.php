<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotContact extends Model
{
    use HasFactory;

    // Bot types
    public const TYPE_GENERAL = 'general';
    public const TYPE_ADMISSIONS = 'admissions';
    public const TYPE_TASKS = 'tasks';

    protected $fillable = [
        'bot_number',
        'name',
        'code',
        'is_active',
        'auto_add_to_contacts',
        'bot_type',
        'description',
        'avatar_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_add_to_contacts' => 'boolean',
    ];

    /**
     * Generate the next bot number
     * Format: 0000000000, 0000000001, 0000000002, etc.
     */
    public static function generateNextBotNumber(): string
    {
        $lastBot = self::orderBy('bot_number', 'desc')->first();
        
        if (!$lastBot) {
            return '0000000000';
        }
        
        $lastNumber = (int) $lastBot->bot_number;
        $nextNumber = $lastNumber + 1;
        
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
     * Get bot by user ID
     */
    public static function getByUserId(int $userId): ?self
    {
        $user = User::find($userId);
        if (!$user || !$user->phone) {
            return null;
        }
        return self::getByPhone($user->phone);
    }

    /**
     * Get all bots that should be auto-added to new users
     */
    public static function getAutoAddBots(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where('auto_add_to_contacts', true)
            ->get();
    }

    /**
     * Get all active bots (for discovery/search)
     */
    public static function getAllActiveBots(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Verify bot code
     */
    public function verifyCode(string $code): bool
    {
        return $this->code === $code && $this->is_active;
    }

    /**
     * Check if this is the general GekyChat AI bot
     */
    public function isGeneralBot(): bool
    {
        return $this->bot_type === self::TYPE_GENERAL;
    }

    /**
     * Check if this is the admissions bot
     */
    public function isAdmissionsBot(): bool
    {
        return $this->bot_type === self::TYPE_ADMISSIONS;
    }

    /**
     * Check if this is the tasks bot
     */
    public function isTasksBot(): bool
    {
        return $this->bot_type === self::TYPE_TASKS;
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
                'phone_verified_at' => now(),
                'username' => \App\Models\User::generateUniqueUsername(),
                'about' => $this->description,
            ]);
            
            // Set avatar if specified
            if ($this->avatar_path) {
                $user->update(['avatar_path' => $this->avatar_path]);
            }
        } else {
            // Update bot user if name or description changed
            $updates = [];
            if ($user->name !== $this->name) {
                $updates['name'] = $this->name;
            }
            if ($this->description && $user->about !== $this->description) {
                $updates['about'] = $this->description;
            }
            if ($this->avatar_path && $user->avatar_path !== $this->avatar_path) {
                $updates['avatar_path'] = $this->avatar_path;
            }
            if (!empty($updates)) {
                $user->update($updates);
            }
            
            // Ensure phone is verified
            if (!$user->phone_verified_at) {
                $user->markPhoneAsVerified();
            }
            // Ensure username exists
            if (empty($user->username)) {
                $user->ensureUsername();
            }
        }
        
        return $user;
    }

    /**
     * Get the User model for this bot
     */
    public function user(): ?\App\Models\User
    {
        return \App\Models\User::where('phone', $this->bot_number)->first();
    }
}
