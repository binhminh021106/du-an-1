<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category; 

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(4),
            'category_id' => Category::query()->inRandomOrder()->first()->id ?? Category::factory(),
            'thumbnail_url' => $this->faker->imageUrl(640, 480, 'products', true),
            'sold_count' => $this->faker->numberBetween(0, 5000),
            'favorite_count' => $this->faker->numberBetween(0, 1000),
            'review_count' => $this->faker->numberBetween(0, 200),
            'average_rating' => $this->faker->randomFloat(1, 1, 5),
            'status' => $this->faker->randomElement(['active', 'draft']),
            'description' => $this->faker->paragraph(5),
        ];
    }
}