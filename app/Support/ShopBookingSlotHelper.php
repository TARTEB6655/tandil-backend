<?php

namespace App\Support;

use App\Models\Cart;
use App\Services\JobSchedulingService;
use Carbon\Carbon;

/**
 * Shared booking-date/slot helpers for shop cart, checkout and orders.
 */
final class ShopBookingSlotHelper
{
    public static function normalizedDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public static function normalizedSlot(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{start: ?string, duration_minutes: ?int}
     */
    public static function parseSlotRange(?string $bookingSlot): array
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
            // Single time like "09:00 AM" from product picker (`slots[].time`),
            // or 24h "09:00" / "09:00:00" if that was stored on the order item.
            if (preg_match('/^\s*(\d{1,2}:\d{2}\s*(?:AM|PM))\s*$/i', $slot, $single)) {
                try {
                    $start24 = Carbon::parse(trim($single[1]))->format('H:i');

                    return [
                        'start' => $start24,
                        'duration_minutes' => self::configuredDurationMinutesForStart($start24),
                    ];
                } catch (\Throwable $e) {
                    return ['start' => null, 'duration_minutes' => null];
                }
            }

            if (preg_match('/^\s*(\d{1,2}:\d{2})(?::\d{2})?\s*$/', $slot, $h24)) {
                try {
                    $start24 = Carbon::createFromFormat('H:i', substr($h24[1], 0, 5))->format('H:i');

                    return [
                        'start' => $start24,
                        'duration_minutes' => self::configuredDurationMinutesForStart($start24),
                    ];
                } catch (\Throwable $e) {
                    return ['start' => null, 'duration_minutes' => null];
                }
            }

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
            $duration = $start->diffInMinutes($end->copy()->addDay(), false);
        }

