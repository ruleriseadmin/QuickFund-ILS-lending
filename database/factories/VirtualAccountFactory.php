<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VirtualAccount>
 */
class VirtualAccountFactory extends Factory
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
            'payable_code' => 'VIRTUAL_ACCOUNTMX'.rand(1000000, 999999999999),
            'account_name' => $this->faker->name(),
            'account_number' => '1100000000',
            'bank_name' => 'Wema Bank',
            'bank_code' => 'WEMA'
        ];
    }
}
