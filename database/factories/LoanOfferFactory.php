<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\{LoanOffer, Offer, Customer};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoanOffer>
 */
class LoanOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'offer_id' => Offer::factory(),
            'customer_id' => Customer::factory(),
            'amount' => round($this->faker->numberBetween(config('quickfund.minimum_loan_amount'), config('quickfund.maximum_loan_amount')), -3),
            'interest' => config('quickfund.loan_interest'),
            'default_interest' => config('quickfund.default_interest'),
            'tenure' => $this->faker->randomElement(config('quickfund.loan_tenures')),
            'currency' => config('services.interswitch.default_currency_code'),
            'expiry_date' => $this->faker->optional()->passthrough(now()->addWeeks($this->faker->randomDigitNotZero())->format('Y-m-d')),
            'default_fees_addition_days' => config('quickfund.days_to_attach_late_payment_fees')
        ];
    }
}
