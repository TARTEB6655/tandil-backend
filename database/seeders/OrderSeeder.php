<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates dummy orders with order items for e-commerce testing.
     */
    public function run()
    {
        // Get all products and users (clients)
        $products = Product::all();
        $users = User::where('role', 'client')->get();

        // If no client users exist, create some
        if ($users->isEmpty()) {
            $this->command->warn('No client users found. Creating sample clients...');
            for ($i = 1; $i <= 10; $i++) {
                $users->push(User::create([
                    'name' => 'Client ' . $i,
                    'email' => 'client' . $i . '@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'client',
                    'status' => 'active',
                ]));
            }
        }

        if ($products->isEmpty()) {
            $this->command->error('No products found. Please run ProductSeeder first.');
            return;
        }

        $this->command->info('Creating orders with order items...');

        // Payment statuses
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
        $orderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

        // Create 50 orders
        for ($i = 1; $i <= 50; $i++) {
            // Random user
            $user = $users->random();

            // Random payment and order status
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $orderStatus = $orderStatuses[array_rand($orderStatuses)];

            // Random date within last 3 months
            $createdAt = Carbon::now()->subDays(rand(0, 90))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => 0, // Will calculate after items
                'payment_status' => $paymentStatus,
                'payment_reference' => $paymentStatus === 'paid' ? 'PAY-' . strtoupper(uniqid()) : null,
                'paid_at' => $paymentStatus === 'paid' ? $createdAt->copy()->addMinutes(rand(5, 60)) : null,
                'order_status' => $orderStatus,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addDays(rand(0, 5)),
            ]);

            // Add 1-5 random products to order
            $numItems = rand(1, 5);
            $selectedProducts = $products->random(min($numItems, $products->count()));
            $totalAmount = 0;

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 5);
                $price = $product->price;
                $subtotal = $quantity * $price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $totalAmount += $subtotal;
            }

            // Update order total
            $order->update(['total_amount' => $totalAmount]);

            if ($i % 10 === 0) {
                $this->command->info("Created {$i} orders...");
            }
        }

        $this->command->info('Orders seeded successfully!');
        $this->command->info('Total orders: ' . Order::count());
        $this->command->info('Total order items: ' . OrderItem::count());
    }
}
