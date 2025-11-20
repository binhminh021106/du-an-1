<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Mật khẩu mặc định cho tất cả user được tạo từ factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fullName' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'avatar_url' => $this->faker->imageUrl(100, 100, 'people'),
            'status' => $this->faker->randomElement(['active', 'inactive', 'banned']),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password', 
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Báo rằng user này chưa xác thực email.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}