<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'expires_at',
        'verified',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];

    /**
     * Generate a new OTP code
     */
    public static function generate(string $phone, int $digits = 6, int $ttlMinutes = 5): string
    {
        $max = (10 ** $digits) - 1;
        $code = str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);

        self::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $code;
    }

    /**
     * Verify OTP code
     */
    public static function verify(string $phone, string $code): bool
    {
        $otp = self::where('phone', $phone)
            ->where('code', $code)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($otp) {
            $otp->update(['verified' => true]);
            return true;
        }

        return false;
    }

    /**
     * Clean up old OTP codes
     */
    public static function cleanExpired(): int
    {
        return self::where('expires_at', '<', now()->subDay())->delete();
    }

    /**
     * Check rate limiting for OTP requests
     */
    public static function canRequest(string $phone, int $hourlyLimit = 3): bool
    {
        $count = self::where('phone', $phone)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return $count < $hourlyLimit;
    }
}

