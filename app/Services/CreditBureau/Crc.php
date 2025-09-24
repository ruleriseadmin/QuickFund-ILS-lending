<?php

namespace App\Services\CreditBureau;

use App\Models\Loan;
use App\Models\Customer;
use App\Contracts\CreditBureau;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\{Http, DB};

class Crc implements CreditBureau
{
    protected string $reportBaseUrl;
    protected string $reportUserId;

    public function __construct()
    {
        $this->reportBaseUrl = 'https://files.creditreferencenigeria.net/crccreditbureau_Datasubmission_Webservice/JSON/api/';
        $this->reportUserId = config('services.crc.reporting_userid', 'crcautomations');
    }

    /**
     * Check if the credit bureau check passes
     */
    public function passesCheck($customer, $setting)
    {
        $useCrcCheck = $setting?->use_crc_check ?? config('quickfund.use_crc_check');
        $daysToMakeCrcCheck = $setting?->days_to_make_crc_check ?? config('quickfund.days_to_make_crc_check');

        /**
         * Check if First CRC checks should be ignored
         */
        if (!$useCrcCheck) {
            return true;
        }

        /**
         * Check if a customer is whitelisted
         */
        if (
            $customer->isWhitelistedManually() ||
            $customer->isWhitelistedByCode()
        ) {
            return true;
        }

        /**
         * Check if the customer has BVN. This should not run based on the system configuration
         */
        if (!isset($customer->bvn)) {
            return false;
        }

        // Load the crc relationship
        $customer->load(['crc']);

        /**
         * Check if the CRC request should not be made then we use the CRC records stored to check if the
         * customer passes CRC check
         */
        if (!$this->shouldMakeRequest($customer, $daysToMakeCrcCheck)) {
            // Check if CRC check is passed based on application standards
            return $this->passedCheckByApplication($customer->crc, $setting);
        }

        // Make the request to get the customer details from CRC
        $basicRequestBody = $this->basicRequest($customer->bvn);

        // Check to know if the CRC request returned a consumer no hit response
        if ($this->requestIsConsumerNoHit($basicRequestBody)) {
            return true;
        }

        // Check to know if the CRC request returned a consumer hit response
        if ($this->requestIsConsumerHit($basicRequestBody)) {
            // Save the fresh CRC record
            $crc = $this->saveRecord($customer, $basicRequestBody, $setting);

            // Check if CRC check is passed based on application standards
            return $this->passedCheckByApplication($crc, $setting);
        }

        // Check to know if the CRC request returned a consumer search result response that requires merging
        if ($this->requestIsConsumerSearchResult($basicRequestBody)) {
            /**
             * We perform a merge request to merge the data
             */
            $mergeRequestBody = $this->mergeRequest($basicRequestBody);

            // CRC data merging successful. Save the fresh CRC record
            $crc = $this->saveRecord($customer, $mergeRequestBody, $setting);

            // Check if CRC check is passed based on application standards
            return $this->passedCheckByApplication($crc, $setting);
        }

        // Fallback to something weird
        return false;
    }

    /**
     * CRC basic request to get customer details based on BVN
     */
    public function basicRequest($bvn)
    {
        $response = Http::acceptJson()
            ->post(config('services.crc.url'), [
                'Request' => json_encode([
                    '@REQUEST_ID' => '1',
                    'REQUEST_PARAMETERS' => [
                        'REPORT_PARAMETERS' => [
                            '@REPORT_ID' => '7463',
                            '@SUBJECT_TYPE' => '1',
                            '@RESPONSE_TYPE' => '5'
                        ],
                        'INQUIRY_REASON' => [
                            '@CODE' => '1',
                        ],
                        'APPLICATION' => [
                            '@PRODUCT' => '017',
                            '@NUMBER' => '232',
                            '@AMOUNT' => '15000',
                            '@CURRENCY' => 'NGN'
                        ]
                    ],
                    'SEARCH_PARAMETERS' => [
                        '@SEARCH-TYPE' => '4',
                        'BVN_NO' => $bvn
                    ]
                ]),
                'UserName' => config('services.crc.username'),
                'Password' => config('services.crc.password')
            ]);

        $body = $response->json();

        if ($this->failedRequest($body)) {
            throw new CustomException('CRC basic check failed.', 503);
        }

        return $body;
    }

