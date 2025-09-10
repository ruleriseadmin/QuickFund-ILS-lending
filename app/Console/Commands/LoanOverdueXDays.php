<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Carbon, Str};
use App\Models\LoanOffer;
use App\Services\Calculation\{
    Money as MoneyCalculator,
    Date
};
use App\Jobs\Interswitch\SendSms;

class LoanOverdueXDays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:overdue-x-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send informational messages for loans overdue by (x) days';

    /**
     * The number of after the overdue date to send messages
     */
    private $consecutiveDays = 3;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * We add 1 as we do not want to send this SMS to the users on the overdue date as we already
         * have a message for that
         */
        $this->consecutiveDays = $this->consecutiveDays + 1;

        $timezone = config('quickfund.date_query_timezone');

        $today = today()->timezone($timezone)->toDateTimeString();

        $overdueLoans = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                ->where('status', LoanOffer::OVERDUE)
                                ->whereHas('loan', fn($query) => $query->where('due_date', '>', Carbon::parse($today)->subDays($this->consecutiveDays)->format('Y-m-d'))
                                                                    ->where('due_date', '<=', Carbon::parse($today)->format('Y-m-d')))
                                ->get();

        foreach ($overdueLoans as $overdueLoan) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $overdueLoan->loan->amount_remaining + $overdueLoan->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            $overdueDays = app()->make(Date::class)->dayDifference($today, $overdueLoan->loan->due_date, $timezone);

            // We only want to send this messages to users that the overdue days for 2 or more days
            if ($overdueDays <= 1) {
                continue;
            }

            // Send the reminder message
            SendSms::dispatch(
                __('interswitch.debt_overdue_x_days_message', [
                    'amount_remaining' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                    'overdue_days' => $overdueDays,
                    'pluralization' => Str::plural('day', $overdueDays),
                    'ussd_repayment_amount' => ceil($higherDenominationLoanBalance),
                    'virtual_account_details' => isset($overdueLoan->customer->virtualAccount) ? "or transfer to {$overdueLoan->customer->virtualAccount->bank_name}, Acc No: {$overdueLoan->customer->virtualAccount->account_number}" : ''
                ]),
                $overdueLoan->customer->phone_number,
                $overdueLoan->id,
                true
            );
        }
    }
}
