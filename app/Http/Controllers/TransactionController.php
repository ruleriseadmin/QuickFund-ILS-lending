<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Exceptions\Interswitch\{
    InternalCustomerNotFoundException,
    UncollectedLoanException,
    UnknownOfferException
};
use App\Http\Requests\{StoreTransactionRequest, UpdateTransactionRequest, TransactionNotificationRequest, SearchTransactionsRequest};
use App\Jobs\Interswitch\SendSms;
use App\Models\{CollectionCase, Transaction, Customer, LoanOffer};
use App\Services\Calculation\Money as MoneyCalculator;
use App\Services\Loans\Calculator as LoanCalculator;
use App\Services\Interswitch as InterswitchService;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $transactions = Transaction::with(['loan.loanOffer.customer'])
            ->latest()->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $transactions);
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
     * @param  \App\Http\Requests\StoreTransactionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreTransactionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['loan.loanOffer.customer']);

        return $this->sendSuccess(__('app.request_successful'), 200, $transaction);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function edit(Transaction $transaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateTransactionRequest  $request
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transaction $transaction)
    {
        //
    }

    /**
     * Payment notification on a transaction
     */
    public function notification(TransactionNotificationRequest $request)
    {
        $data = $request->validated();

        // Get the customer or throw a customer not found exception
        $customer = Customer::where('phone_number', $data['customerId'])
            ->firstOr(fn() => throw new InternalCustomerNotFoundException);

        // Get the loan offer and make sure that the customer is the owner of the loan
        $loanOffer = $customer->loanOffers()
            ->findOr($data['loanId'], fn() => throw new UnknownOfferException);

        // Load the loan relationship
        $loanOffer->load(['loan']);

        // // Check if there was a loan collected on this offer
        if (!isset($loanOffer->loan)) {
            throw new UncollectedLoanException;
        }

        /**
         * If the payment reference does not exist, we deduct the amount remaining from the 
         * amount paid. This is used since it acts like a webhook and could run multiple times so
         * we deduct the remaining amount only the first time
         */
        if (
            $loanOffer->loan->transactions()
                ->where('interswitch_payment_reference', $data['paymentRef'])
                ->doesntExist()
        ) {
            // Process the payment
            DB::transaction(function () use ($data, $loanOffer) {
                // Get the amount paid
                $amountPaid = $data['amount'];

                // Process the payment
                app()->make(LoanCalculator::class)->processPayment($loanOffer->loan, $amountPaid);

                // Create the record of the transaction
                $loanOffer->loan->transactions()
                    ->create([
                        'amount' => $data['amount'],
                        'interswitch_payment_reference' => $data['paymentRef'],
                        'type' => Transaction::PAYMENT
                    ]);
            });
        }

        /**
         * Check if the remaining amount is less than or equal to 0 then mark it as "CLOSED"
         * If the remaining amount is less than 0, We refund the excess
         */
        if (
            $loanOffer->loan->amount_remaining <= 0 &&
            $loanOffer->loan->penalty_remaining <= 0
        ) {
            // Process the closure of the loan
            DB::transaction(function () use ($loanOffer) {
                // Update the status of the loan to "CLOSED"
                $status = app()->make(InterswitchService::class)->status(LoanOffer::CLOSED, $loanOffer->id);

                // Close the loan in the database
                $loanOffer->forceFill([
                    'status' => LoanOffer::CLOSED
                ])->save();

                // Closed any associated collection case
                $loanOffer->collectionCase()
                    ->update([
                        'status' => CollectionCase::CLOSED
                    ]);
            });

            $higherDenominationAmount = app()->make(MoneyCalculator::class, [
                'value' => $loanOffer->loan->amount
            ])->toHigherDenomination()->getValue();

            $message = __('interswitch.loan_fully_collected_message', [
                'covered_amount' => config('quickfund.currency_representation') . number_format($higherDenominationAmount, 2),
                'loan_request_url' => config('quickfund.loan_request_url')
            ]);
        } else {
            $higherDenominationPaymentAmount = app()->make(MoneyCalculator::class, [
                'value' => $data['amount']
            ])->toHigherDenomination()->getValue();

            $higherDenominationRemainingAmount = app()->make(MoneyCalculator::class, [
                'value' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            $message = __('interswitch.loan_partially_collected_message', [
                'recovered_amount' => config('quickfund.currency_representation') . number_format($higherDenominationPaymentAmount, 2),
                'remaining_amount' => config('quickfund.currency_representation') . number_format($higherDenominationRemainingAmount, 2)
            ]);
        }

        /**
         * Send the customer an SMS for payment completion
         */
        SendSms::dispatch(
            $message,
            $customer->phone_number,
            $loanOffer->id
        );

        return $this->sendInterswitchSuccessMessage(__('interswitch.success'), 200, [
            'loanStatus' => $loanOffer->status
        ]);
    }

    /**
     * Query a transaction
     */
    public function query(Request $request, Transaction $transaction)
    {
        // Make the request to query a transaction
        $queryDetails = app()->make(InterswitchService::class)->query($transaction->id, true, true);

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'response_code' => $queryDetails['responseCode'] ?? null,
            'response_message' => $queryDetails['responseMessage'] ?? null,
            'transaction_reference' => $queryDetails['transactionRef'] ?? null,
            'transaction_id' => $queryDetails['transactionId'] ?? null,
            'transaction_date' => Carbon::parse($queryDetails['transactionDate'] ?? now())->toIso8601ZuluString()
        ]);
    }

    /**
     * Search for a transaction
     */
    public function search(SearchTransactionsRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        // Get the transactions based on the query
        $transactions = Transaction::with(['loan.loanOffer.customer'])
            ->when($data['interswitch_response_code'] ?? null, fn($query, $value) => $query->where('interswitch_transaction_code', $value))
            ->when($data['amount_from'] ?? null, fn($query, $value) => $query->where('amount', '>=', $value))
            ->when($data['amount_to'] ?? null, fn($query, $value) => $query->where('amount', '<=', $value))
            ->when($data['reference'] ?? null, fn($query, $value) => $query->where(fn($query2) => $query2->where('interswitch_payment_reference', 'LIKE', "%{$value}%")
                ->orWhere('interswitch_transaction_reference', 'LIKE', "%{$value}%")))
            ->when($data['type'] ?? null, fn($query, $value) => $query->whereIn('type', $value))
            ->when($data['from_date'] ?? null, fn($query, $value) => $query->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $transactions);
    }

    /**
     * Get the successful transactions
     */
    public function successful(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $successfulTransactions = Transaction::with(['loan.loanOffer.customer'])
            ->where(fn($query) => $query->whereIn('type', [
                Transaction::PAYMENT,
                Transaction::MANUAL
            ])
                ->orwhere(fn($query2) => $query2->where('interswitch_transaction_code', '00')
                    ->whereIn('type', [
                        Transaction::DEBIT,
                        Transaction::CREDIT,
                        Transaction::REFUND
                    ])))
            ->latest()->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $successfulTransactions);
    }
}
