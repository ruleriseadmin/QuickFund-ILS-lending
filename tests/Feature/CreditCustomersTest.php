<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use App\Models\{Blacklist, Customer, Loan, LoanOffer, Transaction};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class CreditCustomersTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Crediting of a customer account was successful
     */
    public function test_customer_account_is_credited_successfully()
    {
        $phone = '+2348123456789';
        $successfulResponseCode = '00';
        $successfulResponseMessage = 'Transaction Successful';
        $transactionId = '6106594104283';
        $transactionRef = 'ZZW|LOC|CA|GTB|AC|290722105310|BUHRG7RN';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::ACCEPTED
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $blacklist = Blacklist::factory()
                            ->create([
                                'phone_number' => $customer->phone_number,
                                'type' => Blacklist::BY_CODE,
                                'completed' => true
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
                'transactionId' => $transactionId,
                'transactionRef' => $transactionRef
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        $this->artisan('loans:credit-customers');
        $loanOffer->refresh();

        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $successfulResponseCode,
            'interswitch_transaction_message' => $successfulResponseMessage,
            'interswitch_transaction_reference' => $transactionRef,
            'type' => Transaction::CREDIT
        ]);
        $this->assertModelMissing($blacklist);
        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Crediting of a customer account failed
     */
    public function test_customer_account_crediting_failed()
    {
        $failedResponseCode = '503';
        $failedResponseMessage = 'Remote not responding';
        $transactionId = '6106594104283';
        $phone = '+2348123456789';
        $customer = Customer::factory()
                            ->create([
                                'phone_number' => $phone
                            ]);
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::ACCEPTED
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $blacklist = Blacklist::factory()
                            ->create([
                                'phone_number' => $customer->phone_number,
                                'type' => Blacklist::BY_CODE,
                                'completed' => true
                            ]);
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/fund' => Http::response([
                'responseCode' => $failedResponseCode,
                'responseMessage' => $failedResponseMessage,
                'transactionId' => $transactionId,
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        $this->artisan('loans:credit-customers');
        $loanOffer->refresh();

        $this->assertDatabaseHas('transactions', [
            'interswitch_transaction_code' => $failedResponseCode,
            'interswitch_transaction_message' => $failedResponseMessage,
            'type' => Transaction::CREDIT
        ]);
        $this->assertModelExists($blacklist);
        $this->assertSame(LoanOffer::ACCEPTED, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }
}
