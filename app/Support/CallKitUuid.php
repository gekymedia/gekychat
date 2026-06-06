<?php

namespace App\Support;

/**
 * Stable CallKit UUID v5 for a call session — must match mobile callKitIdForSession().
 */
final class CallKitUuid
{
    private const NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public static function forCallSession(int $sessionId): string
    {
        return self::uuidV5(self::NAMESPACE, "com.gekychat.app.call.session.{$sessionId}");
    }

    private static function uuidV5(string $namespace, string $name): string
    {
        $nstr = self::parseUuid($namespace);
        $hash = sha1($nstr.$name, true);

        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);

        return self::formatUuid($hash);
    }

    private static function parseUuid(string $uuid): string
    {
        $hex = str_replace(['-', '{', '}'], '', $uuid);
        $bin = hex2bin($hex);

        return $bin !== false ? $bin : '';
    }

    private static function formatUuid(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
