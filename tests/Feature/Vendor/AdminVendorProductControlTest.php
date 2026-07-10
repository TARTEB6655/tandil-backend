<?php

namespace Tests\Feature\Vendor;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorProductControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_disable_and_enable_vendor_product(): void
    {
        [$admin, $vendor, $vp] = $this->seedVendorProduct('approved');

        $this->actingAs($admin)
            ->post(route('admin.vendors.products.disable', [$vendor, $vp]), ['reason' => 'Policy violation'])
            ->assertRedirect();

        $vp->refresh();
        $this->assertTrue($vp->disabled_by_admin);
        $this->assertSame('inactive', $vp->status);
        $this->assertSame('archived', $vp->product->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.vendors.products.enable', [$vendor, $vp]))
            ->assertRedirect();

        $vp->refresh();
        $this->assertFalse($vp->disabled_by_admin);
        $this->assertSame('active', $vp->status);
        $this->assertSame('active', $vp->product->fresh()->status);
    }

    public function test_admin_can_bulk_disable_products(): void
    {
        [$admin, $vendor, $vp] = $this->seedVendorProduct('approved');
        $vp2 = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => Product::create(['name' => 'Second', 'price' => 10, 'status' => 'active', 'category_id' => $vp->product->category_id])->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendors.products.bulk', $vendor), [
                'action' => 'disable',
                'product_ids' => [$vp->id, $vp2->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($vp->fresh()->disabled_by_admin);
        $this->assertTrue($vp2->fresh()->disabled_by_admin);
    }

    public function test_admin_product_pages_are_accessible(): void
    {
        [$admin, $vendor, $vp] = $this->seedVendorProduct('pending');

        $this->actingAs($admin)->get(route('admin.vendors.products', $vendor))->assertOk();
        $this->actingAs($admin)->get(route('admin.vendors.products.show', [$vendor, $vp]))->assertOk();
    }

    /**
     * @return array{0: User, 1: Vendor, 2: VendorProduct}
     */
    private function seedVendorProduct(string $approval): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $vendorUser->id, 'status' => 'approved', 'approved_at' => now()]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Store',
            'owner_name' => 'Owner',
            'email' => 'vendor@test.com',
        ]);

        $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat']);
        $product = Product::create(['name' => 'Widget', 'price' => 25, 'status' => 'active', 'category_id' => $category->id]);
        $vp = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => $approval,
        ]);

        return [$admin, $vendor, $vp];
    }
}
