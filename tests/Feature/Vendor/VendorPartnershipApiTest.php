<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPartnership;
use App\Models\VendorPartnershipApplication;
use App\Models\VendorPartnershipTier;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Database\Seeders\VendorPartnershipTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorPartnershipApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
        $this->seed(VendorPartnershipTierSeeder::class);
    }

    public function test_vendor_can_view_partnership_tiers_and_submit_application(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);
        $basic = VendorPartnershipTier::where('slug', 'basic')->firstOrFail();

        $this->withToken($token)->getJson('/api/vendor/partnership/tiers')
            ->assertOk()
            ->assertJsonPath('data.tiers.0.slug', 'basic')
            ->assertJsonPath('data.tiers.0.price', 200);

        $this->withToken($token)->getJson('/api/vendor/partnership')
            ->assertOk()
            ->assertJsonPath('data.has_partnership', false);

        $this->withToken($token)->postJson('/api/vendor/partnership/applications', [
            'tier_id' => $basic->id,
            'estimated_products' => 15,
            'business_description' => 'We sell organic gifts.',
            'contact_phone' => '+971500000001',
            'payment_method' => 'credit_card',
        ])->assertCreated()
            ->assertJsonPath('data.application.status', 'pending')
            ->assertJsonPath('data.application.tier.slug', 'basic');
    }

    public function test_admin_can_manage_tiers_and_approve_application(): void
    {
        ['vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);
        $admin = $this->makeAdminUser();
        $silver = VendorPartnershipTier::where('slug', 'silver')->firstOrFail();

        $application = VendorPartnershipApplication::create([
            'vendor_id' => $vendor->id,
            'tier_id' => $silver->id,
            'type' => 'new',
            'estimated_products' => 30,
            'business_description' => 'Gift shop in Dubai.',
            'contact_phone' => '+971500000002',
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $this->withToken($admin['token'])->getJson('/api/admin/vendor-partnership/tiers')
            ->assertOk()
            ->assertJsonCount(5, 'data.tiers');

        $this->withToken($admin['token'])->putJson('/api/admin/vendor-partnership/tiers/'.$silver->id, array_merge(
            $this->tierPayload($silver),
            ['price' => 450]
        ))->assertOk()
            ->assertJsonPath('data.tier.price', 450);

        $this->withToken($admin['token'])->postJson('/api/admin/vendor-partnership/applications/'.$application->id.'/approve', [
            'admin_notes' => 'Approved after review.',
        ])->assertOk()
            ->assertJsonPath('data.partnership.tier.slug', 'silver');

        $this->assertDatabaseHas('vendor_partnerships', [
            'vendor_id' => $vendor->id,
            'tier_id' => $silver->id,
            'status' => 'active',
        ]);
    }

    public function test_vendor_cannot_add_products_without_partnership(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);
        $category = $this->makeCategory();

        $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'Blocked Product',
            'price' => 25,
            'category_id' => $category->id,
        ])->assertStatus(403)
            ->assertJsonPath('errors.upgrade_required', true)
            ->assertJsonPath('errors.limit', 'partnership_required');
    }

    public function test_vendor_cannot_exceed_product_limit_for_tier(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);
        $basic = VendorPartnershipTier::where('slug', 'basic')->firstOrFail();
        $basic->update(['max_product_listings' => 1]);
        $this->assignPartnership($vendor, $basic);
        $category = $this->makeCategory();

        $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'First Product',
            'price' => 20,
            'category_id' => $category->id,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'Second Product',
            'price' => 30,
            'category_id' => $category->id,
        ])->assertStatus(403)
            ->assertJsonPath('errors.limit', 'max_product_listings')
            ->assertJsonPath('errors.max', 1)
            ->assertJsonPath('errors.tier', 'basic');
    }

    public function test_vendor_partnership_dashboard_returns_usage_stats(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);
        $gold = VendorPartnershipTier::where('slug', 'gold')->firstOrFail();
        $this->assignPartnership($vendor, $gold);

        $category = $this->makeCategory();
        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $this->withToken($token)->getJson('/api/vendor/partnership')
            ->assertOk()
            ->assertJsonPath('data.has_partnership', true)
            ->assertJsonPath('data.tier.slug', 'gold')
            ->assertJsonPath('data.usage.total_products', 1)
            ->assertJsonPath('data.limits.marketing_exposure', 'high');
    }

    /**
     * @return array{user: User, vendor: Vendor, token: string}
     */
    private function makeVendorUser(VendorStatus $status): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => $status->value,
            'approved_at' => $status === VendorStatus::Approved ? now() : null,
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Partnership Store',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        return [
            'user' => $user->fresh('vendor'),
            'vendor' => $vendor->fresh(),
            'token' => $token,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    private function makeAdminUser(): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return [
            'user' => $user,
            'token' => $user->createToken('test', ['admin'])->plainTextToken,
        ];
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Gifts',
            'slug' => 'gifts-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);
    }

    private function assignPartnership(Vendor $vendor, VendorPartnershipTier $tier): VendorPartnership
    {
        return VendorPartnership::create([
            'vendor_id' => $vendor->id,
            'tier_id' => $tier->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths($tier->duration_months),
            'next_payment_at' => now()->addMonths($tier->duration_months),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tierPayload(VendorPartnershipTier $tier): array
    {
        return [
            'slug' => $tier->slug,
            'name' => $tier->name,
            'badge_color' => $tier->badge_color,
            'price' => (float) $tier->price,
            'currency' => $tier->currency,
            'duration_months' => $tier->duration_months,
            'required_products_min' => $tier->required_products_min,
            'required_products_max' => $tier->required_products_max,
            'max_product_listings' => $tier->max_product_listings,
            'max_partner_product_images' => $tier->max_partner_product_images,
            'marketing_exposure' => $tier->marketing_exposure,
            'social_media_posts_per_month' => $tier->social_media_posts_per_month,
            'app_banners' => $tier->app_banners,
            'home_banner_size' => $tier->home_banner_size,
            'benefits' => $tier->benefits,
            'features' => $tier->features,
            'sort_order' => $tier->sort_order,
            'is_active' => true,
        ];
    }
}
