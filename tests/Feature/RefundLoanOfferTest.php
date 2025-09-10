<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Laravel\Sanctum\Sanctum;
use App\Exceptions\CustomException as ApplicationCustomException;
use App\Models\{LoanOffer, Transaction, User, Role};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class RefundLoanOfferTest extends TestCase
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
        $response = $this->postJson(route('loan-offers.refund', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_refunding_customer()
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
        $response = $this->postJson(route('loan-offers.refund', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => null,
            'transaction_id' => $this->faker->randomNumber()
        ]);

        $response->assertInvalid(['amount']);
        $response->assertUnprocessable();
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
        $response = $this->postJson(route('loan-offers.refund', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->randomNumber(4),
        ]);
    }

    /**
     * Loan is not CLOSED
     */
    public function test_collected_loan_is_not_closed_yet()
    {
        $loanOffer = LoanOffer::factory()
                            ->hasLoan()
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $this->withoutExceptionHandling();
        $this->expectException(ApplicationCustomException::class);
        $this->expectExceptionMessage(__('interswitch.transaction_forbidden', [
            'type' => 'refund',
            'loan_status' => $loanOffer->status,
            'expected_loan_status' => LoanOffer::CLOSED
        ]));
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.refund', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->randomNumber(4)
        ]);
    }

    /**
     * Failed to refund customer
     */
    public function test_refund_customer_failed()
    {
        $failedResponseCode = '104';
        $failedResponseMessage = 'Loan not found';
        $this->withoutExceptionHandling();
        $this->expectException(ApplicationCustomException::class);
        $loanOffer = LoanOffer::factory()->create([
            'status' => LoanOffer::CLOSED
        ]);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/refund' => Http::response([
                'responseCode' => $failedResponseCode,
                'responseMessage' => $failedResponseMessage
            ], 400, [
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

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.refund', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->randomNumber(4)
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $failedResponseCode,
            'interswitch_transaction_message' => $failedResponseMessage,
            'type' => Transaction::REFUND
        ]);
        Bus::assertNotDispatched(SendSms::class);
    }

    /**
     * Customer refunded successfully
     */
    public function test_customer_refunded_successfully()
    {
        $successfulResponseCode = '00';
        $successfulResponseMessage = 'Transaction Successful';
        $transactionRef = 'UBN|WEB|ILS|20170501120945|0198849';
        $loanOffer = LoanOffer::factory()
                            ->hasLoan()
                            ->create([
                                'status' => LoanOffer::CLOSED
                            ]);
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $transaction = $loan->transactions()->create([
            'amount' => $this->faker->randomNumber(),
            'type' => Transaction::DEBIT
        ]);
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/refund' => Http::response([
                'responseCode' => $successfulResponseCode,
                'responseMessage' => $successfulResponseMessage,
                'transactionRef' => $transactionRef
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

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.refund', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->randomNumber(4)
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $successfulResponseCode,
            'interswitch_transaction_message' => $successfulResponseMessage,
            'interswitch_transaction_reference' => $transactionRef,
            'type' => Transaction::REFUND
        ]);
        Bus::assertDispatched(SendSms::class);
    }

}
