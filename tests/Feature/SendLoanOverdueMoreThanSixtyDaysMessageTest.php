<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Foundation\Testing\WithFaker;
use App\Jobs\Interswitch\SendSms;
use App\Models\{LoanOffer, Customer, Loan};
use Tests\TestCase;

class SendLoanOverdueMoreThanSixtyDaysMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Send loan overdue more than 60 days message sent successfully
     */
    public function test_messages_more_than_sixty_days_were_successfully_sent()
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
                        'due_date' => now()->timezone(config('quickfund.date_query_timezone'))->subDays(61)
                    ]);

        $this->artisan('messages:loan-overdue-more-than-sixty-days');

        Bus::assertDispatched(SendSms::class);
    }
}
