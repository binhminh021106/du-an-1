<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product; // <-- Thêm dòng này để lấy Product

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Variant>
 */
class VariantFactory extends Factory // <-- Sửa tên class thành VariantFactory (không có 's')
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalPrice = $this->faker->numberBetween(5_000_000, 30_000_000);
        $price = $this->faker->boolean(80) 
            ? $originalPrice * $this->faker->randomFloat(2, 0.7, 0.95) 
            : $originalPrice; 
        return [
            'product_id' => Product::query()->inRandomOrder()->first()->id ?? Product::factory(),
            'price' => round($price / 1000) * 1000,
            'original_price' => round($originalPrice / 1000) * 1000,
            'stock' => $this->faker->numberBetween(0, 100),
            'configuration' => $this->faker->randomElement(['128GB', '256GB', '512GB', '1TB']),
            'color' => $this->faker->randomElement(['Black', 'White', 'Blue', 'Titanium', 'Red']),
        ];
    }
}