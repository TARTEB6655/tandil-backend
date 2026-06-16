<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\StripeCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStripeGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_save_wrong_prefix_in_test_slot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.payments.update-gateway', 'stripe'), [
            'enabled' => '1',
            'stripe_mode' => 'test',
            'test_public_key' => 'pk_live_wrong',
            'test_secret_key' => 'sk_live_wrong',
        ]);

        $response->assertRedirect(route('admin.payments.settings'));
        $response->assertSessionHasErrors('stripe_keys');
    }

    public function test_admin_can_save_both_modes_and_switch_active_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.payments.update-gateway', 'stripe'), [
            'enabled' => '1',
            'stripe_mode' => 'test',
            'test_public_key' => 'pk_test_saved',
            'test_secret_key' => 'sk_test_saved',
            'live_public_key' => 'pk_live_saved',
            'live_secret_key' => 'sk_live_saved',
        ])->assertRedirect(route('admin.payments.settings'))
            ->assertSessionHasNoErrors();

        $this->assertSame('test', Setting::get('stripe_mode'));
        $this->assertSame('pk_test_saved', Setting::get('stripe_test_public_key'));
        $this->assertSame('sk_test_saved', Setting::get('stripe_test_secret_key'));
        $this->assertSame('pk_live_saved', Setting::get('stripe_live_public_key'));
        $this->assertSame('sk_live_saved', Setting::get('stripe_live_secret_key'));
        $this->assertSame('pk_test_saved', StripeCredentials::publishableKey());

        $this->actingAs($admin)->post(route('admin.payments.update-gateway', 'stripe'), [
            'enabled' => '1',
            'stripe_mode' => 'live',
            'test_public_key' => '',
            'test_secret_key' => '',
            'live_public_key' => '',
            'live_secret_key' => '',
        ])->assertRedirect(route('admin.payments.settings'))
            ->assertSessionHasNoErrors();

        $this->assertSame('live', Setting::get('stripe_mode'));
        $this->assertSame('pk_live_saved', StripeCredentials::publishableKey());
        $this->assertSame('sk_live_saved', StripeCredentials::secretKey());
    }

    public function test_admin_can_switch_mode_without_reentering_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('stripe_test_public_key', 'pk_test_existing', 'text', 'payment');
        Setting::set('stripe_test_secret_key', 'sk_test_existing', 'text', 'payment');
        Setting::set('stripe_live_public_key', 'pk_live_existing', 'text', 'payment');
        Setting::set('stripe_live_secret_key', 'sk_live_existing', 'text', 'payment');
        Setting::set('stripe_mode', 'test', 'text', 'payment');

        $this->actingAs($admin)->post(route('admin.payments.update-gateway', 'stripe'), [
            'enabled' => '1',
            'stripe_mode' => 'live',
            'test_public_key' => 'pk_test_existing',
            'live_public_key' => 'pk_live_existing',
        ])->assertSessionHasNoErrors();

        $this->assertSame('live', StripeCredentials::activeMode());
        $this->assertSame('sk_live_existing', StripeCredentials::secretKey());
    }
}
