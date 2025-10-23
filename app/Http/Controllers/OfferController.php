<?php

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\{GetOffersRequest, StoreOfferRequest, UpdateOfferRequest, AcceptOfferRequest};
use App\Models\{Offer, LoanOffer, Customer, Setting};
use App\Http\Resources\LoanOfferResource;
use App\Exceptions\Interswitch\{
    AccountNumberBlockedException,
    CustomException as InterswitchCustomException,
    NoOfferException,
    CustomerIneligibleException,
    UnknownOfferException,
    OfferExpiredException,
    InternalCustomerNotFoundException,
    OutstandingLoanException,
    SystemErrorException
};
use App\Jobs\CustomerVirtualAccount;
use App\Jobs\Interswitch\CreditCustomer;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Services\{
    Application as ApplicationService,
    Interswitch as InterswitchService
};

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // 
    }

    /**
     * Get offers (Application)
     */
    public function application()
    {
        $offers = Offer::all();

        return $this->sendSuccess(__('app.request_successful'), 200, $offers);
    }

    /**
     * Get offers (Interswitch)
     */
    public function interswitch(GetOffersRequest $request)
    {
        $data = $request->validated();

        // Get the customer details
        $customerDetails = app()->make(InterswitchService::class)->customer($data['customerId']);

        /**
         * We get the details of the customer or we create it
         */
        $customer = Customer::firstOrCreate([
            'phone_number' => $customerDetails['msisdn'],
        ], [
            'first_name' => app()->make(ApplicationService::class)->nullify($customerDetails['firstName']),
            'last_name' => app()->make(ApplicationService::class)->nullify($customerDetails['lastName']),
            'email' => app()->make(ApplicationService::class)->nullify($customerDetails['email']),
            'hashed_phone_number' => app()->make(ApplicationService::class)->nullify($customerDetails['hashedMsisdn']),
            'encrypted_pan' => app()->make(ApplicationService::class)->nullify($customerDetails['encryptedPan']),
            'date_of_birth' => null,
            'bvn' => app()->make(ApplicationService::class)->nullify($customerDetails['bvn']),
            'address' => app()->make(ApplicationService::class)->nullify($customerDetails['address']),
            'city' => app()->make(ApplicationService::class)->nullify($customerDetails['addressCity']),
            'state' => app()->make(ApplicationService::class)->nullify($customerDetails['addressState']),
            'account_number' => app()->make(ApplicationService::class)->nullify($customerDetails['accountNumber']),
            'bank_code' => app()->make(ApplicationService::class)->nullify($customerDetails['bankCode']),
            'gender' => app()->make(ApplicationService::class)->nullify($customerDetails['gender']),
            'country_code' => app()->make(ApplicationService::class)->nullify($customerDetails['countryCode'])
        ]);

        // Get outstanding loans
        $outstandingLoans = $customer->loanOffers()
            ->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::OVERDUE
            ])
            ->get();

        // Load the loan relationship
        $outstandingLoans->load(['loan']);

        /**
         * Make a check to know if the user can borrow again based on their outstanding loans
         */
        if (!$outstandingLoans->isEmpty()) {
            throw new OutstandingLoanException($outstandingLoans);
        }

        // Get the offers
        $loanOffers = $customer->getOffers($data['amount'] ?? null, $data['channelCode'] ?? null);

        // Customer has offers, we return the offers
        return $this->sendInterswitchSuccessMessage(__('interswitch.success'), 200, [
            'offers' => LoanOfferResource::collection($loanOffers->sortByDesc('amount'))
        ]);
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
     * @param  \App\Http\Requests\StoreOfferRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreOfferRequest $request)
    {
        $data = $request->validated();

        /**
         * Check if an offer with the same details already exist
         */
        if (
            Offer::where([
                'amount' => $data['amount'],
                'tenure' => $data['tenure']
            ])->exists()
        ) {
            return $this->sendErrorMessage('An offer with this details already exists');
        }

        /**
         * Get the interest applied on loans
         */
        $setting = Setting::find(Setting::MAIN_ID);

        $interest = $setting?->loan_interest ?? config('quickfund.loan_interest');
        $defaultInterest = $setting?->default_interest ?? config('quickfund.default_interest');
        $defaultFeesAdditionDays = $setting?->days_to_attach_late_payment_fees ?? config('quickfund.days_to_attach_late_payment_fees');

        // Create the offer
        $offer = Offer::create(array_merge($data, [
            'currency' => config('services.interswitch.default_currency_code'),
            'interest' => $interest,
            'default_interest' => $defaultInterest,
            'default_fees_addition_days' => $defaultFeesAdditionDays
        ]));

        return $this->sendSuccess('Offer created successfully', 201, $offer);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Offer  $offer
     * @return \Illuminate\Http\Response
     */
    public function show(Offer $offer)
    {
        return $this->sendSuccess(__('app.request_successful'), 200, $offer);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Offer  $offer
     * @return \Illuminate\Http\Response
     */
    public function edit(Offer $offer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateOfferRequest  $request
     * @param  \App\Models\Offer  $offer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateOfferRequest $request, Offer $offer)
    {
        $data = $request->validated();

        /**
         * Check if an offer with the same details already exist but exclude this offer
         */
        if (
            Offer::where([
                'amount' => $data['amount'],
                'tenure' => $data['tenure']
            ])
                ->where('id', '!=', $offer->id)
                ->exists()
        ) {
            return $this->sendErrorMessage('An offer with this details already exists');
        }

        /**
         * Get the interest applied on loans
         */
        $setting = Setting::find(Setting::MAIN_ID);

        $interest = $setting?->loan_interest ?? config('quickfund.loan_interest');
        $defaultInterest = $setting?->default_interest ?? config('quickfund.default_interest');
        $defaultFeesAdditionDays = $setting?->days_to_attach_late_payment_fees ?? config('quickfund.days_to_attach_late_payment_fees');

        // Update the offer
        $offer->update(array_merge($data, [
            'currency' => config('services.interswitch.default_currency_code'),
            'interest' => $interest,
            'default_interest' => $defaultInterest,
            'fees' => $data['fees'] ?? null,
            'default_fees_addition_days' => $defaultFeesAdditionDays
        ]));

        return $this->sendSuccess('Offer updated successfully', 200, $offer);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Offer  $offer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Offer $offer)
    {
        $offer->delete();

        return $this->sendSuccess('Offer deleted successfully');
    }

    /**
     * Accept an offer
     */
    public function accept(AcceptOfferRequest $request)
    {
        $data = $request->validated();

        // Get the customer or throw a customer not found exception
        $customer = Customer::where('phone_number', $data['customerId'])
            ->firstOr(fn() => throw new InternalCustomerNotFoundException);

        $loanOffer = $customer->loanOffers()->findOr($data['offerId'], fn() => throw new UnknownOfferException);

        // dd($loanOffer);

        $loanOffer->status = LoanOffer::NONE;
        $loanOffer->save();

        // Check if the offer has expired
        if ($loanOffer->hasExpired()) {
            throw new OfferExpiredException;
        }

        // Get outstanding loans
        $outstandingLoans = $customer->loanOffers()
            ->where(fn($query) => $query->where('status', LoanOffer::OPEN)
                ->orWhere('status', LoanOffer::OVERDUE))
            ->get();

        /**
         * Make a check to know if the user can borrow again based on their outstanding loans
         */
        if (!$outstandingLoans->isEmpty()) {
            throw new OutstandingLoanException($outstandingLoans);
        }

        /**
         * Check if the loan offer is having a status that is not "NONE"
         */
        if ($loanOffer->status !== LoanOffer::NONE) {
            throw new InterswitchCustomException('104', __('interswitch.loan_unacceptable', [
                'loan_status' => $loanOffer->status,
                'expected_loan_status' => LoanOffer::NONE
            ]), 400);
        }

        // Make the account resolution request to get new customer details
        // $accountResolutionDetails = app()->make(InterswitchService::class)->accountResolution(
        //     $data['destinationAccountNumber'],
        //     $data['destinationBankCode']
        // );

        // // Update the details of the customer based on the details from the account resolution
        // $customer->update([
        //     'bvn' => !empty($accountResolutionDetails['bvn']) ? $accountResolutionDetails['bvn'] : $customer->bvn,
        //     'first_name' => !empty($accountResolutionDetails['firstName']) ? $accountResolutionDetails['firstName'] : $customer->first_name,
        //     'last_name' => !empty($accountResolutionDetails['lastName']) ? $accountResolutionDetails['lastName'] : $customer->last_name,
        //     'address' => !empty($accountResolutionDetails['residentialAddress']) ? $accountResolutionDetails['residentialAddress'] : $customer->address,
        // ]);

        /**
         * Check if the user has inputted an account number that is different from the one that was used when they
         * first collected a loan form interswitch
         */
        // Get the first closed loan the user collected
        $firstClosedLoan = $customer->loanOffers()
            ->with(['loan'])
            ->where('status', LoanOffer::CLOSED)
            ->first();

        // Check if they have a CLOSED loan
        if (isset($firstClosedLoan)) {
            /**
             * We check if the destination bank details is not the same as the bank details the customer supplied
             * when they took a loan for the first time
             */
            // if (($firstClosedLoan->loan->destination_account_number !== $data['destinationAccountNumber']) ||
            //     ($firstClosedLoan->loan->destination_bank_code !== $data['destinationBankCode'])) {
            //     throw new AccountNumberBlockedException;
            // }
        }


        /**
         * Perform the Credit Bureau checks on the customer
         */
        try {
            $customer->performCreditBureauChecks();

            // If we reach here, all checks passed
            // Log::info('Customer passed all credit bureau checks', [
            //     'customer_id' => $customer->id
            // ]);

        } catch (CustomerIneligibleException $e) {
            // Customer failed credit checks (business logic failure, not network issue)
            throw $e;
        } catch (\Throwable $e) {
            // Network or other system errors
            return $this->sendInterswitchCustomMessage('104', __('app.external_service_unavailable'), 503);
        }

        // Accept the offer
        try {
            DB::beginTransaction();

            /**
             * Create the record of the loan
             */
            $loan = $loanOffer->loan()->updateOrCreate([], [
                'amount' => $loanOffer->amount,
                'amount_payable' => app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->amount
                ])->totalPayable($loanOffer->interest)->getValue(),
                'amount_remaining' => app()->make(MoneyCalculator::class, [
                    'value' => $loanOffer->amount,
                    'fees' => $loanOffer->fees
                ])->totalPayable($loanOffer->interest)->getValue(),
                'destination_account_number' => $data['destinationAccountNumber'],
                'destination_bank_code' => $data['destinationBankCode'],
                'token' => $data['token'] ?? "",
                'reference_id' => $data['loanReferenceId'],
                'due_date' => now()->addDays($loanOffer->tenure + 1)
            ]);

            // Update the status of the loan in the application to "ACCEPTED"
            $loanOffer->forceFill([
                'status' => LoanOffer::ACCEPTED
            ])->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw new SystemErrorException('Failed to update loan status: ' . $e->getMessage());
        }

        /**
         * If the customer does not have a virtual account, we create the virtual account if all the necessary
         * checks are put in place
         */
        if (
            $customer->virtualAccount()->doesntExist() &&
            isset($customer->first_name) &&
            isset($customer->last_name)
        ) {
            // Create the virtual account for the customer
            CustomerVirtualAccount::dispatch($customer);
        }

        // Dispatch the job to credit the customer
        CreditCustomer::dispatch($loanOffer)
            ->delay(2);

        // Offer accepted successfully
        return $this->sendInterswitchSuccessMessage('Offer accepted successfully.', 200, [
            'loanId' => (string) $loanOffer->id,
            'approvedAmount' => $loanOffer->amount
        ]);
    }

}
