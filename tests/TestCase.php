<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Helpers\TestHelpers;

abstract class TestCase extends BaseTestCase
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions for all tests
        if (\App\Models\User::count() === 0) {
            $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
            $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        }
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
}
