<?php

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use App\Http\Requests\{DueInDaysRequest, LoanPaymentProcessingRequest, RefundCustomerRequest, SearchLoanOffersRequest, SmsChoiceRequest, UpdateLoanOfferRequest, StoreLoanOfferRequest, UpdateLoanStatusRequest};
use App\Jobs\Interswitch\SendSms;
use App\Models\{Blacklist, CollectionCase, LoanOffer, Transaction};
use App\Exceptions\Interswitch\UncollectedLoanException;
use App\Services\Calculation\{
    Date,
    Money as MoneyCalculator
};
use App\Services\Loans\Calculator as LoanCalculator;
use App\Exceptions\CustomException as ApplicationCustomException;
use App\Services\Interswitch as InterswitchService;
use App\Jobs\RequeryTransaction;

class LoanOfferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => 'string|sometimes',
        ]);

        $perPage = $data['per_page'] ?? config('quickfund.per_page');

        $loanOffers = LoanOffer::with(['customer', 'loan.latestTransaction'])
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $loanOffers);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreLoanOfferRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLoanOfferRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\LoanOffer  $loanOffer
     * @return \Illuminate\Http\Response
     */
    public function show(LoanOffer $loanOffer)
    {
        $loanOffer->load([
            'customer',
            'loan.latestTransaction'
        ]);

        return $this->sendSuccess(__('app.request_successful'), 200, $loanOffer);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\LoanOffer  $loanOffer
     * @return \Illuminate\Http\Response
     */
    public function edit(LoanOffer $loanOffer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateLoanOfferRequest  $request
     * @param  \App\Models\LoanOffer  $loanOffer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLoanOfferRequest $request, LoanOffer $loanOffer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\LoanOffer  $loanOffer
     * @return \Illuminate\Http\Response
     */
    public function destroy(LoanOffer $loanOffer)
    {
        //
    }

    /**
     * Debit the customer on the collected loan
     */
    public function debit(LoanOffer $loanOffer)
    {
        $loanOffer->load(['loan', 'customer']);

        // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new ApplicationCustomException(__('interswitch.uncollected_loan'), 400);
        }

        /**
         * Check if the loan is "OPEN" or "OVERDUE"
         */
        if ($loanOffer->status !== LoanOffer::OPEN && $loanOffer->status !== LoanOffer::OVERDUE) {
            throw new ApplicationCustomException(__('interswitch.transaction_forbidden', [
                'type' => 'debit',
                'loan_status' => $loanOffer->status,
                'expected_loan_status' => implode(' or ', [
                    '"' . LoanOffer::OPEN . '"',
                    '"' . LoanOffer::OVERDUE . '"'
                ])
            ]), 400);
        }

        /**
         * Check if the remaining amount is deductable
         */
        if (
            $loanOffer->loan->amount_remaining <= 0 &&
            $loanOffer->loan->penalty_remaining <= 0
        ) {
            throw new ApplicationCustomException(__('interswitch.loan_paid_in_full'), 400);
        }

        /**
         * Create the record of the transaction
         */
        $transaction1 = $loanOffer->loan->transactions()->create([
            'amount' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
        ]);

        try {
            /**
             * We initiate debit. This is regarded as the first debit
             */
            $debitDetails1 = app()->make(InterswitchService::class)->debit(
                $transaction1->amount,
                $loanOffer->customer->phone_number,
                $loanOffer->id,
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
                    app()->make(LoanCalculator::class)->processDebit($debitDetails1, $loanOffer->loan, $transaction1);

                    // Initialize the debit variables
                    $deductedAmount = $transaction1->amount;
                    $transactionMessage = $transaction1->interswitch_transaction_message;
                    $transactionCode = $transaction1->interswitch_transaction_code;
                    $transactionReference = $transaction1->interswitch_transaction_reference;
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
                        $transaction2 = $loanOffer->loan->transactions()->create([
                            'amount' => $debitDetails1['deductableAmount']
                        ]);

                        try {
                            /**
                             * We initiate debit again. This is the second debit and we will not take the available
                             * balance anymore
                             */
                            $debitDetails2 = app()->make(InterswitchService::class)->debit(
                                $transaction2->amount,
                                $loanOffer->customer->phone_number,
                                $loanOffer->id,
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
                                    app()->make(LoanCalculator::class)->processDebit($debitDetails2, $loanOffer->loan, $transaction2);

                                    // Initialize the debit variables
                                    $deductedAmount = $transaction2->amount;
                                    $transactionMessage = $transaction2->interswitch_transaction_message;
                                    $transactionCode = $transaction2->interswitch_transaction_code;
                                    $transactionReference = $transaction2->interswitch_transaction_reference;
                                } else {
                                    // Debit was not successful. Process the failure of debit
                                    app()->make(LoanCalculator::class)->processNonDebit($debitDetails2, $transaction2);

                                    // Could not complete debit on customer available balance. We end the debit request
                                    throw new ApplicationCustomException('Failed to initiate debit on available balance: ' . ($debitDetails1['responseMessage'] ?? 'Unknown error occurred. Please try again'), 503);
                                }
                            } else {
                                /**
                                 * For some reason we did not get a response code back. This might be due to network errors. In
                                 * such case we dispatch a job on the transaction so as to process the debit transaction on the
                                 * background
                                 */
                                RequeryTransaction::dispatch($transaction2, 'debit')
                                    ->delay(300);

                                return $this->sendSuccess('Debit on transaction is currently running in background.', 200, [
                                    'transaction_id' => $transaction2->id
                                ]);
                            }
                        } catch (Throwable $e2) {
                            // Something terrible happened
                            RequeryTransaction::dispatch($transaction2, 'debit')
                                ->delay(300);

                            return $this->sendSuccess('Debit on transaction is currently running in background.', 200, [
                                'transaction_id' => $transaction2->id
                            ]);
                        }
                    } else {
                        // There was no deductable amount. We end the debit request
                        throw new ApplicationCustomException('Failed to initiate debit: ' . ($debitDetails1['responseMessage'] ?? 'Unknown error occurred. Please try again'), 503);
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

                return $this->sendSuccess('Debit on transaction is currently running in background.', 200, [
                    'transaction_id' => $transaction1->id
                ]);
            }

            /**
             * Debit was successful. We check if the loan is qualified for closure
             */
            if (
                $loanOffer->loan->amount_remaining <= 0 &&
                $loanOffer->loan->penalty_remaining <= 0
            ) {
                // Loan is qualified for closure. Process the closure of the loan
                DB::transaction(function () use ($loanOffer) {
                    // Update the status of the loan to "CLOSED"
                    $status = app()->make(InterswitchService::class)->status(LoanOffer::CLOSED, $loanOffer->id);

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
                    'value' => $loanOffer->loan->amount
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_fully_collected_message', [
                    'covered_amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                    'loan_request_url' => config('quickfund.loan_request_url')
                ]);
            } else {
                // Loan is still in present state
                $higherDenominationPaymentAmount = app()->make(MoneyCalculator::class, [
                    'value' => $deductedAmount
                ])->toHigherDenomination()->getValue();

                $higherDenominationRemainingAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_partially_collected_message', [
                    'recovered_amount' => config('quickfund.currency_representation') . number_format($higherDenominationPaymentAmount, 2),
                    'remaining_amount' => config('quickfund.currency_representation') . number_format($higherDenominationRemainingAmount, 2)
                ]);
            }

            $transactionAmount = app()->make(MoneyCalculator::class, [
                'value' => $deductedAmount
            ])->toHigherDenomination()->getValue();

            $amountRemaining = app()->make(MoneyCalculator::class, [
                'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            /**
             * Send SMS that account has been debited
             */
            SendSms::dispatch(
                $message,
                $loanOffer->customer->phone_number,
                $loanOffer->id,
                true
            );

            return $this->sendSuccess('Customer debited successfully', 200, [
                'amount_deducted' => config('quickfund.currency_representation') . number_format($transactionAmount, 2),
                'amount_remaining' => config('quickfund.currency_representation') . number_format($amountRemaining, 2),
                'transaction_code' => $transactionCode,
                'transaction_message' => $transactionMessage,
                'transaction_reference' => $transactionReference
            ]);
        } catch (Throwable $e) {
            // Something strange happened
            RequeryTransaction::dispatch($transaction1, 'debit')
                ->delay(300);

            return $this->sendSuccess('Debit on transaction is currently running in background.', 200, [
                'transaction_id' => $transaction1->id
            ]);
        }
    }

    /**
     * Credit the customer on the collected loan
     */
    public function credit(LoanOffer $loanOffer)
    {
        $loanOffer->load(['loan', 'customer']);

        // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new ApplicationCustomException(__('interswitch.uncollected_loan'), 400);
        }

        /**
         * A customer can only be credited when the loan status is "ACCEPTED"
         */
        if ($loanOffer->status !== LoanOffer::ACCEPTED) {
            throw new ApplicationCustomException(__('interswitch.transaction_forbidden', [
                'type' => 'credit',
                'loan_status' => $loanOffer->status,
                'expected_loan_status' => LoanOffer::ACCEPTED
            ]), 400);
        }

        // Create the record of the transaction
        $transaction = $loanOffer->loan->transactions()->create([
            'amount' => $loanOffer->amount
        ]);

        // The higher denominational value of the borrowed amount
        $higherDenominationAmount = app()->make(MoneyCalculator::class, [
            'value' => $loanOffer->amount
        ])->toHigherDenomination()->getValue();

        try {
            // Initiate credit on the customer
            $creditDetails = app()->make(InterswitchService::class)->credit(
                $loanOffer->customer->phone_number,
                $loanOffer,
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
                    // Credit was successful
                    DB::transaction(function () use ($transaction, $creditDetails, $loanOffer) {
                        // Update the loan status to "OPEN" on our end
                        $loanOffer->forceFill([
                            'status' => LoanOffer::OPEN
                        ])->save();

                        // Update the transaction details
                        $transaction->update([
                            'interswitch_transaction_message' => $creditDetails['responseMessage'] ?? null,
                            'interswitch_transaction_code' => $creditDetails['responseCode'] ?? null,
                            'interswitch_transaction_reference' => $creditDetails['transactionRef'] ?? null,
                            'type' => Transaction::CREDIT
                        ]);

                        /**
                         * We check if the customer was blacklisted and completed their blacklisted period. We then remove them
                         * from the blacklist
                         */
                        if (
                            Blacklist::where('phone_number', $loanOffer->customer->phone_number)
                                ->where('type', Blacklist::BY_CODE)
                                ->where('completed', true)
                                ->exists()
                        ) {
                            // Remove the customer from the blacklist
                            Blacklist::where('phone_number', $loanOffer->customer->phone_number)
                                ->where('type', Blacklist::BY_CODE)
                                ->where('completed', true)
                                ->delete();
                        }
                    });

                    // The higher denominational value of the repayment amount
                    $higherDenominationRepaymentAmount = app()->make(MoneyCalculator::class, [
                        'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                    ])->toHigherDenomination()->getValue();

                    // Send SMS to the customer
                    SendSms::dispatch(
                        __('interswitch.disbursement_message', [
                            'amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                            'service_fee' => config('quickfund.currency_representation') . number_format(0, 2),
                            'due_date' => $loanOffer->loan->due_date->format('d-m-Y'),
                            'ussd_repayment_amount' => ceil($higherDenominationRepaymentAmount),
                            'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                        ]),
                        $loanOffer->customer->phone_number,
                        $loanOffer->id,
                        true
                    );
                } else {
                    /**
                     * We will not update the status of the loan so we can requery the transaction later
                     */
                    // Update the transaction details
                    $transaction->update([
                        'interswitch_transaction_message' => $creditDetails['responseMessage'] ?? null,
                        'interswitch_transaction_code' => $creditDetails['responseCode'] ?? null,
                        'interswitch_transaction_reference' => $creditDetails['transactionRef'] ?? null,
                        'type' => Transaction::CREDIT
                    ]);

                    SendSms::dispatch(
                        __('interswitch.failed_disbursement_due_to_technical_issues'),
                        $loanOffer->customer->phone_number,
                        $loanOffer->id,
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

                return $this->sendSuccess('Credit on transaction is currently running in background.', 200, [
                    'transaction_id' => $transaction->id
                ]);
            }

            return $this->sendSuccess('Credit initiated successfully.', 200, [
                'amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                'transaction_message' => $transaction->interswitch_transaction_message,
                'transaction_reference' => $transaction->interswitch_transaction_reference
            ]);
        } catch (Throwable $e) {
            // An attrocity occurred
            RequeryTransaction::dispatch($transaction, 'credit')
                ->delay(7200);

            return $this->sendSuccess('Credit on transaction is currently running in background.', 200, [
                'transaction_id' => $transaction->id
            ]);
        }
    }

    /**
     * Update the status of a loan
     */
    public function status(UpdateLoanStatusRequest $request, LoanOffer $loanOffer)
    {
        $data = $request->validated();

        // Update the status of the laon
        DB::transaction(function () use ($data, $loanOffer) {
            // Update the status of the loan to "CLOSED"
            $status = app()->make(InterswitchService::class)->status($data['status'], $loanOffer->id);

            // Close the loan in the database
            $loanOffer->forceFill([
                'status' => $data['status']
            ])->save();
        });

        // If the status is closed, we send a loan closed message
        if ($loanOffer->status === LoanOffer::CLOSED) {
            $loanOffer->load(['customer', 'loan']);

            $currencyRepresentation = config('quickfund.currency_representation');
            $loanRequestUrl = config('quickfund.loan_request_url');

            $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                'value' => $loanOffer->loan->amount
            ])->toHigherDenomination()->getValue();

            SendSms::dispatch(
                __('interswitch.loan_fully_collected_message', [
                    'covered_amount' => $currencyRepresentation . number_format($higherDenominationAmount, 2),
                    'loan_request_url' => $loanRequestUrl
                ]),
                $loanOffer->customer->phone_number,
                $loanOffer->id,
                true
            );
        }

        return $this->sendSuccess('Loan status updated successfully', 200, [
            'status' => $loanOffer->status
        ]);
    }

    /**
     * Refund the customer on a loan
     */
    public function refund(RefundCustomerRequest $request, LoanOffer $loanOffer)
    {
        $data = $request->validated();

        $loanOffer->load(['loan', 'customer']);

        // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new ApplicationCustomException(__('interswitch.uncollected_loan'), 400);
        }

        /**
         * A customer can only be refunded when the loan status is "CLOSED"
         */
        if ($loanOffer->status !== LoanOffer::CLOSED) {
            throw new ApplicationCustomException(__('interswitch.transaction_forbidden', [
                'type' => 'refund',
                'loan_status' => $loanOffer->status,
                'expected_loan_status' => LoanOffer::CLOSED
            ]), 400);
        }

        // Create the record of the transaction
        $transaction = $loanOffer->loan->transactions()->create([
            'amount' => $data['amount']
        ]);

        $higherDenominationAmount = app()->make(MoneyCalculator::class, [
            'value' => $data['amount']
        ])->toHigherDenomination()->getValue();

        try {
            // Initiate refund on the customer
            $refundDetails = app()->make(InterswitchService::class)->refund(
                $data['amount'],
                $loanOffer->customer->phone_number,
                $loanOffer->id,
                $transaction->id,
                true,
                true
            );

            /**
             * We check if a response code was returned. Usually, we get a response code
             */
            if (isset($refundDetails['responseCode'])) {
                // Response code returned. Now we check if the refund transaction was successful.
                if ($refundDetails['responseCode'] === '00') {
                    // Update the transaction details
                    $transaction->update([
                        'interswitch_transaction_message' => $refundDetails['responseMessage'] ?? null,
                        'interswitch_transaction_code' => $refundDetails['responseCode'] ?? null,
                        'interswitch_transaction_reference' => $refundDetails['transactionRef'] ?? null,
                        'type' => Transaction::REFUND
                    ]);

                    /**
                     * Send SMS that account has been refunded
                     */
                    SendSms::dispatch(
                        'You have been refunded the amount of ' . config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2) . '. Thank you for patronizing us',
                        $loanOffer->customer->phone_number,
                        $loanOffer->id,
                        true
                    );
                } else {
                    /**
                     * We will not update the status of the loan so we can requery the transaction later
                     */
                    // Update the transaction details
                    $transaction->update([
                        'interswitch_transaction_message' => $refundDetails['responseMessage'] ?? null,
                        'interswitch_transaction_code' => $refundDetails['responseCode'] ?? null,
                        'interswitch_transaction_reference' => $refundDetails['transactionRef'] ?? null,
                        'type' => Transaction::REFUND
                    ]);
                }
            } else {
                /**
                 * For some reason we did not get a response code back. This might be due to network errors. In
                 * such case we dispatch a job on the transaction so as to process the refund transaction on the
                 * background
                 */
                RequeryTransaction::dispatch($transaction, 'refund')
                    ->delay(7200);

                return $this->sendSuccess('Refund on transaction is currently running in background.', 200, [
                    'transaction_id' => $transaction->id
                ]);
            }

            return $this->sendSuccess('Customer refunded initiated.', 200, [
                'amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                'transaction_message' => $transaction->interswitch_transaction_message,
                'transaction_reference' => $transaction->interswitch_transaction_reference
            ]);
        } catch (Throwable $e) {
            // Something bad happened
            RequeryTransaction::dispatch($transaction, 'refund')
                ->delay(7200);

            return $this->sendSuccess('Refund on transaction is currently running in background.', 200, [
                'transaction_id' => $transaction->id
            ]);
        }
    }

    /**
     * Get the transactions on a loan
     */
    public function transactions(Request $request, LoanOffer $loanOffer)
    {
        $loanOffer->load(['loan']);

        // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new ApplicationCustomException(__('interswitch.uncollected_loan'), 400);
        }

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        // Get the transactions on a loan
        $transactions = $loanOffer->loan->transactions()->latest()->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $transactions);
    }

    /**
     * Choose SMS to send to a customer
     */
    public function smsChoice(SmsChoiceRequest $request, LoanOffer $loanOffer)
    {
        $data = $request->validated();

        $loanOffer->load(['loan', 'customer.virtualAccount']);

        // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new ApplicationCustomException(__('interswitch.uncollected_loan'), 400);
        }

        $currencyRepresentation = config('quickfund.currency_representation');
        $loanRequestUrl = config('quickfund.loan_request_url');
        $assistanceUrl = config('quickfund.terms_and_conditions');
        $timezone = config('quickfund.timezone');

        $today = today()->timezone($timezone)->toDateTimeString();

        switch ($data['choice']) {
            case 'duplicate_loan_message':
                $message = __('interswitch.duplicate_loan_message');
                break;

            case 'insufficient_funds_collection_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.insufficient_funds_collection_message', [
                    'current_amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}"
                ]);
                break;

            case 'disbursement_message':
                $loanAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount
                ])->toHigherDenomination()->getValue();

                $repaymentAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $serviceFee = app()->make(MoneyCalculator::class, [
                    'value' => 0
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.disbursement_message', [
                    'amount' => "{$currencyRepresentation}{$loanAmount}",
                    'service_fee' => "{$currencyRepresentation}{$serviceFee}",
                    'due_date' => $loanOffer->loan->due_date->format('d-m-Y'),
                    'ussd_repayment_amount' => ceil($repaymentAmount),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'debt_warning_days_3_message':
                $loanBalance = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_warning_days_3_message', [
                    'loan_balance' => "{$currencyRepresentation}{$loanBalance}",
                    'due_date' => $loanOffer->loan->due_date->format('d-m-Y'),
                    'ussd_repayment_amount' => ceil($loanBalance),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'debt_warning_days_1_message':
                $loanBalance = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_warning_days_1_message', [
                    'loan_balance' => "{$currencyRepresentation}{$loanBalance}",
                    'ussd_repayment_amount' => ceil($loanBalance),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'insufficient_funds_message':
                $message = __('interswitch.insufficient_funds_message');
                break;

            case 'no_debts_at_hand_message':
                $message = __('interswitch.no_debts_at_hand_message', [
                    'loan_request_url' => $loanRequestUrl,
                ]);
                break;

            case 'loan_partially_collected_message':
                $loanBalance = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_partially_collected_message', [
                    'recovered_amount' => "{$currencyRepresentation}X,XXX.XX",
                    'remaining_amount' => "{$currencyRepresentation}{$loanBalance}"
                ]);
                break;

            case 'loan_fully_collected_message':
                $coveredAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_fully_collected_message', [
                    'covered_amount' => "{$currencyRepresentation}{$coveredAmount}",
                    'loan_request_url' => $loanRequestUrl
                ]);
                break;

            case 'loan_fully_collected_message':
                $coveredAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_fully_collected_message', [
                    'covered_amount' => "{$currencyRepresentation}{$coveredAmount}",
                    'loan_request_url' => $loanRequestUrl
                ]);
                break;

            case 'late_fee_partially_collected_message':
                $debtAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.late_fee_partially_collected_message', [
                    'covered_late_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'remaining_late_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'debt_amount' => "{$currencyRepresentation}{$debtAmount}"
                ]);
                break;

            case 'late_fee_fully_collected_message':
                $debtAmount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.late_fee_fully_collected_message', [
                    'covered_late_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'debt_amount' => "{$currencyRepresentation}{$debtAmount}"
                ]);
                break;

            case 'loan_with_late_fee_partially_collected_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_with_late_fee_partially_collected_message', [
                    'covered_late_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'recovered_amount' => "{$currencyRepresentation}X,XXX.XX",
                    'amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}"
                ]);
                break;

            case 'loan_with_late_fee_fully_collected_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_with_late_fee_fully_collected_message', [
                    'covered_late_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'recovered_amount' => "{$currencyRepresentation}X,XXX.XX",
                    'loan_request_url' => $loanRequestUrl
                ]);
                break;

            case 'loan_with_late_fee_fully_collected_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.loan_with_late_fee_fully_collected_message', [
                    'covered_late_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'recovered_amount' => "{$currencyRepresentation}X,XXX.XX",
                    'loan_request_url' => $loanRequestUrl
                ]);
                break;

            case 'debt_overdue_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_overdue_message', [
                    'amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}",
                    'default_amount' => "{$currencyRepresentation}X,XXX.XX",
                    'ussd_repayment_amount' => ceil($currentAmountRemaining),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'debt_overdue_first_week_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_overdue_first_week_message', [
                    'amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}",
                    'overdue_days' => 7,
                    'ussd_repayment_amount' => ceil($currentAmountRemaining),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'debt_overdue_second_week_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_overdue_second_week_message', [
                    'amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}",
                    'overdue_days' => 14,
                    'ussd_repayment_amount' => ceil($currentAmountRemaining),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'debt_overdue_third_week_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_overdue_third_week_message', [
                    'amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}",
                    'overdue_days' => 21,
                    'ussd_repayment_amount' => ceil($currentAmountRemaining),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'debt_overdue_fourth_week_message':
                $currentAmountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.debt_overdue_fourth_week_message', [
                    'amount_remaining' => "{$currencyRepresentation}{$currentAmountRemaining}",
                    'overdue_days' => 28,
                    'ussd_repayment_amount' => ceil($currentAmountRemaining),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            case 'has_no_debt_message':
                $message = __('interswitch.has_no_debt_message', [
                    'loan_request_url' => $loanRequestUrl
                ]);
                break;

            case 'has_debt_without_penalty_message':
                $amount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount
                ])->toHigherDenomination()->getValue();

                $amountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.has_debt_without_penalty_message', [
                    'amount' => "{$currencyRepresentation}{$amount}",
                    'credit_date' => $loanOffer->loan->created_at->format('d-m-Y'),
                    'remaining_amount' => "{$currencyRepresentation}{$amountRemaining}",
                    'due_date' => $loanOffer->loan->due_date->format('d-m-Y')
                ]);
                break;

            case 'has_debt_with_penalty_message':
                $amount = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount
                ])->toHigherDenomination()->getValue();

                $amountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $message = __('interswitch.has_debt_with_penalty_message', [
                    'amount' => "{$currencyRepresentation}{$amount}",
                    'credit_date' => $loanOffer->loan->created_at->format('d-m-Y'),
                    'remaining_amount' => "{$currencyRepresentation}{$amountRemaining}",
                    'penal_fee' => "{$currencyRepresentation}X,XXX.XX",
                    'due_date' => $loanOffer->loan->due_date->format('d-m-Y')
                ]);
                break;

            case 'blacklist_scoring_message':
                $message = __('interswitch.blacklist_scoring_message', [
                    'assistance_url' => $assistanceUrl
                ]);
                break;

            case 'failed_disbursement_due_to_wrong_account_number_message':
                $message = __('interswitch.failed_disbursement_due_to_wrong_account_number_message');
                break;

            case 'failed_disbursement_due_to_technical_issues':
                $message = __('interswitch.failed_disbursement_due_to_technical_issues');
                break;

            case 'debt_overdue_x_days_message':
                $amountRemaining = app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
                ])->toHigherDenomination()->getValue();

                $overdueDays = app()->make(Date::class)->dayDifference($today, $loanOffer->loan->due_date, $timezone);

                $message = __('interswitch.debt_overdue_x_days_message', [
                    'amount_remaining' => "{$currencyRepresentation}{$amountRemaining}",
                    'overdue_days' => $overdueDays,
                    'pluralization' => Str::plural('day', $overdueDays),
                    'ussd_repayment_amount' => ceil($amountRemaining),
                    'virtual_account_details' => isset($loanOffer->customer->virtualAccount) ? "or transfer to {$loanOffer->customer->virtualAccount->bank_name}, Acc No: {$loanOffer->customer->virtualAccount->account_number}" : ''
                ]);
                break;

            default:
                throw new ApplicationCustomException('Invalid SMS choice', 503);
                break;
        }

        // Send the SMS
        SendSms::dispatch(
            $message,
            $loanOffer->customer->phone_number,
            $loanOffer->id,
            true
        );

        return $this->sendSuccess('SMS sending successfully initiated.', 200, [
            'message' => $message
        ]);
    }

    /**
     * Search for loan offers
     */
    public function search(SearchLoanOffersRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        // Get the loan offers based on the query
        $loanOffers = LoanOffer::with(['customer', 'loan.latestTransaction'])
            ->when($data['status'] ?? null, fn($query, $value) => $query->whereIn('status', $value))
            ->when($data['from_date'] ?? null, fn($query, $value) => $query->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['due_from_date'] ?? null, fn($query, $value) => $query->whereHas('loan', fn($query2) => $query2->where('due_date', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['due_to_date'] ?? null, fn($query, $value) => $query->whereHas('loan', fn($query2) => $query2->where('due_date', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $loanOffers);
    }

    /**
     * Process the payment of a loan
     */
    public function paymentProcessing(LoanPaymentProcessingRequest $request, LoanOffer $loanOffer)
    {
        $data = $request->validated();

        // Load the loan relationship
        $loanOffer->load(['loan', 'customer']);

        /**
         * Check if the loan is "OPEN" or "OVERDUE"
         */
        if ($loanOffer->status !== LoanOffer::OPEN && $loanOffer->status !== LoanOffer::OVERDUE) {
            throw new ApplicationCustomException(__('interswitch.loan_unprocessable', [
                'loan_status' => $loanOffer->status,
                'expected_loan_statuses' => implode(' or ', [
                    '"' . LoanOffer::OPEN . '"',
                    '"' . LoanOffer::OVERDUE . '"'
                ])
            ]), 400);
        }

        // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new ApplicationCustomException(__('interswitch.uncollected_loan'), 400);
        }

        /**
         * Check if the remaining amount is deductable
         */
        if (
            $loanOffer->loan->amount_remaining <= 0 &&
            $loanOffer->loan->penalty_remaining <= 0
        ) {
            throw new ApplicationCustomException(__('interswitch.loan_paid_in_full'), 400);
        }

        // Process the payment of the loan
        DB::transaction(function () use ($data, $loanOffer, $request) {
            // Process the payment
            $amountPaid = $data['amount'];

            app()->make(LoanCalculator::class)->processPayment($loanOffer->loan, $amountPaid);

            // Create the record of the transaction
            $transaction = $loanOffer->loan->transactions()->create([
                'amount' => $data['amount'],
                'type' => Transaction::MANUAL
            ]);

            // Log the user that performed the request
            $transaction->forceFill([
                'user_id' => $request->user()->id
            ])->save();
        });

        // Check if the remaining amount is less than or equal to 0 then mark it as "CLOSED"
        if (
            $loanOffer->loan->amount_remaining <= 0 &&
            $loanOffer->loan->penalty_remaining <= 0
        ) {
            // Process the closure of the loan
            DB::transaction(function () use ($loanOffer) {
                // Update the status of the loan to "CLOSED"
                $status = app()->make(InterswitchService::class)->status(LoanOffer::CLOSED, $loanOffer->id, true);

                // Close the loan in the database
                $loanOffer->forceFill([
                    'status' => LoanOffer::CLOSED
                ])->save();

                // Update the associated collection case to closed
                $loanOffer->collectionCase()
                    ->update([
                        'status' => CollectionCase::CLOSED
                    ]);
            });

            $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                'value' => $loanOffer->loan->amount
            ])->toHigherDenomination()->getValue();

            $message = __('interswitch.loan_fully_collected_message', [
                'covered_amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                'loan_request_url' => config('quickfund.loan_request_url')
            ]);
        } else {
            $higherDenominationPaymentAmount = app()->make(MoneyCalculator::class, [
                'value' => $data['amount']
            ])->toHigherDenomination()->getValue();

            $higherDenominationRemainingAmount = app()->make(MoneyCalculator::class, [
                'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            $message = __('interswitch.loan_partially_collected_message', [
                'recovered_amount' => config('quickfund.currency_representation') . number_format($higherDenominationPaymentAmount, 2),
                'remaining_amount' => config('quickfund.currency_representation') . number_format($higherDenominationRemainingAmount, 2)
            ]);
        }

        /**
         * Send the customer an SMS for payment completion
         */
        SendSms::dispatch(
            $message,
            $loanOffer->customer->phone_number,
            $loanOffer->id,
            true
        );

        return $this->sendSuccess('Payment successfully processed.', 200, [
            'loan_status' => $loanOffer->status
        ]);
    }

    /**
     * Get loans due in certain days
     */
    public function dueInDays(DueInDaysRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        // Get the loan offers based on the query
        $loanOffers = LoanOffer::with(['customer', 'loan.latestTransaction'])
            ->where('status', LoanOffer::OPEN)
            ->whereHas('loan', fn($query) => $query->whereDate('due_date', now()->addDays($data['days'])->timezone(config('quickfund.date_query_timezone'))))
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $loanOffers);
    }
}
