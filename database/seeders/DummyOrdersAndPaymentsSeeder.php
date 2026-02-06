<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DummyOrdersAndPaymentsSeeder extends Seeder
{
    /**
     * Seed packages, 5 paid orders with items, and payment/transaction data.
     */
    public function run(): void
    {
        $this->command->info('Seeding packages, orders, and payments...');

        // 1. Packages
        $this->seedPackages();

        // 2. Ensure we have a client and products
        $client = $this->getOrCreateClient();
        $products = $this->getOrCreateProducts();

        // 3. Create 5 paid orders with order items
        for ($i = 1; $i <= 5; $i++) {
            $this->createPaidOrder($client, $products, $i);
        }

        $this->command->info('Dummy orders and payments seeded: 5 paid orders, order items, and transactions.');
    }

    private function seedPackages(): void
    {
        $packages = [
            [
                'name' => 'Starter Pack',
                'type' => Package::TYPE_COMBINED,
                'price' => 149.00,
                'description' => 'Perfect for getting started.',
                'sort_order' => 0,
            ],
            [
                'name' => 'Fruit Lover',
                'type' => Package::TYPE_FRUIT,
                'price' => 199.00,
                'description' => 'Fresh seasonal fruits.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Vegetable Box',
                'type' => Package::TYPE_VEGETABLE,
                'price' => 179.00,
                'description' => 'Farm fresh vegetables.',
                'sort_order' => 2,
            ],
        ];

        foreach ($packages as $p) {
            Package::firstOrCreate(
                ['type' => $p['type']],
                [
                    'name' => $p['name'],
                    'slug' => Str::slug($p['name']),
                    'price' => $p['price'],
                    'description' => $p['description'] ?? null,
                    'image' => null,
                    'is_active' => true,
                    'sort_order' => $p['sort_order'],
                ]
            );
        }
        $this->command->info('Packages seeded.');
    }

    private function getOrCreateClient(): User
    {
        $client = User::where('role', 'client')->first();
        if ($client) {
            return $client;
        }
        $this->command->warn('No client user found. Creating one (client@example.com / password).');
        return User::create([
            'name' => 'Demo Client',
            'email' => 'client@example.com',
            'password' => bcrypt('password'),
            'role' => 'client',
            'status' => 'active',
        ]);
    }

    private function getOrCreateProducts()
    {
        $products = Product::limit(10)->get();
        if ($products->isNotEmpty()) {
            return $products;
        }
        $this->command->warn('No products found. Creating 3 sample products.');
        $category = Category::first();
        if (! $category) {
            $category = Category::firstOrCreate(
                ['slug' => 'general'],
                ['name' => 'General', 'is_active' => true]
            );
        }
        $names = ['Organic Fertilizer 5kg', 'Drip Irrigation Kit', 'Premium Potting Soil'];
        $prices = [89.00, 149.00, 59.00];
        $created = collect();
        foreach ($names as $idx => $name) {
            $created->push(Product::create([
                'category_id' => $category->id,
                'name' => $name,
                'price' => $prices[$idx],
                'description' => 'Sample product for orders.',
                'status' => 'active',
                'stock' => 100,
            ]));
        }
        return $created;
    }

    private function createPaidOrder(User $client, $products, int $orderNumber): void
    {
        $products = $products->random(min(3, $products->count()));
        $createdAt = Carbon::now()->subDays(rand(1, 30));

        $order = Order::create([
            'user_id' => $client->id,
            'total_amount' => 0,
            'payment_status' => 'paid',
            'payment_reference' => 'PAY-' . strtoupper(Str::random(10)),
            'payment_method' => ['paypal', 'stripe', 'cash'][array_rand(['paypal', 'stripe', 'cash'])],
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'paid_at' => $createdAt->copy()->addMinutes(5),
            'order_status' => ['processing', 'shipped', 'delivered'][array_rand(['processing', 'shipped', 'delivered'])],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $totalAmount = 0;
        foreach ($products as $product) {
            $quantity = rand(1, 4);
            $price = (float) $product->price;
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

        $order->update(['total_amount' => $totalAmount]);

        Transaction::firstOrCreate(
            [
                'transactionable_type' => Order::class,
                'transactionable_id' => $order->id,
            ],
            [
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                'type' => 'payment',
                'gateway' => $order->payment_method ?? 'paypal',
                'payment_method' => $order->payment_method ?? 'paypal',
                'amount' => $totalAmount,
                'currency' => 'AED',
                'status' => 'completed',
                'gateway_transaction_id' => 'GW-' . strtoupper(Str::random(14)),
                'gateway_response' => ['status' => 'success', 'order_id' => $order->id],
                'notes' => "Dummy payment for order #{$order->id}",
                'processed_at' => $order->paid_at,
            ]
        );
    }
}
