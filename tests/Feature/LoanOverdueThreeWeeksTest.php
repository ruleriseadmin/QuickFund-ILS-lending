<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Customer};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class LoanOverdueThreeWeeksTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Three weeks loan overdue message sent successfully
     */
    public function test_three_weeks_loan_overdue_message_was_sent_successfully()
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
                            ->hasLoan([
                                'due_date' => Carbon::parse(now(config('quickfund.date_query_timezone'))->toDateTimeString())->subWeeks(3)
                            ])
                            ->create([
                                'status' => LoanOffer::OVERDUE
                            ]);

        $this->artisan('loans:overdue-three-weeks');

        Bus::assertDispatched(SendSms::class);
    }
}
