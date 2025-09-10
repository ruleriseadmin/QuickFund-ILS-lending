<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'amount' => round($this->faker->numberBetween(config('quickfund.minimum_loan_amount'), config('quickfund.maximum_loan_amount')), -3),
            'interest' => config('quickfund.loan_interest'),
            'default_interest' => config('quickfund.default_interest'),
            'tenure' => $this->faker->randomElement(config('quickfund.loan_tenures')),
            'cycles' => $this->faker->numberBetween(1, 5),
            'currency' => config('services.interswitch.default_currency_code'),
            'expiry_date' => $this->faker->optional()->passthrough(now()->addWeeks($this->faker->randomDigitNotZero())->format('Y-m-d')),
            'default_fees_addition_days' => config('quickfund.days_to_attach_late_payment_fees')
        ];
    }
}
