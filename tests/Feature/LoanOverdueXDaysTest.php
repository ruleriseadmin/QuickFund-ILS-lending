<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Customer};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class LoanOverdueXDaysTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * (X) days loan overdue message sent successfully
     */
    public function test_x_days_loan_overdue_message_was_sent_successfully()
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
                                'due_date' => Carbon::parse(today(config('quickfund.date_query_timezone'))->toDateTimeString())->subDays(3)
                            ])
                            ->create([
                                'status' => LoanOffer::OVERDUE
                            ]);

        $this->artisan('loans:overdue-x-days');

        Bus::assertDispatched(SendSms::class);
    }
}
