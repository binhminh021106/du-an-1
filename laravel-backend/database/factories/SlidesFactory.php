<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Slides>
 */
class SlidesFactory extends Factory
{
    private static $order = 1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'image_url' => 'https://placehold.co/1200x400/EFEFEF/AAAAAA?text=Slide+Banner+' . self::$order,
            'link_url' => $this->faker->url(),
            'order_number' => self::$order++,
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'active', 'draft']),
        ];
    }
}
