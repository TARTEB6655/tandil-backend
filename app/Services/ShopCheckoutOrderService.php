<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Builds shop orders for checkout (guest or authenticated).
 * Used by Stripe / PayPal flows.
 */
class ShopCheckoutOrderService
{
    /**
     * Normalize checkout request data.
     *
     * Supports:
     * - shipping_address / shipping
     * - booking_date / date
     * - booking_slot / slot
     * - nested booking object
     */
    public function normalizeCheckoutRequest(Request $request): void
    {
        $all = $request->all();

        /*
         * Normalize shipping address.
         */
        $shipping = $all['shipping_address']
            ?? $all['shipping']
            ?? null;

        if (is_array($shipping)) {
            $all['full_name'] = $shipping['fullName']
                ?? $shipping['full_name']
                ?? $all['full_name']
                ?? null;

            $all['phone_number'] = $shipping['phone']
                ?? $shipping['phone_number']
                ?? $all['phone_number']
                ?? null;

            $all['street_address'] = $shipping['street']
                ?? $shipping['street_address']
                ?? $all['street_address']
                ?? null;

            $all['city'] = $shipping['city']
                ?? $all['city']
                ?? null;

            $all['state'] = $shipping['state']
                ?? $all['state']
                ?? null;

            $all['zip_code'] = $shipping['zipCode']
                ?? $shipping['zip_code']
                ?? $all['zip_code']
                ?? null;

            $all['country'] = $shipping['country']
                ?? $all['country']
                ?? null;
        }

        /*
         * Normalize booking date.
         *
         * Supported (the mobile app is React Native/JS, so camelCase
         * variants are just as likely as snake_case — same reasoning as
         * fullName/zipCode above for shipping):
         * booking_date / bookingDate / date / selectedDate
         */
        $all['booking_date'] = $all['booking_date']
            ?? $all['bookingDate']
            ?? $all['date']
            ?? $all['selectedDate']
            ?? null;

        /*
         * Normalize booking slot.
         *
         * Supported:
         * booking_slot / bookingSlot / slot / selectedSlot / timeSlot / time_slot
         */
        $all['booking_slot'] = $all['booking_slot']
            ?? $all['bookingSlot']
            ?? $all['slot']
            ?? $all['selectedSlot']
            ?? $all['timeSlot']
            ?? $all['time_slot']
            ?? null;

        /*
         * Support nested booking object.
         *
         * Example:
         *
         * booking: {
         *     booking_date: "2026-08-20",
         *     booking_slot: "10:00 AM - 12:00 PM"
         * }
         */
        if (
            isset($all['booking'])
            && is_array($all['booking'])
        ) {
            $booking = $all['booking'];

            $all['booking_date'] = $all['booking_date']
                ?? $booking['booking_date']
                ?? $booking['bookingDate']
                ?? $booking['date']
                ?? null;

            $all['booking_slot'] = $all['booking_slot']
                ?? $booking['booking_slot']
                ?? $booking['bookingSlot']
                ?? $booking['slot']
                ?? null;
        }

        /*
         * Clean booking date.
         */
        if (is_string($all['booking_date'])) {
            $all['booking_date'] = trim(
                $all['booking_date']
            );

            if ($all['booking_date'] === '') {
                $all['booking_date'] = null;
            }
        }

        /*
         * Clean booking slot.
         */
        if (is_string($all['booking_slot'])) {
            $all['booking_slot'] = trim(
                $all['booking_slot']
            );

            if ($all['booking_slot'] === '') {
                $all['booking_slot'] = null;
            }
        }

        /*
         * Diagnostic only: neither booking_date nor booking_slot resolved from any
         * known alias. Log the raw request's top-level keys (and any booking-shaped
         * values under alternate names) so we can see exactly what the client sent
         * without guessing at field names — no PII beyond what the client already
         * submitted for this checkout, and dates/time slots aren't sensitive.
         */
        if ($all['booking_date'] === null && $all['booking_slot'] === null) {
            Log::info('Checkout: no booking_date/booking_slot resolved from request', [
                'raw_keys' => array_keys($request->all()),
                'booking_related_raw' => collect($request->all())->only([
                    'booking_date', 'bookingDate', 'date', 'selectedDate',
                    'booking_slot', 'bookingSlot', 'slot', 'selectedSlot', 'timeSlot', 'time_slot',
                    'booking',
                ])->all(),
            ]);
        }

        $request->merge($all);
    }

