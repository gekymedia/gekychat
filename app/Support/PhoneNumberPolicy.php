<?php

namespace App\Support;

use App\Http\Support\GhanaPhoneNormalizer;
use App\Models\BotContact;
use App\Models\OtpCode;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Central gate for OTP/SMS: real Ghana mobiles only, plus admin bot whitelist.
 */
final class PhoneNumberPolicy
{
    public const CODE_INVALID = 'invalid_phone';
    public const CODE_SUSPICIOUS = 'suspicious_phone';
    public const CODE_RATE_LIMIT = 'otp_rate_limited';

    /**
     * @return array{
     *   ok: bool,
     *   phone: ?string,
     *   is_bot: bool,
     *   bot: ?BotContact,
     *   is_test: bool,
     *   test_otp: ?string,
     *   message: ?string,
     *   code: ?string
     * }
     */
    public static function evaluateForOtp(string $raw): array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        $bot = BotContact::getByPhone($digits);
        if (! $bot) {
            $normalizedProbe = GhanaPhoneNormalizer::normalizeLoginPhone($raw);
            if ($normalizedProbe !== '') {
                $bot = BotContact::getByPhone($normalizedProbe);
            }
        }

        if ($bot) {
            return [
                'ok' => true,
                'phone' => $bot->bot_number,
                'is_bot' => true,
                'bot' => $bot,
                'is_test' => false,
                'test_otp' => null,
                'message' => null,
                'code' => null,
            ];
        }

        $testNumbers = config('phone.test_numbers', []);
        if (isset($testNumbers[$digits])) {
            if ($testOtp = self::testOtpIfAllowed($digits)) {
                return [
                    'ok' => true,
                    'phone' => $digits,
                    'is_bot' => false,
                    'bot' => null,
                    'is_test' => true,
                    'test_otp' => $testOtp,
                    'message' => null,
                    'code' => null,
                ];
            }

            return self::reject(self::CODE_INVALID, 'Please enter a valid mobile number.');
        }

        $phone = GhanaPhoneNormalizer::normalizeLoginPhone($raw);
        if ($phone === '') {
            return self::reject(self::CODE_INVALID, 'Please enter a valid mobile number.');
        }

        if (self::isBlockedPlaceholder($phone)) {
            return self::reject(self::CODE_INVALID, 'Please enter a valid mobile number.');
        }

        if (! self::isGhanaMobilePrefix($phone)) {
            return self::reject(self::CODE_INVALID, 'Please enter a valid mobile number.');
        }

        if (self::isSuspiciousPattern($phone)) {
            return self::reject(self::CODE_SUSPICIOUS, 'Please enter a valid mobile number.');
        }

        return [
            'ok' => true,
            'phone' => $phone,
            'is_bot' => false,
            'bot' => null,
            'is_test' => false,
            'test_otp' => null,
            'message' => null,
            'code' => null,
        ];
    }

    /**
     * @return array{message: string, code: string}|null Null when within limits.
     */
    public static function checkOtpRateLimits(string $phone, ?string $ip = null): ?array
    {
        $hourlyPhone = (int) config('phone.otp_hourly_limit_per_phone', 3);
        if (! OtpCode::canRequest($phone, $hourlyPhone)) {
            return [
                'message' => 'Too many verification requests for this number. Please try again later.',
                'code' => self::CODE_RATE_LIMIT,
            ];
        }

        if ($ip !== null && $ip !== '') {
            $ipKey = 'otp_ip:'.sha1($ip);
            $ipLimit = (int) config('phone.otp_hourly_limit_per_ip', 10);
            if (RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
                return [
                    'message' => 'Too many verification requests. Please try again later.',
                    'code' => self::CODE_RATE_LIMIT,
                ];
            }
        }

        return null;
    }

    public static function recordOtpRateLimitHit(?string $ip): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $ipKey = 'otp_ip:'.sha1($ip);
        RateLimiter::hit($ipKey, 3600);
    }

    public static function isGhanaMobilePrefix(string $phone): bool
    {
        if (! preg_match('/^0\d{9}$/', $phone)) {
            return false;
        }

        $prefix = substr($phone, 0, 3);

        return in_array($prefix, config('phone.ghana_mobile_prefixes', []), true);
    }

    public static function isSuspiciousPattern(string $phone): bool
    {
        if (! preg_match('/^0(\d{9})$/', $phone, $m)) {
            return true;
        }

        $national = $m[1];

        // 0000000001-style ranges are reserved for admin bots only.
        if (preg_match('/^0{7,}/', $phone)) {
            return true;
        }

        // All identical digits (0244444444, 0200000000, …).
        if (preg_match('/^(\d)\1{8}$/', $national)) {
            return true;
        }

        // Obvious sequences (012345678, 987654321, …).
        if (self::isSequentialDigits($national)) {
            return true;
        }

        // Highly repetitive (0101010101, 0202020202, …).
        if (preg_match('/^(\d{2})\1{3}\d?$/', $national)) {
            return true;
        }

        return false;
    }

    private static function isBlockedPlaceholder(string $phone): bool
    {
        return str_starts_with($phone, '_legacy_')
            || str_starts_with($phone, '_unregistered_');
    }

    private static function testOtpIfAllowed(string $phone): ?string
    {
        $testNumbers = config('phone.test_numbers', []);
        if (! isset($testNumbers[$phone])) {
            return null;
        }

        $allowed = config('phone.allow_test_numbers', false)
            || app()->environment(['local', 'testing']);

        if (! $allowed) {
            return null;
        }

        return (string) $testNumbers[$phone];
    }

    private static function isSequentialDigits(string $nineDigits): bool
    {
        $asc = true;
        $desc = true;
        for ($i = 1; $i < strlen($nineDigits); $i++) {
            $prev = (int) $nineDigits[$i - 1];
            $cur = (int) $nineDigits[$i];
            if ($cur !== ($prev + 1) % 10) {
                $asc = false;
            }
            if ($cur !== ($prev + 9) % 10) {
                $desc = false;
            }
        }

        return $asc || $desc;
    }

    /**
     * @return array{ok: false, phone: null, is_bot: false, bot: null, is_test: false, test_otp: null, message: string, code: string}
     */
    private static function reject(string $code, string $message): array
    {
        return [
            'ok' => false,
            'phone' => null,
            'is_bot' => false,
            'bot' => null,
            'is_test' => false,
            'test_otp' => null,
            'message' => $message,
            'code' => $code,
        ];
    }
}
