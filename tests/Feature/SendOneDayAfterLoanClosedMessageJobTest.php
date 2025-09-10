<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Foundation\Testing\WithFaker;
use App\Jobs\Interswitch\SendSms;
use App\Models\{LoanOffer, Customer, Loan};
use App\Jobs\SendOneDayAfterLoanClosedMessage;
use Tests\TestCase;

class SendOneDayAfterLoanClosedMessageJobTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * One day after loan has been closed message sent
     */
    public function test_message_sent_after_one_day_of_loan_closed_was_sent_successfully()
    {
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::CLOSED,
                                'updated_at' => now()->timezone(config('quickfund.date_query_timezone'))->subDays(1)
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();

        (new SendOneDayAfterLoanClosedMessage([$customer]))->handle();

        Bus::assertDispatched(SendSms::class);
    }
}
