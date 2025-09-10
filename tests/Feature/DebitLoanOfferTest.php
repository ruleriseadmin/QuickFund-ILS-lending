<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Http, Bus};
use Laravel\Sanctum\Sanctum;
use App\Exceptions\CustomException as ApplicationCustomException;
use App\Models\{CollectionCase, LoanOffer, Role, Transaction, User};
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;
use Tests\TestCase;

class DebitLoanOfferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Offer not found
     */
    public function test_offer_is_not_found()
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
        $response = $this->postJson(route('loan-offers.debit', [
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
        $response = $this->postJson(route('loan-offers.debit', [
            'loanOffer' => $loanOffer
        ]));
    }
    
    /**
     * Loan is not OVERDUE
     */
    public function test_collected_loan_is_not_overdue_yet()
    {
        $loanOffer = LoanOffer::factory()
                            ->hasLoan()
                            ->create([
                                'status' => LoanOffer::PENDING
                            ]);
        $this->withoutExceptionHandling();
        $this->expectException(ApplicationCustomException::class);
        $this->expectExceptionMessage(__('interswitch.transaction_forbidden', [
            'type' => 'debit',
            'loan_status' => $loanOffer->status,
            'expected_loan_status' => implode(' or ', [
                '"'.LoanOffer::OPEN.'"',
                '"'.LoanOffer::OVERDUE.'"'
            ])
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
        $response = $this->postJson(route('loan-offers.debit', [
            'loanOffer' => $loanOffer
        ]));
    }

    /**
     * Loan has already been paid in full
     */
    public function test_loan_has_already_been_paid_in_full()
    {
        $loanOffer = LoanOffer::factory()
                            ->hasLoan([
                                'amount_remaining' => 0
                            ])
                            ->create([
                                'status' => LoanOffer::OVERDUE
                            ]);
        $this->withoutExceptionHandling();
        $this->expectException(ApplicationCustomException::class);
        $this->expectExceptionMessage(__('interswitch.loan_paid_in_full'));
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
        $response = $this->postJson(route('loan-offers.debit', [
            'loanOffer' => $loanOffer
        ]));
    }

    /**
     * Customer was successfully debited and loan was closed
     */
    public function test_customer_debited_and_loan_was_successfully_closed()
    {
        $successfulResponseCode = '00';
        $successfulResponseMessage = 'Successful';
        $transactionId = '958984578597843798438';
        $transactionRef = 'UBN|WEB|ILS|20170501120945|0198849';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $loanOffer = LoanOffer::factory()
                            ->hasLoan([
                                'penalty' => 2000,
                                'penalty_remaining' => 2000
                            ])
                            ->create([
                                'status' => LoanOffer::OVERDUE
                            ]);
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $totalRemainingAmount = $loan->amount_remaining + $loan->penalty_remaining;
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/debit' => Http::response([
                'responseCode' => $successfulResponseCode,
                'responseDescription' => $successfulResponseMessage,
                'transactionRef' => $transactionRef,
                'transactionId' => $transactionId
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/update' => Http::response([
                'responseCode' => '00',
                'responseMessage' => 'Successful'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.debit', [
            'loanOffer' => $loanOffer
        ]));
        $loanOffer->refresh();
        $loan->refresh();
        $collectionCase->refresh();
        $transactionAmount = app()->make(MoneyCalculator::class, [
            'value' => $totalRemainingAmount
        ])->toHigherDenomination()->getValue();
        $amountRemaining = app()->make(MoneyCalculator::class, [
            'value' => $loan->amount_remaining + $loan->penalty_remaining
        ])->toHigherDenomination()->getValue();

        $response->assertOk();
        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        $this->assertSame(CollectionCase::CLOSED, $collectionCase->status);
        $this->assertSame(0, $loan->amount_remaining);
        $this->assertSame(0, $loan->penalty_remaining);
        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $successfulResponseCode,
            'interswitch_transaction_message' => $successfulResponseMessage,
            'interswitch_transaction_reference' => $transactionRef, 
            'type' => Transaction::DEBIT,
            'amount' => $totalRemainingAmount
        ]);
        Bus::assertDispatched(SendSms::class);
        $response->assertSee([
            'amount_deducted' => config('quickfund.currency_representation').number_format($transactionAmount, 2),
            'amount_remaining' => config('quickfund.currency_representation').number_format($amountRemaining, 2),
            'transaction_code' => $successfulResponseCode,
            'transaction_message' => $successfulResponseMessage,
            'transaction_reference' => $transactionRef
        ]);
    }

    /**
     * Debit failed the first time but was successful the second time
     */
    public function test_debit_failed_the_first_time_but_was_successful_the_second_time()
    {
        $successfulResponseCode = '00';
        $successfulResponseMessage = 'Successful';
        $successfulTransactionId = '958984578597843798438';
        $successfulTransactionRef = 'UBN|WEB|ILS|20170501120945|0198849';
        $failedResponseCode = '1';
        $failedResponseMessage = 'Insufficient Balance';
        $failedTransactionId = '958984578597843792442';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $loanOffer = LoanOffer::factory()
                            ->hasLoan([
                                'penalty' => 2000,
                                'amount_remaining' => 1400000,
                                'penalty_remaining' => 20000
                            ])
                            ->create([
                                'status' => LoanOffer::OVERDUE
                            ]);
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $totalRemainingAmount = $loan->amount_remaining + $loan->penalty_remaining;
        $accountBalance = 1000000;
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/debit' => Http::sequence()
                                                                        ->push([
                                                                            'responseCode' => $failedResponseCode,
                                                                            'responseDescription' => $failedResponseMessage,
                                                                            'accountBalance' => $accountBalance
                                                                        ], 200, [
                                                                            'Content-Type' => 'application/json'
                                                                        ])
                                                                        ->push([
                                                                            'responseCode' => $successfulResponseCode,
                                                                            'responseDescription' => $successfulResponseMessage,
                                                                            'transactionRef' => $successfulTransactionRef,
                                                                            'transactionId' => $successfulTransactionId
                                                                        ], 200, [
                                                                            'Content-Type' => 'application/json'
                                                                        ]),

            config('services.interswitch.base_url').'loans/*/update' => Http::response([
                'responseCode' => '00',
                'responseMessage' => 'Successful'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.debit', [
            'loanOffer' => $loanOffer
        ]));
        $loanOffer->refresh();
        $loan->refresh();
        $collectionCase->refresh();
        $transactionAmount = app()->make(MoneyCalculator::class, [
            'value' => $accountBalance - 100000
        ])->toHigherDenomination()->getValue();
        $amountRemaining = app()->make(MoneyCalculator::class, [
            'value' => $loan->amount_remaining + $loan->penalty_remaining
        ])->toHigherDenomination()->getValue();

        $response->assertOk();
        $this->assertSame(LoanOffer::OVERDUE, $loanOffer->status);
        $this->assertSame(CollectionCase::OPEN, $collectionCase->status);
        $this->assertSame($totalRemainingAmount - ($accountBalance - 100000), ($loan->amount_remaining + $loan->penalty_remaining));
        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $failedResponseCode,
            'interswitch_transaction_message' => $failedResponseMessage,
            'amount' => $totalRemainingAmount, 
            'type' => Transaction::DEBIT,
        ]);
        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $successfulResponseCode,
            'interswitch_transaction_message' => $successfulResponseMessage,
            'interswitch_transaction_reference' => $successfulTransactionRef, 
            'amount' => $accountBalance - 100000,
            'type' => Transaction::DEBIT,
        ]);
        Bus::assertDispatched(SendSms::class);
        $response->assertSee([
            'amount_deducted' => config('quickfund.currency_representation').number_format($transactionAmount, 2),
            'amount_remaining' => config('quickfund.currency_representation').number_format($amountRemaining, 2),
            'transaction_code' => $successfulResponseCode,
            'transaction_message' => $successfulResponseMessage,
            'transaction_reference' => $successfulTransactionRef
        ]);
    }
}
