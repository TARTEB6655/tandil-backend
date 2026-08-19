<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;

final class OrderToVisitDispatcher
{
    public static function createVisitForPaidOrder(Order $order): ?Visit
    {
        try {
            if (($order->payment_status ?? null) !== 'paid') {
                return null;
            }

            $existing = Visit::query()->where('order_id', $order->id)->first();
            if ($existing) {
                return $existing;
            }

            $marker = self::orderMarker($order->id);
            $legacy = Visit::query()->where('notes', 'like', '%' . $marker . '%')->first();
            if ($legacy) {
                if ($legacy->order_id === null) {
                    $legacy->order_id = $order->id;
                    $legacy->save();
                }

                return $legacy;
            }

            $resolved = self::resolveAreaAndSupervisor($order);
            if (! $resolved) {
                return null;
            }

            $ship = $order->getShippingAddressForApi() ?? [];
            $serviceMeta = self::serviceMeta($order);
            $serviceTitle = $serviceMeta['title'];
            $durationMinutes = $serviceMeta['duration_minutes'];
            $clientName = (string) ($ship['full_name'] ?? $order->payerDisplayName());
            $clientEmail = (string) ($ship['email'] ?? ($order->payerEmail() ?? ''));
            $clientPhone = (string) ($ship['phone_number'] ?? ($order->payerPhone() ?? ''));
            $street = trim((string) ($ship['street_address'] ?? ''));
            $city = trim((string) ($ship['city'] ?? ''));
            $state = trim((string) ($ship['state'] ?? ''));
            $zip = trim((string) ($ship['zip_code'] ?? ''));
            $country = trim((string) ($ship['country'] ?? ''));
            $addressLabel = trim(implode(', ', array_filter([$street, $city, $state, $zip, $country], fn ($v) => $v !== '')));
            $locationLabel = $resolved['area']->location ?: ($city !== '' ? $city : $resolved['area']->name);

            $notesParts = [
                $serviceTitle,
                'Order Service Visit',
                $locationLabel,
                $durationMinutes !== null ? ($durationMinutes . ' min') : '-- min',
                'AED ' . number_format((float) $order->total_amount, 2),
                'Client: ' . ($clientName !== '' ? $clientName : 'Customer'),
                'Email: ' . ($clientEmail !== '' ? $clientEmail : '-'),
                'Phone: ' . ($clientPhone !== '' ? $clientPhone : '-'),
                'Address: ' . ($addressLabel !== '' ? $addressLabel : '-'),
                $marker,
            ];

            return Visit::create([
                'subscription_id' => null,
                'order_id' => (int) $order->id,
                'technician_id' => null,
                'supervisor_id' => (int) $resolved['supervisor_id'],
                'area_id' => (int) $resolved['area']->id,
                'scheduled_date' => now()->toDateString(),
                'status' => 'pending',
                'notes' => implode(' | ', $notesParts),
                'price' => (float) $order->total_amount,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Order-to-visit dispatch failed: ' . $e->getMessage(), ['order_id' => $order->id]);
            return null;
        }
    }

    private static function orderMarker(int $orderId): string
    {
        return '[SHOP-ORDER:' . $orderId . ']';
    }

    /**
     * @return array{area: Area, supervisor_id: int}|null
     */
    private static function resolveAreaAndSupervisor(Order $order): ?array
    {
        $ship = $order->getShippingAddressForApi();
        if (! is_array($ship)) {
            return null;
        }

        $country = self::normalizeCountry((string) ($ship['country'] ?? ''));
        $city = strtolower(trim((string) ($ship['city'] ?? '')));
        $state = strtolower(trim((string) ($ship['state'] ?? '')));
        $street = strtolower(trim((string) ($ship['street_address'] ?? '')));
        $zip = strtolower(trim((string) ($ship['zip_code'] ?? '')));

        $areas = Area::with('supervisors')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Area $a) => $a->supervisors->isNotEmpty())
            ->values();
        if ($areas->isEmpty()) {
            return null;
        }

        if ($country !== '') {
            $countryMatched = $areas->filter(function (Area $area) use ($country) {
                return self::normalizeCountry((string) ($area->country ?? '')) === $country;
            })->values();
            if ($countryMatched->isNotEmpty()) {
                $areas = $countryMatched;
            }
        }

        $matched = $areas->first(function (Area $a) use ($city, $state, $street, $zip) {
            $hay = strtolower(trim((string) ($a->name . ' ' . ($a->location ?? '') . ' ' . ($a->description ?? ''))));
            return ($city !== '' && str_contains($hay, $city))
                || ($state !== '' && str_contains($hay, $state))
                || ($street !== '' && str_contains($hay, $street))
                || ($zip !== '' && str_contains($hay, $zip));
        });

        // Fallback: no textual area match, but supervised areas exist. Route the order
        // to an active area that has a supervisor (prefer the country-matched subset) so
        // the area supervisor always sees the order, regardless of address wording or status.
        if (! $matched instanceof Area) {
            $matched = $areas->first();
        }

        if (! $matched instanceof Area) {
            return null;
        }

        return [
            'area' => $matched,
            'supervisor_id' => (int) $matched->supervisors->first()->id,
        ];
    }

    /**
     * @return array{title:string,duration_minutes:int|null}
     */
    private static function serviceMeta(Order $order): array
    {
        $item = $order->items()->with('product:id,name,job_duration')->first();
        if ($item && $item->product && ! empty($item->product->name)) {
            return [
                'title' => (string) $item->product->name,
                'duration_minutes' => self::extractDurationMinutes((string) ($item->product->job_duration ?? '')),
            ];
        }

        return [
            'title' => 'Order #' . $order->id,
            'duration_minutes' => null,
        ];
    }

    private static function extractDurationMinutes(string $raw): ?int
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d+)\s*(?:min|mins|minute|minutes|m)\b/', $value, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(?:hour|hours|hr|hrs|h)\b/', $value, $m)) {
            return (int) $m[1] * 60;
        }
        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    private static function normalizeCountry(string $country): string
    {
        $raw = strtolower(trim($country));

        return match ($raw) {
            'uae', 'u.a.e', 'ae', 'united arab emirates' => 'uae',
            default => $raw,
        };
    }
}

