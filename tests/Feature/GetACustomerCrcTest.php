<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Customer, Role, User};

class GetACustomerCrcTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Customer not found
     */
    public function test_customer_is_not_found()
    {
        $customer = Customer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.crc', [
            'customer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Customer CRC report was successfully fetched
     */
    public function test_customer_crc_report_is_successfully_fetched()
    {
        $customer = Customer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        Http::fake([
            config('services.crc.url') => Http::response([
                'ConsumerHitResponse' => [
                    'BODY' => [
                        'SummaryOfPerformance' => null,
                        'ReportDetailBVN' => null,
                        'ContactHistory' => null,
                        'AddressHistory' => null,
                        'ClassificationInsType' => null,
                        'ClassificationProdType' => null,
                        'CREDIT_SCORE_DETAILS' => null,
                        'CREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'YES',
                                'LAST_REPORTED_DATE' => '30-APR-2022',
                                'NO_OF_DELINQCREDITFACILITIES' => '0'
                            ]
                        ],
                        'MFCREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'YES',
                                'NO_OF_DELINQCREDITFACILITIES' => '0',
                            ]
                        ],
                        'MGCREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'NO',
                                'NO_OF_DELINQCREDITFACILITIES' => '0'
                            ]
                        ],
                        'NANO_CONSUMER_PROFILE' => 'Consumer profile details'
                    ],
                    'HEADER' => 'Header content'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.crc', [
            'customer' => $customer
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('crcs', [
            'customer_id' => $customer->id
        ]);
    }
}
