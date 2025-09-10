<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\Interswitch as InterswitchService;
use App\Services\Loans\Calculator as LoanCalculator;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Models\{CollectionCase, LoanOffer, Transaction};
use App\Jobs\Interswitch\SendSms;

class RequeryTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The transaction
     */
    public $transaction;

    /**
     * The type of transaction
     */
    public $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($transaction, $type)
    {
        $this->transaction = $transaction;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Load the necessary relationships
        $this->transaction->load([
            'loan.loanOffer.customer.virtualAccount',
        ]);

        // Get the necessary things
        $loan = $this->transaction->loan;
        $loanOffer = $this->transaction->loan->loanOffer;
        $customer = $this->transaction->loan->loanOffer->customer;
        $virtualAccount = $this->transaction->loan->loanOffer->customer->virtualAccount;

        // Just in case an exception is thrown, we catch the exception and mark the job as failed
        try {
            $queryDetails = app()->make(InterswitchService::class)->query(
                $this->transaction->id,
                true,
                true
            );
        } catch (Throwable $e) {
            return $this->fail('Exception thrown during requery.');
        }
        

        /**
         * We check if a response code was returned. If it was not returned, we fail the job so it can be
         * retried again
         */
        if (!isset($queryDetails['responseCode'])) {
            return $this->fail('Response code not returned.');
        }

        /**
         * Requery was successful. So we process the query based on the type of transaction
         */
        switch (strtolower($this->type)) {
            case 'debit':
                // Process the debit based on the response
                if ($queryDetails['responseCode'] === '00') {
                    /**
                     * Because of the fact that this is a job, it could be retried multiple times. We do not
                     * want to process a transaction that has already been processed
                     */
                    if (Transaction::where('interswitch_transaction_reference', $queryDetails['transactionRef'])
                                ->doesntExist()) {
                        // Debit was successful. Process the debit transaction
                        app()->make(LoanCalculator::class)->processDebit(
                            $queryDetails,
                            $loan,
                            $this->transaction
                        );
                    }

                    /**
                     * Debit was successful. We check if the loan is qualified for closure
                     */
                    if ($loan->amount_remaining <= 0 &&
                        $loan->penalty_remaining <= 0) {
                        // Loan is qualified for closure. Process the closure of the loan
                        DB::transaction(function() use ($loanOffer) {
                            // Update the status of the loan to "CLOSED"
                            $status = app()->make(InterswitchService::class)->status(LoanOffer::CLOSED, $loanOffer->id, true);

                            // Close the loan in the database
                            $loanOffer->forceFill([
                                'status' => LoanOffer::CLOSED
                            ])->save();

                            // Closed any associated collection case
                            $loanOffer->collectionCase()
                                    ->update([
                                        'status' => CollectionCase::CLOSED
                                    ]);
                        });

                        $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                            'value' => $loan->amount
                        ])->toHigherDenomination()->getValue();

                        $message = __('interswitch.loan_fully_collected_message', [
                            'covered_amount' => config('quickfund.currency_representation').number_format($higherDenominationAmount, 2),
                            'loan_request_url' => config('quickfund.loan_request_url')
                        ]);
                    } else {
                        // Loan is still in present state
                        $higherDenominationPaymentAmount = app()->make(MoneyCalculator::class, [
                            'value' => $this->transaction->amount
                        ])->toHigherDenomination()->getValue();

                        $higherDenominationRemainingAmount = app()->make(MoneyCalculator::class, [
                            'value' => $loan->amount_remaining + $loan->penalty_remaining
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
                        $customer->phone_number,
                        $loanOffer->id,
                        true
                    );
                } else {
                    // Debit was not successful. Process the failure of debit
                    app()->make(LoanCalculator::class)->processNonDebit(
                        $queryDetails,
                        $this->transaction
                    );
                }
            break;

            case 'credit':
                /**
                 * We check if the transaction returned with a response code. This means that the transaction
                 * details was successfully fetched.
                 */
                if ($queryDetails['responseCode'] === '00') {
                    /**
                     * Here the transaction was successful. We store the transaction and change the status
                     * of the loan to "OPEN"
                     */
                    // Credit was successful. Process the successful credit of a customer
                    app()->make(LoanCalculator::class)->processCredit(
                        $queryDetails,
                        $loanOffer,
                        $this->transaction,
                        true,
                        true
                    );

                    // The higher denominational value of the borrowed amount
                    $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                        'value' => $loanOffer->amount
                    ])->toHigherDenomination()->getValue();

                    // The higher denominational value of the repayment amount
                    $higherDenominationRepaymentAmount = app()->make(MoneyCalculator::class, [
                        'value' => $loan->amount_remaining + $loan->penalty_remaining
                    ])->toHigherDenomination()->getValue();

                    // Send SMS to the customer
                    SendSms::dispatch(
                        __('interswitch.disbursement_message', [
                            'amount' => config('quickfund.currency_representation').number_format($higherDenominationAmount, 2),
                            'service_fee' => config('quickfund.currency_representation').number_format(0, 2),
                            'due_date' => $loan->due_date->format('d-m-Y'),
                            'ussd_repayment_amount' => ceil($higherDenominationRepaymentAmount),
                            'virtual_account_details' => isset($virtualAccount) ? "or transfer to {$virtualAccount->bank_name}, Acc No: {$virtualAccount->account_number}" : ''
                        ]),
                        $customer->phone_number,
                        $loanOffer->id,
                        true
                    );
                } else {
                    // Response gotten back on credit was not successful. Process the non credit situation
                    app()->make(LoanCalculator::class)->processNonCredit(
                        $queryDetails,
                        $this->transaction,
                        true
                    );

                    SendSms::dispatch(
                        __('interswitch.failed_disbursement_due_to_technical_issues'),
                        $customer->phone_number,
                        $loanOffer->id,
                        true
                    );
                }      
            break;

            case 'refund':
                // Process the debit based on the response
                if ($queryDetails['responseCode'] === '00') {
                    /**
                     * Because of the fact that this is a job, it could be retried multiple times. We do not
                     * want to process a transaction that has already been processed
                     */
                    if (Transaction::where('interswitch_transaction_reference', $queryDetails['transactionRef'])
                                ->doesntExist()) {
                        // Update the transaction details
                        $this->transaction->update([
                            'interswitch_transaction_message' => $queryDetails['responseMessage'] ?? null,
                            'interswitch_transaction_code' => $queryDetails['responseCode'] ?? null,
                            'interswitch_transaction_reference' => $queryDetails['transactionRef'] ?? null,
                            'type' => Transaction::REFUND
                        ]);
                    }

                    $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                        'value' => $this->transaction->amount
                    ])->toHigherDenomination()->getValue();

                    /**
                     * Send SMS that account has been refunded
                     */
                    SendSms::dispatch(
                        'You have been refunded the amount of '.config('quickfund.currency_representation').number_format($higherDenominationAmount, 2).'. Thank you for patronizing us',
                        $customer->phone_number,
                        $loanOffer->id,
                        true
                    );
                } else {
                    // Update the transaction details
                    $this->transaction->update([
                        'interswitch_transaction_message' => $queryDetails['responseMessage'] ?? null,
                        'interswitch_transaction_code' => $queryDetails['responseCode'] ?? null,
                        'interswitch_transaction_reference' => $queryDetails['transactionRef'] ?? null,
                        'type' => Transaction::REFUND
                    ]);
                }
            break;
            
            default:
                
            break;
        }
    }
}
