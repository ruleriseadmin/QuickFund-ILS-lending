<?php

namespace App\Exports;

use App\Exports\Sheets\CrcIndividualBorrower;
use App\Exports\Sheets\CreditInformation;
use Maatwebsite\Excel\Concerns\{FromCollection, WithMultipleSheets};

class CrcReport implements FromCollection, WithMultipleSheets
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
    public function sheets(): array
    {
        return [
            new CrcIndividualBorrower($this->loans),
            new CreditInformation($this->loans)
        ];
    }
}
