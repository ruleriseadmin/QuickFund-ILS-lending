<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use App\Models\{Blacklist, LoanOffer, Customer, Loan, Transaction};
use Tests\TestCase;

class RequeryAcceptedLoansTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Transaction was confirmed as successful
     */
    public function test_transaction_successful_after_requery()
    {
        $phone = '+2348123456789';
        $successfulTransactionCode = '00';
        $successfulTransactionMessage = 'SUCCESSFUL';
        $successfulTransactionReference = 'ZZW|LOC|CA|SKYE|AC|150922210133|AJNWYUGT';
        $failedTransactionCode = '51';
        $failedTransactionMessage = 'FAILED';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::ACCEPTED,
                                'updated_at' => now()->subHours(5)
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $transaction1 = Transaction::factory()
                                ->for($loan)
                                ->create([
                                    'type' => Transaction::CREDIT
                                ]);
        $transaction2 = Transaction::factory()
                                ->for($loan)
                                ->create([
                                    'type' => Transaction::NONE
                                ]);
        $blacklist = Blacklist::factory()
                            ->create([
                                'phone_number' => $customer->phone_number,
                                'type' => Blacklist::BY_CODE,
                                'completed' => true
                            ]);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::sequence()
                                                                            ->push([
                                                                                'responseCode' => $successfulTransactionCode,
                                                                                'responseMessage' => $successfulTransactionMessage,
                                                                                'transactionRef' => $successfulTransactionReference,
                                                                                'transactionId' => '6106594104283',
                                                                                'transactionDate' => '2022-06-08 14:23:23.393'
                                                                            ], 200, [
                                                                                'Content-Type' => 'application/json'
                                                                            ])
                                                                            ->push([
                                                                                'responseCode' => $failedTransactionCode,
                                                                                'responseMessage' => $failedTransactionMessage,
                                                                                'transactionId' => '6106594104242',
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

        $this->artisan('loans:requery-accepted');
        $loanOffer->refresh();

        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
        $this->assertModelMissing($blacklist);
        $this->assertNotNull($loanOffer->last_requeried_at);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction1->id,
            'interswitch_transaction_code' => $successfulTransactionCode,
            'interswitch_transaction_message' => $successfulTransactionMessage,
            'interswitch_transaction_reference' => $successfulTransactionReference
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction2->id,
            'interswitch_transaction_code' => $failedTransactionCode,
            'interswitch_transaction_message' => $failedTransactionMessage,
        ]);
    }

    /**
     * Transaction was confirmed as failed
     */
    public function test_transaction_failed_after_requery()
    {
        $phone = '+2348123456789';
        $failedTransactionCode = '51';
        $failedTransactionMessage = 'FAILED';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::ACCEPTED,
                                'updated_at' => now()->subHours(5)
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $transaction1 = Transaction::factory()
                                ->for($loan)
                                ->create([
                                    'type' => Transaction::CREDIT
                                ]);
        $transaction2 = Transaction::factory()
                                ->for($loan)
                                ->create([
                                    'type' => Transaction::NONE
                                ]);
        $blacklist = Blacklist::factory()
                            ->create([
                                'phone_number' => $customer->phone_number,
                                'type' => Blacklist::BY_CODE,
                                'completed' => true
                            ]);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/query*' => Http::sequence()
                                                                            ->push([
                                                                                'responseCode' => $failedTransactionCode,
                                                                                'responseMessage' => $failedTransactionCode,
                                                                                'transactionId' => '6106594104283',
                                                                                'transactionDate' => '2022-06-08 14:23:23.393'
                                                                            ], 200, [
                                                                                'Content-Type' => 'application/json'
                                                                            ])
                                                                            ->push([
                                                                                'responseCode' => $failedTransactionCode,
                                                                                'responseMessage' => $failedTransactionMessage,
                                                                                'transactionId' => '6106594104242',
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

        $this->artisan('loans:requery-accepted');
        $loanOffer->refresh();

        $this->assertSame(LoanOffer::FAILED, $loanOffer->status);
        $this->assertModelExists($blacklist);
        $this->assertNotNull($loanOffer->last_requeried_at);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction1->id,
            'interswitch_transaction_code' => $failedTransactionCode,
            'interswitch_transaction_message' => $failedTransactionCode,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction2->id,
            'interswitch_transaction_code' => $failedTransactionCode,
            'interswitch_transaction_message' => $failedTransactionMessage,
        ]);
    }
}
