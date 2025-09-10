<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use App\Models\{CollectionCase, Customer, LoanOffer, Transaction};
use Tests\TestCase;
use App\Jobs\Interswitch\SendSms;

class DebitDueLoansTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Customer was successfully debited and loan was closed
     */
    public function test_customer_debited_and_loan_was_successfully_closed()
    {
        $successfulResponseCode = '00';
        $successfulResponseMessage = 'Successful';
        $transactionId = '958984578597843798438';
        $transactionRef = 'UBN|WEB|ILS|20170501120945|0198849';
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->hasLoan([
                                'due_date' => today()->format('Y-m-d'),
                                'amount_remaining' => 140000,
                                'penalty' => 2000,
                                'penalty_remaining' => 2000
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
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

        $this->artisan('loans:debit-due');
        $loanOffer->refresh();
        $loan->refresh();
        $collectionCase->refresh();

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
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->hasLoan([
                                'due_date' => today()->format('Y-m-d'),
                                'amount_remaining' => 140000,
                                'penalty' => 2000,
                                'penalty_remaining' => 2000
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
        $totalRemainingAmount = $loan->amount_remaining + $loan->penalty_remaining;
        $accountBalance = 120000;
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

        $this->artisan('loans:debit-due');
        $loanOffer->refresh();
        $loan->refresh();
        $collectionCase->refresh();

        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
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
    }
}
