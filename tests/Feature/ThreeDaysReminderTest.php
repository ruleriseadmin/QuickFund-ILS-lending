<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Customer};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class ThreeDaysReminderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Three days reminder was sent successfully
     */
    public function test_three_days_reminder_was_sent_successfully()
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
                                'due_date' => Carbon::parse(now(config('quickfund.date_query_timezone'))->toDateTimeString())->addDays(3)
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);

        $this->artisan('loans:three-days-reminder');

        Bus::assertDispatched(SendSms::class);
    }
}
