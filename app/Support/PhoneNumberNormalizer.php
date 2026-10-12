<?php

namespace App\Support;

final class PhoneNumberNormalizer
{
    /**
     * Normalize to digits-only international form (UAE default when local 0-prefix).
     * Example: +971 50 123 4567 → 971501234567
     */
    public static function digitsKey(?string $phone, string $defaultCountry = 'AE'): ?string
    {
        if ($phone === null) {
            return null;
        }
        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        if ($defaultCountry === 'AE') {
            if (str_starts_with($digits, '971')) {
                return $digits;
            }
            if (str_starts_with($digits, '0')) {
                return '971'.ltrim($digits, '0');
            }
            if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
                return '971'.$digits;
            }
        }

        return ltrim($digits, '0') !== '' ? $digits : null;
    }

    /**
     * @return list<string> DB lookup variants for a stored phone value.
     */
    public static function lookupVariants(?string $phone, string $defaultCountry = 'AE'): array
    {
        $key = self::digitsKey($phone, $defaultCountry);
        if ($key === null) {
            return [];
        }

        $variants = [
            $key,
            '+'.$key,
            '00'.$key,
        ];

        if (str_starts_with($key, '971')) {
            $local = '0'.substr($key, 3);
            $variants[] = $local;
            $variants[] = substr($key, 3);
        }

        return array_values(array_unique($variants));
    }

    public static function maskForDisplay(?string $phone): string
    {
        $key = self::digitsKey($phone);
        if ($key === null || strlen($key) < 4) {
            return '*** *** ****';
        }
        $last4 = substr($key, -4);

        return '*** *** '.$last4;
    }
}
