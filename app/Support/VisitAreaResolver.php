<?php

namespace App\Support;

use App\Models\Area;
use App\Services\NominatimForwardGeocoder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Resolves an operational {@see Area} + supervisor from visit/location payloads.
 *
 * Resolution order: optional area_id → GPS when latitude/longitude are sent
 * → substring match on area text (often English) → optional OpenStreetMap Nominatim forward geocode
 * of the typed address (any script) then GPS again. Operational areas need centre coordinates + radius in DB.
 */
final class VisitAreaResolver
{
    public static function fromRequest(Request $request): ?array
    {
        return self::resolve([
            'area_id' => $request->input('area_id'),
            'country' => $request->input('country', 'UAE'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'street_address' => $request->input('street_address'),
            'zip_code' => $request->input('zip_code'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{area: Area, supervisor_id: int, distance_km: float|null}|null
     */
    public static function resolve(array $payload): ?array
    {
        if (! empty($payload['area_id'])) {
            $area = Area::with('supervisors')
                ->where('is_active', true)
                ->find((int) $payload['area_id']);
            if ($area && $area->supervisors->isNotEmpty()) {
                return [
                    'area' => $area,
                    'supervisor_id' => (int) $area->supervisors->first()->id,
                    'distance_km' => null,
                ];
            }
        }

        $country = self::normalizeLocaleText((string) ($payload['country'] ?? 'UAE'));
        $city = self::normalizeLocaleText((string) ($payload['city'] ?? ''));
        $state = self::normalizeLocaleText((string) ($payload['state'] ?? ''));
        $streetAddress = self::normalizeLocaleText((string) ($payload['street_address'] ?? ''));
        $zipCode = self::normalizeLocaleText((string) ($payload['zip_code'] ?? ''));

        $lat = self::nullableCoordinate($payload['latitude'] ?? null);
        $lng = self::nullableCoordinate($payload['longitude'] ?? null);

        $areas = Area::with('supervisors')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Area $a) => $a->supervisors->isNotEmpty())
            ->values();

        if ($areas->isEmpty()) {
            return null;
        }

        $normalizedCountry = self::normalizeCountry($country);
        if ($normalizedCountry !== '') {
            $countryMatched = $areas->filter(function (Area $area) use ($normalizedCountry) {
                return self::normalizeCountry((string) ($area->country ?? '')) === $normalizedCountry;
            })->values();
            if ($countryMatched->isNotEmpty()) {
                $areas = $countryMatched;
            }
        }

        if ($lat !== null && $lng !== null) {
            $gps = self::resolveClosestByGps($areas, $lat, $lng);
            if ($gps !== null) {
                return $gps;
            }
        }

        $nameMatched = self::filterAreasByAddressText($areas, $city, $state, $streetAddress, $zipCode);

        if ($nameMatched->isNotEmpty()) {
            $selected = $nameMatched->first();

            return [
                'area' => $selected,
                'supervisor_id' => (int) $selected->supervisors->first()->id,
                'distance_km' => null,
            ];
        }

        if ($lat === null || $lng === null) {
            $geo = NominatimForwardGeocoder::firstLatLngFromAddressLines(
                (string) ($payload['street_address'] ?? ''),
                (string) ($payload['city'] ?? ''),
                (string) ($payload['state'] ?? ''),
                (string) ($payload['zip_code'] ?? ''),
                (string) ($payload['country'] ?? ''),
            );
            if ($geo !== null) {
                $gps = self::resolveClosestByGps($areas, $geo['lat'], $geo['lng']);
                if ($gps !== null) {
                    return $gps;
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Area>  $areas
     * @return Collection<int, Area>
     */
    private static function filterAreasByAddressText(
        Collection $areas,
        string $city,
        string $state,
        string $streetAddress,
        string $zipCode
    ): Collection {
        return $areas->filter(function (Area $area) use ($city, $state, $streetAddress, $zipCode) {
            if ($city === '' && $state === '' && $streetAddress === '' && $zipCode === '') {
                return false;
            }
            $hay = self::normalizeLocaleText(trim((string) ($area->name . ' ' . ($area->location ?? '') . ' ' . ($area->description ?? ''))));

            return ($city !== '' && str_contains($hay, $city))
                || ($state !== '' && str_contains($hay, $state))
                || ($streetAddress !== '' && str_contains($hay, $streetAddress))
                || ($zipCode !== '' && str_contains($hay, $zipCode));
        })->sortBy('priority')->values();
    }

    private static function nullableCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  Collection<int, Area>  $areas
     * @return array{area: Area, supervisor_id: int, distance_km: float}|null
     */
    private static function resolveClosestByGps(Collection $areas, float $lat, float $lng): ?array
    {
        $closest = null;
        foreach ($areas as $area) {
            if ($area->latitude === null || $area->longitude === null) {
                continue;
            }

            $distance = self::distanceKm($lat, $lng, (float) $area->latitude, (float) $area->longitude);
            $radius = max(0.1, (float) ($area->service_radius_km ?? 30));
            if ($distance > $radius) {
                continue;
            }

            if ($closest === null
                || $distance < $closest['distance_km']
                || ($distance === $closest['distance_km'] && (int) $area->priority < (int) $closest['area']->priority)
            ) {
                $closest = [
                    'area' => $area,
                    'supervisor_id' => (int) $area->supervisors->first()->id,
                    'distance_km' => round($distance, 2),
                ];
            }
        }

        return $closest;
    }

    private static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private static function normalizeLocaleText(string $s): string
    {
        $t = trim($s);
        if ($t === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($t, 'UTF-8');
        }

        return strtolower($t);
    }

    private static function normalizeCountry(string $country): string
    {
        $raw = self::normalizeLocaleText($country);
        if ($raw === '') {
            return '';
        }

        if (in_array($raw, ['uae', 'u.a.e', 'ae', 'united arab emirates', 'emirates'], true)) {
            return 'uae';
        }

        if (str_contains($raw, '(uae)')) {
            return 'uae';
        }

        if (str_contains($raw, 'متحدہ') && str_contains($raw, 'عرب')) {
            return 'uae';
        }

        if (str_contains($raw, 'إمارات') || str_contains($raw, 'الإمارات') || str_contains($raw, 'امارات')) {
            return 'uae';
        }

        return $raw;
    }
}
