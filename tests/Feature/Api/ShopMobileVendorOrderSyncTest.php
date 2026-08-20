<?php

namespace Tests\Feature\Api;

use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopMobileVendorOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_stripe_confirm_creates_vendor_order_mapping(): void
    {
        Config::set('services.stripe.secret', 'sk_test_vendor_sync');
        Config::set('services.stripe.key', 'pk_test_vendor_sync');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '0');

        $vendorUser = User::factory()->create(['role' => 'vendor', 'email' => 'vendor-sync@test.com']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Vendor Sync Shop',
            'owner_name' => 'Owner',
            'email' => 'vendor-sync@test.com',
            'phone' => '+971500000001',
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $client = User::factory()->create(['role' => 'client']);
        Cart::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $amountMinor = 10500; // 100 + 5% tax

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amountMinor) {
            $url = $request->url();
            if (str_contains($url, 'payment_intents/pi_vendor_sync') && $request->method() === 'GET') {
                return Http::response([
                    'id' => 'pi_vendor_sync',
                    'status' => 'succeeded',
                    'amount' => $amountMinor,
                ], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_vendor_sync'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_vendor_sync',
                    'client_secret' => 'pi_vendor_sync_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$client->createToken('t')->plainTextToken,
        ];

        $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => [
                'full_name' => 'Test User',
                'phone' => '+971501234567',
                'street' => 'Sheikh Zayed Road',
                'city' => 'Dubai',
                'state' => 'DXB',
                'zip_code' => '00000',
                'country' => 'UAE',
            ],
        ], $headers)->assertOk();

        $this->postJson('/api/shop/checkout/confirm', [
            'payment_intent_id' => 'pi_vendor_sync',
        ], $headers)->assertStatus(201);

        $this->assertDatabaseHas('vendor_order_mappings', [
            'vendor_id' => $vendor->id,
        ]);

        $mapping = VendorOrderMapping::where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($mapping);
        $this->assertNotNull($mapping->vendor_notified_at);

        $vendorUser->refresh();
        $this->assertSame(1, $vendorUser->notifications()->count());
        $notification = $vendorUser->notifications()->first();
        $this->assertSame(
            \App\Notifications\VendorNewPaidOrderNotification::class,
            $notification->type
        );
        $this->assertSame('New paid order', $notification->data['title'] ?? null);
        $this->assertSame($mapping->id, $notification->data['meta']['vendor_order_mapping_id'] ?? null);

        // Confirm again / re-notify must not duplicate.
        \App\Support\OrderVendorNotifier::notifyVendorsForPaidOrder(
            \App\Models\Order::where('payment_reference', 'pi_vendor_sync')->firstOrFail()
        );
        $this->assertSame(1, $vendorUser->fresh()->notifications()->count());

        Sanctum::actingAs($vendorUser);
        $list = $this->getJson('/api/vendor/orders');
        $list->assertOk();
        $ids = collect($list->json('data.items') ?? $list->json('data') ?? [])
            ->pluck('id')
            ->all();
        $this->assertContains($mapping->id, $ids);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);
        $adminList = $this->getJson('/api/admin/marketplace/orders?exclude_demo=1');
        $adminList->assertOk();
        $adminIds = collect($adminList->json('data.items') ?? [])->pluck('id')->all();
        $this->assertContains($mapping->id, $adminIds);
        $adminRow = collect($adminList->json('data.items'))->firstWhere('id', $mapping->id);
        $this->assertFalse((bool) ($adminRow['is_demo'] ?? true));
        $this->assertSame('Vendor Sync Shop', $adminRow['vendor_name'] ?? null);
    }
}
