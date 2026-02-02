<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class UserFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete user flow: Register -> Login -> Create Subscription -> View Visits
     */
    public function test_complete_client_flow()
    {
        // 1. Register
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ]);

        $registerResponse->assertStatus(201);
        $token = $registerResponse->json('data.token');

        // 2. View Profile (pass Bearer token in header)
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/auth/profile');
        $profileResponse->assertStatus(200);

        // 3. Create Subscription
        $user = User::where('email', 'client@example.com')->first();
        Sanctum::actingAs($user);

        $subscriptionResponse = $this->postJson('/api/subscriptions', [
            'plan' => '1_month',
            'start_date' => now()->toDateString(),
        ]);

        $subscriptionResponse->assertStatus(201);

        // 4. View Subscriptions
        $subscriptionsResponse = $this->getJson('/api/subscriptions');
        $subscriptionsResponse->assertStatus(200);
    }

    /**
     * Test complete technician flow: Login -> View Visits -> Accept -> Start -> Complete
     */
    public function test_complete_technician_flow()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        // 1. View assigned visits
        $visit = $this->createVisit(['technician_id' => $technician->id, 'status' => 'pending']);

        $visitsResponse = $this->getJson('/api/tech/visits');
        $visitsResponse->assertStatus(200);

        // 2. Accept visit
        $acceptResponse = $this->postJson("/api/tech/visits/{$visit->id}/accept");
        $acceptResponse->assertStatus(200);

        // 3. Start visit
        $visit->refresh();
        $startResponse = $this->postJson("/api/tech/visits/{$visit->id}/start");
        $startResponse->assertStatus(200);

        // 4. Complete visit
        $visit->refresh();
        $completeResponse = $this->postJson("/api/tech/visits/{$visit->id}/complete", [
            'notes' => 'Visit completed successfully',
        ]);
        $completeResponse->assertStatus(200);
    }

    /**
     * Test complete shop flow: View Products -> Add to Cart -> Checkout -> View Order
     */
    public function test_complete_shop_flow()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        // 1. View products
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $productsResponse = $this->getJson('/api/shop/products');
        $productsResponse->assertStatus(200);

        // 2. Checkout
        $checkoutResponse = $this->postJson('/api/shop/checkout', [
            'items' => [
                ['product_id' => $product1->id, 'qty' => 2],
                ['product_id' => $product2->id, 'qty' => 1],
            ],
            'total_amount' => ($product1->price * 2) + $product2->price,
        ]);

        $checkoutResponse->assertStatus(200);

        // 3. View orders
        $ordersResponse = $this->getJson('/api/shop/orders');
        $ordersResponse->assertStatus(200);
    }
}




