<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use App\Models\{LoanOffer, Transaction};
use App\Services\Interswitch as InterswitchService;
use App\Jobs\Interswitch\SendSms;
use App\Services\Loans\Calculator as LoanCalculator;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Jobs\RequeryTransaction;

class CreditCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:credit-customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Credit the customers with accepted loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * We consider "ACCEPTED" loans which for some reason, the CreditCustomer job doesn't run.
         * This will help us to credit those customers
         */
        $acceptedLoanOffers = LoanOffer::with(['loan', 'customer.virtualAccount'])
            ->where('status', LoanOffer::ACCEPTED)
            ->whereHas('loan')
            ->whereDoesntHave('loan.transactions', fn($query) => $query->where('type', Transaction::CREDIT))
            ->get();

        foreach ($acceptedLoanOffers as $acceptedLoanOffer) {
            // Check if the customer has an outstanding loan
            if (
                LoanOffer::where('customer_id', $acceptedLoanOffer->customer->id)
                    ->whereIn('status', [
                        LoanOffer::OPEN,
                        LoanOffer::OVERDUE
                    ])
                    ->exists()
            ) {
                continue;
            }

            /**
             * We make sure that the user does not have any transaction. This is already done by the command
             * that calls the job but we are calling it again just in case.
             */
            if (
                $acceptedLoanOffer->loan->transactions()
                    ->doesntExist()
            ) {
                // Create the record of the transaction
                $transaction = $acceptedLoanOffer->loan->transactions()->create([
                    'amount' => $acceptedLoanOffer->amount
                ]);

                try {
                    // Initiate credit on the customer
                    $creditDetails = app()->make(InterswitchService::class)->credit(
                        $acceptedLoanOffer->customer->phone_number,
                        $acceptedLoanOffer,
                        $transaction->id,
                        true,
                        true
                    );

                    /**
                     * We check if a response code was returned. Usually, we get a response code
                     */
                    if (isset($creditDetails['responseCode'])) {
                        // Response code returned. Now we check if the credit transaction was successful.
                        if ($creditDetails['responseCode'] === '00') {
                            // Credit was successful. Process the successful credit of a customer
                            app()->make(LoanCalculator::class)->processCredit($creditDetails, $acceptedLoanOffer, $transaction, true);

                            // The higher denominational value of the borrowed amount
                            $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                                'value' => $acceptedLoanOffer->amount
                            ])->toHigherDenomination()->getValue();

                            // The higher denominational value of the repayment amount
                            $higherDenominationRepaymentAmount = app()->make(MoneyCalculator::class, [
                                'value' => $acceptedLoanOffer->loan->amount_remaining + $acceptedLoanOffer->loan->penalty_remaining
                            ])->toHigherDenomination()->getValue();

                            // Send SMS to the customer
                            SendSms::dispatch(
                                __('interswitch.disbursement_message', [
                                    'amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                                    'service_fee' => config('quickfund.currency_representation') . number_format(0, 2),
                                    'due_date' => $acceptedLoanOffer->loan->due_date->format('d-m-Y'),
                                    'ussd_repayment_amount' => ceil($higherDenominationRepaymentAmount),
                                    'virtual_account_details' => isset($acceptedLoanOffer->customer->virtualAccount) ? "or transfer to {$acceptedLoanOffer->customer->virtualAccount->bank_name}, Acc No: {$acceptedLoanOffer->customer->virtualAccount->account_number}" : ''
                                ]),
                                $acceptedLoanOffer->customer->phone_number,
                                $acceptedLoanOffer->id,
                                true
                            );
                        } else {
                            // Response gotten back on credit was not successful. Process the non credit situation
                            app()->make(LoanCalculator::class)->processNonCredit($creditDetails, $transaction, true);

                            SendSms::dispatch(
                                __('interswitch.failed_disbursement_due_to_technical_issues'),
                                $acceptedLoanOffer->customer->phone_number,
                                $acceptedLoanOffer->id,
                                true
                            );
                        }
                    } else {
                        /**
                         * For some reason we did not get a response code back. This might be due to network errors. In
                         * such case we dispatch a job on the transaction so as to process the credit transaction on the
                         * background
                         */
                        RequeryTransaction::dispatch($transaction, 'credit')
                            ->delay(7200);
                    }
                } catch (Throwable $e) {
                    // Error occurred while initiating credit
                    RequeryTransaction::dispatch($transaction, 'credit')
                        ->delay(7200);
                }
            }
        }
    }
}
