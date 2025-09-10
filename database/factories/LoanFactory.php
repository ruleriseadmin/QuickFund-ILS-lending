<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\{Loan, LoanOffer};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'loan_offer_id' => LoanOffer::factory(),
            'amount' => $this->faker->randomElement([
                100000,
                200000,
                250000,
                500000,
                1000000,
            ]),
            'amount_payable' => fn($attributes) => $attributes['amount'],
            'amount_remaining' => fn($attributes) => $attributes['amount'],
            'destination_account_number' => '0123456789',
            'destination_bank_code' => '011',
            'due_date' => now()->addDays(14)
        ];
    }
}
