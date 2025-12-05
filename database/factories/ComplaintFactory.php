<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Visit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition()
    {
        return [
            'visit_id' => null,
            'client_id' => null,
            'status' => $this->faker->randomElement(['open', 'in_progress', 'resolved', 'escalated']),
            'notes' => $this->faker->sentence(),
        ];
    }
}