    /**
     * Create guest order.
     */
    public function createGuestOrder(
        Request $request,
        string $paymentMethod
    ): ?Order {
        $items = $request->input('items', []);

        /*
         * Calculate subtotal.
         */
        $subtotal = 0;

        foreach ($items as $item) {
            $product = \App\Models\Product::find(
                $item['product_id'] ?? null
            );

            if ($product) {
                $subtotal +=
                    $this->resolveItemUnitPrice(
                        $item,
                        $product
                    )
                    * (int) ($item['qty'] ?? 1);
            }
        }

        $subtotal = round($subtotal, 2);

        /*
         * Build cart lines for order summary.
         */
        $cartLines = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;

            if ($productId) {
                $cartLines[] = [
                    'product_id' => $productId,
                ];
            }
        }

        /*
         * Calculate order totals.
         */
        $summary = CartController::buildOrderSummary(
            $subtotal,
            0,
            $cartLines
        );

        $total = $summary['total'];
        $shippingAmount = $summary['shipping'];
        $taxAmount = $summary['tax'];
        $taxPercent = $summary['tax_percent'];

        /*
         * Special instructions.
         */
        $special = $request->input(
            'special_instructions'
        );

        if (is_string($special)) {
            $special = mb_substr(
                trim($special),
                0,
                2000
            );

            $special = $special === ''
                ? null
                : $special;
        } else {
            $special = null;
        }

        /*
         * Booking date.
         *
         * IMPORTANT:
         * orders table has booking_date.
         */
        $bookingDate = $request->input(
            'booking_date'
        );

        if (is_string($bookingDate)) {
            $bookingDate = trim($bookingDate);

            if ($bookingDate === '') {
                $bookingDate = null;
            }
        }

        /*
         * Booking slot.
         *
         * IMPORTANT:
         * orders table has booking_slot.
         *
         * It does NOT have:
         * slot_id
         * slot_start_time
         * slot_end_time
         */
        $bookingSlot = $request->input(
            'booking_slot'
        );

        if (is_string($bookingSlot)) {
            $bookingSlot = trim($bookingSlot);

            if ($bookingSlot === '') {
                $bookingSlot = null;
            }
        }

        /*
         * Product timing fallback.
         */
        $timing = $this->defaultTimingFromOrderItems(
            $items
        );

        /*
         * Create guest order.
         */
        $order = Order::create([
            'user_id' => null,

            'guest_email' => $request->input(
                'email'
            ),

            'guest_full_name' => $request->input(
                'full_name'
            ),

            'guest_phone' => $request->input(
                'phone_number'
            ),

            'guest_street_address' => $request->input(
                'street_address'
            ),

            'guest_city' => $request->input(
                'city'
            ),

            'guest_state' => $request->input(
                'state'
            ),

            'guest_zip_code' => $request->input(
                'zip_code'
            ),

            'guest_country' => $request->input(
                'country'
            ),

            'shipping_address_id' => null,

            'total_amount' => $total,
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_percent' => $taxPercent,
            'shipping_amount' => $shippingAmount,

            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,

            /*
             * Booking fields that ACTUALLY exist
             * in orders table.
             */
            'booking_date' => $bookingDate,
            'booking_slot' => $bookingSlot,

            'special_instructions' => $special,

            'estimated_arrival' => $timing[
                'estimated_arrival'
            ],

            'job_duration' => $timing[
                'job_duration'
            ],
        ]);

