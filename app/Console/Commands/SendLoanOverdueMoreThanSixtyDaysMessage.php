<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Application as ApplicationService;

class SendLoanOverdueMoreThanSixtyDaysMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:loan-overdue-more-than-sixty-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send message to customers with loans overdue more than 60 days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get the loans has been overdue between 21 to 60 days
        $loanOffers = LoanOffer::with(['customer.virtualAccount'])
                            ->where('status', LoanOffer::OVERDUE)
                            ->whereHas('loan', fn($query) => $query->whereDate('due_date', '<', Carbon::parse(now()->timezone($timezone)->subDays(60)->toDateTimeString())))
                            ->get();

        foreach ($loanOffers as $loanOffer) {
            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.loan_overdue_more_than_60_days', [
                    'virtual_account_message' => isset($loanOffer->customer->virtualAccount) ? ' To avoid this, repay your loan by transferring '.app()->make(ApplicationService::class)->moneyDisplay($loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining).' to '.$loanOffer->customer->virtualAccount->bank_name.' Account number '.$loanOffer->customer->virtualAccount->account_number.', Verify that the account name is your name.' : null
                ]),
                $loanOffer->customer->phone_number,
                $loanOffer->id,
                true
            );
        }
    }
}
