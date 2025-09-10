<?php

namespace App\Services\Loans;

use Illuminate\Support\Facades\DB;
use App\Models\{Blacklist, LoanOffer, Transaction};
use App\Services\Interswitch as InterswitchService;

class Calculator
{
    /**
     * Process the payment of a loan
     */
    public function processPayment($loan, $amount)
    {
        /**
         * Check if there is any deductable amount
         */
        if ($amount > 0) {
            // Check if there is any penalty remaining
            if ($loan->penalty_remaining > 0) {
                // Deduct the penalty remaining
                $this->deductPenaltyRemaining($loan, $amount);
            }
        }

        /**
         * Check if there is any deductable amount
         */
        if ($amount > 0) {
            // Check if there is any amount remaining
            if ($loan->amount_remaining > 0) {
                // Deduct the amount remaining
                $this->deductAmountRemaining($loan, $amount);
            }
        }
    }

    /**
     * Process the credit on a customer
     */
    public function processCredit($creditDetails, $loanOffer, $transaction, $inApp = true, $shouldForceUpdateStatus = false)
    {
        // Credit was successful
        DB::transaction(function() use ($transaction, $creditDetails, $loanOffer, $shouldForceUpdateStatus) {
            if ($shouldForceUpdateStatus === true) {
                // Make the request to change the loan status to "OPEN"
                $status = app()->make(InterswitchService::class)->status(LoanOffer::OPEN, $loanOffer->id, true);
            }

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
            if (Blacklist::where('phone_number', $loanOffer->customer->phone_number)
                        ->where('type', Blacklist::BY_CODE)
                        ->where('completed', true)
                        ->exists()) {
                // Remove the customer from the blacklist
                Blacklist::where('phone_number', $loanOffer->customer->phone_number)
                        ->where('type', Blacklist::BY_CODE)
                        ->where('completed', true)
                        ->delete();
            }
        });
    }

    /**
     * Process the non credit on a customer
     */
    public function processNonCredit($creditDetails, $transaction, $inApp = true)
    {
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
    }

    /**
     * Process the debit on a customer
     */
    public function processDebit($debitDetails, $loan, $transaction)
    {
        // Debit was successful
        DB::transaction(function() use ($transaction, $debitDetails, $loan) {
            // Process the transaction
            $this->processPayment($loan, $transaction->amount);

            // Update the transaction details
            $transaction->update([
                'interswitch_transaction_message' => $debitDetails['responseMessage'] ?? $debitDetails['responseDescription'] ?? null,
                'interswitch_transaction_code' => $debitDetails['responseCode'] ?? null,
                'interswitch_transaction_reference' => $debitDetails['transactionRef'] ?? null,
                'type' => Transaction::DEBIT
            ]);
        });
    }

    /**
     * Process the non debit on a customer
     */
    public function processNonDebit($debitDetails, $transaction)
    {
        // Update the transaction details
        $transaction->update([
            'interswitch_transaction_message' => $debitDetails['responseMessage'] ?? $debitDetails['responseDescription'] ?? null,
            'interswitch_transaction_code' => $debitDetails['responseCode'] ?? null,
            'interswitch_transaction_reference' => $debitDetails['transactionRef'] ?? null,
            'type' => Transaction::DEBIT
        ]);
    }

    /**
     * Deduct the penalty remaining
     */
    private function deductPenaltyRemaining($loan, &$amount)
    {
        $amount -= $loan->penalty_remaining;

        // Check if the amount was enough to deduct all penalty remaining
        if ($amount >= 0) {
            // Amount was enough to deduct all penalty remaining
            $loan->forceFill([
                'penalty_remaining' => 0
            ])->save();
        } else {
            // Money was not enough to deduct all penalties
            $loan->forceFill([
                'penalty_remaining' => abs($amount)
            ])->save();

            $amount = 0;
        }
    }

    /**
     * Deduct the amount remaining
     */
    private function deductAmountRemaining($loan, &$amount)
    {
        $amount -= $loan->amount_remaining;

        // Check if the amount was enough to deduct all amount remaining
        if ($amount >= 0) {
            // Amount was enough to deduct all amount remaining
            $loan->forceFill([
                'amount_remaining' => 0
            ])->save();
        } else {
            // Money was not enough to deduct amount remaining
            $loan->forceFill([
                'amount_remaining' => abs($amount)
            ])->save();

            $amount = 0;
        }
    }
}