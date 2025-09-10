<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Jobs\Interswitch\SendSms;

class DueTodayReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:due-today-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Due day repayment reminder for open loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OPEN" loans that are due the next day
        $loansDueToday = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                    ->where('status', LoanOffer::OPEN)
                                    ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())))
                                    ->get();

        foreach ($loansDueToday as $loanDueToday) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanDueToday->loan->amount_remaining + $loanDueToday->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_due_today_message', [
                    'loan_balance' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanDueToday->customer->virtualAccount) ? "or transfer to {$loanDueToday->customer->virtualAccount->bank_name}, Acc No: {$loanDueToday->customer->virtualAccount->account_number}" : ''
                ]),
                $loanDueToday->customer->phone_number,
                $loanDueToday->id,
                true
            );
        }
    }
}
