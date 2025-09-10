<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Role, Transaction};
use Tests\TestCase;

class QueryTransactionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Transaction not found
     */
    public function test_transaction_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $transaction = Transaction::factory()
                                ->create();
        
        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('transactions.query', [
            'transaction' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Request to query transaction successful
     */
    public function test_request_to_fetch_transaction_details_was_sent_successfully()
    {
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                'responseMessage' => 'FAILED',
                'transactionId' => '6106594104283',
                'transactionDate' => '2022-06-08 14:23:23.393'
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $transaction = Transaction::factory()
                                ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('transactions.query', [
            'transaction' => $transaction
        ]));

        $response->assertOk();
    }

}
