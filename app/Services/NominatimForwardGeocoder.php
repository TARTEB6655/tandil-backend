<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forward geocoding via OpenStreetMap Nominatim (no API key).
 * Used when clients send multilingual typed addresses without map coordinates.
 */
final class NominatimForwardGeocoder
{
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

        $base = rtrim((string) config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');
        $timeout = (int) config('services.nominatim.timeout', 10);
        $configuredUa = config('services.nominatim.user_agent');
        $userAgent = is_string($configuredUa) && trim($configuredUa) !== ''
            ? trim($configuredUa)
            : (config('app.name', 'Tandil') . ' Backend (' . (config('app.url') ?: 'https://example.com') . ')');

        try {
            $response = Http::timeout($timeout)
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
            if (! is_array($row)) {
                return null;
            }

            if (! isset($row['lat'], $row['lon'])) {
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