        return [
            'start' => $start->format('H:i'),
            'duration_minutes' => $duration > 0 ? (int) $duration : null,
        ];
    }

    /**
     * Display bounds for track/order summary.
     *
     * Product API exposes `slots[].time` as start-only (e.g. "09:00 AM").
     * End comes from admin JobTimeSlot duration when the stored string has no range.
     *
     * @return array{start: ?string, end: ?string, duration_minutes: ?int}
     */
    public static function resolveDisplayBounds(?string $bookingSlot): array
    {
        $slot = trim((string) $bookingSlot);
        if ($slot === '') {
            return ['start' => null, 'end' => null, 'duration_minutes' => null];
        }

        if (preg_match(
            '/^\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*(?:-|–|—|to)\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*$/i',
            $slot,
            $matches
        )) {
            return [
                'start' => trim($matches[1]),
                'end' => trim($matches[2]),
                'duration_minutes' => self::parseSlotRange($slot)['duration_minutes'],
            ];
        }

        $parsed = self::parseSlotRange($slot);
        $start24 = $parsed['start'];
        $duration = $parsed['duration_minutes'];

        if ($start24 === null) {
            return ['start' => null, 'end' => null, 'duration_minutes' => null];
        }

        try {
            $startCarbon = Carbon::createFromFormat('H:i', $start24);
            $startDisplay = $startCarbon->format('h:i A');
            $endDisplay = null;
            if ($duration !== null && $duration > 0) {
                $endDisplay = $startCarbon->copy()->addMinutes($duration)->format('h:i A');
            }

            return [
                'start' => $startDisplay,
                'end' => $endDisplay,
                'duration_minutes' => $duration,
            ];
        } catch (\Throwable $e) {
            return ['start' => $slot, 'end' => null, 'duration_minutes' => $duration];
        }
    }

    public static function configuredDurationMinutesForStart(string $startTime24h): ?int
    {
        $start = substr(trim($startTime24h), 0, 5);
        if ($start === '') {
            return null;
        }

        $slot = \App\Models\JobTimeSlot::query()
            ->where('is_active', true)
            ->where(function ($q) use ($start) {
                $q->where('start_time', $start)
                    ->orWhere('start_time', 'like', $start.':%')
                    ->orWhere('start_time', 'like', $start.'%');
            })
            ->orderBy('sort_order')
            ->first();

        if ($slot === null) {
            return null;
        }

        $minutes = (int) $slot->duration_minutes;

        return $minutes > 0 ? $minutes : null;
    }

    public static function slotStartTime24h(?string $bookingSlot): ?string
    {
        return self::parseSlotRange($bookingSlot)['start'];
    }

    /**
     * Validate a customer-selected date/time slot against scheduling rules.
     * Returns an error message, or null when valid / no slot supplied.
     */
    public static function validate(?string $bookingDate, ?string $bookingSlot, ?int $productId = null): ?string
    {
        $bookingDate = self::normalizedDate($bookingDate);
        $bookingSlot = self::normalizedSlot($bookingSlot);

        if ($bookingDate === null && $bookingSlot === null) {
            return null;
        }

        if ($bookingDate === null) {
            return 'Please select a booking date.';
        }

        if ($bookingSlot === null) {
            return 'Please select a time slot for the selected date.';
        }

        $start = self::slotStartTime24h($bookingSlot);
        if ($start === null) {
            return 'Selected time slot format is invalid.';
        }

        return JobSchedulingService::validateSlotForBooking(
            $bookingDate,
            $start,
            null,
            true,
            $productId
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{booking_date: ?string, booking_slot: ?string}
     */
    public static function resolveFromItemArray(
        array $item,
        ?string $fallbackDate = null,
        ?string $fallbackSlot = null
    ): array {
        $booking = is_array($item['booking'] ?? null) ? $item['booking'] : [];

        $date = self::normalizedDate(
            $item['booking_date']
            ?? $item['bookingDate']
            ?? $item['date']
            ?? $item['selectedDate']
            ?? $booking['booking_date']
            ?? $booking['date']
            ?? null
        ) ?? $fallbackDate;

        $slot = self::normalizedSlot(
            $item['booking_slot']
            ?? $item['bookingSlot']
            ?? $item['slot']
            ?? $item['timeSlot']
            ?? $item['selectedSlot']
            ?? $booking['booking_slot']
            ?? $booking['slot']
            ?? null
        ) ?? $fallbackSlot;

        return [
            'booking_date' => $date,
            'booking_slot' => $slot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkoutLinePayloadWithFallback(
        Cart $cart,
        ?string $fallbackDate = null,
        ?string $fallbackSlot = null
    ): array {
        $payload = $cart->checkoutLinePayload();

        if (empty($payload['booking_date']) && $fallbackDate !== null) {
            $payload['booking_date'] = $fallbackDate;
        }
        if (empty($payload['booking_slot']) && $fallbackSlot !== null) {
            $payload['booking_slot'] = $fallbackSlot;
        }

        return $payload;
    }

    /**
     * @param  iterable<int, Cart>  $cartItems
     * @return array<int, array<string, mixed>>
     */
    public static function summaryItemsFromCart(
        iterable $cartItems,
        ?string $fallbackDate = null,
        ?string $fallbackSlot = null
    ): array {
        $lines = [];

        foreach ($cartItems as $cart) {
            if ($cart->product === null) {
                continue;
            }

            $payload = self::checkoutLinePayloadWithFallback($cart, $fallbackDate, $fallbackSlot);
            $qty = (int) ($payload['quantity'] ?? 1);
            $unit = (float) ($payload['unit_price'] ?? 0);
            $area = isset($payload['required_area']) ? (float) $payload['required_area'] : null;
            $pricing = \App\Support\ServiceAreaPricing::lineApiFields(
                $cart->product,
                $unit,
                $qty,
                $area
            );

            $lines[] = array_merge([
                'product_id' => (int) $payload['product_id'],
                'name' => (string) $cart->product->name,
                'quantity' => $qty,
                'unit_price' => $unit,
                'required_area' => $area,
                'line_total' => $pricing['line_total'],
                'booking_date' => $payload['booking_date'] ?? null,
                'booking_slot' => $payload['booking_slot'] ?? null,
                'selected_option_ids' => $payload['selected_options'] ?? [],
            ], $pricing);
        }

        return $lines;
    }
}
