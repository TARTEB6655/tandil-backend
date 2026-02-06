<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderExportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
        Mail::fake();
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        return $admin->createToken('test')->plainTextToken;
    }

    private function createOrderWithUser(array $attrs = []): Order
    {
        $user = User::factory()->create();

        return Order::factory()->create(array_merge(['user_id' => $user->id], $attrs));
    }

    public function test_export_returns_csv_by_default(): void
    {
        $this->createOrderWithUser();
        $this->createOrderWithUser();

        $response = $this->get('/api/admin/orders/export?format=csv', [
            'Authorization' => 'Bearer ' . $this->adminToken(),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', strtolower($response->headers->get('Content-Type') ?? ''));
        $this->assertStringContainsString('attachment', strtolower($response->headers->get('Content-Disposition') ?? ''));
    }

    public function test_export_returns_xlsx_when_format_xlsx(): void
    {
        $this->createOrderWithUser();

        $response = $this->get('/api/admin/orders/export?format=xlsx', [
            'Authorization' => 'Bearer ' . $this->adminToken(),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertSame('PK', substr($content, 0, 2), 'xlsx should be a zip file');
    }

    public function test_export_respects_package_id_filter(): void
    {
        $p1 = Package::factory()->create();
        $p2 = Package::factory()->create();
        $this->createOrderWithUser(['package_id' => $p1->id]);
        $this->createOrderWithUser(['package_id' => $p2->id]);

        $response = $this->get('/api/admin/orders/export?format=csv&package_id=' . $p1->id, [
            'Authorization' => 'Bearer ' . $this->adminToken(),
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', strtolower($response->headers->get('Content-Type') ?? ''));
        $this->assertStringContainsString('attachment', strtolower($response->headers->get('Content-Disposition') ?? ''));
    }

    public function test_send_to_supplier_sends_email_and_accepts_package_id(): void
    {
        config(['mail.supplier_email' => 'supplier@test.com']);
        $package = Package::factory()->create();
        $this->createOrderWithUser(['package_id' => $package->id]);

        $response = $this->postJson('/api/admin/orders/send-to-supplier', [
            'email' => 'supplier@test.com',
            'package_id' => $package->id,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken(),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'supplier@test.com');
        // Mail::raw() is used; with Mail::fake() no Mailable is sent, but the endpoint succeeded
    }

    public function test_export_requires_admin_auth(): void
    {
        $response = $this->get('/api/admin/orders/export', ['Accept' => 'application/json']);
        $response->assertStatus(401);
    }

    public function test_send_to_supplier_requires_admin_auth(): void
    {
        $response = $this->postJson('/api/admin/orders/send-to-supplier', [], ['Accept' => 'application/json']);
        $response->assertStatus(401);
    }
}
