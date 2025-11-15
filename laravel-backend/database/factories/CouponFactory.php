<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 1. CHỌN LOẠI TRƯỚC
        $type = $this->faker->randomElement(['percentage', 'fixed']);

        $name = '';
        $value = 0;
        $min_spend = 0;

        // 2. TẠO DATA HỢP LÝ CHO TỪNG LOẠI
        if ($type === 'percentage') {
            // Loại %: Giảm 10%, 15%, 20%
            $value = $this->faker->randomElement([10, 15, 20]);
            $name = "Giảm $value% toàn bộ đơn hàng";
            $min_spend = $this->faker->randomElement([500000, 1000000]); // Yêu cầu tiêu 500k/1tr
        } else {
            // Loại tiền mặt: Giảm 50k, 100k, 200k (VND)
            $value = $this->faker->randomElement([50000, 100000, 200000]);
            $name = "Giảm " . number_format($value) . " VNĐ";
            $min_spend = $this->faker->randomElement([700000, 1000000, 2000000]); // Yêu cầu tiêu 700k/1tr/2tr
        }

        // 3. TRẢ VỀ KẾT QUẢ
        return [
            'name' => $name,
            
            // DÒNG NÀY ĐÃ ĐƯỢC SỬA:
            'code' => strtoupper($this->faker->unique()->lexify('SAVE???###')), // Ví dụ: SAVEABC123

            'min_spend' => $min_spend,
            'type' => $type,
            'value' => $value,
            'usage_limit' => $this->faker->numberBetween(100, 1000),
            'usage_count' => 0, // Quan trọng: Luôn bắt đầu bằng 0
            'usage_limit_per_user' => $this->faker->randomElement([1, 1, 2, 5]), // Hầu hết là 1
            'expires_at' => $this->faker->dateTimeBetween('now', '+3 months'), // Hết hạn trong 3 tháng
        ];
    }
}