<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forward geocoding via OpenStreetMap Nominatim (no API key).
 * Used when clients send multilingual typed addresses without map coordinates.
 *
 * Results are cached; failures are cached briefly so slow/unreachable Nominatim
 * does not block checkout on every retry.
 */
final class NominatimForwardGeocoder
{
    private const CACHE_HIT_TTL_SECONDS = 86400; // 24h

    private const CACHE_MISS_TTL_SECONDS = 300; // 5m — avoid hammering a down/slow service

    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function firstLatLngFromAddressLines(
    string $street,
    string $city,
    string $state,
    string $zip,
    string $country,
    ): ?array {
        if (! filter_var(config('services.nominatim.forward_geocode_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $parts = [];
        foreach ([$street, $city, $state, $zip, $country] as $segment) {
            $t = trim($segment);
            if ($t !== '') {
                $parts[] = $t;
            }
        }

        if ($parts === []) {
            return null;
        }

        $q = implode(', ', $parts);
        if (mb_strlen($q) < 2) {
            return null;
        }

        $cacheKey = 'nominatim:forward:' . md5(mb_strtolower($q, 'UTF-8'));

        $cached = Cache::get($cacheKey);
        if ($cached === 'miss') {
            return null;
        }
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        $result = self::fetchLatLng($q);

        if ($result !== null) {
            Cache::put($cacheKey, $result, self::CACHE_HIT_TTL_SECONDS);
        } else {
            Cache::put($cacheKey, 'miss', self::CACHE_MISS_TTL_SECONDS);
        }

        return $result;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function fetchLatLng(string $q): ?array
    {
        $base = rtrim((string) config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');
        $timeout = max(1, (int) config('services.nominatim.timeout', 4));
        $connectTimeout = max(1, (int) config('services.nominatim.connect_timeout', 2));
        $configuredUa = config('services.nominatim.user_agent');
        $userAgent = is_string($configuredUa) && trim($configuredUa) !== ''
            ? trim($configuredUa)
            : (config('app.name', 'Tandil') . ' Backend (' . (config('app.url') ?: 'https://example.com') . ')');

        try {
            $response = Http::connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'application/json',
                ])
                ->get($base . '/search', [
                    'format' => 'jsonv2',
                    'q' => $q,
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('Nominatim forward geocode HTTP error.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $rows = $response->json();
            if (! is_array($rows) || $rows === []) {
                return null;
            }

            $row = $rows[0];
            if (! is_array($row) || ! isset($row['lat'], $row['lon'])) {
                return null;
            }

            $lat = (float) $row['lat'];
            $lng = (float) $row['lon'];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return null;
            }

            return ['lat' => $lat, 'lng' => $lng];
        } catch (\Throwable $e) {
            Log::warning('Nominatim forward geocode failed: ' . $e->getMessage());

            return null;
        }
    }
}
