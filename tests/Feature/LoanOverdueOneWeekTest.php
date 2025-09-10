<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Customer};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class LoanOverdueOneWeekTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    /**
     * One week loan overdue message sent successfully
     */
    public function test_one_week_loan_overdue_message_was_sent_successfully()
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
                                'due_date' => Carbon::parse(now(config('quickfund.date_query_timezone'))->toDateTimeString())->subWeek()
                            ])
                            ->create([
                                'status' => LoanOffer::OVERDUE
                            ]);

        $this->artisan('loans:overdue-one-week');

        Bus::assertDispatched(SendSms::class);
    }
}
