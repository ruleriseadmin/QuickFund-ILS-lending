<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Whitelist;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Whitelist>
 */
class WhitelistFactory extends Factory
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
                Whitelist::MANUALLY,
                Whitelist::BY_CODE
            ])
        ];
    }
}
