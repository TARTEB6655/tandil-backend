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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorProfileScreenApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_get_profile_returns_edit_profile_form_fields(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(withStats: true);

        $this->withToken($token)->getJson('/api/vendor/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.header.name', 'Ali Vendor')
            ->assertJsonPath('data.profile.stats.products', 1)
            ->assertJsonPath('data.profile.edit_profile.title', 'Edit Profile')
            ->assertJsonPath('data.profile.edit_profile.editable.business_name', 'Green Fields Agro Supplies')
            ->assertJsonPath('data.profile.edit_profile.editable.owner_name', 'Ali Vendor')
            ->assertJsonPath('data.profile.edit_profile.editable.city', 'Al Ain')
            ->assertJsonPath('data.profile.edit_profile.editable.bank_name', 'Emirates NBD')
            ->assertJsonPath('data.profile.edit_profile.verified_by_admin.emirate', 'Abu Dhabi')
            ->assertJsonPath('data.profile.edit_profile.verified_by_admin.locked', true)
            ->assertJsonPath('data.profile.read_only.vendor_id', $vendor->id)
            ->assertJsonMissingPath('data.profile.business_information')
            ->assertJsonMissingPath('data.profile.location_address')
            ->assertJsonMissingPath('data.profile.payment_methods');
    }

    public function test_post_profile_returns_same_edit_profile_shape_as_get(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $this->withToken($token)->post('/api/vendor/profile', [
            'owner_name' => 'Updated Vendor',
            'phone' => '+971500000099',
            'description' => 'Updated store description',
            'business_name' => 'Updated Business',
            'address' => 'New Address',
            'city' => 'Dubai',
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'delivery_radius' => 30,
            'minimum_order_amount' => 75,
            'bank_name' => 'ADCB',
            'iban' => 'AE123456789012345678901',
            'account_holder_name' => 'Updated Business',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.profile.edit_profile.editable.owner_name', 'Updated Vendor')
            ->assertJsonPath('data.profile.edit_profile.editable.business_name', 'Updated Business')
            ->assertJsonPath('data.profile.edit_profile.editable.delivery_radius', 30)
            ->assertJsonPath('data.profile.edit_profile.editable.minimum_order_amount', 75)
            ->assertJsonPath('data.profile.edit_profile.store_branding.logo_url', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonMissingPath('data.profile.business_information');
    }

    public function test_vendor_cannot_update_restricted_profile_fields(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $this->withToken($token)->postJson('/api/vendor/profile', [
            'status' => 'approved',
            'commission_rate' => 5,
            'trade_license_number' => 'HACKED',
            'vendor_type' => 'meat',
            'email' => 'hacked@test.com',
            'emirate' => 'Dubai',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'status',
                'commission_rate',
                'trade_license_number',
                'vendor_type',
                'email',
                'emirate',
                'password',
                'password_confirmation',
            ]);
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
            'commission_rate' => 12.5,
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Green Fields Agro Supplies',
            'owner_name' => 'Ali Vendor',
            'email' => $user->email,
            'phone' => '+971500000001',
            'vendor_type' => 'fruits',
            'trade_license_number' => 'TL-ORIGINAL',
            'emirate' => 'Abu Dhabi',
            'city' => 'Al Ain',
            'address' => 'Industrial Area 1',
            'operating_hours' => '08:00 - 22:00',
            'delivery_radius' => 25,
            'minimum_order_amount' => 50,
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
