<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Foundation\Testing\WithFaker;
use App\Jobs\Interswitch\SendSms;
use App\Models\{LoanOffer, Customer, Loan};
use Tests\TestCase;

class SendLoanOverdueElevenToTwentyDaysMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Send loan overdue eleven to twenty days message sent successfully
     */
    public function test_messages_sent_on_loans_overdue_for_eleven_to_twenty_days_were_successfully_sent()
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
                                'status' => LoanOffer::OVERDUE,
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'due_date' => now()->timezone(config('quickfund.date_query_timezone'))->subDays($this->faker->numberBetween(11, 20))
                    ]);

        $this->artisan('messages:loan-overdue-eleven-to-twenty-days');

        Bus::assertDispatched(SendSms::class);
    }
}
