<?php

namespace App\Support;

class Cursor {
    public static function encode(\DateTimeInterface $ts, int $id): string {
        return base64_encode($ts->format('c') . '|' . $id);
    }
    public static function decode(?string $cursor): ?array {
        if (!$cursor) return null;
        $decoded = base64_decode($cursor, true);
        if ($decoded === false) return null;
        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2) return null;
        return [new \DateTimeImmutable($parts[0]), (int) $parts[1]];
    }
}
