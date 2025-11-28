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
            'type' => $this->faker->randomElement(['before','after']),
            'photo_path' => $this->faker->imageUrl(640, 480, 'nature'),
        ];
    }
}