    /**
     * CRC merge request to for duplicate data
     */
    public function mergeRequest($responseBody)
    {
        $response = Http::acceptJson()
            ->post(config('services.crc.url'), [
                'Request' => json_encode([
                    '@REQUEST_ID' => '1',
                    'REQUEST_PARAMETERS' => [
                        'REPORT_PARAMETERS' => [
                            '@REPORT_ID' => '7463',
                            '@SUBJECT_TYPE' => '1',
                            '@RESPONSE_TYPE' => '5'
                        ],
                        'INQUIRY_REASON' => [
                            '@CODE' => '1',
                        ],
                        'APPLICATION' => [
                            '@PRODUCT' => '001',
                            '@NUMBER' => '232',
                            '@AMOUNT' => '1000',
                            '@CURRENCY' => 'NGN'
                        ],
                        'REQUEST_REFERENCE' => [
                            '@REFERENCE-NO' => $responseBody['ConsumerSearchResultResponse']['REFERENCENO'],
                            'MERGE_REPORT' => [
                                '@PRIMARY-BUREAU-ID' => $responseBody['ConsumerSearchResultResponse']['BODY']['SEARCHRESULTLIST'][0]['BUREAUID'],
                                'BUREAU_ID' => collect($responseBody['ConsumerSearchResultResponse']['BODY']['SEARCHRESULTLIST'])->pluck('BUREAUID')->toArray()
                            ]
                        ]
                    ]
                ]),
                'UserName' => config('services.crc.username'),
                'Password' => config('services.crc.password')
            ]);

        $body = $response->json();

        if ($this->failedRequest($body)) {
            throw new CustomException('CRC data merging failed.', 503);
        }

        return $body;
    }

    /**
     * Check to know if the CRC request failed
     */
    public function failedRequest($responseBody)
    {
        return isset($responseBody['ErrorResponse']);
    }

    /**
     * Check to know if the CRC request returns no result
     */
    public function requestIsConsumerNoHit($responseBody)
    {
        return isset($responseBody['ConsumerNoHitResponse']);
    }

    /**
     * Check to know if the CRC request returns a result
     */
    public function requestIsConsumerHit($responseBody)
    {
        return isset($responseBody['ConsumerHitResponse']);
    }

    /**
     * Check to know if the CRC request returns a response that requires data merging
     */
    public function requestIsConsumerSearchResult($responseBody)
    {
        return isset($responseBody['ConsumerSearchResultResponse']);
    }

    /**
     * Check to know if the CRC check request should be made
     */
    public function shouldMakeRequest($customer, $daysToMakeCrcCheck)
    {
        if (!isset($customer->crc_check_last_requested_at)) {
            return true;
        }

        if (!isset($customer->crc)) {
            return true;
        }

        if (isset($customer->crc_check_last_requested_at) && isset($customer->crc) && $customer->crc_check_last_requested_at->addDays($daysToMakeCrcCheck) < now()) {
            return true;
        }

        return false;
    }

