<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\Customer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Services\CreditBureau\Crc;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessCrcReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
    protected $customers;

    protected string $reportBaseUrl;
    protected string $reportUserId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customers)
    {
        $this->customers = $customers;
        $this->reportBaseUrl = 'https://files.creditreferencenigeria.net/crccreditbureau_Datasubmission_Webservice/JSON/api/';
        $this->reportUserId = config('services.crc.reporting_userid', 'crcautomations');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        try {
            $crcService = new Crc();

            // 🔹 Borrower info (Individual)
            $borrowerPayload = $this->customers->map(function ($customer) {
                return [
                    'CustomerID' => $customer->formatted_customer_id,
                    'BranchCode' => $customer->branch_code ?? '01',
                    'Surname' => $customer->last_name ?: 'UNKNOWN',
                    'Firstname' => $customer->first_name ?: 'UNKNOWN',
                    'Middlename' => $customer->middle_name ?? '',
                    'DateOfBirth' => $customer->date_of_birth
                        ? $customer->date_of_birth->format('d/m/Y')
                        : '01/01/1900', // ✅ safe fallback
                    'NationalIdentityNumber' => $customer->nin ?? '',
                    'DriversLicenseNo' => $customer->drivers_license ?? '',
                    'BVNNo' => $customer->bvn ?? '',
                    'PassportNo' => $customer->passport_no ?? '',
                    'Gender' => strtoupper(substr($customer->gender, 0, 1)) === 'M' ? 'M' : 'F',
                    'Nationality' => 'NIGERIA',
                    'MobileNumber' => $customer->phone_number ?? '',
                    'PrimaryAddressLine1' => $customer->address ?? 'UNKNOWN',
                    'PrimaryAddressLine2' => $customer->address2 ?? '',
                    'PrimarycityLGA' => $customer->city ?? 'UNKNOWN',
                    'PrimaryState' => $customer->state ?? 'UNKNOWN',
                    'PrimaryCountry' => 'NIGERIA',
                    'EmailAddress' => $customer->email ?? '',
                    'MaritalStatus' => $customer->marital_status ?? '',
                    'EmploymentStatus' => $customer->employment_status ?? 'UE', // UE = unemployed
                    'Occupation' => $customer->occupation ?? 'UNKNOWN',
                    'BusinessCategory' => $customer->business_category ?? 'General',
                    'BusinessSector' => $customer->business_sector ?? 'General',
                    'BorrowerType' => 'I', // I = Individual
                    'OtherID' => '',
                    'TaxID' => $customer->tax_id ?? '',
                    'PictureFilePath' => '',
                    'EmployerName' => $customer->employer_name ?? '',
                    'EmployerAddressLine1' => $customer->employer_address ?? '',
                    'EmployerAddressLine2' => $customer->employer_address2 ?? '',
                    'EmployerCity' => $customer->employer_city ?? '',
                    'EmployerState' => $customer->employer_state ?? '',
                    'EmployerCountry' => $customer->employer_country ?? '',
                    'Title' => $customer->title ?? '',
                    'PlaceOfBirth' => $customer->place_of_birth ?? '',
                    'WorkPhone' => $customer->work_phone ?? '',
                    'HomePhone' => $customer->home_phone ?? '',
                    'SecondaryAddressLine1' => $customer->secondary_address ?? '',
                    'SecondaryAddressLine2' => $customer->secondary_address2 ?? '',
                    'SecondarycityLGA' => $customer->secondary_city ?? '',
                    'SecondaryState' => $customer->secondary_state ?? '',
                    'SecondaryCountry' => $customer->secondary_country ?? '',
                    'SpousesSurname' => $customer->spouse_surname ?? '',
                    'SpousesFirstname' => $customer->spouse_firstname ?? '',
                    'SpousesMiddlename' => $customer->spouse_middlename ?? '',
                ];
            });

            $this->reportIndividualBorrowersInformation($borrowerPayload);



            // 🔹 Credit info (Loans)
            $payload = $this->customers->flatMap(function (Customer $customer) {
                return $customer->loans()
                    ->whereNull('crc_reported_at')
                    ->get()
                    ->map(function ($loan) use ($customer) {
                        return [
                            "loan_id" => $loan->id,
                            'CustomerID' => $customer->formatted_customer_id,
                            'AccountNumber' => $loan->destination_account_number ?? '',
                            'AccountStatus' => $loan->is_closed ? 'Closed' : 'Open',
                            'AccountStatusDate' => now()->format('d/m/Y'),
                            'DateOfLoanDisbursement' => $loan->created_at
                                ? $loan->created_at->format('d/m/Y')
                                : '',
                            'CreditLimitAmount' => $loan->amount ?? 0,
                            'LoanAmountAvailed' => $loan->amount_payable ?? 0,
                            'OutstandingBalance' => $loan->amount_remaining ?? 0,
                            'Currency' => 'NGN',
                            'LoanType' => $loan->loan_type ?? 'Commercial Overdraft',
                            'MaturityDate' => $loan->due_date
                                ? $loan->due_date->format('d/m/Y')
                                : '',
                            'LoanClassification' => $loan->classification ?? 'Performing',
                            'InstalmentAmount' => $loan->installment_amount ?? '',
                            'DaysInArrears' => $loan->days_in_arrears ?? '0',
                            'OverdueAmount' => $loan->overdue_amount ?? '0',
                            'LoanTenor' => $loan->tenor ?? '',
                            'RepaymentFrequency' => $loan->repayment_frequency ?? '',
                            'LastPaymentDate' => $loan->last_payment_date
                                ? $loan->last_payment_date->format('d/m/Y')
                                : '',
                            'LastPaymentAmount' => $loan->last_payment_amount ?? '',
                            'LegalChallengeStatus' => '',
                            'LitigationDate' => '',
                            'ConsentStatus' => '',
                            'LoanSecurityStatus' => $loan->secured ? 'YES' : 'NO',
                            'CollateralType' => $loan->collateral_type ?? '',
                            'CollateralDetails' => $loan->collateral_details ?? '',
                            'PreviousAccountNumber' => '',
                            'PreviousName' => '',
                            'PreviousCustomerID' => '',
                            'PreviousBranchCode' => '',
                        ];
                    });
            });

            if (empty($payload)) {
                Log::info('No new loans to report to CRC.');
                return;
            }

            // 🔹 Submit loans in chunks
            collect($payload)->chunk(20)->each(function ($chunk, $index) {
                $loanIds = collect($chunk)->pluck('loan_id');

                // Prepare payload without loan_id
                $cleanPayload = collect($chunk)->map(function ($item) {
                    return collect($item)->except(['loan_id'])->toArray();
                })->toArray();

                $response = $this->reportCreditInformation($cleanPayload);

                if (!$response || isset($response['error'])) {
                    Log::error("CRC upload failed for chunk {$index}", [
                        'chunk_size' => $chunk->count(),
                        'response' => $response,
                    ]);
                } else {
                    Loan::whereIn('id', $loanIds)->update([
                        'crc_reported_at' => now(),
                    ]);

                    Log::info("CRC upload success for chunk {$index}", [
                        'count' => $chunk->count(),
                        'response' => $response,
                    ]);
                }
            });

        } catch (\Throwable $e) {
            Log::error('Process CRC Report job failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    /**
     * Report borrowers' personal info to CRC
     */
    public function reportIndividualBorrowersInformation($payload)
    {
        return $this->sendRequest('neIndividualborrower', $payload);
    }

    /**
     * Report credit/loan info to CRC
     */
    public function reportCreditInformation($payload)
    {
        return $this->sendRequest('nECreditInfo', $payload);
    }

    /**
     * Internal helper for making requests
     */
    protected function sendRequest(string $endpoint, $payload)
    {
        try {
            $response = Http::asForm()->post(
                $this->reportBaseUrl . $endpoint . '/',
                [
                    'payload' => is_string($payload) ? $payload : json_encode($payload),
                    'userid' => $this->reportUserId,
                ]
            );

            if ($response->failed()) {
                Log::error("CRC API call to [{$endpoint}] failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } else {
                Log::info("CRC API call to [{$endpoint}] succeeded", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("CRC API exception on [{$endpoint}]", [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

}
