<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Customer, Role, User};

class GetACustomerFirstCentralTest extends TestCase
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
        $response = $this->getJson(route('customers.first-central', [
            'customer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Customer First Central report was successfully fetched
     */
    public function test_customer_first_central_report_is_successfully_fetched()
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
            config('services.first_central.base_url') . 'Login' => Http::response([
                [
                    'DataTicket' => 'Data ticket content'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'ConnectConsumerMatch' => Http::response([
                [
                    'MatchedConsumer' => [
                        [
                            'ConsumerID' => '178520820',
                            'EnquiryID' => '60260335',
                            'MatchingEngineID' => '76566'
                        ]
                    ]
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'consumerprime' => Http::response([
                [
                    'SubjectList' => 'subject list content'
                ],
                [
                    'PersonalDetailsSummary' => 'Personal details summary content'
                ],
                // [
                //     'Scoring' => [
                //         [
                //             'TotalConsumerScore' => config('quickfund.minimum_approved_credit_score')
                //         ]
                //     ]
                // ],
                [
                    'CreditSummary' => [
                        [
                            'NumberofAccountsInBadStanding' => '0'
                        ]
                    ]
                ],
                [
                    'PerformanceClassification' => 'Performance classification details'
                ],
                [
                    'EnquiryDetails' => 'Enquiry details'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.first-central', [
            'customer' => $customer
        ]));

        info($response->json());

        $response->assertOk();
        $this->assertDatabaseHas('first_centrals', [
            'customer_id' => $customer->id
        ]);
    }
}
