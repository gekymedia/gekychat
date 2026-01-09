<?php

namespace App\Helpers;

class AvatarHelper
{
    /**
     * List of colors for avatar placeholders
     */
    private static array $colors = [
        '#EF5350', // Red
        '#42A5F5', // Blue
        '#66BB6A', // Green
        '#FFA726', // Orange
        '#AB47BC', // Purple
        '#EC407A', // Pink
        '#5C6BC0', // Indigo
        '#26A69A', // Teal
        '#29B6F6', // Cyan
        '#9CCC65', // Lime
        '#FFCA28', // Amber
        '#FF7043', // Deep Orange
        '#8D6E63', // Brown
        '#78909C', // Blue Grey
        '#7E57C2', // Deep Purple
        '#00ACC1', // Cyan (darker)
    ];

    /**
     * Get a consistent color for a given name
     */
    public static function getColorForName(string $name): string
    {
        if (empty($name)) {
            return self::$colors[0];
        }

        // Use hash to get consistent color for same name
        $hash = crc32($name);
        $index = abs($hash) % count(self::$colors);
        return self::$colors[$index];
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
