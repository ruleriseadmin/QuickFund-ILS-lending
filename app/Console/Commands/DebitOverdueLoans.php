<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use App\Models\{CollectionCase, LoanOffer};
use App\Jobs\RequeryTransaction;
use App\Jobs\Interswitch\SendSms;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Services\Loans\Calculator as LoanCalculator;
use App\Services\Interswitch as InterswitchService;

class DebitOverdueLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:debit-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debit the overdue loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * Get the "OVERDUE" loan offers
         */
        $overdueLoanOffers = LoanOffer::with(['loan', 'customer'])
                                    ->where('status', LoanOffer::OVERDUE)
                                    ->has('loan')
                                    ->get();

        foreach ($overdueLoanOffers as $overdueLoanOffer) {
            // Get a fresh instance of the loan to know if the loan is closed or fully paid for
            $overdueLoanOffer->refresh();
            $overdueLoanOffer->loan->refresh();

            if ($overdueLoanOffer->status !== LoanOffer::CLOSED &&
                ($overdueLoanOffer->loan->amount_remaining > 0 || $overdueLoanOffer->loan->penalty_remaining > 0)) {
                /**
                 * Loan is still qualified for deduction. Create the record of the transaction
                 */
                $transaction1 = $overdueLoanOffer->loan->transactions()->create([
                    'amount' => $overdueLoanOffer->loan->amount_remaining + $overdueLoanOffer->loan->penalty_remaining
                ]);

                try {
                    /**
                     * We initiate debit. This is regarded as the first debit
                     */
                    $debitDetails1 = app()->make(InterswitchService::class)->debit(
                        $transaction1->amount,
                        $overdueLoanOffer->customer->phone_number,
                        $overdueLoanOffer->id,
                        $transaction1->id,
                        true,
                        true,
                        true
                    );

                    /**
                     * We check if a response code was returned. Usually, we get a response code
                     */
                    if (isset($debitDetails1['responseCode'])) {
                        // Response code returned. Now we check if the debit transaction was successful.
                        if ($debitDetails1['responseCode'] === '00') {
                            // Debit was successful. Process the debit transaction
                            app()->make(LoanCalculator::class)->processDebit($debitDetails1, $overdueLoanOffer->loan, $transaction1);

                            // Initialize the debit variables
                            $deductedAmount = $transaction1->amount;
                        } else {
                            // Debit was not successful. Process the failure of debit
                            app()->make(LoanCalculator::class)->processNonDebit($debitDetails1, $transaction1);

                            /**
                             * Due to the fact that the customer can have a deductable amount in their bank account, we
                             * initiate debit on the deductable amount in their account
                             */
                            if (isset($debitDetails1['deductableAmount'])) {
                                /**
                                 * Another debit can be initiated based on the account balance of the customer. We initiate
                                 * a second debit on the customer
                                 */
                                /**
                                 * Create the record of the second transaction
                                 */
                                $transaction2 = $overdueLoanOffer->loan->transactions()->create([
                                    'amount' => $debitDetails1['deductableAmount']
                                ]);
                                
                                try {
                                    /**
                                     * We initiate debit again. This is the second debit and we will not take the available
                                     * balance anymore
                                     */
                                    $debitDetails2 = app()->make(InterswitchService::class)->debit(
                                        $transaction2->amount,
                                        $overdueLoanOffer->customer->phone_number,
                                        $overdueLoanOffer->id,
                                        $transaction2->id,
                                        true,
                                        true,
                                        false
                                    );
                                    
                                    /**
                                     * We check if a response code was returned. Usually, we get a response code
                                     */
                                    if (isset($debitDetails2['responseCode'])) {
                                        // Response code returned. Now we check if the debit transaction was successful.
                                        if ($debitDetails2['responseCode'] === '00') {
                                            // Debit was successful. Process the debit transaction
                                            app()->make(LoanCalculator::class)->processDebit($debitDetails2, $overdueLoanOffer->loan, $transaction2);

                                            // Initialize the debit variables
                                            $deductedAmount = $transaction2->amount;
                                        } else {
                                            // Debit was not successful. Process the failure of debit
                                            app()->make(LoanCalculator::class)->processNonDebit($debitDetails2, $transaction2);

                                            continue;
                                        }
                                    } else {
                                        /**
                                         * For some reason we did not get a response code back. This might be due to network errors. In
                                         * such case we dispatch a job on the transaction so as to process the debit transaction on the
                                         * background
                                         */
                                        RequeryTransaction::dispatch($transaction2, 'debit')
                                                        ->delay(300);

                                        continue;
                                    }
                                } catch (Throwable $e2) {
                                    // Something strange happened
                                    RequeryTransaction::dispatch($transaction2, 'debit')
                                                    ->delay(300);

                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    } else {
                        /**
                         * For some reason we did not get a response code back. This might be due to network errors. In
                         * such case we dispatch a job on the transaction so as to process the debit transaction on the
                         * background
                         */
                        RequeryTransaction::dispatch($transaction1, 'debit')
                                        ->delay(300);

                        continue;
                    }

                    /**
                     * Debit was successful. We check if the loan is qualified for closure
                     */
                    if ($overdueLoanOffer->loan->amount_remaining <= 0 &&
                        $overdueLoanOffer->loan->penalty_remaining <= 0) {
                        // Loan is qualified for closure. Process the closure of the loan
                        DB::transaction(function() use ($overdueLoanOffer) {
                            // Update the status of the loan to "CLOSED"
                            $status = app()->make(InterswitchService::class)->status(LoanOffer::CLOSED, $overdueLoanOffer->id);

                            // Close the loan in the database
                            $overdueLoanOffer->forceFill([
                                'status' => LoanOffer::CLOSED
                            ])->save();

                            // Closed any associated collection case
                            $overdueLoanOffer->collectionCase()
                                ->update([
                                    'status' => CollectionCase::CLOSED
                                ]);
                        });

                        $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                            'value' => $overdueLoanOffer->loan->amount
                        ])->toHigherDenomination()->getValue();

                        $message = __('interswitch.loan_fully_collected_message', [
                            'covered_amount' => config('quickfund.currency_representation').number_format($higherDenominationAmount, 2),
                            'loan_request_url' => config('quickfund.loan_request_url')
                        ]);
                    } else {
                        // Loan is still in present state
                        $higherDenominationPaymentAmount = app()->make(MoneyCalculator::class, [
                            'value' => $deductedAmount
                        ])->toHigherDenomination()->getValue();

                        $higherDenominationRemainingAmount = app()->make(MoneyCalculator::class, [
                            'value' => $overdueLoanOffer->loan->amount_remaining + $overdueLoanOffer->loan->penalty_remaining
                        ])->toHigherDenomination()->getValue();

                        $message = __('interswitch.loan_partially_collected_message', [
                            'recovered_amount' => config('quickfund.currency_representation').number_format($higherDenominationPaymentAmount, 2),
                            'remaining_amount' => config('quickfund.currency_representation').number_format($higherDenominationRemainingAmount, 2)
                        ]);
                    }

                    /**
                     * Send SMS that account has been debited
                     */
                    SendSms::dispatch(
                        $message,
                        $overdueLoanOffer->customer->phone_number,
                        $overdueLoanOffer->id,
                        true
                    );
                } catch (Throwable $e) {
                    // Error completing debit request
                    RequeryTransaction::dispatch($transaction1, 'debit')
                                    ->delay(300);
                }
            }
        }
    }
}
