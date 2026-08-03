<?php

namespace Tests\Feature\Admin;

use App\Models\Emirate;
use App\Models\User;
use App\Models\VendorType;
use Database\Seeders\VendorTypeAndEmirateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorTypeAndEmirateAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(VendorTypeAndEmirateSeeder::class);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->token = $this->admin->createToken('admin')->plainTextToken;
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_admin_lists_vendor_types_with_expected_shape(): void
    {
        $response = $this->getJson('/api/admin/vendor-types', $this->auth());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    ['id', 'name', 'slug', 'is_active'],
                ],
            ]);

        $first = $response->json('data.0');
        $this->assertSame(['id', 'name', 'slug', 'is_active'], array_keys($first));
        $this->assertGreaterThanOrEqual(9, count($response->json('data')));
    }

    public function test_admin_creates_vendor_type_via_form_data(): void
    {
        $response = $this->post('/api/admin/vendor-types', [
            'name' => 'Organic Dairy',
            'slug' => '',
            'is_active' => '1',
        ], $this->auth());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Organic Dairy')
            ->assertJsonPath('data.slug', 'organic-dairy')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(['id', 'name', 'slug', 'is_active'], array_keys($response->json('data')));
        $this->assertDatabaseHas('vendor_types', ['slug' => 'organic-dairy', 'is_active' => 1]);
    }

    public function test_admin_updates_and_toggles_and_deletes_vendor_type(): void
    {
        $id = VendorType::query()->where('slug', 'honey')->value('id');

        $this->post('/api/admin/vendor-types/'.$id, [
            'name' => 'Natural Honey',
            'is_active' => '1',
        ], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.name', 'Natural Honey')
            ->assertJsonPath('data.slug', 'honey');

        $this->postJson('/api/admin/vendor-types/'.$id.'/toggle-status', [], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson('/api/admin/vendor-types/'.$id, [], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertDatabaseMissing('vendor_types', ['id' => $id]);
    }

    public function test_admin_emirates_crud_via_form_data(): void
    {
        $create = $this->post('/api/admin/emirates', [
            'name' => 'Test Emirate',
            'slug' => 'test-emirate',
            'is_active' => '1',
        ], $this->auth());

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Test Emirate')
            ->assertJsonPath('data.slug', 'test-emirate')
            ->assertJsonPath('data.is_active', true);
        $this->assertSame(['id', 'name', 'slug', 'is_active'], array_keys($create->json('data')));

        $id = (int) $create->json('data.id');

        $this->post('/api/admin/emirates/'.$id, [
            'name' => 'Test Emirate Updated',
        ], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.name', 'Test Emirate Updated');

        $this->postJson('/api/admin/emirates/'.$id.'/toggle-status', [], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/admin/emirates?is_active=1', $this->auth())
            ->assertOk()
            ->assertJsonMissing(['name' => 'Test Emirate Updated']);

        $this->deleteJson('/api/admin/emirates/'.$id, [], $this->auth())
            ->assertOk();

        $this->assertDatabaseMissing('emirates', ['id' => $id]);
    }

    public function test_non_admin_cannot_manage_vendor_types(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $token = $client->createToken('c')->plainTextToken;

        $this->getJson('/api/admin/vendor-types', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(403);
    }

    public function test_public_registration_options_returns_active_only(): void
    {
        VendorType::query()->where('slug', 'nuts')->update(['is_active' => false]);
        Emirate::query()->where('slug', 'ajman')->update(['is_active' => false]);

        $response = $this->getJson('/api/vendor/auth/registration-options');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'vendor_types' => [['id', 'name', 'slug', 'is_active']],
                    'emirates' => [['id', 'name', 'slug', 'is_active']],
                ],
            ]);

        $typeSlugs = collect($response->json('data.vendor_types'))->pluck('slug')->all();
        $emirateSlugs = collect($response->json('data.emirates'))->pluck('slug')->all();

        $this->assertNotContains('nuts', $typeSlugs);
        $this->assertContains('fruits', $typeSlugs);
        $this->assertNotContains('ajman', $emirateSlugs);
        $this->assertContains('dubai', $emirateSlugs);

        foreach ($response->json('data.vendor_types') as $row) {
            $this->assertTrue($row['is_active']);
            $this->assertSame(['id', 'name', 'slug', 'is_active'], array_keys($row));
        }
    }
}
