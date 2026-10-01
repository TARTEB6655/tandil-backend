<?php

namespace Database\Factories;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TipFactory extends Factory
{
    protected $model = Tip::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraphs(2, true),
            'type' => $this->faker->randomElement(['weekly', 'monthly', 'seasonal', 'general']),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'language' => $this->faker->randomElement(['en', 'ar', 'ur']),
            'scheduled_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 month'),
            'created_by' => null,
        ];
    }
}
