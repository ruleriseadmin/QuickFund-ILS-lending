<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\{Transaction, Loan};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->randomElement([
                100000,
                200000,
                250000,
                500000,
                1000000,
            ]),
            'interswitch_transaction_message' => $this->faker->sentence(),
            'interswitch_transaction_code' => $this->faker->randomNumber(3),
            'interswitch_payment_reference' => $this->faker->unique()->randomNumber(),
            'type' => $this->faker->randomElement([
                Transaction::DEBIT,
                Transaction::CREDIT,
                Transaction::PAYMENT,
                Transaction::REFUND
            ])
        ];
    }
}
