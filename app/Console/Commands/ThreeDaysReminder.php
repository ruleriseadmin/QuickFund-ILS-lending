<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;

class ThreeDaysReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:three-days-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Three days repayment reminder for open loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OPEN" loans that are due in three days time
        $loansDueIn3DaysTime = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                        ->where('status', LoanOffer::OPEN)
                                        ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())->addDays(3)))
                                        ->get();

        foreach ($loansDueIn3DaysTime as $loanDueIn3DaysTime) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanDueIn3DaysTime->loan->amount_remaining + $loanDueIn3DaysTime->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_warning_days_3_message', [
                    'loan_balance' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'due_date' => $loanDueIn3DaysTime->loan->due_date->format('d-m-Y'),
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanDueIn3DaysTime->customer->virtualAccount) ? "or transfer to {$loanDueIn3DaysTime->customer->virtualAccount->bank_name}, Acc No: {$loanDueIn3DaysTime->customer->virtualAccount->account_number}" : ''
                ]),
                $loanDueIn3DaysTime->customer->phone_number,
                $loanDueIn3DaysTime->id,
                true
            );
        }
    }
}
