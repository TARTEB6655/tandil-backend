<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition()
    {
        return [
            'visit_id' => null,
            'supervisor_id' => null,
            'technician_notes' => $this->faker->sentence(),
            'supervisor_notes' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'recommendations' => [],
            'recommended_products' => [],
            'status' => $this->faker->randomElement(['draft', 'pending', 'approved', 'sent_to_client']),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
