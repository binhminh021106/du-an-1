<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CartItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Liên kết CartItem với một Cart ngẫu nhiên (hoặc tạo mới nếu chưa tồn tại)
            'cart_id' => Cart::factory(),
            
            // Liên kết CartItem với một Variant ngẫu nhiên
            // (Bạn cần đảm bảo Variant Factory đã tồn tại và hoạt động)
            'variant_id' => Variant::factory(),
            
            // Số lượng sản phẩm ngẫu nhiên từ 1 đến 5
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }
}