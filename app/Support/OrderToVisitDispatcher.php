<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Visit;
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
    /**
     * Backfill scheduled_date/time on an existing visit from its order item booking
     * (fixes visits created when only start-only slots like "09:00 AM" were stored,
     * and visits whose notes only say "Recreated from Order #N" with no order_id).
     */
    public static function syncVisitScheduleFromLinkedOrder(Visit $visit): Visit
    {
        $visit->loadMissing(['orderItem.product', 'order']);

        $item = $visit->orderItem;
        $order = $visit->order;

        // Recover order/item from notes when FKs were never set.
        if (($visit->order_id === null || $item === null) && is_string($visit->notes) && $visit->notes !== '') {
            $orderIdFromNotes = self::orderIdFromVisitNotes($visit->notes);
            if ($orderIdFromNotes !== null) {
                if ($visit->order_id === null) {
                    $visit->order_id = $orderIdFromNotes;
                }
                $order = $order ?? Order::query()->find($orderIdFromNotes);
            }
            if (preg_match('/\[ITEM:(\d+)\]/', $visit->notes, $itemMatch)) {
                $itemId = (int) $itemMatch[1];
                if ($visit->order_item_id === null) {
                    $visit->order_item_id = $itemId;
                }
                $item = $item ?? OrderItem::query()->with('product:id,name,job_duration')->find($itemId);
            }
        }

        if ($item === null && ($visit->order_id || $order)) {
            $item = self::resolveOrderItemForVisit($visit, (int) ($visit->order_id ?? $order?->id));
        }

        $order = $order
            ?? ($item ? Order::query()->find($visit->order_id ?? $item->order_id) : null)
            ?? ($visit->order_id ? Order::query()->find($visit->order_id) : null);

        if ($item === null && $order === null) {
            return $visit;
        }

        $bookingDate = $item?->booking_date?->toDateString()
            ?? $order?->booking_date?->toDateString();
        $bookingSlot = ShopBookingSlotHelper::parseSlotRange($item?->booking_slot ?? $order?->booking_slot);
        $durationMinutes = $bookingSlot['duration_minutes']
            ?? self::extractDurationMinutes((string) ($item?->product?->job_duration ?? ''));

        // Persist order/item link if we recovered it from notes.
        $linkUpdates = [];
        if ($visit->order_id === null && ($order?->id || $item?->order_id)) {
            $linkUpdates['order_id'] = (int) ($order?->id ?? $item->order_id);
        }
        if ($visit->order_item_id === null && $item?->id) {
            $linkUpdates['order_item_id'] = (int) $item->id;
        }
        if ($linkUpdates !== []) {
            $visit->fill($linkUpdates);
            $visit->save();
        }

        return self::backfillVisitSchedule($visit, $bookingDate, $bookingSlot['start'], $durationMinutes);
    }

    /**
     * Pull shop order id from notes: [SHOP-ORDER:49] or "Recreated from Order #49".
     */
    public static function orderIdFromVisitNotes(?string $notes): ?int
    {
        if (! is_string($notes) || $notes === '') {
            return null;
        }
        if (preg_match('/\[SHOP-ORDER:(\d+)\]/', $notes, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bOrder\s*#\s*(\d+)\b/i', $notes, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Prefer the linked item, then date-matching booked item, then any booked item, then sole item.
     */
    private static function resolveOrderItemForVisit(Visit $visit, int $orderId): ?OrderItem
    {
        if ($orderId <= 0) {
            return null;
        }

        $items = OrderItem::query()
            ->with('product:id,name,job_duration')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        if ($visit->order_item_id) {
            $exact = $items->firstWhere('id', (int) $visit->order_item_id);
            if ($exact) {
                return $exact;
            }
        }

        $visitDate = $visit->scheduled_date?->toDateString();
        $withBooking = $items->filter(function (OrderItem $i) {
            return ($i->booking_slot !== null && trim((string) $i->booking_slot) !== '')
                || $i->booking_date !== null;
        })->values();

        if ($visitDate !== null && $withBooking->isNotEmpty()) {
            $sameDate = $withBooking->first(function (OrderItem $i) use ($visitDate) {
                return $i->booking_date?->toDateString() === $visitDate;
            });
            if ($sameDate) {
                return $sameDate;
            }
        }

        if ($withBooking->count() === 1) {
            return $withBooking->first();
        }

        if ($withBooking->isNotEmpty()) {
            return $withBooking->first();
        }

        return $items->count() === 1 ? $items->first() : null;
    }

    public static function createVisitsForPaidOrder(Order $order): Collection
    {
        try {
            if (($order->payment_status ?? null) !== 'paid') {
                return collect();
            }

            $items = $order->items()->with('product:id,name,job_duration,type', 'product.services')->get();
            // Only service lines create supervisor/technician Visits.
            $items = $items->filter(
                fn (OrderItem $item) => OrderFulfillmentType::forOrderItem($item) === OrderFulfillmentType::SERVICE
            )->values();
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
     * @param  array{area: Area, supervisor_id: ?int}  $resolved
     */
    private static function createOrFindVisitForItem(Order $order, OrderItem $item, array $resolved): ?Visit
    {
        $bookingDate = $item->booking_date?->toDateString() ?? $order->booking_date?->toDateString();
        $bookingSlot = ShopBookingSlotHelper::parseSlotRange($item->booking_slot ?? $order->booking_slot);
        $durationMinutes = $bookingSlot['duration_minutes']
            ?? self::extractDurationMinutes((string) ($item->product->job_duration ?? ''));

        $existing = Visit::query()->where('order_item_id', $item->id)->first();
        if ($existing) {
            $existing = self::backfillVisitAreaPool($existing, $resolved);

            return self::backfillVisitSchedule($existing, $bookingDate, $bookingSlot['start'], $durationMinutes);
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

            $legacy = self::backfillVisitAreaPool($legacy, $resolved);

            return self::backfillVisitSchedule($legacy, $bookingDate, $bookingSlot['start'], $durationMinutes);
        }

        $ship = $order->getShippingAddressForApi() ?? [];
        $serviceTitle = (string) ($item->product->name ?? ('Order #' . $order->id . ' item'));

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
            // Unclaimed pool: all area supervisors see it until one claims.
            'supervisor_id' => $resolved['supervisor_id'] !== null ? (int) $resolved['supervisor_id'] : null,
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
     * Ensure an existing visit is in the area pool so all mapped supervisors see it
     * on GET /supervisor/assignments/new. Does not steal a claimed job (keeps supervisor_id).
     *
     * @param  array{area: Area, supervisor_id: ?int}  $resolved
     */
    private static function backfillVisitAreaPool(Visit $visit, array $resolved): Visit
    {
        $updates = [];
        if ($visit->area_id === null) {
            $updates['area_id'] = (int) $resolved['area']->id;
        }

        if ($updates !== []) {
            $visit->update($updates);
            $visit->refresh();
        }

        return $visit;
    }

    /**
     * Repair unclaimed visits that never got area_id (they never appear on New Jobs).
     * Safe: only fills null area_id; never changes supervisor_id.
     */
    public static function repairUnclaimedVisitsMissingAreaId(int $limit = 100): int
    {
        $repaired = 0;
        $visits = Visit::query()
            ->whereNull('area_id')
            ->whereNull('supervisor_id')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get();

        foreach ($visits as $visit) {
            $order = Order::query()->with(['items.product', 'shippingAddress'])->find($visit->order_id);
            if (! $order) {
                continue;
            }
            $resolved = self::resolveAreaAndSupervisor($order);
            if (! $resolved) {
                continue;
            }
            $visit->update(['area_id' => (int) $resolved['area']->id]);
            $repaired++;
        }

        return $repaired;
    }

    /**
     * Fill missing schedule on an already-created visit (e.g. start-only "09:00 AM"
     * slots that the old range-only parser skipped).
     */
    private static function backfillVisitSchedule(
        Visit $visit,
        ?string $bookingDate,
        ?string $startTime24h,
        ?int $durationMinutes
    ): Visit {
        $updates = [];

        if ($visit->scheduled_date === null && $bookingDate !== null) {
            $updates['scheduled_date'] = $bookingDate;
        }

        if (($visit->scheduled_time === null || $visit->scheduled_time === '') && $startTime24h !== null) {
            $updates['scheduled_time'] = $startTime24h;
            // When recovering a missing time, prefer the order-item booking date.
            if ($bookingDate !== null) {
                $updates['scheduled_date'] = $bookingDate;
            }
        }

        if (($visit->duration_minutes === null || (int) $visit->duration_minutes <= 0) && $durationMinutes !== null) {
            $updates['duration_minutes'] = $durationMinutes;
        }

        if ($updates !== []) {
            $visit->update($updates);
            $visit->refresh();
        }

        return $visit;
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
     * @return array{area: Area, supervisor_id: ?int}|null
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

        // Leave supervisor_id null so EVERY supervisor mapped to this area sees the job.
        // First supervisor to claim (POST .../assignments/{id}/claim) owns it.
        return [
            'area' => $matched,
            'supervisor_id' => null,
        ];
    }

    /**
     * @deprecated Prefer ShopBookingSlotHelper::parseSlotRange — kept for callers.
     *
     * @return array{start: ?string, duration_minutes: ?int}
     */
    private static function parseBookingSlot(?string $bookingSlot): array
    {
        return ShopBookingSlotHelper::parseSlotRange($bookingSlot);
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
