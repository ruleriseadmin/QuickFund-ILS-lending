<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Bus\PendingBatch;
use App\Models\{LoanOffer, Customer, Loan};
use Tests\TestCase;

class SendOneDayAfterLoanClosedMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * One day after loan has been closed message sent
     */
    public function test_message_sent_after_one_day_of_loan_closed_was_sent_successfully()
    {
        Bus::fake();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::CLOSED,
                                'updated_at' => now()->timezone(config('quickfund.date_query_timezone'))->subDays(1)
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();

        $this->artisan('messages:one-day-after-loan-closed');

        Bus::assertBatched(fn(PendingBatch $pendingBatch) => $pendingBatch->name === 'send-one-day-after-loan-closed-message');
    }
}
