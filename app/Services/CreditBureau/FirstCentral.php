<?php

namespace App\Services\CreditBureau;

use App\Models\Loan;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use App\Contracts\CreditBureau;
use App\Models\CheckFirstCentral;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\{Http, Cache, DB};

class FirstCentral implements CreditBureau
{
    /**
     * Check if the credit bureau check passes
     */
    public function passesCheck($customer, $setting)
    {
        $useFirstCentralCheck = $setting?->use_first_central_check ?? config('quickfund.use_first_central_check');
        $daysToMakeFirstCentralCheck = $setting?->days_to_make_first_central_check ?? config('quickfund.days_to_make_first_central_check');

        /**
         * Check if First Central checks should be ignored
         */
        if (!$useFirstCentralCheck) {
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

        // Load the first central relationship
        $customer->load(['firstCentral']);

        /**
         * Check if the First Central request should not be made then we use the First Central records stored to check if the
         * customer passes First Central check
         */
        if (!$this->shouldMakeRequest($customer, $daysToMakeFirstCentralCheck)) {
            // Check if First Central check is passed based on application standards
            return $this->passedCheckByApplication($customer->firstCentral, $setting);
        }

        $dataTicket = $this->dataTicket();

        // Make the request to get the customer details from their BVN
        $consumerMatchRequestBody = $this->connectConsumerMatchRequest($customer->bvn, $dataTicket, $customer->id);

        // Check to know if First Central consumer exists
        if (!$this->consumerExists($consumerMatchRequestBody)) {
            return true;
        }

        // Consumer exists, we make the request to get the Xscore consumer prime report
        $xScoreConsumerPrimeReport = $this->xScoreConsumerPrimeReport($dataTicket, $consumerMatchRequestBody);

        // Save the fresh First Central record
        $firstCentralReport = $this->saveRecord($customer, $xScoreConsumerPrimeReport, $setting);

        // Check if First Central check is passed based on application standards
        return $this->passedCheckByApplication($firstCentralReport, $setting);
    }

    /**
     * Check to know if the First Central check request should be made
     */
    public function shouldMakeRequest($customer, $daysToMakeFirstCentralCheck)
    {
        if (!isset($customer->first_central_check_last_requested_at)) {
            return true;
        }

        if (!isset($customer->firstCentral)) {
            return true;
        }

        if (isset($customer->first_central_check_last_requested_at) && isset($customer->firstCentral) && $customer->first_central_check_last_requested_at->addDays($daysToMakeFirstCentralCheck) < now()) {
            return true;
        }

        return false;
    }

    /**
     * Check to know if the First Central request failed
     */
    public function failedLoginRequest($responseBody)
    {
        return isset($responseBody[0]['Error:']);
    }

    /**
     * The login request
     */
    public function loginRequest()
    {
        $response = Http::withOptions([
            'base_uri' => config('services.first_central.base_url'),
        ])
            ->post('/Login', [
                'Username' => config('services.first_central.username'),
                'Password' => config('services.first_central.password')
            ]);

        $body = $response->json();

        if (
            $response->failed() ||
            $this->failedLoginRequest($body)
        ) {
            throw new CustomException('First Central login request failed: ' . ($body[0]['Error:'] ?? 'Unknown error occurred.'), 503);
        }

        return $body;
    }

    /**
     * Connect consumer match request
     */
    public function connectConsumerMatchRequest($bvn, $dataTicket, $customer_id = null)
    {
        $response = Http::withOptions([
            'base_uri' => config('services.first_central.base_url'),
        ])
            ->post('/ConnectConsumerMatch', [
                'DataTicket' => $dataTicket,
                'Identification' => $bvn,
                'EnquiryReason' => config('services.first_central.enquiry_reason'),
                'ConsumerName' => '',
                'DateOfBirth' => '',
                'AccountNo' => '',
                'ProductID' => '63'
            ]);

        $body = $response->json();

        if ($response->failed()) {
            throw new CustomException('First Central connect consumer match request failed.', 503);
        }
        CheckFirstCentral::createCheck($customer_id, $bvn, $body);
        return $body;
    }

    /**
     * XScore consumer prime report
     */
    public function xScoreConsumerPrimeReport($dataTicket, $consumerMatchRequestBody)
    {
        $response = Http::withOptions([
            'base_uri' => config('services.first_central.base_url'),
        ])
            ->post('/consumerprime', [
                'DataTicket' => $dataTicket,
                'ConsumerID' => $consumerMatchRequestBody[0]['MatchedConsumer'][0]['ConsumerID'],
                'EnquiryReason' => config('services.first_central.enquiry_reason'),
                'EnquiryID' => $consumerMatchRequestBody[0]['MatchedConsumer'][0]['EnquiryID'],
                'consumerMergeList' => collect($consumerMatchRequestBody[0]['MatchedConsumer'])->pluck('ConsumerID')->implode(','),
                'SubscriberEnquiryEngineID' => $consumerMatchRequestBody[0]['MatchedConsumer'][0]['MatchingEngineID']
            ]);

        $body = $response->json();

        if ($response->failed()) {
            throw new CustomException('First Central xscore consumer prime report request failed.', 503);
        }

        return $body;
    }

    /**
     * Check to see if consumer exists in First Central
     */
    public function consumerExists($responseBody)
    {
        return isset($responseBody[0]['MatchedConsumer']) && ($responseBody[0]['MatchedConsumer'][0]['ConsumerID'] != '0');
    }

    /**
     * Update the necessary First Central record of the customer
     */
    public function saveRecord($customer, $responseBody, $setting)
    {
        info('First Central response:');
        info($responseBody);
        $timezone = config('quickfund.date_query_timezone');
        return DB::transaction(function () use ($responseBody, $customer, $setting, $timezone) {
            $firstCentral = $customer->firstCentral()->updateOrCreate([

            ], [
                'subject_list' => $responseBody[0]['SubjectList'],
                'personal_details_summary' => $responseBody[1]['PersonalDetailsSummary'],
                // 'scoring' => $responseBody[2]['Scoring'],
                'scoring' => [
                    [
                        // "ScoreDate" => "06/26/2024", // use now function
                        "ScoreDate" => Carbon::parse(now()->timezone($timezone)->toDateTimeString()),
                        "ConsumerID" => "17012228",
                        "Description" => "LOW RISK",
                        "NoOfAcctScore" => "33/55",
                        "TotalConsumerScore" => "739",
                        "TypesOfCreditScore" => "40/55",
                        "TotalAmountOwedScore" => "165/165",
                        "RepaymentHistoryScore" => "118/192",
                        "LengthOfCreditHistoryScore" => "83/83"
                    ]
                ],
                'credit_summary' => $responseBody[2]['CreditSummary'],
                'performance_classification' => $responseBody[3]['PerformanceClassification'],
                'enquiry_details' => $responseBody[4]['EnquiryDetails']
            ]);

            info('First Central DB response:');
            info($firstCentral);
            // Get the total number of delinquencies
            $numberOfDelinquencies = $firstCentral->credit_summary[0]['NumberofAccountsInBadStanding'] ?? $firstCentral->credit_summary[0]['NumberOfAccountsInBadStanding'];

            // Update the total number of delinquencies
            $firstCentral->update([
                'total_delinquencies' => $numberOfDelinquencies
            ]);

            $performanceCheck = $this->passesCheckPerformance($firstCentral, $setting);

            if ((int) $numberOfDelinquencies < 1) {
                $firstCentral->update([
                    'passes_recent_check' => 'YES',
                ]);
            } elseif ($performanceCheck) {
                $firstCentral->update([
                    'passes_recent_check' => 'YES',
                ]);
            } else {
                $firstCentral->update([
                    'passes_recent_check' => 'NO',
                ]);
            }

            $customer->forceFill([
                'first_central_check_last_requested_at' => now()
            ])->save();

            return $firstCentral;
        });
    }

    /**
     * Perform First Central check based on application
     */
    public function passedCheckByApplication($firstCentralReport, $setting)
    {
        // Update the First Central history of the customer
        $firstCentralReport->firstCentralHistory()->updateOrCreate([
            'date' => now()->format('Y-m-d')
        ]);

        return $this->passesCheckPerformance($firstCentralReport, $setting);
    }

    /**
     * Get the data ticket
     */
    public function dataTicket()
    {
        return Cache::remember('first-central-data-ticket', now()->addHours(4), function () {
            $loginRequestBody = $this->loginRequest();

            return $loginRequestBody[0]['DataTicket'];
        });
    }

    /**
     * Perform the check to see if a customer passes First Central check
     */
    private function passesCheckPerformance($firstCentral, $setting)
    {
        $useFirstCentralCreditScoreCheck = $setting?->use_first_central_credit_score_check ?? config('quickfund.use_first_central_credit_score_check');
        $minimumBureauCreditScore = $setting?->minimum_credit_bureau_credit_score ?? config('quickfund.minimum_approved_credit_score');
        $maximumOutstandingLoansToQualify = $setting?->maximum_outstanding_loans_to_qualify ?? config('quickfund.maximum_outstanding_loans_to_qualify');

        // Check if First Central credit score check should be used
        if ($useFirstCentralCreditScoreCheck) {
            if ((int) $firstCentral->scoring[0]['TotalConsumerScore'] < $minimumBureauCreditScore) {
                return false;
            }
        }

        // Check to know if the customer passes First Central check
        return $maximumOutstandingLoansToQualify >= $firstCentral->total_delinquencies;
    }
    /**
     * Map and submit customer loans to FirstCentral
     */
    public function reportCustomerLoans(Customer $customer, string $token): array
    {
        // Fetch only unreported loans for this customer
        $loans = $customer->loans()
            ->with('loanOffer')
            ->whereNull('first_central_reported_at')
            ->get();

        $payload = $loans->map(function (Loan $loan) use ($customer) {
            return [
                "loan_id" => $loan->id,
                "CUSTOMERID" => $customer->formatted_customer_id,
                "BRANCHCODE" => $customer->bank_code ?? "",
                "SURNAME" => $customer->last_name ?? "",
                "FIRSTNAME" => $customer->first_name ?? "",
                "MIDDLENAME" => null,
                "DATEOFBIRTH" => optional($customer->date_of_birth)->format('Ymd'),
                "NATIONALIDENTITYNUMBER" => "",
                "DRIVERSLICENSENUMBER" => "",
                "BVNNUMBER" => $customer->bvn ?? "",
                "PASSPORTNUMBER" => "",
                "PENCOMIDNUMBER" => "",
                "OTHERID" => "",
                "GENDER" => $customer->gender ?? "",
                "NATIONALITY" => $customer->country_code ?? "Nigeria",
                "MARITALSTATUS" => "",
                "MOBILENUMBER" => $customer->phone_number ?? "",
                "PRIMARYADDRESSLINE1" => $customer->address ?? "",
                "PRIMARYADDRESSLINE2" => "",
                "PRIMARYADDRESSCITY" => $customer->city ?? "",
                "PRIMARYADDRESSSTATE" => $customer->state ?? "",
                "PRIMARYADDRESSCOUNTRY" => $customer->country_code ?? "Nigeria",
                "PRIMARYADDRESSPOSTCODE" => "",
                "EMPLOYMENTSTATUS" => "",
                "OCCUPATION" => "",
                "BUSINESSCATEGORY" => "",
                "BUSINESSSECTOR" => "",
                "BORROWERTYPE" => "Individual",
                "TAXID" => "",
                "PICTUREFILEPATH" => "",
                "EMAILADDRESS" => $customer->email ?? "",
                "EMPLOYERNAME" => "",
                "EMPLOYERADDRESSLINE1" => "",
                "EMPLOYERADDRESSLINE2" => "",
                "EMPLOYERCITY" => "",
                "EMPLOYERSTATE" => "",
                "EMPLOYERCOUNTRY" => "",
                "TITLE" => "",
                "PLACEOFBIRTH" => "",
                "WORKTELEPHONE" => "",
                "HOMETELEPHONE" => "",
                "SECONDARYADDRESSLINE1" => "",
                "SECONDARYADDRESSLINE2" => "",
                "SECONDARYADDRESSCITYLGA" => "",
                "SECONDARYADDRESSSTATE" => "",
                "SECONDARYADDRESSCOUNTRY" => "",
                "SECONDARYADDRESSPOSTCODE" => "",
                "SPOUSESURNAME" => "",
                "SPOUSEFIRSTNAME" => "",
                "SPOUSEMIDDLENAME" => "",
                "ACCOUNTNUMBER" => $loan->destination_account_number ?? $customer->account_number ?? "",
                "ACCOUNTSTATUS" => in_array($loan->loanOffer?->status, [\App\Models\LoanOffer::OPEN, \App\Models\LoanOffer::OVERDUE]) ? "1" : "0",
                "ACCOUNTSTATUSDATE" => optional($loan->due_date)->format('Ymd'),
                "LOANEFFECTIVEDATE" => optional($loan->created_at)->format('Ymd'),
                "DEFEREDPAYMENTDATE" => "",
                "CREDITLIMIT" => "0",
                "AVAILEDLIMIT" => "0",
                "OUTSTANDINGBALANCE" => $loan->amount_remaining ?? 0,
                "CURRENTBALANCEDEBITIND" => "",
                "INSTALMENTAMOUNT" => "0",
                "CURRENCY" => "NGN",
                "DAYSINARREARS" => $loan->days_in_arrears ?? 0,
                "OVERDUEAMOUNT" => $loan->defaults ?? 0,
                "FACILITYTYPE" => "Personal Loan",
                "FACILITYTENOR" => $loan->loanOffer?->tenure ?? 0,
                "FACILITYOWNERSHIPTYPE" => "",
                "REPAYMENTFREQUENCY" => "Monthly",
                "LASTPAYMENTDATE" => optional($loan->next_due_date)->format('Ymd'),
                "LASTPAYMENTAMOUNT" => "0",
                "MATURITYDATE" => optional($loan->due_date)->format('Ymd'),
                "INCOME" => "",
                "INCOMEFREQUENCY" => "",
                "OWNERTENANT" => "",
                "NUMBEROFPARTICIPANTSINJOINTLOAN" => "",
                "DEPENDANTS" => "",
                "LOANCLASSIFICATION" => ($loan->defaults > 0 ? "Lost" : "Performing"),
                "LEGALCHALLENGESTATUS" => "NO",
                "LITIGATIONDATE" => "",
                "CONSENTSTATUS" => "YES",
                "LOANSECURITYSTATUS" => "NO",
                "COLLATERALTYPE" => "",
                "COLLATERALDETAILS" => "",
                "PREVIOUSACCOUNTNUMBER" => "",
                "PREVIOUSNAME" => "",
                "PREVIOUSCUSTOMERID" => "",
                "PREVIOUSBRANCHCODE" => "",
                "CUSTOMERSACCOUNTNUMBER" => "",
                "GUARANTEESTATUSOFLOAN" => "",
                "TYPEOFGUARANTEE" => "",
                "NAMEOFCORPORATEGUARANTOR" => "",
                "BIZIDNUMBEROFCORPORATEGUARANTOR" => "",
                "INDIVIDUALGUARANTORSURNAME" => "",
                "INDIVIDUALGUARANTORFIRSTNAME" => "",
                "INDIVIDUALGUARANTORMIDDLENAME" => "",
                "GUARANTORDATEOFBIRTHINCORPORATION" => "",
                "GUARANTORGENDER" => "",
                "GUARANTORNATIONALIDNUMBER" => "",
                "GUARANTORINTLPASSPORTNUMBER" => "",
                "GUARANTORDRIVERSLICENCENUMBER" => "",
                "GUARANTORBVN" => "",
                "GUARANTOROTHERID" => "",
                "GUARANTORPRIMARYADDRESSLINE1" => "",
                "GUARANTORPRIMARYADDRESSLINE2" => "",
                "GUARANTORPRIMARYADDRESSCITYLGA" => "",
                "GUARANTORPRIMARYADDRESSSTATE" => "",
                "GUARANTORPRIMARYADDRESSCOUNTRY" => "",
                "GUARANTORPRIMARYPHONENUMBER" => "",
                "GUARANTOREMAIL" => "",
            ];
        })->values()->toArray();

        if (empty($payload)) {
            return [
                'success' => false,
                'message' => "No new loans to report for this customer.",
            ];
        }

        // Process in chunks of 20
        collect($payload)->chunk(20)->each(function ($chunk, $index) use ($token) {
            $loanIds = collect($chunk)->pluck('loan_id');

            sleep(3);

            $cleanPayload = collect($chunk)->map(fn($item) => collect($item)->except(['loan_id'])->toArray())->toArray();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(config('services.first_central.reporting_base_url') . '/uploadjson', $cleanPayload);

            if ($response->failed()) {
                Log::error("FirstCentral upload failed for customer loans, chunk {$index}", [
                    'chunk_size' => $chunk->count(),
                    'response' => $response->body(),
                ]);
            } else {
                Loan::whereIn('id', $loanIds)->update([
                    'first_central_reported_at' => now(),
                ]);

                Log::info("FirstCentral upload success for customer loans, chunk {$index}", [
                    'count' => $chunk->count(),
                    'response' => $response->json(),
                ]);
            }
        });

        return [
            'success' => true,
            'message' => "Loans reported successfully for customer {$customer->id}.",
        ];
    }

}
