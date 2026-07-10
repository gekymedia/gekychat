<?php

namespace App\Support;

/**
 * Compare client version strings like 1.2.3+45 (semver + optional build).
 */
class AppVersionComparator
{
    /**
     * @return int negative if $a < $b, 0 if equal, positive if $a > $b
     */
    public static function compare(string $a, string $b): int
    {
        [$semverA, $buildA] = self::parse($a);
        [$semverB, $buildB] = self::parse($b);

        $semverCmp = self::compareSemver($semverA, $semverB);
        if ($semverCmp !== 0) {
            return $semverCmp;
        }

        return $buildA <=> $buildB;
    }

    public static function isLessThan(string $current, string $target): bool
    {
        return self::compare($current, $target) < 0;
    }

    /**
     * @return array{0: array<int, int>, 1: int}
     */
    public static function parse(string $version): array
    {
        $version = trim($version);
        $build = 0;

        if (str_contains($version, '+')) {
            [$version, $buildPart] = explode('+', $version, 2);
            $build = is_numeric($buildPart) ? (int) $buildPart : 0;
        }

        $parts = array_map('intval', explode('.', $version));
        while (count($parts) < 3) {
            $parts[] = 0;
        }

        return [array_slice($parts, 0, 3), $build];
    }

    /**
     * @param array<int, int> $a
     * @param array<int, int> $b
     */
    protected static function compareSemver(array $a, array $b): int
    {
        for ($i = 0; $i < 3; $i++) {
            $cmp = ($a[$i] ?? 0) <=> ($b[$i] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }
}
