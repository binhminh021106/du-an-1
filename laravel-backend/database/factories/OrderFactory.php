<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Khởi tạo các biến tiền tệ
        $subtotal = $this->faker->numberBetween(500000, 5000000); // 500k - 5 triệu
        $shipping = 30000;
        $discount = $this->faker->boolean(30) ? $this->faker->numberBetween(10000, 100000) : 0; // 30% cơ hội có giảm giá
        $total = $subtotal + $shipping - $discount;

        // Các trạng thái và phương thức thanh toán có thể
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $paymentMethods = ['cod', 'bank_transfer', 'momo'];
        $paymentStatuses = ['unpaid', 'paid', 'refunded'];

        return [
            // Thông tin User/Khách hàng
            // 70% cơ hội đơn hàng có user đăng nhập, 30% là khách vãng lai (guest)
            'user_id' => $this->faker->boolean(70) ? User::factory() : null,
            
            'customer_name' => $this->faker->name, 
            'customer_phone' => $this->faker->phoneNumber,
            'customer_email' => $this->faker->unique()->safeEmail,
            'shipping_address' => $this->faker->address,

            // Thông tin chi tiết tiền tệ
            'subtotal_amount' => $subtotal,
            'shipping_fee' => $shipping,
            'discount_amount' => $discount,
            'total_amount' => max(0, $total), // Đảm bảo tổng tiền không âm

            // Trạng thái đơn hàng và thanh toán
            'status' => $this->faker->randomElement($statuses),
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'payment_status' => $this->faker->randomElement($paymentStatuses),

            // Coupon (Liên kết với Coupon ngẫu nhiên nếu có discount)
            'coupon_id' => ($discount > 0) ? Coupon::factory() : null,
        ];
    }
    
    /**
     * State: Đơn hàng đã hoàn thành và được thanh toán.
     */
    public function deliveredAndPaid(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'delivered',
                'payment_status' => 'paid',
            ];
        });
    }

    /**
     * State: Đơn hàng bị hủy.
     */
    public function cancelled(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
            ];
        });
    }
}