<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Foundation\Testing\WithFaker;
use App\Jobs\Interswitch\SendSms;
use App\Jobs\SendSevenDaysAfterLoanClosedMessage;
use App\Models\{LoanOffer, Customer, Loan};
use Tests\TestCase;

class SendSevenDaysAfterLoanClosedMessageJobTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Seven days after loan has been closed message sent
     */
    public function test_message_sent_after_seven_days_of_loan_closed_was_sent_successfully()
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
                                'updated_at' => now()->timezone(config('quickfund.date_query_timezone'))->subDays(7)
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();

        (new SendSevenDaysAfterLoanClosedMessage([$customer]))->handle();

        Bus::assertDispatched(SendSms::class);
    }
}
