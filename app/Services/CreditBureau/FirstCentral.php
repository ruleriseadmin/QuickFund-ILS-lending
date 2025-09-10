<?php

namespace App\Services\CreditBureau;

use Illuminate\Support\Facades\{Http, Cache, DB};
use App\Contracts\CreditBureau;
use App\Exceptions\CustomException;
use Illuminate\Support\Carbon;

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
        $consumerMatchRequestBody = $this->connectConsumerMatchRequest($customer->bvn, $dataTicket);

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
    public function connectConsumerMatchRequest($bvn, $dataTicket)
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

            if ($this->passesCheckPerformance($firstCentral, $setting)) {
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
}
