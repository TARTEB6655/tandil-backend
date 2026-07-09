<?php

namespace Database\Seeders;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Models\VendorProduct;
use App\Services\Vendor\VendorOrderSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VendorDemoOrdersSeeder extends Seeder
{
    public const DEMO_MARKER = '[DEMO_VENDOR_ORDER]';

    public function run(): void
    {
        $vendorId = (int) (getenv('SEED_VENDOR_ID') ?: 0);
        $vendor = $vendorId > 0
            ? Vendor::query()->find($vendorId)
            : Vendor::query()
                ->where('status', VendorStatus::Approved->value)
                ->orderBy('id')
                ->first();

        if ($vendor === null) {
            $this->command->error('No approved vendor found. Approve a vendor first or run with SEED_VENDOR_ID=123 php artisan db:seed --class=VendorDemoOrdersSeeder');

            return;
        }

        $product = Product::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->first();

        if ($product === null) {
            $product = $this->createDemoProduct($vendor);
            $this->command->warn('No vendor product found — created demo product: '.$product->name);
        }

        $scenarios = [
            [
                'customer' => [
                    'name' => 'Ahmed Ali',
                    'email' => 'ahmed.demo@tandil.test',
                    'phone' => '+971500000101',
                    'street' => 'Business Bay, Tower A',
                    'city' => 'Dubai',
                    'country' => 'UAE',
                ],
                'qty' => 2,
                'unit_price' => 17.50,
                'status' => VendorOrderStatus::Pending,
                'notes' => 'Please call before delivery.',
                'tracking' => null,
            ],
            [
                'customer' => [
                    'name' => 'Sarah Hassan',
                    'email' => 'sarah@email.com',
                    'phone' => '+971509876543',
                    'street' => 'Jumeirah Beach Residence, Apt 12B',
                    'city' => 'Dubai',
                    'country' => 'UAE',
                ],
                'qty' => 1,
                'unit_price' => 35.00,
                'status' => VendorOrderStatus::Confirmed,
                'notes' => 'Please deliver in the afternoon between 2-4 PM',
                'tracking' => null,
            ],
            [
                'customer' => [
                    'name' => 'Omar Khan',
                    'email' => 'omar.demo@tandil.test',
                    'phone' => '+971500000303',
                    'street' => 'Al Maryah Island',
                    'city' => 'Abu Dhabi',
                    'country' => 'UAE',
                ],
                'qty' => 3,
                'unit_price' => 12.00,
                'status' => VendorOrderStatus::Shipped,
                'notes' => 'Leave at reception if not home.',
                'tracking' => 'TRK-2026-0001',
            ],
        ];

        $sync = app(VendorOrderSyncService::class);
        $createdMappings = [];

        foreach ($scenarios as $index => $scenario) {
            $subtotal = round($scenario['qty'] * $scenario['unit_price'], 2);
            $shipping = 5.00;
            $tax = round($subtotal * 0.05, 2);
            $total = round($subtotal + $shipping + $tax, 2);

            $order = Order::create([
                'guest_full_name' => $scenario['customer']['name'],
                'guest_email' => $scenario['customer']['email'],
                'guest_phone' => $scenario['customer']['phone'],
                'guest_street_address' => $scenario['customer']['street'],
                'guest_city' => $scenario['customer']['city'],
                'guest_country' => $scenario['customer']['country'],
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'tax_percent' => 5,
                'shipping_amount' => $shipping,
                'total_amount' => $total,
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
                'payment_reference' => 'demo_pay_'.Str::lower(Str::random(8)),
                'order_status' => 'processing',
                'special_instructions' => self::DEMO_MARKER.' '.$scenario['notes'],
                'estimated_arrival' => now()->addDays(3 + $index),
                'paid_at' => now()->subHours(6 - $index),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $scenario['qty'],
                'price' => $scenario['unit_price'],
                'subtotal' => $subtotal,
            ]);

            $sync->syncFromOrder($order->fresh('items.product'));

            $mapping = VendorOrderMapping::query()
                ->where('order_id', $order->id)
                ->where('vendor_id', $vendor->id)
                ->first();

            if ($mapping === null) {
                $this->command->warn('Skipping order #'.$order->id.' — vendor mapping was not created.');

                continue;
            }

            $this->applyVendorStatus($mapping, $scenario['status'], $scenario['tracking']);
            $createdMappings[] = $mapping->fresh();

            $this->command->info(sprintf(
                'Demo order #%d → mapping #%d (%s) — %s — AED %s',
                $order->id,
                $mapping->id,
                $scenario['status']->value,
                $scenario['customer']['name'],
                number_format($total, 2)
            ));
        }

        if ($createdMappings === []) {
            $this->command->error('No demo vendor orders were created.');

            return;
        }

        $this->command->newLine();
        $this->command->info('Created '.count($createdMappings).' demo vendor orders for vendor #'.$vendor->id.'.');
        $this->command->info('Test with: GET /api/vendor/orders (vendor_token for this vendor).');
        $this->command->info('Remove later: php artisan db:seed --class=VendorDemoOrdersCleanupSeeder');
    }

    private function createDemoProduct(Vendor $vendor): Product
    {
        $category = Category::query()->where('is_active', true)->first();

        if ($category === null) {
            $category = Category::create([
                'name' => 'Demo Produce',
                'slug' => 'demo-produce-'.Str::lower(Str::random(4)),
                'is_active' => true,
                'shipping_cost' => 0,
                'tax_percentage' => 5,
            ]);
        }

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Fresh Tomatoes (Demo)',
            'price' => 35,
            'stock' => 100,
            'status' => 'active',
        ]);

        VendorProduct::firstOrCreate(
            ['vendor_id' => $vendor->id, 'product_id' => $product->id],
            ['status' => 'active', 'approval_status' => 'approved']
        );

        return $product;
    }

    private function applyVendorStatus(
        VendorOrderMapping $mapping,
        VendorOrderStatus $targetStatus,
        ?string $trackingNumber
    ): void {
        $flow = [
            VendorOrderStatus::Pending,
            VendorOrderStatus::Confirmed,
            VendorOrderStatus::Processing,
            VendorOrderStatus::Shipped,
            VendorOrderStatus::Delivered,
        ];

        $targetIndex = array_search($targetStatus, $flow, true);
        if ($targetIndex === false) {
            $mapping->update([
                'status' => $targetStatus->value,
                'tracking_number' => $trackingNumber,
            ]);

            return;
        }

        VendorOrderStatusLog::query()
            ->where('vendor_order_mapping_id', $mapping->id)
            ->delete();

        foreach (array_slice($flow, 0, $targetIndex + 1) as $index => $status) {
            $updates = ['status' => $status->value];
            if ($status === VendorOrderStatus::Shipped && $trackingNumber) {
                $updates['tracking_number'] = $trackingNumber;
            }

            $mapping->update($updates);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => $status->value,
                'changed_by' => null,
                'note' => $index === 0 ? 'Demo order placed.' : 'Demo status update.',
                'created_at' => now()->subDays($targetIndex - $index)->subHours(2),
                'updated_at' => now()->subDays($targetIndex - $index)->subHours(2),
            ]);
        }
    }
}
