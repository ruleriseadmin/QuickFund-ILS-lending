<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\{CollectionCase, User};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CollectionCaseRemark>
 */
class CollectionCaseRemarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'collection_case_id' => CollectionCase::factory(),
            'user_id' => User::factory(),
            'remark' => $this->faker->sentence(),
            'remarked_at' => now(),
            'promised_to_pay_at' => $this->faker->optional()->date(),
            'comment' => $this->faker->paragraph(),
            'already_paid_at' => $this->faker->optional()->date()
        ];
    }
}
