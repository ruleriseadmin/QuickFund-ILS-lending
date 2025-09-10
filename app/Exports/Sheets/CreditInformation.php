<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\{FromCollection, WithCustomValueBinder, WithMapping, WithHeadings, WithTitle};
use App\Services\Application as ApplicationService;
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder};

class CreditInformation extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithMapping, WithHeadings, WithTitle
{
    /**
     * The loans collected by the customer
     */
    private $loans;

    /**
     * Create an instance
     */
    public function __construct($loans)
    {
        $this->loans = $loans;
    }

    /**
     * The collection of the export
     */
    public function collection()
    {
        return $this->loans;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Customer ID',
            'Account Number',
            'Account Status',
            'Account Status Date',
            'Date of loan (facility) disbursement/Loan effective date',
            'Credit limit/Facility amount/Global limit',
            'Loan (Facility) Amount/Availed Limit',
            'Outstanding balance',
            'Instalment amount',
            'Currency',
            'Days in arrears',
            'Overdue amount',
            'Loan (Facility) type',
            'Loan (Facility) Tenor',
            'Repayment frequency',
            'Last payment date',
            'Last payment amount',
            'Maturity date',
            'Loan Classification',
            'Legal Challenge Status',
            'Litigation Date',
            'Consent Status',
            'Loan Security Status',
            'Collateral Type',
            'Collateral Details',
            'Previous Account number',
            'Previous Name',
            'Previous Customer ID',
            'Previous Branch code',
        ];
    }

    /**
     * @param  mixed  $row
     * @return array
     */
    public function map($loan): array
    {
        return [
            $loan->loanOffer->customer->id,
            "{$loan->loanOffer->customer->account_number}-{$loan->loanOffer->customer->bank_code}",
            '001',
            null,
            $loan->created_at->format('d-M-Y'),
            app()->make(ApplicationService::class)->moneyFormat($loan->loanOffer->amount),
            app()->make(ApplicationService::class)->moneyFormat($loan->loanOffer->amount),
            app()->make(ApplicationService::class)->moneyFormat($loan->amount_remaining + $loan->penalty_remaining),
            app()->make(ApplicationService::class)->moneyFormat($loan->amount_remaining),
            'NGN',
            app()->make(ApplicationService::class)->daysInArrears($loan),
            app()->make(ApplicationService::class)->overdueAmount($loan),
            '033',
            $loan->loanOffer->tenure,
            app()->make(ApplicationService::class)->repaymentFrequency($loan->loanOffer->tenure),
            app()->make(ApplicationService::class)->lastPaymentDate($loan),
            app()->make(ApplicationService::class)->lastPaymentAmount($loan),
            $loan->due_date->format('d-M-Y'),
            null,
            'No',
            null,
            'Yes',
            'No',
            null,
            null,
            null,
            null,
            null,
            null,
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Credit Information';
    }
}
