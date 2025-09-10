<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Carbon, Str};
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Application as ApplicationService;

class SendLoanOverdueElevenToTwentyDaysMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:loan-overdue-eleven-to-twenty-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send message to customers with loans overdue 11 to 20 days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get the loans has been overdue between 3 to 10 days
        $loanOffers = LoanOffer::with(['customer.virtualAccount'])
                            ->where('status', LoanOffer::OVERDUE)
                            ->whereHas('loan', fn($query) => $query->whereDate('due_date', '>=', Carbon::parse(now()->timezone($timezone)->subDays(20)->toDateTimeString()))
                                                                ->whereDate('due_date', '<=', Carbon::parse(now()->timezone($timezone)->subDays(11)->toDateTimeString())))
                            ->get();

        foreach ($loanOffers as $loanOffer) {
            $overdueDays = Carbon::parse(Carbon::parse($loanOffer->loan->due_date)->timezone($timezone)->toDateTimeString())->diffInDays(now()->timezone($timezone)->toDateTimeString());

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.loan_overdue_11_to_20_days', [
                    'due_days_difference' => $overdueDays.' '.Str::plural('day', $overdueDays),
                    'virtual_account_message' => isset($loanOffer->customer->virtualAccount) ? ' Transfer '.app()->make(ApplicationService::class)->moneyDisplay($loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining).' to '.$loanOffer->customer->virtualAccount->bank_name.' Account number '.$loanOffer->customer->virtualAccount->account_number.'. Verify that the account name is your name.' : null
                ]),
                $loanOffer->customer->phone_number,
                $loanOffer->id,
                true
            );
        }
    }
}
