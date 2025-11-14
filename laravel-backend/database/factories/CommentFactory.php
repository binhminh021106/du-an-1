<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
// Import 2 Model cha
use App\Models\Product;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Lấy 1 product_id ngẫu nhiên (hoặc tạo mới)
            'product_id' => Product::query()->inRandomOrder()->first()?->id ?? Product::factory(),
            
            // Lấy 1 user_id ngẫu nhiên (hoặc tạo mới)
            'user_id' => User::query()->inRandomOrder()->first()?->id ?? User::factory(),
            
            // Nội dung bình luận
            'content' => $this->faker->paragraph(2),

            // Mặc định là bình luận "cha" (gốc)
            'parent_id' => null,

            // Trạng thái (75% là 'approved', 25% là 'pending')
            'status' => $this->faker->randomElement(['approved', 'approved', 'approved', 'pending']),
        ];
    }
}