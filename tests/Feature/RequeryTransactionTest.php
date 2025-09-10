<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use App\Models\{CollectionCase, Customer, Loan, LoanOffer, Transaction};
use App\Jobs\RequeryTransaction;
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class RequeryTransactionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Debit was successful after requery
     */
    public function test_debit_was_successful_after_requery()
    {
        $this->withoutExceptionHandling();
        $amountPaid = 100000;
        $successfulTransactionCode = '00';
        $successfulTransactionMessage = 'SUCCESSFUL';
        $successfulTransactionReference = 'ZZW|LOC|CA|SKYE|AC|150922210133|AJNWYUGT';
        Bus::fake();
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'amount_remaining' => $amountPaid,
                        'penalty' => 0,
                        'penalty_remaining' => 0
                     ]);
        $transaction = Transaction::factory()
                                ->for($loan)
                                ->create([
                                    'amount' => $amountPaid
                                ]);
         $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                                                                                'responseCode' => $successfulTransactionCode,
                                                                                'responseMessage' => $successfulTransactionMessage,
                                                                                'transactionRef' => $successfulTransactionReference,
                                                                                'transactionId' => '6106594104283',
                                                                                'transactionDate' => '2022-06-08 14:23:23.393'
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

        (new RequeryTransaction($transaction, 'debit'))->handle();
        $loanOffer->refresh();
        $loan->refresh();
        $transaction->refresh();
        $collectionCase->refresh();

        $this->assertSame(0, $loan->amount_remaining);
        $this->assertSame(CollectionCase::CLOSED, $collectionCase->status);
        $this->assertSame($successfulTransactionCode, $transaction->interswitch_transaction_code);
        $this->assertSame($successfulTransactionMessage, $transaction->interswitch_transaction_message);
        $this->assertSame($successfulTransactionReference, $transaction->interswitch_transaction_reference);
        $this->assertSame(Transaction::DEBIT, $transaction->type);
        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Debit failed after requery
     */
    public function test_debit_failed_after_requery()
    {
        $amountPaid = 100000;
        $failedResponseCode = '1';
        $failedResponseMessage = 'Insufficient Balance';
        $failedTransactionId = '958984578597843792442';
        Bus::fake();
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'amount_remaining' => $amountPaid,
                        'penalty' => 0,
                        'penalty_remaining' => 0
                     ]);
        $transaction = Transaction::factory()
                                ->for($loan)
                                ->create([
                                    'amount' => $amountPaid
                                ]);
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                                                                                'responseCode' => $failedResponseCode,
                                                                                'responseMessage' => $failedResponseMessage,
                                                                                'accountBalance' => 500000
                                                                            ], 200, [
                                                                                'Content-Type' => 'application/json'
                                                                            ]),
        ]);

        (new RequeryTransaction($transaction, 'debit'))->handle();
        $loanOffer->refresh();
        $loan->refresh();
        $transaction->refresh();
        $collectionCase->refresh();

        $this->assertSame($amountPaid, $loan->amount_remaining);
        $this->assertSame(CollectionCase::OPEN, $collectionCase->status);
        $this->assertSame($failedResponseCode, $transaction->interswitch_transaction_code);
        $this->assertSame($failedResponseMessage, $transaction->interswitch_transaction_message);
        $this->assertSame(Transaction::DEBIT, $transaction->type);
        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
        Bus::assertNotDispatched(SendSms::class);
    }

    /**
     * Credit was successful after requery
     */
    public function test_credit_was_successful_after_requery()
    {
        $successfulTransactionCode = '00';
        $successfulTransactionMessage = 'SUCCESSFUL';
        $successfulTransactionReference = 'ZZW|LOC|CA|SKYE|AC|150922210133|AJNWYUGT';
        Bus::fake();
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::ACCEPTED,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $transaction = Transaction::factory()
                                ->for($loan)
                                ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                                                                                'responseCode' => $successfulTransactionCode,
                                                                                'responseMessage' => $successfulTransactionMessage,
                                                                                'transactionRef' => $successfulTransactionReference,
                                                                                'transactionId' => '6106594104283',
                                                                                'transactionDate' => '2022-06-08 14:23:23.393'
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

        (new RequeryTransaction($transaction, 'credit'))->handle();
        $loanOffer->refresh();
        $transaction->refresh();

        $this->assertSame($successfulTransactionCode, $transaction->interswitch_transaction_code);
        $this->assertSame($successfulTransactionMessage, $transaction->interswitch_transaction_message);
        $this->assertSame($successfulTransactionReference, $transaction->interswitch_transaction_reference);
        $this->assertSame(Transaction::CREDIT, $transaction->type);
        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Credit failed after requery
     */
    public function test_credit_failed_after_requery()
    {
        $failedResponseCode = '51';
        $failedResponseMessage = 'FAILED';
        $failedTransactionId = '958984578597843792442';
        Bus::fake();
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::ACCEPTED,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $transaction = Transaction::factory()
                                ->for($loan)
                                ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                                                                                'responseCode' => $failedResponseCode,
                                                                                'responseMessage' => $failedResponseMessage,
                                                                            ], 200, [
                                                                                'Content-Type' => 'application/json'
                                                                            ]),
        ]);

        (new RequeryTransaction($transaction, 'credit'))->handle();
        $loanOffer->refresh();
        $transaction->refresh();

        $this->assertSame($failedResponseCode, $transaction->interswitch_transaction_code);
        $this->assertSame($failedResponseMessage, $transaction->interswitch_transaction_message);
        $this->assertSame(Transaction::CREDIT, $transaction->type);
        $this->assertSame(LoanOffer::ACCEPTED, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Refund was successful after requery
     */
    public function test_refund_was_successful_after_requery()
    {
        $successfulTransactionCode = '00';
        $successfulTransactionMessage = 'SUCCESSFUL';
        $successfulTransactionReference = 'ZZW|LOC|CA|SKYE|AC|150922210133|AJNWYUGT';
        Bus::fake();
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::CLOSED,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $transaction = Transaction::factory()
                                ->for($loan)
                                ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                                                                                'responseCode' => $successfulTransactionCode,
                                                                                'responseMessage' => $successfulTransactionMessage,
                                                                                'transactionRef' => $successfulTransactionReference,
                                                                                'transactionId' => '6106594104283',
                                                                                'transactionDate' => '2022-06-08 14:23:23.393'
                                                                            ], 200, [
                                                                                'Content-Type' => 'application/json'
                                                                            ]),
        ]);

        (new RequeryTransaction($transaction, 'refund'))->handle();
        $loanOffer->refresh();
        $transaction->refresh();

        $this->assertSame($successfulTransactionCode, $transaction->interswitch_transaction_code);
        $this->assertSame($successfulTransactionMessage, $transaction->interswitch_transaction_message);
        $this->assertSame($successfulTransactionReference, $transaction->interswitch_transaction_reference);
        $this->assertSame(Transaction::REFUND, $transaction->type);
        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Refund failed after requery
     */
    public function test_refund_failed_after_requery()
    {
        $failedResponseCode = '51';
        $failedResponseMessage = 'FAILED';
        $failedTransactionId = '958984578597843792442';
        Bus::fake();
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::CLOSED,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $transaction = Transaction::factory()
                                ->for($loan)
                                ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::response([
                                                                                'responseCode' => $failedResponseCode,
                                                                                'responseMessage' => $failedResponseMessage,
                                                                            ], 200, [
                                                                                'Content-Type' => 'application/json'
                                                                            ]),
        ]);

        (new RequeryTransaction($transaction, 'refund'))->handle();
        $loanOffer->refresh();
        $transaction->refresh();

        $this->assertSame($failedResponseCode, $transaction->interswitch_transaction_code);
        $this->assertSame($failedResponseMessage, $transaction->interswitch_transaction_message);
        $this->assertSame(Transaction::REFUND, $transaction->type);
        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        Bus::assertNotDispatched(SendSms::class);
    }
}
