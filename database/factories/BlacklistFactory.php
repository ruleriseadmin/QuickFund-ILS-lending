<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Blacklist;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blacklist>
 */
class BlacklistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'phone_number' => $this->faker->phoneNumber(),
            'type' => $this->faker->randomElement([
                Blacklist::MANUALLY,
                Blacklist::BY_CODE
            ])
        ];
    }
}
