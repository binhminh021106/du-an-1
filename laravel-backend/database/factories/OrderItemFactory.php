<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Tên của model tương ứng với factory.
     *
     * @var string
     */
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Giả sử Variant có trường 'price' hoặc một cách để xác định giá cơ sở.
        // Đầu tiên, lấy ngẫu nhiên một biến thể sản phẩm (Variant).
        $variant = Variant::inRandomOrder()->first();

        // Đảm bảo chúng ta có ít nhất một Variant và Order để tránh lỗi.
        if (!$variant || !Order::exists()) {
             // Trả về dữ liệu mặc định/giữ chỗ nếu không có dữ liệu cần thiết.
             // Hoặc bạn có thể ném exception trong Seeder để cảnh báo.
             return [
                 'order_id' => 1,
                 'variant_id' => 1,
                 'quantity' => 1,
                 'price' => 10.00,
             ];
        }

        // Tạo số lượng ngẫu nhiên
        $quantity = $this->faker->numberBetween(1, 5);
        
        // Giả định Variant có thuộc tính 'price' (hoặc 'selling_price').
        // Nếu không có, bạn cần thay đổi logic lấy giá ở đây.
        // Giá bán (price) trong OrderItem thường được lấy từ Variant tại thời điểm đặt hàng.
        $itemPrice = $variant->price ?? $this->faker->randomFloat(2, 5, 500); // Dùng giá của variant nếu có, nếu không thì dùng giá ngẫu nhiên.

        return [
            // Lấy ngẫu nhiên một order_id đã tồn tại
            'order_id' => Order::inRandomOrder()->first()->id,

            // Sử dụng variant_id đã lấy ở trên
            'variant_id' => $variant->id,

            // Số lượng sản phẩm
            'quantity' => $quantity,

            // Giá của sản phẩm đó tại thời điểm đặt hàng (một sản phẩm)
            'price' => $itemPrice,

            // Nếu muốn lưu tổng tiền (subtotal) cho item này, bạn có thể thêm:
            // 'subtotal' => $itemPrice * $quantity, 
        ];
    }
}