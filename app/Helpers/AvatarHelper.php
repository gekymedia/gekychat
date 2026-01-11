<?php

namespace App\Helpers;

class AvatarHelper
{
    /**
     * Gradient pairs: [light, dark] for 3D effect similar to Telegram
     */
    private static array $gradientPairs = [
        ['#EF5350', '#C62828'], // Red
        ['#42A5F5', '#1565C0'], // Blue
        ['#66BB6A', '#2E7D32'], // Green
        ['#FFA726', '#E65100'], // Orange
        ['#AB47BC', '#6A1B9A'], // Purple
        ['#EC407A', '#AD1457'], // Pink
        ['#5C6BC0', '#283593'], // Indigo
        ['#26A69A', '#00695C'], // Teal
        ['#29B6F6', '#0277BD'], // Light Blue
        ['#9CCC65', '#558B2F'], // Light Green
        ['#FFCA28', '#F57F17'], // Yellow
        ['#FF7043', '#D84315'], // Deep Orange
        ['#8D6E63', '#5D4037'], // Brown
        ['#78909C', '#455A64'], // Blue Grey
        ['#7E57C2', '#4527A0'], // Deep Purple
        ['#00ACC1', '#00838F']  // Cyan
    ];

    /**
     * Get a consistent gradient for a given name (for inline styles)
     */
    public static function getColorForName(string $name): string
    {
        if (empty(trim($name))) {
            [$light, $dark] = self::$gradientPairs[0];
            return "linear-gradient(135deg, {$light} 0%, {$dark} 100%)";
        }

        // Use hash to get consistent gradient for same name
        $hash = crc32(strtolower(trim($name)));
        $index = abs($hash) % count(self::$gradientPairs);
        [$light, $dark] = self::$gradientPairs[$index];
        return "linear-gradient(135deg, {$light} 0%, {$dark} 100%)";
    }

    /**
     * Get initials from a name
     */
    public static function getInitials(string $name): string
    {
        if (empty(trim($name))) {
            return '?';
        }

        $parts = array_filter(explode(' ', trim($name)));
        if (count($parts) >= 2) {
            // First letter of first name + first letter of last name
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        } elseif (count($parts) === 1) {
            $first = reset($parts);
            if (strlen($first) >= 2) {
                return strtoupper(substr($first, 0, 2));
            }
            return strtoupper(substr($first, 0, 1));
        }

        return '?';
    }
}
