<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class OrderToVisitDispatcher
{
    /**
     * One order can bundle several different products, each booked for its own
     * date/time slot — so this dispatches one Visit per order item (not one per
     * order), each carrying that item's own scheduled_date/scheduled_time.
     *
     * @return Collection<int, Visit>
     */
    public static function createVisitsForPaidOrder(Order $order): Collection
    {
        try {
            if (($order->payment_status ?? null) !== 'paid') {
                return collect();
            }

            $items = $order->items()->with('product:id,name,job_duration')->get();
            if ($items->isEmpty()) {
                return collect();
            }

            $resolved = self::resolveAreaAndSupervisor($order);
            if (! $resolved) {
                return collect();
            }

            return $items->map(fn (OrderItem $item) => self::createOrFindVisitForItem($order, $item, $resolved))
                ->filter()
                ->values();
        } catch (\Throwable $e) {
            Log::warning('Order-to-visit dispatch failed: ' . $e->getMessage(), ['order_id' => $order->id]);

            return collect();
        }
    }

    /**
     * @deprecated Use createVisitsForPaidOrder() — kept only so any stray external
     * caller doesn't hard-fail; returns the first visit created/found, if any.
     */
    public static function createVisitForPaidOrder(Order $order): ?Visit
    {
        return self::createVisitsForPaidOrder($order)->first();
    }

    /**
     * @param  array{area: Area, supervisor_id: int}  $resolved
     */
    private static function createOrFindVisitForItem(Order $order, OrderItem $item, array $resolved): ?Visit
    {
        $existing = Visit::query()->where('order_item_id', $item->id)->first();
        if ($existing) {
            return $existing;
        }

        $marker = self::itemMarker($order->id, $item->id);
        $legacy = Visit::query()->where('notes', 'like', '%' . $marker . '%')->first();

        // Orders created before per-item scheduling only ever got one Visit, tagged
        // with the order-only marker (no item id). Adopt that legacy visit for the
        // first item of a single-item order instead of creating a duplicate.
        if (! $legacy && $order->items()->count() === 1) {
            $legacy = Visit::query()->where('notes', 'like', '%' . self::orderMarker($order->id) . '%')->first();
        }

        if ($legacy) {
            if ($legacy->order_item_id === null) {
                $legacy->order_item_id = $item->id;
                if ($legacy->order_id === null) {
                    $legacy->order_id = $order->id;
                }
                $legacy->save();
            }

            return $legacy;
        }

        $ship = $order->getShippingAddressForApi() ?? [];
        $serviceTitle = (string) ($item->product->name ?? ('Order #' . $order->id . ' item'));
        $bookingDate = $item->booking_date?->toDateString() ?? $order->booking_date?->toDateString();
        $bookingSlot = self::parseBookingSlot($item->booking_slot ?? $order->booking_slot);
        $durationMinutes = $bookingSlot['duration_minutes']
            ?? self::extractDurationMinutes((string) ($item->product->job_duration ?? ''));

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
            'AED ' . number_format((float) $item->subtotal, 2),
            'Client: ' . ($clientName !== '' ? $clientName : 'Customer'),
            'Email: ' . ($clientEmail !== '' ? $clientEmail : '-'),
            'Phone: ' . ($clientPhone !== '' ? $clientPhone : '-'),
            'Address: ' . ($addressLabel !== '' ? $addressLabel : '-'),
            $marker,
        ];

        return Visit::create([
            'subscription_id' => null,
            'order_id' => (int) $order->id,
            'order_item_id' => (int) $item->id,
            'technician_id' => null,
            'supervisor_id' => (int) $resolved['supervisor_id'],
            'area_id' => (int) $resolved['area']->id,
            'scheduled_date' => $bookingDate ?? now()->toDateString(),
            'scheduled_time' => $bookingSlot['start'],
            'duration_minutes' => $durationMinutes,
            'status' => 'pending',
            'notes' => implode(' | ', $notesParts),
            'price' => (float) $item->subtotal,
        ]);
    }

    /**
     * Two adjacent bracket groups (not one combined marker) so the order-id portion
     * stays byte-identical to the pre-multi-item marker format — anything still
     * searching for '[SHOP-ORDER:{id}]' as a substring (or via the equivalent
     * \[SHOP-ORDER:(\d+)\] regex) keeps matching visits created by this dispatcher.
     */
    private static function itemMarker(int $orderId, int $orderItemId): string
    {
        return self::orderMarker($orderId) . '[ITEM:' . $orderItemId . ']';
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
     * Parses a booked slot string (e.g. "10:00 AM - 12:00 PM" / "10:00 - 12:00" /
     * "10:00 AM to 12:00 PM") into a 24h start time + duration for the Visit's
     * scheduled_time/duration_minutes. Returns nulls (not an error) when the slot
     * is empty or unparseable — the caller falls back to the product's configured
     * duration in that case.
     *
     * @return array{start: ?string, duration_minutes: ?int}
     */
    private static function parseBookingSlot(?string $bookingSlot): array
    {
        $slot = trim((string) $bookingSlot);
        if ($slot === '') {
            return ['start' => null, 'duration_minutes' => null];
        }

        if (! preg_match(
            '/^\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*(?:-|–|—|to)\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*$/i',
            $slot,
            $matches
        )) {
            return ['start' => null, 'duration_minutes' => null];
        }

        try {
            $start = Carbon::parse($matches[1]);
            $end = Carbon::parse($matches[2]);
        } catch (\Throwable $e) {
            return ['start' => null, 'duration_minutes' => null];
        }

        $duration = $start->diffInMinutes($end, false);
        if ($duration <= 0) {
            // "10:00 PM - 12:00 AM" style overnight slot; treat as next day.
            $duration = $start->diffInMinutes($end->addDay(), false);
        }

        return [
            'start' => $start->format('H:i'),
            'duration_minutes' => $duration > 0 ? (int) $duration : null,
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
