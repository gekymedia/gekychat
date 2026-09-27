<?php

namespace App\Support;

/**
 * Best-effort phone → country label for chat peer info cards.
 */
class PhoneCountry
{
    /** @var array<string, string> dial code => country */
    private const DIAL_CODES = [
        '233' => 'Ghana',
        '234' => 'Nigeria',
        '254' => 'Kenya',
        '27' => 'South Africa',
        '1' => 'United States',
        '44' => 'United Kingdom',
        '91' => 'India',
        '971' => 'United Arab Emirates',
        '966' => 'Saudi Arabia',
        '49' => 'Germany',
        '33' => 'France',
        '39' => 'Italy',
        '34' => 'Spain',
        '31' => 'Netherlands',
        '32' => 'Belgium',
        '41' => 'Switzerland',
        '43' => 'Austria',
        '46' => 'Sweden',
        '47' => 'Norway',
        '45' => 'Denmark',
        '358' => 'Finland',
        '353' => 'Ireland',
        '61' => 'Australia',
        '64' => 'New Zealand',
        '86' => 'China',
        '81' => 'Japan',
        '82' => 'South Korea',
        '65' => 'Singapore',
        '60' => 'Malaysia',
        '62' => 'Indonesia',
        '66' => 'Thailand',
        '84' => 'Vietnam',
        '63' => 'Philippines',
        '55' => 'Brazil',
        '52' => 'Mexico',
        '54' => 'Argentina',
        '20' => 'Egypt',
        '212' => 'Morocco',
        '216' => 'Tunisia',
        '250' => 'Rwanda',
        '256' => 'Uganda',
        '255' => 'Tanzania',
        '251' => 'Ethiopia',
        '237' => 'Cameroon',
        '225' => "Côte d'Ivoire",
        '221' => 'Senegal',
    ];

    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    public static function fromPhone(?string $phone): ?string
    {
        $digits = self::digits($phone);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return 'Ghana';
        }

        $codes = array_keys(self::DIAL_CODES);
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($codes as $code) {
            if (str_starts_with($digits, $code)) {
                return self::DIAL_CODES[$code];
            }
        }

        return null;
    }

    public static function originLabel(?string $phone): string
    {
        $country = self::fromPhone($phone);
        if ($country === null) {
            return __('Phone number');
        }

        return __('Phone number from :country', ['country' => $country]);
    }
}
