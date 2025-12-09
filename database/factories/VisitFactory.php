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
        $status = $this->faker->randomElement(['pending', 'accepted', 'in_progress', 'completed', 'cancelled']);
        
        return [
            'subscription_id' => null,
            'technician_id' => null,
            'supervisor_id' => null,
            'area_id' => null,
            'scheduled_date' => $scheduled->toDateString(),
            'completed_date' => $status === 'completed' ? $scheduled->copy()->addDays(rand(1, 3))->toDateString() : null,
            'status' => $status,
            'approved_by' => null,
            'approved_at' => null,
            'accepted_at' => $status !== 'pending' ? $scheduled->copy()->addHours(rand(1, 24)) : null,
            'started_at' => in_array($status, ['in_progress', 'completed']) ? $scheduled->copy()->addHours(rand(2, 48)) : null,
            'completed_at' => $status === 'completed' ? $scheduled->copy()->addHours(rand(3, 72)) : null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
