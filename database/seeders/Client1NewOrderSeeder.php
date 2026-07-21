<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds one fresh paid shop order for client1@test.com.
 * Starting order_status is "pending" (not "confirmed") for client dashboard testing.
 *
 * Run: php artisan db:seed --class=Client1NewOrderSeeder
 */
class Client1NewOrderSeeder extends Seeder
{
    public const DEMO_MARKER = '[SEED-CLIENT1-NEW-ORDER]';

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

        $qty = 1;
        $unitPrice = (float) $product->price;
        $subtotal = round($qty * $unitPrice, 2);
        $shipping = 5.00;
        $taxPercent = 5;
        $tax = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $shipping + $tax, 2);

        $order = Order::withoutEvents(function () use ($client, $product, $subtotal, $tax, $taxPercent, $shipping, $total, $qty, $unitPrice) {
            return Order::create([
                'user_id' => $client->id,
                'guest_full_name' => $client->name ?: 'Client One',
                'guest_email' => $client->email,
                'guest_phone' => $client->phone ?: '+971500000001',
                'guest_street_address' => 'Office 302, Al Khalidiya, Corniche Road',
                'guest_city' => 'Abu Dhabi',
                'guest_state' => 'Abu Dhabi',
                'guest_zip_code' => '00000',
                'guest_country' => 'UAE',
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

        $this->command->info('Created new order #' . $order->id . ' (' . $order->publicOrderNumber() . ') for client1@test.com.');
        $this->command->info('Product: ' . $product->name . ' — AED ' . number_format($total, 2));
        $this->command->info('order_status: pending (not confirmed)');
        $this->command->info('Address: Office 302, Al Khalidiya, Corniche Road, Abu Dhabi, UAE 00000');
        $this->command->info('Login: client1@test.com / password123');
        $this->command->info('Track: GET /api/orders/' . $order->id . '/track');
    }
}
