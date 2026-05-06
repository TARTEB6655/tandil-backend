<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\Log;

final class OrderSupervisorNotifier
{
    /**
     * Notify supervisors for the resolved area when a shop order becomes paid.
     *
     * This is intentionally "best effort": if the address cannot be mapped to an active area with supervisors,
     * the order is still valid and we simply skip the supervisor notification.
     */
    public static function notifySupervisorsForPaidOrder(Order $order, float $total, string $placedBy): void
    {
        try {
            $resolved = self::resolveAreaForOrder($order);
            if (! $resolved) {
                return;
            }

            /** @var Area $area */
            $area = $resolved['area'];
            $supervisors = $area->supervisors;
            if ($supervisors->isEmpty()) {
                return;
            }

            $who = $order->payerDisplayName();
            $city = (string) (($resolved['ship']['city'] ?? '') ?: '');
            $country = (string) (($resolved['ship']['country'] ?? '') ?: '');

            foreach ($supervisors as $sup) {
                if (! $sup instanceof User) {
                    continue;
                }
                $sup->notify(new AdminNotification(
                    'New Order in Your Area',
                    "A new order #{$order->id} has been placed in {$area->name} ({$city}" . ($country !== '' ? ", {$country}" : '') . ") by {$who} via {$placedBy} for AED {$total}."
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Order supervisor notify failed: '.$e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * @return array{area: Area, ship: array}|null
     */
    private static function resolveAreaForOrder(Order $order): ?array
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

        if ($city === '' && $state === '' && $street === '' && $zip === '' && $country === '') {
            return null;
        }

        $areas = Area::with('supervisors')
            ->where('is_active', true)
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

        if (! $matched instanceof Area) {
            return null;
        }

        return ['area' => $matched, 'ship' => $ship];
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

