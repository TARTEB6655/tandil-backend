<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition()
    {
        $start = Carbon::now()->subDays(rand(0,30));
        $period = [30,90,180,365][array_rand([0,1,2,3])];
        $paymentStatus = $this->faker->randomElement(['pending', 'paid', 'failed']);
        
        return [
            'client_id' => null, // set by seeder
            'plan' => $this->faker->randomElement(['1_month','3_month','6_month','12_month']),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays($period)->toDateString(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentStatus === 'paid' ? $this->faker->uuid() : null,
            'paid_at' => $paymentStatus === 'paid' ? $start->copy()->addHours(rand(1, 24)) : null,
            'total_visits' => rand(1,12),
            'completed_visits' => 0,
            'target_type' => 'specific_clients',
            'picture' => $this->faker->imageUrl(640, 480, 'business', true),
            'description' => $this->faker->sentence(),
        ];
    }
}