    /**
     * Update the necessary CRC record of the customer
     */
    public function saveRecord($customer, $responseBody, $setting)
    {
        $body = $responseBody['ConsumerHitResponse']['BODY'];
        $header = $responseBody['ConsumerHitResponse']['HEADER'];

        return DB::transaction(function () use ($body, $header, $customer, $setting) {
            $crc = $customer->crc()->updateOrCreate([], [
                'summary_of_performance' => $body['SummaryOfPerformance'],
                'bvn_report_detail' => $body['ReportDetailBVN'],
                'contact_history' => $body['ContactHistory'],
                'address_history' => $body['AddressHistory'],
                'classification_institution_type' => $body['ClassificationInsType'],
                'classification_product_type' => $body['ClassificationProdType'],
                'credit_score_details' => $body['CREDIT_SCORE_DETAILS'],
                'credit_facilities_summary' => [
                    'credit' => $body['CREDIT_NANO_SUMMARY'],
                    'mf_credit' => $body['MFCREDIT_NANO_SUMMARY'],
                    'mg_credit' => $body['MGCREDIT_NANO_SUMMARY']
                ],
                'profile_details' => $body['NANO_CONSUMER_PROFILE'],
                'header' => $header,
            ]);

            // Get the total number of delinquencies
            $numberOfDelinquencies = (
                (int) $crc->credit_facilities_summary['credit']['SUMMARY']['NO_OF_DELINQCREDITFACILITIES'] +
                (int) $crc->credit_facilities_summary['mf_credit']['SUMMARY']['NO_OF_DELINQCREDITFACILITIES'] +
                (int) $crc->credit_facilities_summary['mg_credit']['SUMMARY']['NO_OF_DELINQCREDITFACILITIES']
            );

            // Update the total number of delinquencies
            $crc->update([
                'total_delinquencies' => $numberOfDelinquencies
            ]);

            if ($this->passesCheckPerformance($crc, $setting)) {
                $crc->update([
                    'passes_recent_check' => 'YES',
                ]);
            } else {
                $crc->update([
                    'passes_recent_check' => 'NO',
                ]);
            }

            $customer->forceFill([
                'crc_check_last_requested_at' => now()
            ])->save();

            return $crc;
        });
    }

    /**
     * Perform CRC check based on application
     */
    public function passedCheckByApplication($crcReport, $setting)
    {
        // Update the CRC history of the customer
        $crcReport->crcHistory()->updateOrCreate([
            'date' => now()->format('Y-m-d')
        ]);

        return $this->passesCheckPerformance($crcReport, $setting);
    }

    /**
     * Perform the check to see if a customer passes CRC check
     */
    private function passesCheckPerformance($crc, $setting)
    {
        $useCrcCreditScoreCheck = $setting?->use_crc_credit_score_check ?? config('quickfund.use_crc_credit_score_check');
        $minimumBureauCreditScore = $setting?->minimum_credit_bureau_credit_score ?? config('quickfund.minimum_approved_credit_score');
        $maximumOutstandingLoansToQualify = $setting?->maximum_outstanding_loans_to_qualify ?? config('quickfund.maximum_outstanding_loans_to_qualify');

        // Check if CRC credit score check should be used
        if ($useCrcCreditScoreCheck) {
            if (
                isset($crc->credit_score_details) &&
                ((int) $crc->credit_score_details['CREDIT_SCORE_SUMMARY']['CREDIT_SCORE'] < $minimumBureauCreditScore)
            ) {
                return false;
            }
        }

        // Check to know if the customer passes CRC check
        return $maximumOutstandingLoansToQualify >= $crc->total_delinquencies;
    }


    public function reportCustomerLoans(Customer $customer): array
    {
        $results = [
            'customer_id' => $customer->id,
            'borrower' => null,
            'loans' => [],
        ];

        // 🔹 Borrower info (Individual)
        $borrowerPayload = $this->prepareBorrowerPayload($customer);
        $borrowerResponse = $this->reportIndividualBorrowersInformation($borrowerPayload);
        $results['borrower'] = $borrowerResponse;

        // 🔹 Credit info (Loans)
        $loanPayload = $this->prepareLoanPayload($customer);

        if (empty($loanPayload)) {
            Log::info("No new loans to report to CRC for customer {$customer->id}");
            $results['loans'][] = [
                'status' => 'skipped',
                'message' => "No new loans to report for customer {$customer->id}",
            ];
            return $results;
        }

        // 🔹 Submit loans in chunks
        collect($loanPayload)->chunk(20)->each(function ($chunk, $index) use ($customer, &$results) {
            $loanIds = collect($chunk)->pluck('loan_id');

            sleep(3);

            // Prepare payload without loan_id
            $cleanPayload = collect($chunk)->map(
                fn($item) => collect($item)->except(['loan_id'])->toArray()
            )->toArray();

            $response = $this->reportCreditInformation($cleanPayload);

            if (!$response || isset($response['error'])) {
                Log::error("CRC upload failed for customer {$customer->id}, chunk {$index}", [
                    'chunk_size' => $chunk->count(),
                    'response' => $response,
                ]);

                $results['loans'][] = [
                    'chunk' => $index,
                    'status' => 'failed',
                    'loan_ids' => $loanIds,
                    'response' => $response,
                ];
            } else {
                Loan::whereIn('id', $loanIds)->update([
                    'crc_reported_at' => now(),
                ]);

                Log::info("CRC upload success for customer {$customer->id}, chunk {$index}", [
                    'count' => $chunk->count(),
                    'response' => $response,
                ]);

                $results['loans'][] = [
                    'chunk' => $index,
                    'status' => 'success',
                    'loan_ids' => $loanIds,
                    'response' => $response,
                ];
            }
        });

        return $results;
    }


