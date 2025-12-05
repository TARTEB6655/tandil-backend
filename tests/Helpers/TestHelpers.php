<?php

namespace Tests\Helpers;

use App\Models\User;
use App\Models\Area;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\Complaint;
use App\Models\Report;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

trait TestHelpers
{
    /**
     * Create an admin user
     */
    protected function createAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'admin',
            'status' => 'active',
        ], $attributes));
        
        $user->assignRole('admin');
        return $user;
    }

    /**
     * Create a supervisor user
     */
    protected function createSupervisor(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'supervisor',
            'status' => 'active',
        ], $attributes));
        
        $user->assignRole('supervisor');
        return $user;
    }

    /**
     * Create a technician user
     */
    protected function createTechnician(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'technician',
            'status' => 'active',
        ], $attributes));
        
        $user->assignRole('technician');
        return $user;
    }

    /**
     * Create a client/customer user
     */
    protected function createCustomer(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'client',
            'status' => 'active',
        ], $attributes));
        
        $user->assignRole('client');
        return $user;
    }

    /**
     * Create an area manager user
     */
    protected function createAreaManager(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'area_manager',
            'status' => 'active',
        ], $attributes));
        
        $user->assignRole('area_manager');
        return $user;
    }

    /**
     * Create an HR user
     */
    protected function createHr(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'hr',
            'status' => 'active',
        ], $attributes));
        
        $user->assignRole('hr');
        return $user;
    }

    /**
     * Login as a specific role and return the token
     */
    protected function loginAs(string $role, array $attributes = []): array
    {
        $method = 'create' . ucfirst($role === 'client' ? 'customer' : $role);
        
        if (!method_exists($this, $method)) {
            throw new \Exception("Role {$role} not supported");
        }

        $user = $this->$method($attributes);
        $token = $user->createToken('test-token')->plainTextToken;
        
        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Authenticate as a user
     */
    protected function actingAsUser(User $user): void
    {
        Sanctum::actingAs($user);
    }

    /**
     * Create a visit
     */
    protected function createVisit(array $attributes = []): Visit
    {
        if (!isset($attributes['subscription_id'])) {
            $client = $this->createCustomer();
            $subscription = $this->createSubscription(['client_id' => $client->id]);
            $attributes['subscription_id'] = $subscription->id;
        }

        if (!isset($attributes['technician_id'])) {
            $technician = $this->createTechnician();
            $attributes['technician_id'] = $technician->id;
        }

        if (!isset($attributes['area_id'])) {
            $area = Area::factory()->create();
            $attributes['area_id'] = $area->id;
        }

        if (!isset($attributes['supervisor_id'])) {
            $supervisor = $this->createSupervisor();
            $attributes['supervisor_id'] = $supervisor->id;
        }

        return Visit::factory()->create($attributes);
    }

    /**
     * Create a complaint
     */
    protected function createComplaint(array $attributes = []): Complaint
    {
        if (!isset($attributes['visit_id'])) {
            $visit = $this->createVisit();
            $attributes['visit_id'] = $visit->id;
            $attributes['client_id'] = $visit->subscription->client_id;
        }

        if (!isset($attributes['client_id'])) {
            $client = $this->createCustomer();
            $attributes['client_id'] = $client->id;
        }

        return Complaint::factory()->create($attributes);
    }

    /**
     * Create an order
     */
    protected function createOrder(array $attributes = []): Order
    {
        if (!isset($attributes['user_id'])) {
            $customer = $this->createCustomer();
            $attributes['user_id'] = $customer->id;
        }

        $order = Order::factory()->create($attributes);

        // Create order items if products provided
        if (isset($attributes['items']) && is_array($attributes['items'])) {
            foreach ($attributes['items'] as $item) {
                OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? Product::factory()->create()->id,
                    'quantity' => $item['quantity'] ?? 1,
                    'price' => $item['price'] ?? 100,
                    'subtotal' => ($item['quantity'] ?? 1) * ($item['price'] ?? 100),
                ]);
            }
        }

        return $order;
    }

    /**
     * Create a product
     */
    protected function createProduct(array $attributes = []): Product
    {
        if (!isset($attributes['category_id'])) {
            $category = Category::factory()->create();
            $attributes['category_id'] = $category->id;
        }

        return Product::factory()->create($attributes);
    }

    /**
     * Create a subscription
     */
    protected function createSubscription(array $attributes = []): Subscription
    {
        if (!isset($attributes['client_id'])) {
            $client = $this->createCustomer();
            $attributes['client_id'] = $client->id;
        }

        if (!isset($attributes['plan'])) {
            $attributes['plan'] = '1_month';
        }

        if (!isset($attributes['start_date'])) {
            $attributes['start_date'] = now();
        }

        if (!isset($attributes['end_date'])) {
            $months = ['1_month' => 1, '3_month' => 3, '6_month' => 6, '12_month' => 12];
            $monthsCount = $months[$attributes['plan']] ?? 1;
            $attributes['end_date'] = now()->addMonths($monthsCount);
        }

        if (!isset($attributes['amount'])) {
            $prices = ['1_month' => 500, '3_month' => 1450, '6_month' => 2900, '12_month' => 5500];
            $attributes['amount'] = $prices[$attributes['plan']] ?? 500;
        }

        return Subscription::factory()->create($attributes);
    }

    /**
     * Create a report
     */
    protected function createReport(array $attributes = []): Report
    {
        if (!isset($attributes['visit_id'])) {
            $visit = $this->createVisit();
            $attributes['visit_id'] = $visit->id;
        }

        if (!isset($attributes['supervisor_id'])) {
            $supervisor = $this->createSupervisor();
            $attributes['supervisor_id'] = $supervisor->id;
        }

        return Report::factory()->create($attributes);
    }

    /**
     * Create an area
     */
    protected function createArea(array $attributes = []): Area
    {
        return Area::factory()->create($attributes);
    }

    /**
     * Make authenticated request with token
     */
    protected function authenticatedJson(string $method, string $uri, array $data = [], ?string $token = null): \Illuminate\Testing\TestResponse
    {
        $headers = [];
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $this->json($method, $uri, $data, $headers);
    }
}

