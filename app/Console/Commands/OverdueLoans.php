<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Setting, User};
use App\Services\Calculation\Money as MoneyCalculator;
use App\Services\Interswitch as InterswitchService;
use App\Jobs\Interswitch\SendSms;

class OverdueLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add default payment to overdue loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // The timezone to use
        $timezone = config('quickfund.date_query_timezone');

        // Get the settings of the application
        $setting = Setting::find(Setting::MAIN_ID);

        // Get the collectors of the application
        $collectors = User::whereHas('role', fn($query) => $query->where('permissions', 'LIKE', '%collection-cases%'))
                        ->get();
                        
        $collectorCounter = 0;

        /**
         * Get the "OPEN" loans that are "OVERDUE" and the "OVERDUE" loans that are defaulted again
         */
        $overdueLoanOffers = LoanOffer::with(['loan', 'customer.virtualAccount'])
                                    ->whereHas('loan', fn($query) => $query->whereDate('due_date', '<', Carbon::parse(now()->timezone($timezone)->toDateTimeString())))
                                    ->where(fn($query) => $query->where('status', LoanOffer::OPEN)
                                                                ->orWhere(fn($query2) => $query2->where('status', LoanOffer::OVERDUE)
                                                                                                ->whereHas('loan', fn($query3) => $query3->whereNotNull('next_due_date')
                                                                                                                                        ->whereDate('next_due_date', '<', Carbon::parse(now()->timezone($timezone)->toDateTimeString())))))
                                    ->get();

        foreach ($overdueLoanOffers as $overdueLoanOffer) {
            // Get a fresh instance of the loan to know if the loan is closed or fully paid for
            $overdueLoanOffer->refresh();
            $overdueLoanOffer->loan->refresh();

            if ($overdueLoanOffer->status !== LoanOffer::CLOSED &&
                ($overdueLoanOffer->loan->amount_remaining > 0 || $overdueLoanOffer->loan->penalty_remaining > 0)) {
                // Get the number of days that penalty should stop accruing on a loan
                $daysToStopPenaltyFromAccruing = $setting?->days_to_stop_penalty_from_accruing ?? config('quickfund.days_to_stop_penalty_from_accruing');

                // Check if the due date has exceeded the number of days to stop accruing penalty
                if ($overdueLoanOffer->loan->due_date->addDays($daysToStopPenaltyFromAccruing) < today()) {
                    $addedAmount = 0;
                } else {
                    $addedAmount = ($overdueLoanOffer->default_interest / 100) * $overdueLoanOffer->loan->amount;
                    $addedAmount = (int) $addedAmount;
                }

                $higherDenominationAddedAmount = app()->make(MoneyCalculator::class, [
                    'value' => $addedAmount
                ])->toHigherDenomination()->getValue();

                // Get the loan balance
                $overdueLoanOfferBalance = $overdueLoanOffer->loan->amount_remaining + $overdueLoanOffer->loan->penalty_remaining;

                $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                    'value' => $overdueLoanOfferBalance
                ])->toHigherDenomination()->getValue();

                // Process the overdue loan logic
                DB::transaction(function() use ($overdueLoanOffer, $addedAmount, $collectors, &$collectorCounter) {
                    // Increase the penalties
                    $overdueLoanOffer->loan->increment('penalty', $addedAmount);

                    $overdueLoanOffer->loan->increment('penalty_remaining', $addedAmount);

                    // Increase the number of defaults
                    $overdueLoanOffer->loan->increment('defaults');

                    // Update the loan next due date
                    $overdueLoanOffer->loan->update([
                        'next_due_date' => Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())->addDays($overdueLoanOffer->default_fees_addition_days)
                    ]);

                    if ($overdueLoanOffer->status !== LoanOffer::OVERDUE) {
                        // Update the loan status
                        $status = app()->make(InterswitchService::class)->status(LoanOffer::OVERDUE, $overdueLoanOffer->id, true);

                        $overdueLoanOffer->forceFill([
                            'status' => LoanOffer::OVERDUE
                        ])->save();
                    }

                    // Check if a collection case is not created for the overdue loan
                    if ($overdueLoanOffer->collectionCase()
                            ->doesntExist()) {
                        if ($collectors->isNotEmpty()) {
                            /**
                             * We check if the counter is the same or greater than the collectors count
                             */
                            if ($collectorCounter >= $collectors->count()) {
                                $collectorCounter = 0;
                            }
        
                            // Assign a collector to the overdue loan
                            $collector = $collectors->slice($collectorCounter, 1)->first();
        
                            // Just in case the collector has been deleted, we check for the existence of the collector
                            if ($collector->exists) {
                                $overdueLoanOffer->collectionCase()
                                    ->updateOrCreate([

                                    ], [
                                        'user_id' => $collector->id,
                                        'assigned_at' => now()
                                    ]);
                            }
        
                            // Increase the counter so the next loan goes to the next collector
                            $collectorCounter++;
                        }
                    }
                });

                $ussdRepaymentAmount = app()->make(MoneyCalculator::class, [
                    'value' => $overdueLoanOffer->loan->amount_remaining + $overdueLoanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();
    
                SendSms::dispatch(
                    __('interswitch.debt_overdue_message', [
                        'amount_remaining' => config('quickfund.currency_representation').number_format($higherDenominationLoanBalance, 2),
                        'default_amount' => config('quickfund.currency_representation').number_format($higherDenominationAddedAmount, 2),
                        'ussd_repayment_amount' => ceil($ussdRepaymentAmount),
                        'virtual_account_details' => isset($overdueLoanOffer->customer->virtualAccount) ? "or transfer to {$overdueLoanOffer->customer->virtualAccount->bank_name}, Acc No: {$overdueLoanOffer->customer->virtualAccount->account_number}" : ''
                    ]),
                    $overdueLoanOffer->customer->phone_number,
                    $overdueLoanOffer->id,
                    true
                );
            }
        }
    }
}
