<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;

class LoanOverdueTwoWeeks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:overdue-two-weeks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send informational messages for loans overdue by two weeks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OVERDUE" loans that are two weeks old
        $loansOverdueForTwoWeeks = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                        ->where('status', LoanOffer::OVERDUE)
                                        ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())->subWeeks(2)))
                                        ->get();

        foreach ($loansOverdueForTwoWeeks as $loanOverdueForTwoWeeks) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanOverdueForTwoWeeks->loan->amount_remaining + $loanOverdueForTwoWeeks->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_overdue_second_week_message', [
                    'amount_remaining' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'overdue_days' => 14,
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanOverdueForTwoWeeks->customer->virtualAccount) ? "or transfer to {$loanOverdueForTwoWeeks->customer->virtualAccount->bank_name}, Acc No: {$loanOverdueForTwoWeeks->customer->virtualAccount->account_number}" : ''
                ]),
                $loanOverdueForTwoWeeks->customer->phone_number,
                $loanOverdueForTwoWeeks->id,
                true
            );
        }
    }
}
