<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\{CollectionCase, LoanOffer, User};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CollectionCase>
 */
class CollectionCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'loan_offer_id' => LoanOffer::factory(),
            'assigned_at' => now()
        ];
    }
}
