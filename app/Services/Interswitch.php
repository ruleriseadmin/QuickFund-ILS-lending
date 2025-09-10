<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Http, Cache};
use App\Models\Customer;
use App\Exceptions\Interswitch\CustomException as InterswitchCustomException;
use App\Exceptions\CustomException as ApplicationCustomException;

class Interswitch
{
    /**
     * Authenticate with the client API
     */
    public function auth($inApp = false)
    {
        return Cache::remember('interswitch-auth', config('services.interswitch.oauth_token_expiration'), function () use ($inApp) {
            $response = Http::acceptJson()
                ->asForm()
                ->withBasicAuth(config('services.interswitch.client_id'), config('services.interswitch.client_secret'))
                ->post(config('services.interswitch.oauth_token_url'), [
                    'grant_type' => 'client_credentials',
                    'scope' => 'profile'
                ]);

            // The data gotten back from the interswitch API call
            $data = $response->json();

            // Abort the request if the interswitch API call fails
            if ($response->failed()) {
                $message = 'Failed to authenticate with Interswitch API: ' . $data['description'] ?? 'Unknown error occurred, Please try again later.';
                $statusCode = $response->status();

                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException('5000', $message, $statusCode);
            }

            return $data;
        });
    }

    /**
     * Get a customer
     */
    public function customer($customerId, $inApp = false)
    {
        $auth = $this->auth($inApp);

        // Get the customer information
        $response = Http::timeout(60)->retry(3, 1500)->acceptJson()
            ->withToken($auth['access_token'])
            ->get(config('services.interswitch.customer_info_url'), [
                'msisdn' => $customerId,
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to fetch customer details: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            if ($inApp) {
                throw new ApplicationCustomException($message, $statusCode);
            }

            throw new InterswitchCustomException($code, $message, $statusCode);
        }

        return $data;
    }

    /**
     * Get the credit score of a customer
     */
    public function creditScore($customerId, $inApp = false)
    {
        $auth = $this->auth($inApp);

        $response = Http::acceptJson()
            ->withToken($auth['access_token'])
            ->get(config('services.interswitch.customer_credit_score_url'), [
                'msisdn' => $customerId,
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to fetch credit score: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            if ($inApp) {
                throw new ApplicationCustomException($message, $statusCode);
            }

            throw new InterswitchCustomException($code, $message, $statusCode);
        }

        return $data;
    }

    /**
     * Get the credit score history of a customer
     */
    public function creditScoreHistory($customerId, $inApp = false)
    {
        $auth = $this->auth($inApp);

        $response = Http::acceptJson()
            ->withToken($auth['access_token'])
            ->get(config('services.interswitch.customer_credit_score_url') . '/history', [
                'msisdn' => $customerId,
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to fetch credit score history: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            if ($inApp) {
                throw new ApplicationCustomException($message, $statusCode);
            }

            throw new InterswitchCustomException($code, $message, $statusCode);
        }

        return $data;
    }

    /**
     * Send an SMS to a customer
     */
    public function sendSms($message, $customerId, $loanId, $inApp = false, $shouldSkip = false)
    {
        $auth = $this->auth($inApp);

        $response = Http::acceptJson()
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->withToken($auth['access_token'])
            ->post('/sms', [
                'message' => $message,
                'customerId' => $customerId,
                'loanId' => $loanId,
                'providerCode' => config('services.interswitch.provider_code')
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        info('Send SMS response below');
        info($data);
        info('Send SMS response code');
        info($response->status());

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to send SMS: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            /**
             * Due to fact that sending SMS can be is a major functionality, we know that it can be looped
             * through.  We use a should skip so as not to throw an exception when a sending SMS fails for a particular
             * iteration
             */
            if ($shouldSkip === false) {
                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException($code, $message, $statusCode);
            }
        }

        return $data;
    }

    /**
     * Initiate credit on a loan
     */
    public function credit($customerId, $loanOffer, $transactionId, $inApp = false, $shouldSkip = false)
    {
        $auth = $this->auth($inApp);

        info('Credit transaction ID: ' . $transactionId);

        $response = Http::withToken($auth['access_token'])
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->post("loans/{$loanOffer->id}/fund", [
                'customerId' => $customerId,
                'providerCode' => config('services.interswitch.provider_code'),
                'amount' => $loanOffer->amount,
                'currencyCode' => $loanOffer->currency,
                'transactionId' => config('services.interswitch.transaction_prefix') . (string) $transactionId,
                'qtTerminalId' => config('services.interswitch.quickteller_terminal_id'),
                'loanReferenceId' => $loanOffer->loan->reference_id
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        info('Credit response below');
        info($data);
        info('Credit response code below');
        info($response->status());
        info('Credit response body below');
        info($response->body());

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->creditFailedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to credit customer: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            /**
             * Due to fact that credit can be is a major functionality, we know that it can be looped through.
             * We use a should skip so as not to throw an exception when a credit fails for a particular
             * iteration
             */
            if ($shouldSkip === false) {
                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException($code, $message, $statusCode);
            }
        }

        return $data;
    }

    /**
     * Initiate debit on a loan
     */
    public function debit($amount, $customerId, $loanId, $transactionId, $inApp = false, $shouldSkip = false, $shouldTakeAvailableBalance = true)
    {
        $auth = $this->auth($inApp);

        info('Debit transaction ID: ' . $transactionId);

        $response = Http::withToken($auth['access_token'])
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->post("loans/{$loanId}/debit", [
                'customerId' => $customerId,
                'amount' => $amount,
                'providerCode' => config('services.interswitch.provider_code'),
                'transactionId' => config('services.interswitch.transaction_prefix') . (string) $transactionId,
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        info('Debit response below');
        info($data);
        info('Debit response code below');
        info($response->status());
        info('Debit response body below');
        info($response->body());

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            /**
             * When debit fails, and the balance of the customer is returned. We try to initiate debit on the
             * customer based on their account balance that is returned. We also make sure that they have a
             * balance of more that NGN1,000 and we collect the amount that we can collect while making sure that
             * they are left with at least NGN1,000
             */
            if ($shouldTakeAvailableBalance === true) {
                // Check if account balance is returned and the customer has more that NGN1,000
                if (isset($data['accountBalance']) && ($data['accountBalance'] > 100000)) {
                    $amountToDeduct = $data['accountBalance'] - 100000;

                    /**
                     * Check if the amount to deduct is greater that the actual amount that the customer is
                     * owing. Then we deduct the main amount. This is added to make sure that we do not over
                     * debit a customer.
                     */
                    if ($amountToDeduct > $amount) {
                        $amountToDeduct = $amount;
                    }

                    /**
                     * We merge the data so as to be able to know when to initiate a debit on a customer based on
                     * their current account balance. We will use the deductable amount key so as to know when
                     * to initiate debit again the second time
                     */
                    $data['deductableAmount'] = $amountToDeduct;
                }
            }

            $message = 'Failed to debit customer: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            /**
             * Due to fact that debit can be is a major functionality, we know that it can be looped through.
             * We use a should skip so as not to throw an exception when a debit fails for a particular
             * iteration
             */
            if ($shouldSkip === false) {
                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException($code, $message, $statusCode);
            }
        }

        return $data;
    }

    /**
     * Initiate refund on a loan
     */
    public function refund($amount, $customerId, $loanId, $transactionId, $inApp = false, $shouldSkip = false)
    {
        $auth = $this->auth($inApp);

        info('Refund transaction ID: ' . $transactionId);

        $response = Http::acceptJson()
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->withToken($auth['access_token'])
            ->post('/payments/refund', [
                'customerId' => $customerId,
                'loanId' => $loanId,
                'amount' => $amount,
                'providerCode' => config('services.interswitch.provider_code'),
                'transactionId' => config('services.interswitch.transaction_prefix') . (string) $transactionId
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        info('Refund response below');
        info($data);
        info('Refund response code below');
        info($response->status());
        info('Refund response body below');
        info($response->body());

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to refund customer: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            /**
             * Due to fact that refund can be is a major functionality, we know that it can be looped through.
             * We use a should skip so as not to throw an exception when a refund fails for a particular
             * iteration
             */
            if ($shouldSkip === false) {
                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException($code, $message, $statusCode);
            }
        }

        return $data;
    }

    /**
     * Query a transaction
     */
    public function query($transactionId, $inApp = false, $shouldSkip = false)
    {
        $auth = $this->auth($inApp);

        info('Query transaction ID: ' . $transactionId);

        $response = Http::acceptJson()
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->withToken($auth['access_token'])
            ->get("/payments/query", [
                'transactionId' => config('services.interswitch.transaction_prefix') . (string) $transactionId,
                'providerCode' => config('services.interswitch.provider_code')
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        info('Query transaction response below');
        info($data);
        info('Query transaction response code');
        info($response->status());

        // Abort the request if the interswitch API call fails
        if ($response->failed()) {
            $message = 'Failed to query transaction: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            /**
             * Due to fact that debit can be is a major functionality, we know that it can be looped through.
             * We use a should skip so as not to throw an exception when a debit fails for a particular
             * iteration
             */
            if ($shouldSkip === false) {
                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException($code, $message, $statusCode);
            }
        }

        return $data;
    }

    /**
     * Get the virtual account of a customer
     */
    public function virtualAccount($customer, $inApp = false, $shouldSkip = false)
    {
        $auth = $this->auth($inApp);

        $response = Http::acceptJson()
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->withToken($auth['access_token'])
            ->post('/payments/customer-virtual-account', [
                'customerId' => $customer->phone_number,
                'customerName' => (string) "{$customer?->first_name} {$customer?->last_name}",
                'providerCode' => config('services.interswitch.provider_code'),
                'currencyCode' => config('services.interswitch.default_currency_code')
            ]);

        // The data gotten back from the interswitch API call
        $data = $response->json();

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to fetch customer virtual account: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            /**
             * Due to fact that credit can be is a major functionality, we know that it can be looped through.
             * We use a should skip so as not to throw an exception when a credit fails for a particular
             * iteration
             */
            if ($shouldSkip === false) {
                if ($inApp) {
                    throw new ApplicationCustomException($message, $statusCode);
                }

                throw new InterswitchCustomException($code, $message, $statusCode);
            }
        }

        return $data;
    }

    /**
     * Resolve bank details from bank code and account number
     */
    public function accountResolution($accountNumber, $bankCode, $inApp = false)
    {
        $auth = $this->auth($inApp);

        $response = Http::withOptions([
            'base_uri' => config('services.interswitch.name_enquiry_base_url'),
        ])
            ->acceptJson()
            ->asJson()
            ->withToken($auth['access_token'])
            ->get("/inquiry/bank-code/{$bankCode}/account/{$accountNumber}");

        // The data gotten back from the interswitch API call
        $data = $response->json();

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to resolve account details: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            if ($inApp) {
                throw new ApplicationCustomException($message, $statusCode);
            }

            throw new InterswitchCustomException($code, $message, $statusCode);
        }

        return $data;
    }

    /**
     * Update the status of a loan
     */
    public function status($status, $loanId, $inApp = false)
    {
        $auth = $this->auth($inApp);

        info('Loan ID for status: ' . $loanId);

        // Make the request to update the loan status
        $response = Http::acceptJson()
            ->withOptions([
                'base_uri' => config('services.interswitch.base_url'),
            ])
            ->withToken($auth['access_token'])
            ->put("/loans/{$loanId}/update", [
                'status' => $status,
                'providerCode' => config('services.interswitch.provider_code')
            ]);

        $data = $response->json();

        info('Loan status response below');
        info($data);
        info('Loan status update response status below');
        info($response->status());

        // Abort the request if the interswitch API call fails
        if (
            $response->failed() ||
            $this->failedBasedOnResponseBody($data)
        ) {
            $message = 'Failed to update loan status: ' . $this->failureResponse($data);
            $code = $this->failureCode($data);
            $statusCode = 503;

            if ($inApp) {
                throw new ApplicationCustomException($message, $statusCode);
            }

            throw new InterswitchCustomException($code, $message, $statusCode);
        }

        return $data;
    }

    /**
     * Check if the request failed based on the response body
     */
    private function failedBasedOnResponseBody($body)
    {
        return isset($body['responseCode']) && $body['responseCode'] != '00';
    }

    /**
     * Check if the credit response failed based on the response body
     */
    private function creditFailedBasedOnResponseBody($body)
    {
        return !isset($body['responseCode']) || (isset($body['responseCode']) && $body['responseCode'] != '00');
    }

    /**
     * Get the failure response message based on the request body
     */
    private function failureResponse($body)
    {
        return $body['responseMessage'] ?? 'Unknown error occurred.';
    }

    /**
     * Get the failure response code based on the request body
     */
    private function failureCode($body)
    {
        return $body['responseCode'] ?? '104';
    }
}
