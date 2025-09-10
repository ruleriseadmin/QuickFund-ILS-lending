<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FirstCentral>
 */
class FirstCentralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'customer_id' => Customer::factory(),
            'scoring' => [
                [
                    'TotalConsumerScore' => $this->faker->numberBetween(config('quickfund.minimum_approved_credit_score'), config('quickfund.maximum_approved_credit_score'))
                ]
            ],
            'credit_summary' => [
                [
                    'NumberofAccountsInBadStanding' => '0'
                ]
            ],
        ];
    }
}
