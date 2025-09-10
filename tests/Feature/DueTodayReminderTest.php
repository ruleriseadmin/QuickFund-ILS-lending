<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Customer};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class DueTodayReminderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Due today reminder was sent successfully
     */
    public function test_due_today_reminder_was_successfully_sent()
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
                                'due_date' => Carbon::parse(now(config('quickfund.date_query_timezone'))->toDateTimeString())
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);

        $this->artisan('loans:due-today-reminder');

        Bus::assertDispatched(SendSms::class);
    }
}
