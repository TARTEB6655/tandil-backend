<?php

namespace Tests\Feature\Web;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAreasPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_areas_index_page_loads_for_admin_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Area::factory()->create([
            'name' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/areas');

        $response->assertStatus(200);
        $response->assertSee('Operational Area List');
    }
}

