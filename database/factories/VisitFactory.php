<?php

namespace Database\Factories;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition()
    {
        $scheduled = Carbon::now()->addDays(rand(-5, 10));
        return [
            'subscription_id' => null,
            'technician_id' => null,
            'scheduled_date' => $scheduled->toDateString(),
            'status' => $this->faker->randomElement(['scheduled','completed','missed']),
        ];
    }
}
