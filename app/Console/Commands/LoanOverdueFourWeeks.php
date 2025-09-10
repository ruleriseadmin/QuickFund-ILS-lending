<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;

class LoanOverdueFourWeeks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:overdue-four-weeks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send informational messages for loans overdue by four weeks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OVERDUE" loans that are two weeks old
        $loansOverdueForFourWeeks = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                        ->where('status', LoanOffer::OVERDUE)
                                        ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())->subWeeks(4)))
                                        ->get();

        foreach ($loansOverdueForFourWeeks as $loanOverdueForFourWeeks) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanOverdueForFourWeeks->loan->amount_remaining + $loanOverdueForFourWeeks->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_overdue_fourth_week_message', [
                    'amount_remaining' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'overdue_days' => 28,
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanOverdueForFourWeeks->customer->virtualAccount) ? "or transfer to {$loanOverdueForFourWeeks->customer->virtualAccount->bank_name}, Acc No: {$loanOverdueForFourWeeks->customer->virtualAccount->account_number}" : ''
                ]),
                $loanOverdueForFourWeeks->customer->phone_number,
                $loanOverdueForFourWeeks->id,
                true
            );
        }
    }
}
