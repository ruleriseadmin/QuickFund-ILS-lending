<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Application as ApplicationService;

class SendLoanOverdueTwentyOneToSixtyDaysMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:loan-overdue-twenty-one-to-sixty-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send message to customers with loans overdue 21 to 60 days';

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
                            ->whereHas('loan', fn($query) => $query->whereDate('due_date', '>=', Carbon::parse(now()->timezone($timezone)->subDays(60)->toDateTimeString()))
                                                                ->whereDate('due_date', '<=', Carbon::parse(now()->timezone($timezone)->subDays(21)->toDateTimeString())))
                            ->get();

        foreach ($loanOffers as $loanOffer) {
            $overdueDays = Carbon::parse(Carbon::parse($loanOffer->loan->due_date)->timezone($timezone)->toDateTimeString())->diffInDays(now()->timezone($timezone)->toDateTimeString());

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.loan_overdue_21_to_60_days', [
                    'virtual_account_message' => isset($loanOffer->customer->virtualAccount) ? ' Transfer '.app()->make(ApplicationService::class)->moneyDisplay($loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining).' to '.$loanOffer->customer->virtualAccount->bank_name.' Account number '.$loanOffer->customer->virtualAccount->account_number.', Verify that the account name is your name.' : null
                ]),
                $loanOffer->customer->phone_number,
                $loanOffer->id,
                true
            );
        }
    }
}
