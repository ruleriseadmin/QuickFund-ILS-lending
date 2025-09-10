<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\{LoanOffer, Customer, Loan};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class SendLoanOverdueThreeToTenDaysMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Send loan overdue three to ten days message sent successfully
     */
    public function test_messages_sent_on_loans_overdue_for_three_to_ten_days_were_successfully_sent()
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
                        'due_date' => now()->timezone(config('quickfund.date_query_timezone'))->subDays($this->faker->numberBetween(3, 10))
                    ]);

        $this->artisan('messages:loan-overdue-three-to-ten-days');

        Bus::assertDispatched(SendSms::class);
    }
}
