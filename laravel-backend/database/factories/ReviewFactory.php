<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
// Thêm các model cần thiết
use App\Models\Product;
use App\Models\User;
// Model Review đã có sẵn trong class
// use App\Models\Review; 

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Dựa trên $fillable của model Review.php
        return [
            // 'product_id' và 'user_id' là bắt buộc
            // Yêu cầu bạn phải có ProductFactory và UserFactory
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            
            // Dữ liệu giả cho các cột còn lại
            'rating' => fake()->numberBetween(1, 5), // Rating từ 1 đến 5 sao
            'content' => fake()->paragraph(fake()->numberBetween(2, 5)), // Nội dung review 2-5 đoạn
            'status' => fake()->randomElement(['pending', 'approved']), // Trạng thái
        ];
    }
}