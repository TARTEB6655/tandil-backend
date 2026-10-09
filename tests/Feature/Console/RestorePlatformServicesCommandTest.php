<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestorePlatformServicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_reverts_matching_category_to_service(): void
    {
        $category = Category::factory()->create([
            'name' => 'تصميم لاندسكيب',
            'slug' => 'landscape',
            'is_active' => true,
        ]);

        $this->artisan('catalog:restore-platform-services', ['--slug' => 'landscape'])
            ->assertSuccessful();

        $this->assertDatabaseHas('services', [
            'slug' => 'landscape',
            'name' => 'تصميم لاندسكيب',
        ]);
        $category->refresh();
        $this->assertFalse((bool) $category->is_active);
        $this->assertNotNull($category->converted_to_service_id);
    }

    public function test_restore_skips_when_service_already_exists(): void
    {
        Service::factory()->create(['slug' => 'landscape', 'name' => 'تصميم لاندسكيب']);

        $this->artisan('catalog:restore-platform-services', ['--slug' => 'landscape'])
            ->assertSuccessful();

        $this->assertSame(1, Service::where('slug', 'landscape')->count());
    }

    public function test_dry_run_does_not_create_service(): void
    {
        $this->artisan('catalog:restore-platform-services', [
            '--slug' => 'landscape',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Service::count());
    }
}