    /**
     * Prepare borrower payload for a single customer
     */
    public function prepareBorrowerPayload(Customer $customer): array
    {
        return [
            'CustomerID' => $customer->formatted_customer_id,
            'BranchCode' => $customer->branch_code ?? '01',
            'Surname' => $customer->last_name ?: 'UNKNOWN',
            'Firstname' => $customer->first_name ?: 'UNKNOWN',
            'Middlename' => $customer->middle_name ?? '',
            'DateofBirth' => $customer->date_of_birth
                ? $customer->date_of_birth->format('d/m/Y')
                : '01/01/1900',
            'NationalIdentityNumber' => $customer->nin ?? '',
            'DriversLicenseNo' => $customer->drivers_license ?? '',
            'BVNNo' => $customer->bvn ?? '22231267698',
            'PassportNo' => $customer->passport_no ?? '',
            'Gender' => !empty($customer->gender) ? ucfirst($customer->gender) : 'Female',
            'Nationality' => 'NIGERIA',
            'MobileNumber' => $customer->phone_number ?? '',
            'PrimaryAddressLine1' => $customer->address ?? 'UNKNOWN',
            'PrimaryAddressLine2' => $customer->address2 ?? '',
            'PrimarycityLGA' => $customer->city ?? 'UNKNOWN',
            'PrimaryState' => $customer->state ?? 'UNKNOWN',
            'PrimaryCountry' => 'NIGERIA',
            'EmailAddress' => $customer->email ?? '',
            'MaritalStatus' => $customer->marital_status ?? '',
            'EmploymentStatus' => $customer->employment_status ?? 'UE',
            'Occupation' => $customer->occupation ?? 'UNKNOWN',
            'BusinessCategory' => $customer->business_category ?? 'General',
            'BusinessSector' => $customer->business_sector ?? 'General',
            'BorrowerType' => 'I',
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
    }

    /**
     * Prepare loan payload for a single customer
     */
    public function prepareLoanPayload(Customer $customer): array
    {
        return $customer->loans()
            ->whereNull('crc_reported_at')
            ->get()
            ->map(function ($loan) use ($customer) {
                return [
                    'loan_id' => $loan->id,
                    'CustomerID' => $customer->formatted_customer_id,
                    'AccountNumber' => $loan->destination_account_number ?? '',
                    'AccountStatus' => $loan->is_closed ? 'Closed' : 'Open',
                    'AccountStatusDate' => now()->format('d/m/Y'),
                    'DateOfLoanDisbursement' => $loan->created_at ? $loan->created_at->format('d/m/Y') : '',
                    'CreditLimitAmount' => $loan->amount ?? 0,
                    'LoanAmountAvailed' => $loan->amount_payable ?? 0,
                    'OutstandingBalance' => $loan->amount_remaining ?? 0,
                    'Currency' => 'NGN',
                    'LoanType' => $loan->loan_type ?? 'Commercial Overdraft',
                    'MaturityDate' => $loan->due_date ? $loan->due_date->format('d/m/Y') : '',
                    'LoanClassification' => $loan->classification ?? 'Performing',
                    'InstalmentAmount' => $loan->installment_amount ?? '',
                    'DaysInArrears' => $loan->days_in_arrears ?? '0',
                    'OverdueAmount' => $loan->overdue_amount ?? '0',
                    'LoanTenor' => $loan->tenor ?? '',
                    'RepaymentFrequency' => $loan->repayment_frequency ?? '',
                    'LastPaymentDate' => $loan->last_payment_date ? $loan->last_payment_date->format('d/m/Y') : '',
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
            })->toArray();
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
                    'payload' => json_encode($payload),
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
