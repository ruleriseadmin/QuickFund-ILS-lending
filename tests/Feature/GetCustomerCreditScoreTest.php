<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Customer, Role, User};

class GetCustomerCreditScoreTest extends TestCase
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
        $response = $this->getJson(route('customers.credit-score', [
            'customer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Credit score is successfully fetched
     */
    public function test_customer_credit_score_is_successfully_fetched()
    {
        $score = '40';
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_credit_score_url').'*' => Http::response([
                'responseCode' => '00',
                'creditScores' => [
                    [
                        'score' => $score,
                        'dateCreated' => '2021-01-01'
                    ]
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);
        $customer = Customer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.credit-score', [
            'customer' => $customer
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('credit_scores', [
            'customer_id' => $customer->id,
            'score' => $score
        ]);
    }
}
