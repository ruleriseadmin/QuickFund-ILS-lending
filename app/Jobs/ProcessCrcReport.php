<?php

namespace App\Jobs;

use App\Services\CreditBureau\Crc;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessCrcReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $customers;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customers)
    {
        $this->customers = $customers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $crcService = new Crc();

        // 🔹 Borrower info
        $borrowerPayload = $this->customers->map(function ($customer) {
            return [
                'CustomerID' => $customer->formatted_customer_id,
                'BranchCode' => '01',
                'Surname' => $customer->last_name ?: 'UNKNOWN',
                'Firstname' => $customer->first_name ?: 'UNKNOWN',
                'Middlename' => '',
                'DateofBirth' => $customer->date_of_birth
                    ? $customer->date_of_birth->format('d/m/Y')
                    : '01/01/1900', // ✅ safe fallback
                'NationalIdentityNumber' => '',
                'DriversLicenseNo' => '',
                'BVNNo' => $customer->bvn,
                'PassportNo' => '',
                'Gender' => $customer->gender,
                'Nationality' => 'Nigeria',
                'Mobilenumber' => $customer->phone_number,
                'PrimaryAddressLine1' => $customer->address,
                'Primarycity/LGA' => $customer->city,
                'PrimaryState' => $customer->state,
                'PrimaryCountry' => 'NIGERIA',
                'E-mailaddress' => $customer->email,
                "MaritalStatus" => "",
                "EmploymentStatus" => "",
                "Occupation" => "",
                "BusinessCategory" => "",
                "BusinessSector" => "",
                "BorrowerType" => "",
                "OtherID" => "",
                "TaxID" => "",
                "PictureFilePath" => "",
                "EmployerName" => "",
                "EmployerAddressLine1" => "",
                "EmployerAddressLine2" => "",
                "EmployerCity" => "",
                "EmployerState" => "",
                "EmployerCountry" => "",
                "Title" => "",
                "PlaceofBirth" => "",
                "Workphone" => "",
                "Homephone" => "",
                "SecondaryAddressLine1" => "",
                "SecondaryAddressLine2" => "",
                "SecondaryAddressCity/LGA" => "",
                "SecondaryAddressState" => "",
                "SecondaryAddressCountry" => "",
                "SpousesSurname" => "",
                "SpousesFirstname" => "",
                "SpousesMiddlename" => "",
            ];
        });

        $crcService->reportIndividualBorrowersInformation($borrowerPayload);

        // 🔹 Credit info
        $creditInfoPayload = $this->customers->flatMap(function ($customer) {
            return $customer->loans->map(function ($loan) use ($customer) {
                return [
                    'CustomerID' => $customer->formatted_customer_id,
                    'AccountNumber' => $loan->destination_account_number,
                    'AccountStatus' => 'Open',
                    'AccountStatusDate' => now()->format('d/m/Y'),
                    'DateOfLoan(Facility)Disbursement/LoanEffectiveDate' => $loan->created_at
                        ? $loan->created_at->format('d/m/Y')
                        : '',
                    'CreditLimit(Facility)Amount/GlobalLimit' => $loan->amount,
                    'Loan(Facility)Amount/AvailedLimit' => $loan->amount_payable,
                    'OutstandingBalance' => $loan->amount_remaining,
                    'Currency' => 'Naira',
                    'Loan(Facility)Type' => 'Commercial Overdraft',
                    'MaturityDate' => $loan->due_date
                        ? $loan->due_date->format('d/m/Y')
                        : '',
                    'LoanClassification' => 'Performing',
                    "InstalmentAmount" => "",
                    "DaysInArrears" => (int) $loan->days_in_arrears ?? 0,
                    "OverdueAmount" => (float) $loan->overdue_amount ?? 0,
                    "Loan(Facility)Tenor" => "",
                    "RepaymentFrequency" => "",
                    "LastPaymentDate" => "",
                    "LastPaymentAmount" => "",
                    "LegalChallengeStatus" => "",
                    "LitigationDate" => "",
                    "ConsentStatus" => "",
                    "LoanSecurityStatus" => "NO",
                    "CollateralType" => "",
                    "CollateralDetails" => "",
                    "PreviousAccountNumber" => "",
                    "PreviousName" => "",
                    "PreviousCustomerID" => "",
                    "PreviousBranchCode" => ""
                ];
            });
        });

        $crcService->reportCreditInformation($creditInfoPayload);
    }
}
