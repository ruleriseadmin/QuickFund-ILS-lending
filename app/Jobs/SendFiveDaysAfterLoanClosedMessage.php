<?php

namespace App\Jobs;

use Illuminate\Bus\{Queueable, Batchable};
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\Interswitch\SendSms;

class SendFiveDaysAfterLoanClosedMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * The customers
     */
    public $customers;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customers)
    {
        $this->customers = $customers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->customers as $customer) {
            // Get the latest loan offer that is "CLOSED"
            $latestLoanOffer = $customer->loanOffers->first();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.loan_fully_repaid_after_5_days'),
                $latestLoanOffer->customer->phone_number,
                $latestLoanOffer->id,
                true
            );
        }
    }
}
