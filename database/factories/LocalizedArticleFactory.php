<?php

namespace Database\Factories;

use App\Models\LocalizedArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalizedArticle>
 */
class LocalizedArticleFactory extends Factory
{
    protected $model = LocalizedArticle::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => [
                'en' => fake()->sentence(4),
                'ar' => 'عنوان '.$this->faker->numerify('###'),
                'ur' => 'عنوان '.$this->faker->numerify('###'),
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'ar' => 'وصف '.$this->faker->sentence(8),
                'ur' => 'تفصیل '.$this->faker->sentence(8),
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
