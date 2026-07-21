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
 * Seeds one fresh paid shop order for client1@test.com.
 * Starting order_status is "pending" (not "confirmed") for client dashboard testing.
 * Dispatches a supervisor visit so the job appears in supervisor dashboard.
 *
 * Run: php artisan db:seed --class=Client1NewOrderSeeder
 */
class Client1NewOrderSeeder extends Seeder
{
    public const DEMO_MARKER = '[SEED-CLIENT1-NEW-ORDER]';

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
            ->orderBy('id')
            ->first();

        if ($product === null) {
            $this->command->error('No active products found. Run ServicesCategoriesAndProductsSeeder first.');

            return;
        }

        $this->ensureAbuDhabiAreaWithSupervisor();
        $shippingAddress = $this->ensureClientShippingAddress($client);

        $existing = Order::query()
            ->where('special_instructions', 'like', '%' . self::DEMO_MARKER . '%')
            ->latest('id')
            ->first();

        if ($existing) {
            $this->repairExistingOrder($existing, $client, $shippingAddress);

            return;
        }

        $qty = 1;
        $unitPrice = (float) $product->price;
        $subtotal = round($qty * $unitPrice, 2);
        $shipping = 5.00;
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
                'special_instructions' => self::DEMO_MARKER . ' Fresh order — pending team confirmation.',
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
            'notes' => 'Seeded payment for client1 new order #' . $order->id,
            'processed_at' => $order->paid_at,
        ]);

        $visit = OrderToVisitDispatcher::createVisitForPaidOrder($order->fresh(['items.product', 'shippingAddress']));

        $this->printSummary($order, $product, $total, $visit);
    }

    private function repairExistingOrder(Order $order, User $client, UserAddress $shippingAddress): void
    {
        $order->update([
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
            'payment_status' => 'paid',
            'paid_at' => $order->paid_at ?? now(),
        ]);

        $visit = Visit::query()->where('order_id', $order->id)->first()
            ?? OrderToVisitDispatcher::createVisitForPaidOrder($order->fresh(['items.product', 'shippingAddress']));

        $product = $order->items()->with('product')->first()?->product;

        $this->command->warn('Existing client1 demo order repaired: #' . $order->id . ' (' . $order->publicOrderNumber() . ').');
        $this->printSummary($order->fresh(['items.product', 'shippingAddress']), $product, (float) $order->total_amount, $visit);
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

    private function ensureAbuDhabiAreaWithSupervisor(): void
    {
        $supervisor = User::query()
            ->where('email', 'supervisor1@test.com')
            ->where('role', 'supervisor')
            ->first();

        if ($supervisor === null) {
            $supervisor = User::query()->where('role', 'supervisor')->orderBy('id')->first();
        }

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

        $area->update([
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);

        DB::table('area_supervisor')->updateOrInsert(
            ['area_id' => $area->id, 'user_id' => $supervisor->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function printSummary(Order $order, ?Product $product, float $total, ?Visit $visit): void
    {
        $this->command->info('Order #' . $order->id . ' (' . $order->publicOrderNumber() . ') for client1@test.com.');
        if ($product) {
            $this->command->info('Product: ' . $product->name . ' — AED ' . number_format($total, 2));
        }
        $this->command->info('order_status: ' . $order->order_status);
        $this->command->info('Address: ' . self::ADDRESS['street_address'] . ', ' . self::ADDRESS['city'] . ', ' . self::ADDRESS['country'] . ' ' . self::ADDRESS['zip_code']);
        if ($visit) {
            $this->command->info('Visit #' . $visit->id . ' assigned to supervisor #' . $visit->supervisor_id . ' (area #' . $visit->area_id . ').');
        } else {
            $this->command->warn('No visit dispatched — ensure Abu Dhabi area has an active supervisor.');
        }
        $this->command->info('Login: client1@test.com / password123');
        $this->command->info('Track: GET /api/orders/' . $order->id . '/track');
    }
}
