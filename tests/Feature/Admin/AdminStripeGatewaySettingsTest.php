<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStripeGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_save_mismatched_stripe_key_pair_when_only_public_key_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('stripe_secret_key', 'sk_live_existing', 'text', 'payment');
        Setting::set('stripe_public_key', 'pk_live_existing', 'text', 'payment');

        $response = $this->actingAs($admin)->post(route('admin.payments.update-gateway', 'stripe'), [
            'enabled' => '1',
            'public_key' => 'pk_test_new',
            'secret_key' => '',
        ]);

        $response->assertRedirect(route('admin.payments.settings'));
        $response->assertSessionHasErrors('stripe_keys');
        $this->assertSame('pk_live_existing', Setting::get('stripe_public_key'));
    }

    public function test_admin_can_save_matching_test_stripe_keys_together(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('stripe_secret_key', 'sk_live_existing', 'text', 'payment');
        Setting::set('stripe_public_key', 'pk_live_existing', 'text', 'payment');

        $response = $this->actingAs($admin)->post(route('admin.payments.update-gateway', 'stripe'), [
            'enabled' => '1',
            'public_key' => 'pk_test_new',
            'secret_key' => 'sk_test_new',
        ]);

        $response->assertRedirect(route('admin.payments.settings'));
        $response->assertSessionHasNoErrors();
        $this->assertSame('pk_test_new', Setting::get('stripe_public_key'));
        $this->assertSame('sk_test_new', Setting::get('stripe_secret_key'));
    }
}
