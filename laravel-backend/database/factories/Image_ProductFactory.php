<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product; 
use App\Models\Image_product; 

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Image_product>
 */
class Image_ProductFactory extends Factory
{
    /**
     * Tên Model tương ứng.
     * Khai báo rõ ở đây để nó chạy 100%
     * @var string
     */
    protected $model = Image_product::class;


    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::query()->inRandomOrder()->first()->id ?? Product::factory(),
            'image_url' => $this->faker->imageUrl(640, 480, 'ecommerce', true),
        ];
    }
}