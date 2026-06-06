<?php

namespace App\Http\Support;

final class GhanaPhoneNormalizer
{
    /**
     * Ghana login/API storage format: 0 + 9 mobile digits (e.g. 0241234567).
     * Accepts national input without trunk 0 when +233 is shown separately.
     */
    public static function normalizeLoginPhone(?string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return '';
        }

        if (str_starts_with($d, '233') && strlen($d) >= 11) {
            $d = substr($d, 3);
        }
        if (str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        if (strlen($d) > 9) {
            $d = substr($d, -9);
        }
        if (strlen($d) !== 9) {
            return '';
        }

        return '0' . $d;
    }
}
