<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorProfileScreenApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_profile_tab_returns_only_screen_fields(): void
    {
        ['token' => $token] = $this->makeVendorUser(withStats: true);

        $response = $this->withToken($token)->getJson('/api/vendor/profile');

        $response->assertOk()
            ->assertJsonPath('data.profile.header.name', 'Ali Vendor')
            ->assertJsonPath('data.profile.header.subtitle', 'Green Fields Agro Supplies · Al Ain, Abu Dhabi, UAE')
            ->assertJsonPath('data.profile.summary.professional_category', 'Fruits')
            ->assertJsonPath('data.profile.summary.partnership_badge.tier', 'bronze')
            ->assertJsonPath('data.profile.stats.products', 1)
            ->assertJsonPath('data.profile.stats.delivered', 1)
            ->assertJsonPath('data.profile.account_settings.0.id', 'edit_profile')
            ->assertJsonPath('data.profile.account_settings.3.title', 'Payment Methods')
            ->assertJsonMissingPath('data.vendor')
            ->assertJsonMissingPath('data.profile.application')
            ->assertJsonMissingPath('data.profile.edit_profile')
            ->assertJsonMissingPath('data.options');
    }

    public function test_profile_section_query_returns_edit_form_block(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $this->withToken($token)->getJson('/api/vendor/profile?section=edit_profile')
            ->assertOk()
            ->assertJsonPath('data.profile.edit_profile.owner_name', 'Ali Vendor')
            ->assertJsonPath('data.profile.edit_profile.email', 'vendor-profile-screen@test.com')
            ->assertJsonMissingPath('data.profile.business_information')
            ->assertJsonMissingPath('data.options');
    }

    public function test_profile_options_query_returns_dropdowns_only_when_requested(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $this->withToken($token)->getJson('/api/vendor/profile?section=business_information&options=1')
            ->assertOk()
            ->assertJsonPath('data.profile.business_information.business_name', 'Green Fields Agro Supplies')
            ->assertJsonStructure(['data' => ['options' => ['vendor_types', 'emirates']]]);
    }

    /**
     * @return array{user: User, vendor: Vendor, token: string}
     */
    private function makeVendorUser(bool $withStats = false): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'name' => 'Ali Vendor',
            'email' => 'vendor-profile-screen@test.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now()->setDate(2024, 3, 15),
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Green Fields Agro Supplies',
            'owner_name' => 'Ali Vendor',
            'email' => $user->email,
            'phone' => '+971500000001',
            'vendor_type' => 'fruits',
            'emirate' => 'Abu Dhabi',
            'city' => 'Al Ain',
            'address' => 'Industrial Area 1',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Green Fields Agro Supplies',
        ]);

        if ($withStats) {
            $category = Category::create([
                'vendor_id' => $vendor->id,
                'name' => 'Produce',
                'slug' => 'produce',
                'is_active' => true,
                'shipping_cost' => 0,
                'tax_percentage' => 0,
            ]);

            $product = Product::create([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'name' => 'Tomatoes',
                'price' => 25,
                'stock' => 10,
                'status' => 'active',
            ]);

            VendorProduct::create([
                'vendor_id' => $vendor->id,
                'product_id' => $product->id,
                'status' => 'active',
                'approval_status' => 'approved',
            ]);

            $order = Order::create([
                'guest_full_name' => 'Ahmed',
                'guest_email' => 'ahmed@test.com',
                'total_amount' => 100,
                'payment_status' => 'paid',
                'order_status' => 'delivered',
            ]);

            VendorOrderMapping::create([
                'vendor_id' => $vendor->id,
                'order_id' => $order->id,
                'status' => VendorOrderStatus::Delivered->value,
                'total_amount' => 100,
                'commission_amount' => 10,
            ]);
        }

        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        return [
            'user' => $user,
            'vendor' => $vendor->fresh('profile'),
            'token' => $token,
        ];
    }
}
