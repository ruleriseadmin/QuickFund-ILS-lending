<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Crc>
 */
class CrcFactory extends Factory
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
            'credit_facilities_summary' => [
                'credit' => [
                    'SUMMARY' => [
                        'LAST_REPORTED_DATE' => null,
                        'HAS_CREDITFACILITIES' => 'NO',
                        'NO_OF_DELINQCREDITFACILITIES' => '0'
                    ],
                ],
                'mf_credit' => [
                    'SUMMARY' => [
                        'HAS_CREDITFACILITIES' => 'YES',
                        'NO_OF_DELINQCREDITFACILITIES' => '0'
                    ],
                ],
                'mg_credit' => [
                    'SUMMARY' => [
                        'HAS_CREDITFACILITIES' => 'YES',
                        'NO_OF_DELINQCREDITFACILITIES' => '0'
                    ],
                ]
            ],
            'total_delinquencies' => fn($attributes) => (
                (int) $attributes['credit_facilities_summary']['credit']['SUMMARY']['NO_OF_DELINQCREDITFACILITIES'] +
                (int) $attributes['credit_facilities_summary']['mf_credit']['SUMMARY']['NO_OF_DELINQCREDITFACILITIES'] +
                (int) $attributes['credit_facilities_summary']['mg_credit']['SUMMARY']['NO_OF_DELINQCREDITFACILITIES']
            )
        ];
    }
}
