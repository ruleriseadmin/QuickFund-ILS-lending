<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;

class LoanOverdueThreeWeeks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:overdue-three-weeks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send informational messages for loans overdue by three weeks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OVERDUE" loans that are two weeks old
        $loansOverdueForThreeWeeks = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                        ->where('status', LoanOffer::OVERDUE)
                                        ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())->subWeeks(3)))
                                        ->get();

        foreach ($loansOverdueForThreeWeeks as $loanOverdueForThreeWeeks) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanOverdueForThreeWeeks->loan->amount_remaining + $loanOverdueForThreeWeeks->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_overdue_third_week_message', [
                    'amount_remaining' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'overdue_days' => 21,
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanOverdueForThreeWeeks->customer->virtualAccount) ? "or transfer to {$loanOverdueForThreeWeeks->customer->virtualAccount->bank_name}, Acc No: {$loanOverdueForThreeWeeks->customer->virtualAccount->account_number}" : ''
                ]),
                $loanOverdueForThreeWeeks->customer->phone_number,
                $loanOverdueForThreeWeeks->id,
                true
            );
        }
    }
}
