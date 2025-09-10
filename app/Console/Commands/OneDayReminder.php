<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Jobs\Interswitch\SendSms;

class OneDayReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:one-day-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One day repayment reminder for open loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OPEN" loans that are due the next day
        $loansDueNextDay = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                    ->where('status', LoanOffer::OPEN)
                                    ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())->addDay()))
                                    ->get();

        foreach ($loansDueNextDay as $loanDueNextDay) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanDueNextDay->loan->amount_remaining + $loanDueNextDay->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_warning_days_1_message', [
                    'loan_balance' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanDueNextDay->customer->virtualAccount) ? "or transfer to {$loanDueNextDay->customer->virtualAccount->bank_name}, Acc No: {$loanDueNextDay->customer->virtualAccount->account_number}" : ''
                ]),
                $loanDueNextDay->customer->phone_number,
                $loanDueNextDay->id,
                true
            );
        }                         
    }
}
