<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Support\OrderToVisitDispatcher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds one paid guest shop order for Sanjeev (Abu Dhabi, UAE) and dispatches a supervisor visit.
 *
 * Run: php artisan db:seed --class=SanjeevAbuDhabiOrderSeeder
 */
class SanjeevAbuDhabiOrderSeeder extends Seeder
{
    public const DEMO_MARKER = '[SEED-SANJEEV-ORDER]';

    public function run(): void
    {
        $existing = Order::query()
            ->where('special_instructions', 'like', '%' . self::DEMO_MARKER . '%')
            ->first();

        if ($existing) {
            $this->command->warn('Sanjeev demo order already exists: #' . $existing->id . ' (' . $existing->publicOrderNumber() . ').');

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

        $qty = 1;
        $unitPrice = (float) $product->price;
        $subtotal = round($qty * $unitPrice, 2);
        $shipping = 5.00;
        $taxPercent = 5;
        $tax = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $shipping + $tax, 2);

        $order = Order::create([
            'guest_full_name' => 'sanjeev',
            'guest_email' => 'sanjeev.abudhabi@tandil.test',
            'guest_phone' => '+971500000999',
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
            'order_status' => 'processing',
            'special_instructions' => self::DEMO_MARKER . ' Office visit — Al Khalidiya, Corniche Road.',
            'estimated_arrival' => now()->addDays(2),
            'job_duration' => $product->job_duration,
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'price' => $unitPrice,
            'subtotal' => $subtotal,
        ]);

        Transaction::firstOrCreate(
            [
                'transactionable_type' => Order::class,
                'transactionable_id' => $order->id,
            ],
            [
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                'type' => 'payment',
                'gateway' => 'stripe',
                'payment_method' => 'stripe',
                'amount' => $total,
                'currency' => 'AED',
                'status' => 'completed',
                'gateway_transaction_id' => 'GW-' . strtoupper(Str::random(14)),
                'gateway_response' => ['status' => 'success', 'order_id' => $order->id],
                'notes' => 'Seeded payment for Sanjeev Abu Dhabi order #' . $order->id,
                'processed_at' => $order->paid_at,
            ]
        );

        $visit = OrderToVisitDispatcher::createVisitForPaidOrder($order->fresh('items.product'));

        $this->command->info('Created Sanjeev Abu Dhabi order #' . $order->id . ' (' . $order->publicOrderNumber() . ').');
        $this->command->info('Product: ' . $product->name . ' — AED ' . number_format($total, 2));
        $this->command->info('Address: Office 302, Al Khalidiya, Corniche Road, Abu Dhabi, UAE 00000');

        if ($visit) {
            $this->command->info('Visit #' . $visit->id . ' created for supervisor #' . $visit->supervisor_id . ' (area #' . $visit->area_id . ').');
        } else {
            $this->command->warn('Order created but no visit was dispatched — ensure an active Abu Dhabi area has a supervisor assigned.');
        }

        $this->command->info('Track: GET /api/orders/' . $order->id . '/track (guest email + order number).');
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
}
