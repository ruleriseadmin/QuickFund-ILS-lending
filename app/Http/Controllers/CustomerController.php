<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Requests\{SearchCustomerRequest, SearchCustomersBvnRequest, SearchCustomersNameRequest, StoreCustomerRequest, UpdateCustomerRequest};
use App\Models\{Customer, LoanOffer, Setting};
use App\Services\CreditBureau\{
    Crc as CrcService,
    FirstCentral as FirstCentralService
};
use App\Services\Interswitch as InterswitchService;
use App\Exceptions\CustomException as ApplicationCustomException;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $customers = Customer::latest()
            ->withCount([
                'loanOffers as total_loan_count' => fn($query) => $query->whereIn('status', [
                    LoanOffer::OPEN,
                    LoanOffer::CLOSED,
                    LoanOffer::OVERDUE
                ]),
                'loanOffers as default_loan_count' => fn($query) => $query->whereIn('status', [
                    LoanOffer::OPEN,
                    LoanOffer::CLOSED,
                    LoanOffer::OVERDUE
                ])
                    ->whereHas('loan', fn($query2) => $query2->where('defaults', '>', 0))
            ])
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $customers);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreCustomerRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCustomerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function show(Customer $customer)
    {
        $customer->loadCount([
            'loanOffers as total_loan_count' => fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::OVERDUE
            ]),
            'loanOffers as default_loan_count' => fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::OVERDUE
            ])
                ->whereHas('loan', fn($query2) => $query2->where('defaults', '>', 0))
        ]);

        return $this->sendSuccess(__('app.request_successful'), 200, $customer);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function edit(Customer $customer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCustomerRequest  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();

        $customer->update($data);

        return $this->sendSuccess('Customer updated successfully.', 200, $customer);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Customer $customer)
    {
        //
    }

    /**
     * Get the customers that have been granted loans
     */
    public function loaned(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $customers = Customer::latest()
            ->withCount([
                'loanOffers as total_loan_count' => fn($query) => $query->whereIn('status', [
                    LoanOffer::OPEN,
                    LoanOffer::CLOSED,
                    LoanOffer::OVERDUE
                ]),
                'loanOffers as default_loan_count' => fn($query) => $query->whereIn('status', [
                    LoanOffer::OPEN,
                    LoanOffer::CLOSED,
                    LoanOffer::OVERDUE
                ])
                    ->whereHas('loan', fn($query2) => $query2->where('defaults', '>', 0))
            ])
            ->whereHas('loanOffers', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::OVERDUE
            ]))
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $customers);
    }

    /**
     * Get the credit score of a customer
     */
    public function creditScore(Customer $customer)
    {
        $creditScore = $customer->creditScore()
            ->firstOr(function () use ($customer) {
                // Make the request to fetch the credit score
                $creditScoreDetails = app()->make(InterswitchService::class)->creditScore(
                    $customer->phone_number,
                    true
                );

                return $customer->creditScore()
                    ->updateOrCreate([

                    ], [
                        'score' => $creditScoreDetails['creditScores'][0]['score'],
                        'date' => Carbon::parse($creditScoreDetails['creditScores'][0]['dateCreated'])->format('Y-m-d')
                    ]);
            });

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'score' => $creditScore->score,
            'date_created' => $creditScore->date
        ]);
    }

    /**
     * Get the credit score history of a customer
     */
    public function creditScoreHistory(Customer $customer)
    {
        $creditScoreHistoryDetails = app()->make(InterswitchService::class)->creditScoreHistory(
            $customer->phone_number,
            true
        );

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'scores' => collect($creditScoreHistoryDetails['creditScores'])->map(fn($score) => [
                'score' => $score['score'],
                'date_created' => Carbon::parse($score['dateCreated'])->format('Y-m-d')
            ])
        ]);
    }

    /**
     * Search by BVN
     */
    public function searchByBvn(SearchCustomersBvnRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $customers = Customer::where('bvn', $data['q'])->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $customers);
    }

    /**
     * Search by name
     */
    public function searchByName(SearchCustomersNameRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $customers = Customer::where('first_name', 'LIKE', '%' . $data['q'] . '%')
            ->orWhere('last_name', 'LIKE', '%' . $data['q'] . '%')
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $customers);
    }

    /**
     * The loan offers of a customer
     */
    public function loanOffers(Request $request, Customer $customer)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $loanOffers = LoanOffer::with(['loan', 'customer'])
            ->where('customer_id', $customer->id)
            ->where('status', '!=', LoanOffer::NONE)
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $loanOffers);
    }

    /**
     * Get the virtual account of a customer
     */
    public function virtualAccounts(Request $request, Customer $customer)
    {
        $virtualAccount = $customer->virtualAccount()
            ->firstOr(function () use ($customer) {
                /**
                 * Make the request to fetch the virtual account details. We only create
                 * virtual accounts for customers that have both first name and last name
                 */
                if (
                    !isset($customer->first_name) ||
                    !isset($customer->last_name)
                ) {
                    throw new ApplicationCustomException('The customer must have both first name and last name.', 403);
                }

                $virtualAccountDetails = app()->make(InterswitchService::class)->virtualAccount(
                    $customer,
                    true
                );

                return $customer->virtualAccount()
                    ->updateOrCreate([

                    ], [
                        'payable_code' => $virtualAccountDetails['payableCode'],
                        'account_name' => $virtualAccountDetails['accountName'],
                        'account_number' => $virtualAccountDetails['accountNumber'],
                        'bank_name' => $virtualAccountDetails['bankName'],
                        'bank_code' => $virtualAccountDetails['bankCode']
                    ]);
            });

        return $this->sendSuccess(__('app.request_successful'), 200, $virtualAccount);
    }

    /**
     * The credit bureau data from CRC
     */
    public function crc(Customer $customer)
    {
        $crc = $customer->crc()
            ->with(['customer'])
            ->firstOr(function () use ($customer) {
                $setting = Setting::find(Setting::MAIN_ID);

                // Check to know if the customer has BVN
                if (!isset($customer->bvn)) {
                    throw new ApplicationCustomException('Cannot fetch CRC data: BVN of customer absent.', 403);
                }

                // Make the request to get the customer details from CRC
                $basicRequestBody = app()->make(CrcService::class)->basicRequest($customer->bvn);

                // Check to see if the CRC basic request failed
                if (app()->make(CrcService::class)->failedRequest($basicRequestBody)) {
                    throw new ApplicationCustomException('CRC check failed: ' . ($basicRequestBody['ErrorResponse']['BODY']['ERRORLIST'][0] ?? 'Unknown error occurred. Try again later.'), 503);
                }

                // Check to know if the CRC request returned a consumer no hit response
                if (app()->make(CrcService::class)->requestIsConsumerNoHit($basicRequestBody)) {
                    throw new ApplicationCustomException('CRC record of customer not found', 404);
                }

                // Check to know if the CRC request returned a consumer hit response
                if (app()->make(CrcService::class)->requestIsConsumerHit($basicRequestBody)) {
                    // Save the fresh CRC record
                    return app()->make(CrcService::class)->saveRecord($customer, $basicRequestBody, $setting);
                }

                // Check to know if the CRC request returned a consumer search result response that requires merging
                if (app()->make(CrcService::class)->requestIsConsumerSearchResult($basicRequestBody)) {
                    /**
                     * We perform a merge request to merge the data
                     */
                    $mergeRequestBody = app()->make(CrcService::class)->mergeRequest($basicRequestBody);

                    // Check to see if the CRC merge request failed
                    if (app()->make(CrcService::class)->failedRequest($mergeRequestBody)) {
                        throw new ApplicationCustomException('CRC data merging failed: ' . ($mergeRequestBody['ErrorResponse']['BODY']['ERRORLIST'][0] ?? 'Unknown error occurred. Try again later.'), 503);
                    }

                    // Save the fresh CRC record
                    return app()->make(CrcService::class)->saveRecord($customer, $mergeRequestBody, $setting);
                }

                // Fallback for something weird
                throw new ApplicationCustomException('Unknown error while fetching CRC record', 503);
            });

        return $this->sendSuccess(__('app.request_successful'), 200, $crc);
    }

    /**
     * The credit bureau data from First Central
     */
    public function firstCentral(Customer $customer)
    {
        $firstCentral = $customer->firstCentral()->firstOr(function () use ($customer) {
            $setting = Setting::find(Setting::MAIN_ID);

            info('setting');
            info($setting);

            // Check to know if the customer has BVN
            if (!isset($customer->bvn)) {
                throw new ApplicationCustomException('Cannot fetch First Central data: BVN of customer absent.', 403);
            }

            $dataTicket = app()->make(FirstCentralService::class)->dataTicket();

            // Make the request to get the customer details from their BVN
            $consumerMatchRequestBody = app()->make(FirstCentralService::class)->connectConsumerMatchRequest($customer->bvn, $dataTicket);

            // Check to know if First Central consumer exists
            if (!app()->make(FirstCentralService::class)->consumerExists($consumerMatchRequestBody)) {
                throw new ApplicationCustomException('First Central record of customer not found', 404);
            }

            // Consumer exists, we make the request to get the Xscore consumer prime report
            $xScoreConsumerPrimeReport = app()->make(FirstCentralService::class)->xScoreConsumerPrimeReport($dataTicket, $consumerMatchRequestBody);

            // Save the fresh First Central record
            return app()->make(FirstCentralService::class)->saveRecord($customer, $xScoreConsumerPrimeReport, $setting);
        });

        return $this->sendSuccess(__('app.request_successful'), 200, $firstCentral);
    }

    /**
     * Search for a customer
     */
    public function search(SearchCustomerRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $customers = Customer::when($data['name'] ?? null, fn($query, $value) => $query->where(fn($query2) => $query2->where('first_name', 'LIKE', "%{$value}%"))
            ->orWhere('last_name', 'LIKE', "%{$value}%"))
            ->when($data['first_name'] ?? null, fn($query, $value) => $query->where('first_name', 'LIKE', "%{$value}%"))
            ->when($data['last_name'] ?? null, fn($query, $value) => $query->where('last_name', 'LIKE', "%{$value}%"))
            ->when($data['bvn'] ?? null, fn($query, $value) => $query->where('bvn', 'LIKE', "%{$value}%"))
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $customers);
    }
}

