<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Format date for chat message dividers
     * Returns "Today", "Yesterday", or full date like "January 15, 2025"
     * 
     * @param Carbon|string $date
     * @return string
     */
    public static function formatChatDate($date): string
    {
        if (!$date) {
            return '';
        }

        if (!$date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        if ($date->isSameDay($today)) {
            return 'Today';
        } elseif ($date->isSameDay($yesterday)) {
            return 'Yesterday';
        } else {
            return $date->format('F j, Y'); // e.g., "January 15, 2025"
        }
    }

    /**
     * Check if two dates are on different days
     * 
     * @param Carbon|string $date1
     * @param Carbon|string $date2
     * @return bool
     */
    public static function isDifferentDay($date1, $date2): bool
    {
        if (!$date1 || !$date2) {
            return true;
        }

        if (!$date1 instanceof Carbon) {
            $date1 = Carbon::parse($date1);
        }

        if (!$date2 instanceof Carbon) {
            $date2 = Carbon::parse($date2);
        }

        return !$date1->isSameDay($date2);
    }
}
