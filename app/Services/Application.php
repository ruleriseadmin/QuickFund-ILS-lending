<?php

namespace App\Services;

use App\Models\{LoanOffer, Transaction};

class Application
{
    /**
     * Get the higher denomination amount
     */
    public function higherDenominationAmount($amount)
    {
        return $amount / 100;
    }

    /**
     * Get the higher denomination money format
     */
    public function moneyFormat($amount)
    {
        // Get the higher denomination amount
        $higherDenominationAmount = $this->higherDenominationAmount($amount);

        return number_format($higherDenominationAmount, 2);
    }

    /**
     * Get the number format format
     */
    public function numberFormat($number)
    {
        return number_format($number);
    }

    /**
     * Get the higher denomination money display
     */
    public function moneyDisplay($amount)
    {
        return 'NGN '.$this->moneyFormat($amount);
    }

    /**
     * Return a boolean of a string
     */
    public function boolifyString($string)
    {
        if (is_string($string)) {
            if (in_array(strtolower($string), ['false', '0'])) {
                return false;
            }

            return true;
        }

        return $string;
    }

    /**
     * Resolve the gender of a customer
     */
    public function gender($customer)
    {
        // Get the gender from the customer model
        $gender = $customer->gender;

        if (isset($gender)) {
            switch (strtoupper($gender)) {
                case 'MALE':
                case 'M':
                    return 'M';
                break;

                case 'FEMALE':
                case 'F':
                    return 'F';
                break;
            }
        }

        // Use the CRC gender code to get the gender of the customer
        if (isset($customer->crc)) {
            $gender = $customer->crc->profile_details['CONSUMER_DETAILS']['GENDER'];

            switch ($gender) {
                case '001':
                    return 'M';
                break;

                case '002':
                    return 'F';
                break;
            }
        }

        return null;
    }

    /**
     * Get the days in arrears
     */
    public function daysInArrears($loan)
    {
        // The loan is still "OPEN" so it is not yet being defaulted
        if ($loan->loanOffer->status === LoanOffer::OPEN) {
            return $this->numberFormat(0);
        }

        // If the loan is "CLOSED"
        if ($loan->loanOffer->status === LoanOffer::CLOSED) {
            // If the loan is "CLOSED" before the due date, they never defaulted
            if ($loan->loanOffer->updated_at->startOfDay() <= $loan->due_date->startOfDay()) {
                return $this->numberFormat(0);
            }

            return $this->numberFormat($loan->due_date->diffInDays($loan->loanOffer->updated_at));
        }

        // Loan is "OVERDUE"
        return $this->numberFormat($loan->due_date->diffInDays(now()));
    }

    /**
     * Get the overdue amount on a loan
     */
    public function overdueAmount($loan)
    {
        // Check if the status on the loan is overdue
        if ($loan->loanOffer->status !== LoanOffer::OVERDUE) {
            return $this->moneyFormat(0);
        }

        return $this->moneyFormat($loan->amount_remaining + $loan->penalty_remaining);
    }

    /**
     * Get the repayment frequency in words
     */
    public function repaymentFrequency($tenure)
    {
        switch ($tenure) {
            case 14:
                return 'Fortnightly';
            break;

            case 30:
                return 'Monthly';
            break;
            
            default:
                
            break;
        }
    }

    /**
     * Get the last payment date of a loan
     */
    public function lastPaymentDate($loan)
    {
        $lastTransaction = $loan->transactions()
                                ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                                                                                        ->where('interswitch_transaction_code', '00')
                                                                                        ->whereNotNull('interswitch_transaction_reference'))
                                                            ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                                                                                        ->whereNotNull('interswitch_payment_reference')))
                                ->latest()
                                ->first();

        if (isset($lastTransaction)) {
            return $lastTransaction->created_at->format('d-M-Y');
        }

        return null;
    }

    /**
     * Get the last payment amount of a loan
     */
    public function lastPaymentAmount($loan)
    {
        $lastTransaction = $loan->transactions()
                                ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                                                                                        ->where('interswitch_transaction_code', '00')
                                                                                        ->whereNotNull('interswitch_transaction_reference'))
                                                            ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                                                                                        ->whereNotNull('interswitch_payment_reference')))
                                ->latest()
                                ->first();

        if (isset($lastTransaction)) {
            return $this->moneyFormat($lastTransaction->amount);
        }

        return null;
    }

    /**
     * Nullify response from interswitch that is not available
     */
    public function nullify($input)
    {
        if (in_array($input, ['N/A', 'n/a', ''])) {
            return null;
        }

        return $input;
    }
}