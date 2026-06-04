<?php

namespace Database\Seeders;

use App\Enums\VendorDocumentType;
use App\Enums\VendorProductApprovalStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorDocument;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Test vendor accounts for QA and Postman (run after RoleSeeder).
 *
 * vendor1@test.com — Approved (products + sample order)
 * vendor2@test.com — Pending
 * vendor3@test.com — Suspended
 *
 * Password for all: password123
 */
class VendorTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $this->command->info('Creating test vendor users...');

        $v1 = $this->upsertVendor(
            email: 'vendor1@test.com',
            ownerName: 'Ahmed Al Vendor',
            businessName: 'Green Valley Farms',
            status: VendorStatus::Approved,
            phone: '70001001',
            commissionRate: 8.0,
        );

        $v2 = $this->upsertVendor(
            email: 'vendor2@test.com',
            ownerName: 'Sara Pending',
            businessName: 'Desert Bloom Co',
            status: VendorStatus::Pending,
            phone: '70001002',
        );

        $v3 = $this->upsertVendor(
            email: 'vendor3@test.com',
            ownerName: 'Omar Suspended',
            businessName: 'Suspended Supplies LLC',
            status: VendorStatus::Suspended,
            phone: '70001003',
        );

        $this->seedDocuments($v1);
        $this->seedProductsAndOrder($v1);

        $this->command->info('');
        $this->command->info('Test vendors ready:');
        $this->command->info('  vendor1@test.com / password123 — APPROVED (products + order)');
        $this->command->info('  vendor2@test.com / password123 — PENDING');
        $this->command->info('  vendor3@test.com / password123 — SUSPENDED');
    }

    private function upsertVendor(
        string $email,
        string $ownerName,
        string $businessName,
        VendorStatus $status,
        string $phone,
        ?float $commissionRate = null,
    ): Vendor {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $ownerName,
                'password' => 'password123',
                'role' => 'vendor',
                'status' => 'active',
                'phone' => $phone,
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles([Role::findByName('vendor', 'web')]);

        $vendor = Vendor::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status->value,
                'commission_rate' => $commissionRate,
                'approved_at' => $status === VendorStatus::Approved ? now() : null,
                'rejected_at' => $status === VendorStatus::Rejected ? now() : null,
                'suspended_at' => $status === VendorStatus::Suspended ? now() : null,
                'rejection_reason' => null,
            ]
        );

        VendorProfile::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'business_name' => $businessName,
                'owner_name' => $ownerName,
                'email' => $email,
                'phone' => $phone,
                'address' => 'Dubai, UAE — Test address',
                'tax_vat_number' => 'TRN-TEST-'.substr(md5($email), 0, 6),
                'description' => 'Seeded test vendor for marketplace QA.',
            ]
        );

        if (! $vendor->approvalLogs()->where('action', 'seeded')->exists()) {
            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => null,
                'action' => 'seeded',
                'old_status' => null,
                'new_status' => $status->value,
                'notes' => 'Created by VendorTestUsersSeeder',
            ]);
        }

        return $vendor->fresh(['profile', 'user']);
    }

    private function seedDocuments(Vendor $vendor): void
    {
        foreach ([VendorDocumentType::TradeLicense, VendorDocumentType::BusinessProof] as $type) {
            VendorDocument::updateOrCreate(
                ['vendor_id' => $vendor->id, 'type' => $type->value],
                [
                    'file_path' => "vendors/{$vendor->id}/documents/{$type->value}_sample.pdf",
                    'original_name' => "{$type->value}_sample.pdf",
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'admin_notes' => 'Auto-verified test document',
                ]
            );
        }
    }

    private function seedProductsAndOrder(Vendor $vendor): void
    {
        $category = Category::query()->first();
        if ($category === null) {
            $category = Category::factory()->create([
                'name' => 'Test Produce',
                'shipping_cost' => 25,
                'tax_percentage' => 5,
            ]);
        }

        $products = [
            ['name' => 'Organic Tomatoes 1kg', 'price' => 18.50, 'stock' => 120],
            ['name' => 'Fresh Cucumbers 500g', 'price' => 9.75, 'stock' => 3],
        ];

        $productIds = [];
        foreach ($products as $row) {
            $product = Product::updateOrCreate(
                ['vendor_id' => $vendor->id, 'sku' => 'V1-'.strtoupper(substr(md5($row['name']), 0, 8))],
                [
                    'category_id' => $category->id,
                    'name' => $row['name'],
                    'description' => 'Seeded vendor product',
                    'price' => $row['price'],
                    'status' => 'active',
                    'product_type' => Product::TYPE_SIMPLE,
                    'stock' => $row['stock'],
                    'track_quantity' => true,
                ]
            );

            $vp = VendorProduct::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'vendor_id' => $vendor->id,
                    'status' => 'active',
                    'approval_status' => VendorProductApprovalStatus::Approved->value,
                    'approved_at' => now(),
                ]
            );

            VendorInventory::updateOrCreate(
                ['vendor_product_id' => $vp->id],
                ['quantity' => $row['stock'], 'low_stock_threshold' => 5]
            );

            if (! $vp->currentPrice) {
                VendorProductPrice::create([
                    'vendor_product_id' => $vp->id,
                    'price' => $row['price'],
                    'currency' => 'AED',
                    'effective_from' => now(),
                ]);
            }

            $productIds[] = $product->id;
        }

        $client = User::updateOrCreate(
            ['email' => 'client1@test.com'],
            [
                'name' => 'Client One',
                'password' => 'password123',
                'role' => 'client',
                'status' => 'active',
                'phone' => '70000001',
            ]
        );
        $client->syncRoles([Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web'])]);

        if ($productIds === []) {
            return;
        }

        $order = Order::create([
            'user_id' => $client->id,
            'subtotal_amount' => 28.25,
            'tax_amount' => 1.41,
            'shipping_amount' => 25.00,
            'total_amount' => 54.66,
            'tax_percent' => 5,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productIds[0],
            'quantity' => 1,
            'price' => 18.50,
            'subtotal' => 18.50,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productIds[1],
            'quantity' => 1,
            'price' => 9.75,
            'subtotal' => 9.75,
        ]);

        app(\App\Services\Vendor\VendorOrderSyncService::class)->syncFromOrder($order->fresh('items.product'));
    }
}
