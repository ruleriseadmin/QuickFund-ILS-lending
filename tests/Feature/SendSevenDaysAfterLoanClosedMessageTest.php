<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Bus\PendingBatch;
use App\Models\{LoanOffer, Customer, Loan};
use Tests\TestCase;

class SendSevenDaysAfterLoanClosedMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Seven days after loan has been closed message sent
     */
    public function test_message_sent_after_seven_days_of_loan_closed_was_sent_successfully()
    {
        Bus::fake();
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

        $this->artisan('messages:seven-days-after-loan-closed');

        Bus::assertBatched(fn(PendingBatch $pendingBatch) => $pendingBatch->name === 'send-seven-days-after-loan-closed-message');
    }
}
