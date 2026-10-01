<?php

namespace Database\Factories;

use App\Models\MaintenancePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenancePhotoFactory extends Factory
{
    protected $model = MaintenancePhoto::class;

    public function definition(): array
    {
        return [
            'title' => fake()->optional()->words(3, true),
            'before_image_path' => 'maintenance_photos/before-'.fake()->uuid().'.jpg',
            'after_image_path' => 'maintenance_photos/after-'.fake()->uuid().'.jpg',
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
