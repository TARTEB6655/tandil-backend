<?php

namespace Database\Factories;

use App\Models\VisitPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitPhotoFactory extends Factory
{
    protected $model = VisitPhoto::class;

    public function definition()
    {
        return [
            'visit_id' => null,
            'type' => $this->faker->randomElement(['before', 'during', 'after']),
            'photo_path' => 'visit_photos/'.fake()->uuid().'.jpg',
            'show_on_client_app' => true,
        ];
    }
}
