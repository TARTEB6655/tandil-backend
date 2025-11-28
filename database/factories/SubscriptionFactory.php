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
        return [
            'client_id' => null, // set by seeder
            'plan' => $this->faker->randomElement(['1_month','3_month','6_month','12_month']),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays($period)->toDateString(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'payment_status' => 'paid',
            'total_visits' => rand(1,12),
            'completed_visits' => 0,
        ];
    }
}
