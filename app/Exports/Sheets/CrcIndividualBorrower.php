<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\{FromCollection, WithMapping, WithHeadings, WithTitle};
use Illuminate\Support\Str;
use App\Services\Application as ApplicationService;

class CrcIndividualBorrower implements FromCollection, WithMapping, WithHeadings, WithTitle
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
     * @return array
     */
    public function headings(): array
    {
        return [
            'CustomerID',
            'Branch Code',
            'Surname',
            'First Name',
            'Middle Name',
            'Date of Birth',
            'National Identity Number',
            'Drivers License Number',
            'BVN No',
            'Passport No',
            'Gender',
            'Nationality',
            'Marital Status',
            'Mobile Number',
            'Primary Address Line 1',
            'Primary Address Line 2',
            'Primary city/LGA',
            'Primary State',
            'Primary Country',
            'Employment Status',
            'Occupation',
            'Business Category',
            'Business Sector',
            'Borrower Type',
            'Other ID',
            'Tax ID',
            'Picture File Path',
            'Email Address',
            'Employer Name',
            'Employer Address Line 1',
            'Employer Address Line 2',
            'Employer City',
            'Employer State',
            'Employer Country',
            'Title',
            'Place of Birth',
            'Work Phone',
            'Home Phone',
            'Secondary Address Line 1',
            'Secondary Address Line 2',
            'Secondary Address City/LGA',
            'Secondary Address State',
            'Secondary Address Country',
            "Spouse's Surname",
            "Spouse's First Name",
            "Spouse's Middle Name",
        ];
    }

    /**
     * The collection of the export
     */
    public function collection()
    {
        return $this->loans;
    }

    /**
     * @param  mixed  $row
     * @return array
     */
    public function map($loan): array
    {
        return [
            $loan->loanOffer->customer->id,
            '001',
            Str::of($loan->loanOffer->customer->last_name)->upper(),
            Str::of($loan->loanOffer->customer->first_name)->upper(),
            null,
            isset($loan->loanOffer->customer->crc) ? $loan->loanOffer->customer->crc->profile_details['CONSUMER_DETAILS']['DATE_OF_BIRTH'] : null,
            null,
            null,
            $loan->loanOffer->customer->bvn,
            null,
            app()->make(ApplicationService::class)->gender($loan->loanOffer->customer),
            isset($loan->loanOffer->customer->crc) ? $loan->loanOffer->customer->crc->profile_details['CONSUMER_DETAILS']['CITIZENSHIP'] : null,
            null,
            $loan->loanOffer->customer->phone_number,
            Str::of($loan->loanOffer->customer->address)->upper(),
            null,
            null,
            null,
            isset($loan->loanOffer->customer->crc) ? $loan->loanOffer->customer->crc->profile_details['CONSUMER_DETAILS']['CITIZENSHIP'] : null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $loan->loanOffer->customer->email,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Individual Borrower Template';
    }
}
