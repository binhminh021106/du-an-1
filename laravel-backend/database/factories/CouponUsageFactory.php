<?php

namespace Database\Factories;

use App\Models\CouponUsage;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CouponUsage>
 */
class CouponUsageFactory extends Factory
{
    /**
     * Tên của model tương ứng với factory.
     *
     * @var string
     */
    protected $model = CouponUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Lấy ngẫu nhiên một ID từ các bảng User, Coupon và Order.
        // Điều này đảm bảo tính toàn vẹn tham chiếu (foreign key constraints) nếu có.
        return [
            // Lấy ngẫu nhiên một user_id đã tồn tại
            'user_id' => User::inRandomOrder()->first()->id,

            // Lấy ngẫu nhiên một coupon_id đã tồn tại
            'coupon_id' => Coupon::inRandomOrder()->first()->id,

            // Lấy ngẫu nhiên một order_id đã tồn tại và đảm bảo nó chưa được sử dụng.
            // Có thể dùng một logic phức tạp hơn, nhưng đơn giản là lấy ngẫu nhiên một ID.
            // Để đảm bảo tính duy nhất (unique), bạn nên dùng Seeder hoặc logic khác.
            // Ở đây, tôi chỉ lấy ngẫu nhiên.
            'order_id' => Order::inRandomOrder()->first()->id,
            
            // Thêm các trường timestamps và soft deletes nếu cần,
            // nhưng thường Laravel/Eloquent tự quản lý chúng.
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}