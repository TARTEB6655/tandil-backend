<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Visit;
use App\Support\OrderToVisitDispatcher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds an additional small-amount order for client1@test.com.
 * - Same address as the recent order (Al Khalidiya, Corniche Road).
 * - Fresh new-order status: order_status = "pending".
 * - A supervisor visit is created (via Visit::withoutEvents so the order stays
 *   pending) so the area supervisor sees the order regardless of status.
 * - Small total amount, different from the previous order.
 *
 * Run: php artisan db:seed --class=Client1SmallOrderSeeder
 */
class Client1SmallOrderSeeder extends Seeder
{
    public const DEMO_MARKER = '[SEED-CLIENT1-SMALL-ORDER]';

    private const ADDRESS = [
        'street_address' => 'Office 302, Al Khalidiya, Corniche Road',
        'city' => 'Abu Dhabi',
        'state' => 'Abu Dhabi',
        'zip_code' => '00000',
        'country' => 'UAE',
    ];

    public function run(): void
    {
        $client = User::query()
            ->where('email', 'client1@test.com')
            ->where('role', 'client')
            ->first();

        if ($client === null) {
            $this->command->error('Client client1@test.com not found. Run FixedUsersOnlySeeder first.');

            return;
        }

        $product = Product::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->first();

        if ($product === null) {
            $this->command->error('No active products found. Run ServicesCategoriesAndProductsSeeder first.');

            return;
        }

        $this->ensureAbuDhabiAreaWithSupervisor();
        $shippingAddress = $this->ensureClientShippingAddress($client);

        // Small amount, different from the previous order.
        $qty = 1;
        $unitPrice = 8.00;
        $subtotal = round($qty * $unitPrice, 2);
        $shipping = 2.00;
        $taxPercent = 5;
        $tax = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $shipping + $tax, 2);

        $order = Order::withoutEvents(function () use ($client, $product, $shippingAddress, $subtotal, $tax, $taxPercent, $shipping, $total) {
            return Order::create([
                'user_id' => $client->id,
                'guest_full_name' => $client->name ?: 'Client One',
                'guest_email' => $client->email,
                'guest_phone' => $client->phone ?: '+971500000001',
                'guest_street_address' => self::ADDRESS['street_address'],
                'guest_city' => self::ADDRESS['city'],
                'guest_state' => self::ADDRESS['state'],
                'guest_zip_code' => self::ADDRESS['zip_code'],
                'guest_country' => self::ADDRESS['country'],
                'shipping_address_id' => $shippingAddress->id,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'tax_percent' => $taxPercent,
                'shipping_amount' => $shipping,
                'total_amount' => $total,
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
                'payment_reference' => 'seed_pay_' . Str::lower(Str::random(8)),
                'order_status' => 'pending',
                'special_instructions' => self::DEMO_MARKER . ' Small test order — pending team confirmation.',
                'estimated_arrival' => now()->addDays(3),
                'job_duration' => $product->job_duration,
                'paid_at' => now(),
            ]);
        });

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'price' => $unitPrice,
            'subtotal' => $subtotal,
        ]);

        Transaction::create([
            'transactionable_type' => Order::class,
            'transactionable_id' => $order->id,
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'type' => 'payment',
            'gateway' => 'stripe',
            'payment_method' => 'stripe',
            'amount' => $total,
            'currency' => 'AED',
            'status' => 'completed',
            'gateway_transaction_id' => 'GW-' . strtoupper(Str::random(14)),
            'gateway_response' => ['status' => 'success', 'order_id' => $order->id],
            'notes' => 'Seeded payment for client1 small order #' . $order->id,
            'processed_at' => $order->paid_at,
        ]);

        // Create the supervisor visit without firing Visit events, so the order stays
        // "pending" while still being visible to the area supervisor.
        $visit = Visit::withoutEvents(fn () => OrderToVisitDispatcher::createVisitForPaidOrder(
            $order->fresh(['items.product', 'shippingAddress'])
        ));

        $this->command->info('Created small order #' . $order->id . ' (' . $order->publicOrderNumber() . ') for client1@test.com.');
        $this->command->info('Product: ' . $product->name . ' — AED ' . number_format($total, 2) . ' (subtotal ' . number_format($subtotal, 2) . ' + shipping ' . number_format($shipping, 2) . ' + tax ' . number_format($tax, 2) . ')');
        $this->command->info('order_status: pending (fresh new-order status)');
        $this->command->info('Address: ' . self::ADDRESS['street_address'] . ', ' . self::ADDRESS['city'] . ', ' . self::ADDRESS['country'] . ' ' . self::ADDRESS['zip_code']);
        if ($visit) {
            $this->command->info('Visit #' . $visit->id . ' assigned to supervisor #' . $visit->supervisor_id . ' (area #' . $visit->area_id . ') — visible to supervisor while order stays pending.');
        } else {
            $this->command->warn('No visit dispatched — ensure an active area has a supervisor.');
        }
        $this->command->info('Login: client1@test.com / password123');
        $this->command->info('Track: GET /api/orders/' . $order->id . '/track');
    }

    private function ensureAbuDhabiAreaWithSupervisor(): void
    {
        $supervisor = User::query()
            ->where('email', 'supervisor1@test.com')
            ->where('role', 'supervisor')
            ->first()
            ?? User::query()->where('role', 'supervisor')->orderBy('id')->first();

        if ($supervisor === null) {
            $this->command->warn('No supervisor user found — visit dispatch may fail.');

            return;
        }

        $area = Area::query()->firstOrCreate(
            ['name' => 'Abu Dhabi Central'],
            [
                'description' => 'Service area for Abu Dhabi and Corniche Road.',
                'location' => 'Abu Dhabi',
                'country' => 'UAE',
                'is_active' => true,
            ]
        );

        $area->update(['location' => 'Abu Dhabi', 'country' => 'UAE', 'is_active' => true]);

        DB::table('area_supervisor')->updateOrInsert(
            ['area_id' => $area->id, 'user_id' => $supervisor->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function ensureClientShippingAddress(User $client): UserAddress
    {
        return UserAddress::query()->updateOrCreate(
            [
                'user_id' => $client->id,
                'street_address' => self::ADDRESS['street_address'],
                'city' => self::ADDRESS['city'],
            ],
            [
                'type' => 'office',
                'full_name' => $client->name ?: 'Client One',
                'phone_number' => $client->phone ?: '+971500000001',
                'state' => self::ADDRESS['state'],
                'zip_code' => self::ADDRESS['zip_code'],
                'country' => self::ADDRESS['country'],
                'is_default' => true,
            ]
        );
    }
}
