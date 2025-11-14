<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User; // <-- Import User

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserAddress>
 */
class UserAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = \Faker\Factory::create('vi_VN');
        $cities = ['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ'];
        $districts = ['Quận Ba Đình', 'Quận Hoàn Kiếm', 'Quận 1', 'Quận 3', 'Quận Hải Châu', 'Quận Sơn Trà'];
        $wards = ['Phường Phúc Xá', 'Phường Cống Vị', 'Phường Bến Nghé', 'Phường Đa Kao', 'Phường Thạch Thang'];

        return [
            'user_id' => User::query()->inRandomOrder()->first()->id ?? User::factory(),
            'customer_name' => $faker->name(),
            'customer_phone' => $faker->phoneNumber(),
            'shipping_address' => $faker->streetAddress(), // Số nhà, tên đường (vd: "849 Cao Thắng")
            'city' => $this->faker->randomElement($cities), // Tỉnh/Thành phố
            'district' => $this->faker->randomElement($districts), // Quận/Huyện
            'ward' => $this->faker->randomElement($wards), // Phường/Xã

            'is_default' => false,
        ];
    }
}