        /*
         * Create order items.
         */
        foreach ($items as $item) {
            $product = \App\Models\Product::find(
                $item['product_id'] ?? null
            );

            if (!$product) {
                continue;
            }

            $qty = (int) ($item['qty'] ?? 1);

            $unit = $this->resolveItemUnitPrice(
                $item,
                $product
            );

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => $unit,
                'subtotal' => round(
                    $unit * $qty,
                    2
                ),
                'booking_date' => self::itemBookingDate($item),
                'booking_slot' => self::itemBookingSlot($item),
            ]);
        }

        /*
         * Sync order with vendor.
         */
        app(
            \App\Services\Vendor\VendorOrderSyncService::class
        )->syncFromOrder(
            $order->fresh('items.product')
        );

        return $order;
    }

    /**
     * Create authenticated / logged-in user order.
     */
    public function createLoggedInOrder(
        Request $request,
        string $paymentMethod
    ): ?Order {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $addressId = $request->input(
            'address_id'
        );

        $items = $request->input(
            'items',
            []
        );

        /*
         * Resolve shipping address.
         */
        if ($addressId) {
            $address = UserAddress::where(
                'user_id',
                $user->id
            )->find(
                (int) $addressId
            );

            if (!$address) {
                return null;
            }
        } else {
            $address = new UserAddress;

            $address->user_id = $user->id;

            $address->full_name = $request->input(
                'full_name'
            );

            $address->phone_number = $request->input(
                'phone_number'
            );

            $address->street_address = $request->input(
                'street_address'
            );

            $address->city = $request->input(
                'city'
            );

            $address->state = $request->input(
                'state'
            );

            $address->zip_code = $request->input(
                'zip_code'
            );

            $address->country = $request->input(
                'country'
            );

            $address->is_default = false;

            $address->save();
        }

        /*
         * If items are not provided,
         * use user's cart.
         */
        if (empty($items)) {
            $cartItems = Cart::where(
                'user_id',
                $user->id
            )
                ->with('product')
                ->get();

            $validCart = $cartItems->filter(
                fn ($cart) =>
                    $cart->product !== null
            );

            if ($validCart->isEmpty()) {
                return null;
            }

            $subtotal = round(
                $validCart->sum(
                    fn ($cart) =>
                        $cart->quantity
                        * $cart->lineUnitPrice()
                ),
                2
            );

            foreach ($validCart as $cart) {
                $items[] = [
                    'product_id' => $cart->product_id,
                    'qty' => $cart->quantity,
                    'unit_price' => $cart->lineUnitPrice(),
                    'selected_options' =>
                        Cart::normalizeSelectedOptionIds(
                            $cart->selected_options
                        ),
                ];
            }
        } else {
            /*
             * Calculate subtotal from request items.
             */
            $subtotal = 0;

            foreach ($items as $item) {
                $product = \App\Models\Product::find(
                    $item['product_id'] ?? null
                );

                if ($product) {
                    $subtotal +=
                        $this->resolveItemUnitPrice(
                            $item,
                            $product
                        )
                        * (int) ($item['qty'] ?? 1);
                }
            }

            $subtotal = round($subtotal, 2);
        }

        /*
         * Build order summary.
         */
        $summaryCartItems = empty(
            $request->input('items')
        )
            ? Cart::where(
                'user_id',
                $user->id
            )
                ->with('product')
                ->get()
            : null;

        if ($summaryCartItems !== null) {
            $summary = CartController::buildOrderSummary(
                $subtotal,
                0,
                $summaryCartItems
            );
        } else {
            $summary = CartController::buildOrderSummary(
                $subtotal,
                0,
                collect($items)
                    ->map(
                        fn ($row) => [
                            'product_id' =>
                                $row['product_id']
                                ?? null,
                        ]
                    )
                    ->all()
            );
        }

        $total = $summary['total'];
        $shippingAmount = $summary['shipping'];
        $taxAmount = $summary['tax'];
        $taxPercent = $summary['tax_percent'];

        /*
         * Special instructions.
         */
        $special = $request->input(
            'special_instructions'
        );

        if (is_string($special)) {
            $special = mb_substr(
                trim($special),
                0,
                2000
            );

            $special = $special === ''
                ? null
                : $special;
        } else {
            $special = null;
        }

        /*
         * Booking date.
         */
        $bookingDate = $request->input(
            'booking_date'
        );

        if (is_string($bookingDate)) {
            $bookingDate = trim($bookingDate);

            if ($bookingDate === '') {
                $bookingDate = null;
            }
        }

        /*
         * Booking slot.
         */
        $bookingSlot = $request->input(
            'booking_slot'
        );

        if (is_string($bookingSlot)) {
            $bookingSlot = trim($bookingSlot);

            if ($bookingSlot === '') {
                $bookingSlot = null;
            }
        }

        /*
         * Product timing fallback.
         */
        $timing = $this->defaultTimingFromOrderItems(
            $items
        );

        /*
         * Create logged-in order.
         */
        $order = Order::create([
            'user_id' => $user->id,

            'shipping_address_id' => $address->id,

            'total_amount' => $total,
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_percent' => $taxPercent,
            'shipping_amount' => $shippingAmount,

            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,

            /*
             * Booking fields that exist in DB.
             */
            'booking_date' => $bookingDate,
            'booking_slot' => $bookingSlot,

            'special_instructions' => $special,

            'estimated_arrival' => $timing[
                'estimated_arrival'
            ],

            'job_duration' => $timing[
                'job_duration'
            ],
        ]);

        /*
         * Create order items.
         */
        foreach ($items as $item) {
            $product = \App\Models\Product::find(
                $item['product_id'] ?? null
            );

            if (!$product) {
                continue;
            }

            $qty = (int) ($item['qty'] ?? 1);

            $unit = $this->resolveItemUnitPrice(
                $item,
                $product
            );

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => $unit,
                'subtotal' => round(
                    $unit * $qty,
                    2
                ),
                'booking_date' => self::itemBookingDate($item),
                'booking_slot' => self::itemBookingSlot($item),
            ]);
        }

        /*
         * Empty cart after order creation
         * when checkout used the cart.
         */
        if (
            empty(
                $request->input('items')
            )
        ) {
            Cart::where(
                'user_id',
                $user->id
            )->delete();
        }

        /*
         * Sync order with vendor.
         */
        app(
            \App\Services\Vendor\VendorOrderSyncService::class
        )->syncFromOrder(
            $order->fresh('items.product')
        );

        return $order;
    }

    /**
     * Resolve product item unit price.
     *
     * @param array<string, mixed> $item
     */
    private function resolveItemUnitPrice(
        array $item,
        \App\Models\Product $product
    ): float {
        /*
         * If frontend already supplied unit_price,
         * use it.
         */
        if (isset($item['unit_price'])) {
            return round(
                (float) $item['unit_price'],
                2
            );
        }

        $optionIds =
            $item['selected_options']
            ?? $item['option_ids']
            ?? [];

        return Cart::calculateUnitPrice(
            $product,
            $optionIds
        );
    }

    /**
     * Each product in an order can be booked for its own date/time slot (different
     * products, different jobs, possibly on different days) — read the per-item
     * value from the raw items[] array, same alias set as the order-level fields.
     *
     * @param  array<string, mixed>  $item
     */
    private static function itemBookingDate(array $item): ?string
    {
        $value = $item['booking_date'] ?? $item['bookingDate'] ?? $item['date'] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemBookingSlot(array $item): ?string
    {
        $value = $item['booking_slot'] ?? $item['bookingSlot'] ?? $item['slot'] ?? $item['timeSlot'] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Get default timing from order products.
     *
     * First product whose timing is set wins.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array{
     *     estimated_arrival: ?string,
     *     job_duration: ?string
     * }
     */
    private function defaultTimingFromOrderItems(
        array $items
    ): array {
        $estimated = null;
        $duration = null;

        foreach ($items as $item) {
            $product = \App\Models\Product::find(
                $item['product_id'] ?? null
            );

            if (!$product) {
                continue;
            }

            if (
                $estimated === null
                && $product->estimated_arrival
            ) {
                $estimated =
                    $product->estimated_arrival;
            }

            if (
                $duration === null
                && $product->job_duration
            ) {
                $duration =
                    $product->job_duration;
            }

            if (
                $estimated !== null
                && $duration !== null
            ) {
                break;
            }
        }

        return [
            'estimated_arrival' => $estimated,
            'job_duration' => $duration,
        ];
    }
}