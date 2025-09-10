<?php

namespace App\Jobs\Interswitch;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\{Transaction, LoanOffer};
use App\Services\Interswitch as InterswitchService;
use App\Services\Loans\Calculator as LoanCalculator;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Jobs\Interswitch\SendSms;
use App\Jobs\RequeryTransaction;

class CreditCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The loan offer
     */
    public $loanOffer;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($loanOffer)
    {
        $this->loanOffer = $loanOffer;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /**
         * Only loan offers that are accepted are allowed for crediting
         */
        if ($this->loanOffer->status !== LoanOffer::ACCEPTED) {
            return $this->delete();
        }

        /**
         * Load the loan and the customer
         */
        $this->loanOffer->load(['loan', 'customer.virtualAccount']);

        /**
         * Check if the customer has an outstanding loan
         */
        if (LoanOffer::where('customer_id', $this->loanOffer->customer->id)
                    ->whereIn('status', [
                        LoanOffer::OPEN,
                        LoanOffer::OVERDUE
                    ])
                    ->exists()) {
            return $this->delete();
        }

        /**
         * Check if there is a loan collected on the loan offer
         */
        if (!isset($this->loanOffer->loan)) {
            return $this->delete();
        }

        /**
         * We check if there is already a credit transaction
         */
        if (Transaction::where('loan_id', $this->loanOffer->loan->id)
                    ->where('type', Transaction::CREDIT)
                    ->doesntExist()) {
            // Create the record of the transaction
            $transaction = $this->loanOffer->loan->transactions()->create([
                'amount' => $this->loanOffer->amount
            ]);

            try {
                // Initiate credit on the customer
                $creditDetails = app()->make(InterswitchService::class)->credit(
                    $this->loanOffer->customer->phone_number,
                    $this->loanOffer,
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
                        // Process the successful credit of a customer
                        app()->make(LoanCalculator::class)->processCredit($creditDetails, $this->loanOffer, $transaction, true);

                        // The higher denominational value of the borrowed amount
                        $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                            'value' => $this->loanOffer->amount
                        ])->toHigherDenomination()->getValue();

                        // The higher denominational value of the repayment amount
                        $higherDenominationRepaymentAmount = app()->make(MoneyCalculator::class, [
                            'value' => $this->loanOffer->loan->amount_remaining + $this->loanOffer->loan->penalty_remaining
                        ])->toHigherDenomination()->getValue();

                        // Send SMS to the customer
                        SendSms::dispatch(
                            __('interswitch.disbursement_message', [
                                'amount' => config('quickfund.currency_representation').number_format($higherDenominationAmount, 2),
                                'service_fee' => config('quickfund.currency_representation').number_format(0, 2),
                                'due_date' => $this->loanOffer->loan->due_date->format('d-m-Y'),
                                'ussd_repayment_amount' => ceil($higherDenominationRepaymentAmount),
                                'virtual_account_details' => isset($this->loanOffer->customer->virtualAccount) ? "or transfer to {$this->loanOffer->customer->virtualAccount->bank_name}, Acc No: {$this->loanOffer->customer->virtualAccount->account_number}" : ''
                            ]),
                            $this->loanOffer->customer->phone_number,
                            $this->loanOffer->id,
                            true
                        );
                    } else {
                        // Response gotten back on credit was not successful. Process the non credit situation
                        app()->make(LoanCalculator::class)->processNonCredit($creditDetails, $transaction, true);

                        SendSms::dispatch(
                            __('interswitch.failed_disbursement_due_to_technical_issues'),
                            $this->loanOffer->customer->phone_number,
                            $this->loanOffer->id,
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
                // Something weird happened
                RequeryTransaction::dispatch($transaction, 'credit')
                                    ->delay(7200);
            }
        }
    }
}
