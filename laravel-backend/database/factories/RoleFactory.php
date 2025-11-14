<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str; // <-- Thêm dòng này

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Dùng để đếm xem đã tạo role nào
     */
    private static $roleIndex = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $defaultRoles = [
            ['value' => 'admin', 'label' => 'Quản trị viên', 'badgeClass' => 'badge-danger'],
            ['value' => 'editor', 'label' => 'Biên tập viên', 'badgeClass' => 'badge-warning'],
            ['value' => 'user', 'label' => 'Người dùng', 'badgeClass' => 'badge-primary'],
        ];
        if (self::$roleIndex < count($defaultRoles)) {
            $roleData = $defaultRoles[self::$roleIndex];
            self::$roleIndex++;
            return $roleData;
        }
        $randomValue = $this->faker->unique()->word();
        return [
            'value' => $randomValue,
            'label' => Str::ucfirst($randomValue), // Viết hoa chữ đầu
            'badgeClass' => $this->faker->randomElement(['badge-info', 'badge-secondary']),
        ];
    }
}