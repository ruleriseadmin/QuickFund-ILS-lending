<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Crc;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrcHistory>
 */
class CrcHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'crc_id' => Crc::factory(),
            'date' => $this->faker->date()
        ];
    }
}
