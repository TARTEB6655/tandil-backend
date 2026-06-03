<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_account_requires_authentication(): void
    {
        $this->postJson('/api/user/delete-account', [
            'confirmation' => 'DELETE',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_delete_account_requires_delete_confirmation_and_password(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'password' => Hash::make('SecretPass1'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/user/delete-account', [
            'password' => 'SecretPass1',
        ])->assertStatus(422);

        $this->postJson('/api/user/delete-account', [
            'confirmation' => 'DELETE',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_client_can_delete_account_and_personal_data_is_removed(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email' => 'delete-me@example.com',
            'password' => Hash::make('SecretPass1'),
        ]);

        $address = UserAddress::create([
            'user_id' => $user->id,
            'type' => 'home',
            'full_name' => 'Test User',
            'phone_number' => '+971501234567',
            'street_address' => 'Street 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'is_default' => true,
        ]);

        $product = Product::factory()->create(['category_id' => Category::factory()]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $token = $user->createToken('delete-test')->plainTextToken;

        $response = $this->postJson('/api/user/delete-account', [
            'confirmation' => 'DELETE',
            'password' => 'SecretPass1',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_social_client_can_delete_with_confirmation_only(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'apple_id' => 'apple-123',
            'password' => Hash::make('random-social-password'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/user/delete-account', [
            'confirmation' => 'DELETE',
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_non_client_cannot_delete_via_client_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/user/delete-account', [
            'confirmation' => 'DELETE',
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_profile_sections_includes_delete_account(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/settings/sections');
        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains('delete_account', $ids);
    }
}
