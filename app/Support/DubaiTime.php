<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Tandil operates in UAE — report scheduling uses Asia/Dubai wall-clock time.
 */
final class DubaiTime
{
    public const TZ = 'Asia/Dubai';

    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /**
     * Parse client/API datetime as Dubai local for storage.
     * - Values with Z/offset are converted into Asia/Dubai.
     * - Naive values (datetime-local / "Y-m-d H:i:s") are treated as Dubai already.
     */
    public static function parse(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone(self::TZ);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return self::now();
        }

        // ISO with timezone / Z → convert to Dubai wall clock.
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1) {
            return Carbon::parse($raw)->timezone(self::TZ);
        }

        // datetime-local often sends "2026-09-11T18:29" — treat as Dubai.
        $normalized = str_replace('T', ' ', $raw);

        return Carbon::parse($normalized, self::TZ);
    }

    /** Store-friendly string for MySQL datetime columns (Dubai local, no offset). */
    public static function toStorage(mixed $value): string
    {
        return self::parse($value)->format('Y-m-d H:i:s');
    }
}
