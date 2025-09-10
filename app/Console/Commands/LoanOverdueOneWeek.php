<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\LoanOffer;
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;

class LoanOverdueOneWeek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:overdue-one-week';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send informational messages for loans overdue by one week';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get all "OVERDUE" loans that are one week old
        $loansOverdueForOneWeek = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                        ->where('status', LoanOffer::OVERDUE)
                                        ->whereHas('loan', fn($query) => $query->whereDate('due_date', Carbon::parse(now()->timezone($timezone)->toDateTimeString())->subWeek()))
                                        ->get();

        foreach ($loansOverdueForOneWeek as $loanOverdueForOneWeek) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $loanOverdueForOneWeek->loan->amount_remaining + $loanOverdueForOneWeek->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_overdue_first_week_message', [
                    'amount_remaining' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'overdue_days' => 7,
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($loanOverdueForOneWeek->customer->virtualAccount) ? "or transfer to {$loanOverdueForOneWeek->customer->virtualAccount->bank_name}, Acc No: {$loanOverdueForOneWeek->customer->virtualAccount->account_number}" : ''
                ]),
                $loanOverdueForOneWeek->customer->phone_number,
                $loanOverdueForOneWeek->id,
                true
            );
        }
    }
}
