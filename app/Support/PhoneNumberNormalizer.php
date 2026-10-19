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
        return self::internationalDigits($phone, $defaultCountry);
    }

    /**
     * E.164 for SMS gateways (Twilio Verify): +971501234567, +919816025872
     */
    public static function toE164(?string $phone, string $defaultCountry = 'AE'): ?string
    {
        $digits = self::internationalDigits($phone, $defaultCountry);
        if ($digits === null) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * Canonical value to store on users.phone (E.164).
     */
    public static function formatForStorage(?string $phone, string $defaultCountry = 'AE'): ?string
    {
        return self::toE164($phone, $defaultCountry);
    }

    /**
     * @return list<string> DB lookup variants for a stored phone value.
     */
    public static function lookupVariants(?string $phone, string $defaultCountry = 'AE'): array
    {
        $key = self::internationalDigits($phone, $defaultCountry);
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

        if (str_starts_with($key, '91') && strlen($key) === 12) {
            $localIn = substr($key, 2);
            $variants[] = $localIn;
            $variants[] = '0'.$localIn;
        }

        if (strlen($key) === 10 && preg_match('/^[6-9]\d{9}$/', $key)) {
            $variants[] = '91'.$key;
            $variants[] = '+91'.$key;
        }

        return array_values(array_unique($variants));
    }

    public static function maskForDisplay(?string $phone): string
    {
        $key = self::internationalDigits($phone);
        if ($key === null || strlen($key) < 4) {
            return '*** *** ****';
        }
        $last4 = substr($key, -4);

        return '*** *** '.$last4;
    }

    /**
     * Digits-only international number (no leading +).
     */
    public static function internationalDigits(?string $phone, string $defaultCountry = 'AE'): ?string
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

        if (str_starts_with($digits, '971') && strlen($digits) >= 12) {
            return $digits;
        }

        if (str_starts_with($digits, '91') && strlen($digits) === 12 && preg_match('/^91[6-9]\d{9}$/', $digits)) {
            return $digits;
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

        // India mobile without country code (10 digits starting 6–9).
        if (strlen($digits) === 10 && preg_match('/^[6-9]\d{9}$/', $digits)) {
            return '91'.$digits;
        }

        return ltrim($digits, '0') !== '' ? $digits : null;
    }
}
