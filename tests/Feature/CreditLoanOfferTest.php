<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Http, Bus};
use Laravel\Sanctum\Sanctum;
use App\Exceptions\CustomException as ApplicationCustomException;
use App\Models\{LoanOffer, Role, Transaction, User};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class CreditLoanOfferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Loan offer not found
     */
    public function test_loan_offer_is_not_found()
    {
        $loanOffer = LoanOffer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.credit', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Uncollected loan on loan offer
     */
    public function test_loan_has_not_been_collected_on_loan_offer()
    {
        $this->withoutExceptionHandling();
        $this->expectException(ApplicationCustomException::class);
        $this->expectExceptionMessage(__('interswitch.uncollected_loan'));
        $loanOffer = LoanOffer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.credit', [
            'loanOffer' => $loanOffer
        ]));
    }

    /**
     * Unable to credit customer as loan offer status is not accepted
     */
    public function test_cannot_credit_customer_as_loan_status_is_not_accepted()
    {
        $loanOffer = LoanOffer::factory()
                            ->hasLoan()
                            ->create([
                                'status' => LoanOffer::CLOSED
                            ]);
        $this->withoutExceptionHandling();
        $this->expectException(ApplicationCustomException::class);
        $this->expectExceptionMessage(__('interswitch.transaction_forbidden', [
            'type' => 'credit',
            'loan_status' => $loanOffer->status,
            'expected_loan_status' => LoanOffer::ACCEPTED
        ]));
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.credit', [
            'loanOffer' => $loanOffer
        ]));
    }

    /**
     * Customer was successfully credited
     */
    public function test_customer_credited_successfully()
    {
        $successfulResponseCode = '00';
        $successfulResponseMessage = 'Transaction Successful';
        $transactionId = '6106594104283';
        $transactionRef = 'ZZW|LOC|CA|GTB|AC|080822202959|WW7QVK78';
        $loanOffer = LoanOffer::factory()
                            ->hasLoan()
                            ->create([
                                'status' => LoanOffer::ACCEPTED
                            ]);
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/fund' => Http::response([
                'responseCode' => $successfulResponseCode,
                'responseMessage' => $successfulResponseMessage,
                'transactionRef' => $transactionRef,
                'transactionId' => $transactionId
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.credit', [
            'loanOffer' => $loanOffer
        ]));
        $loanOffer->refresh();

        $response->assertOk();
        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $successfulResponseCode,
            'interswitch_transaction_message' => $successfulResponseMessage,
            'type' => Transaction::CREDIT
        ]);
        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }
}
