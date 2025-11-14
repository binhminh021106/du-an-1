<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
// Thêm 2 dòng use này:
use Illuminate\Support\Str; 
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Tạo một câu title ngẫu nhiên
        $title = fake()->sentence(fake()->numberBetween(5, 10)); // Title dài 5-10 từ

        return [
            'title' => $title,
            // Mô tả ngắn, 1-2 câu
            'excerpt' => fake()->paragraph(2), 
            // Nội dung, 5 đoạn, trả về dưới dạng một chuỗi string (tham số 'true')
            'content' => fake()->paragraphs(5, true), 
            // Một URL ảnh placeholder
            'image_url' => fake()->imageUrl(800, 600, 'news', true), 
            // Tạo slug (đường dẫn) từ $title đã tạo ở trên
            'slug' => Str::slug($title), 
            // Lấy ID của tác giả.
            // Dòng này sẽ tự động tạo một User mới, hoặc bạn có thể
            // chỉ định user cụ thể khi gọi factory: User::all()->random()->id
            'author_id' => User::factory(), 
            // Trạng thái, chọn ngẫu nhiên 1 trong 2 giá trị
            'status' => fake()->randomElement(['published', 'draft']), 
        ];
    }
}