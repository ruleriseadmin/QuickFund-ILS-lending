<?php

namespace App\Services\CreditBureau;

use App\Contracts\CreditBureau;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\{Http, DB};

class Crc implements CreditBureau
{


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



}
