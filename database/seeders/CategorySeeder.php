<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['id' => 1, 'name' => 'Fertilizers'],
            ['id' => 2, 'name' => 'Soil'],
            ['id' => 3, 'name' => 'Tools'],
        ];

        foreach ($categories as $category) {
            Category::create([
                'id' => $category['id'],
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
            ]);
        }
    }
}
