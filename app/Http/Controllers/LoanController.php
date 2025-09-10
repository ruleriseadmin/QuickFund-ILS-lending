<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Requests\{DaysPastDueInterestRequest, DaysPastDuePrincipalRequest, DaysPastDueTotalRequest, StoreLoanRequest, GetLoanStatusRequest, GetNplRequest, LoanClosedCountRequest, LoanClosedInterestRequest, LoanClosedPrincipalRequest, LoanClosedTotalRequest, LoanDisbursedCountRequest, LoanDisbursedInterestRequest, LoanDisbursedPrincipalRequest, LoanDisbursedTotalRequest, LoanOpenCountRequest, LoanOpenInterestRequest, LoanOpenPrincipalRequest, LoanOpenTotalRequest, LoanOverdueCountRequest, LoanOverdueInterestRequest, LoanOverduePrincipalRequest, LoanOverdueTotalRequest, PenaltiesCollectedRequest, PenaltiesCountRequest, PenaltiesDueCountRequest, PenaltiesDueRequest, PenaltiesRequest, TotalAmountRecoveredRequest, TotalInterestRecoveredRequest, TotalSuccessfulApplicationsRequest, UpdateLoanRequest};
use App\Models\{Loan, Customer, LoanOffer, Transaction};
use App\Exceptions\Interswitch\{InternalCustomerNotFoundException, UncollectedLoanException, UnknownOfferException};

class LoanController extends Controller
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
     * @param  \App\Http\Requests\StoreLoanRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLoanRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\Response
     */
    public function show(Loan $loan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\Response
     */
    public function edit(Loan $loan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateLoanRequest  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLoanRequest $request, Loan $loan)
    {
        // 
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\Response
     */
    public function destroy(Loan $loan)
    {
        //
    }

    /**
     * Get the status of a loan
     */
    public function status(GetLoanStatusRequest $request, $loanOfferId)
    {
        $data = $request->validated();

        // Get the customer or throw a customer not found exception
        $customer = Customer::where('phone_number', $data['customerId'])
            ->firstOr(fn() => throw new InternalCustomerNotFoundException);

        $loanOffer = $customer->loanOffers()
            ->findOr($loanOfferId, fn() => throw new UnknownOfferException);

        // Load the loan offer
        $loanOffer->load(['loan']);

        /**
         * Check if a record of a loan on the loan offer exists.
         */
        if (!isset($loanOffer->loan)) {
            throw new UncollectedLoanException;
        }

        // Everything looks good, we return the loan status details
        return $this->sendInterswitchSuccessMessage(__('interswitch.success'), 200, [
            'loan' => [
                'status' => $loanOffer->status,
                'remainingAmount' => $loanOffer->loan->amount_remaining + $loanOffer->loan->penalty_remaining,
                'dueDate' => $loanOffer->loan->due_date->toIso8601ZuluString()
            ]
        ]);
    }

    /**
     * The disbursed loans principal
     */
    public function disbursedPrincipal(LoanDisbursedPrincipalRequest $request)
    {
        $data = $request->validated();

        $disbursedPrincipal = Loan::
            when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::OVERDUE,
                LoanOffer::CLOSED
            ]))
                ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                    ->where('interswitch_transaction_code', '00')
                    ->whereNotNull('interswitch_transaction_reference')))
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_disbursed_principal' => (int) $disbursedPrincipal
        ]);
    }

    /**
     * The closed loans principal
     */
    public function closedPrincipal(LoanClosedPrincipalRequest $request)
    {
        $data = $request->validated();

        $closedPrincipal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::CLOSED))
            ->sum('amount');


        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_closed_principal' => (int) $closedPrincipal
        ]);
    }

    /**
     * The overdue loans principal 
     */
    public function overduePrincipal(LoanOverduePrincipalRequest $request)
    {
        $data = $request->validated();

        $overduePrincipal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE))
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_overdue_principal' => (int) $overduePrincipal
        ]);
    }

    /**
     * The disbursed loans total (Principal + Interest)
     */
    public function disbursedTotal(LoanDisbursedTotalRequest $request)
    {
        $data = $request->validated();

        $disbursedTotal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::OVERDUE,
                LoanOffer::CLOSED
            ]))
                ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                    ->where('interswitch_transaction_code', '00')
                    ->whereNotNull('interswitch_transaction_reference')))
            ->sum('amount_payable');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_disbursed_total' => (int) $disbursedTotal
        ]);
    }

    /**
     * The closed loans total (Principal + Interest)
     */
    public function closedTotal(LoanClosedTotalRequest $request)
    {
        $data = $request->validated();

        $closedTotal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::CLOSED))
            ->sum('amount_payable');


        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_closed_total' => (int) $closedTotal
        ]);
    }

    /**
     * The overdue loans total (Principal + Interest)
     */
    public function overdueTotal(LoanOverdueTotalRequest $request)
    {
        $data = $request->validated();

        $overdueTotal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE))
            ->sum('amount_payable');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_overdue_total' => (int) $overdueTotal
        ]);
    }

    /**
     * The disbursed loans interest
     */
    public function disbursedInterest(LoanDisbursedInterestRequest $request)
    {
        $data = $request->validated();


        $disbursedQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::OVERDUE,
                LoanOffer::CLOSED
            ]))
                ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                    ->where('interswitch_transaction_code', '00')
                    ->whereNotNull('interswitch_transaction_reference')));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_disbursed_interest' => (int) ($disbursedQuery->sum('amount_payable') - $disbursedQuery->sum('amount'))
        ]);
    }

    /**
     * The closed loans interest
     */
    public function closedInterest(LoanClosedInterestRequest $request)
    {
        $data = $request->validated();

        $closedQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::CLOSED));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_closed_interest' => (int) ($closedQuery->sum('amount_payable') - $closedQuery->sum('amount'))
        ]);
    }

    /**
     * The overdue loans interest
     */
    public function overdueInterest(LoanOverdueInterestRequest $request)
    {
        $data = $request->validated();

        $overdueQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_overdue_interest' => (int) ($overdueQuery->sum('amount_payable') - $overdueQuery->sum('amount'))
        ]);
    }

    /**
     * The open loans total (Principal + Interest)
     */
    public function openTotal(LoanOpenTotalRequest $request)
    {
        $data = $request->validated();

        $openTotal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN))
            ->sum('amount_payable');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_open_total' => (int) $openTotal
        ]);
    }

    /**
     * The open loans principal
     */
    public function openPrincipal(LoanOpenPrincipalRequest $request)
    {
        $data = $request->validated();

        $openPrincipal = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN))
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_open_principal' => (int) $openPrincipal
        ]);
    }

    /**
     * The open loans interest
     */
    public function openInterest(LoanOpenInterestRequest $request)
    {
        $data = $request->validated();

        $openQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_open_interest' => (int) ($openQuery->sum('amount_payable') - $openQuery->sum('amount'))
        ]);
    }

    /**
     * The due today loans total
     */
    public function dueTodayTotal(Request $request)
    {
        $dueTodayTotal = Loan::whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN))
            ->whereBetween('due_date', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ])
            ->sum('amount_payable');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_due_today_total' => (int) $dueTodayTotal
        ]);
    }

    /**
     * The due today loans principal
     */
    public function dueTodayPrincipal(Request $request)
    {
        $dueTodayPrincipal = Loan::whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN))
            ->whereBetween('due_date', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ])
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_due_today_principal' => (int) $dueTodayPrincipal
        ]);
    }

    /**
     * The due today loans interest
     */
    public function dueTodayInterest(Request $request)
    {
        $dueTodayQuery = Loan::whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN))
            ->whereBetween('due_date', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ]);

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_due_today_interest' => (int) ($dueTodayQuery->sum('amount_payable') - $dueTodayQuery->sum('amount'))
        ]);
    }

    /**
     * The disbursed loans count
     */
    public function disbursedCount(LoanDisbursedCountRequest $request)
    {
        $data = $request->validated();

        $disbursedCount = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::OVERDUE,
                LoanOffer::CLOSED
            ]))
                ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                    ->where('interswitch_transaction_code', '00')
                    ->whereNotNull('interswitch_transaction_reference')))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_disbursed_count' => (int) $disbursedCount
        ]);
    }

    /**
     * The closed loans count
     */
    public function closedCount(LoanClosedCountRequest $request)
    {
        $data = $request->validated();

        $closedCount = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::CLOSED))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_closed_count' => (int) $closedCount
        ]);
    }

    /**
     * The open loans count
     */
    public function openCount(LoanOpenCountRequest $request)
    {
        $data = $request->validated();

        $openCount = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OPEN))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_open_count' => (int) $openCount
        ]);
    }

    /**
     * The overdue loans count
     */
    public function overdueCount(LoanOverdueCountRequest $request)
    {
        $data = $request->validated();

        $overdueCount = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'loans_overdue_count' => (int) $overdueCount
        ]);
    }

    /**
     * The days past due principal
     */
    public function daysPastDuePrincipal(DaysPastDuePrincipalRequest $request)
    {
        $data = $request->validated();

        $daysPastDuePrincipal = Loan::when($data['days'] ?? null, fn($query, $value) => $query->whereBetween('due_date', [
            now()->subDays($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
            now()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
        ]))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE))
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'days_past_due_principal' => (int) $daysPastDuePrincipal
        ]);
    }

    /**
     * The days past due total
     */
    public function daysPastDueTotal(DaysPastDueTotalRequest $request)
    {
        $data = $request->validated();

        $daysPastDueTotal = Loan::when($data['days'] ?? null, fn($query, $value) => $query->whereBetween('due_date', [
            now()->subDays($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
            now()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
        ]))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE))
            ->sum('amount_payable');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'days_past_due_total' => (int) $daysPastDueTotal
        ]);
    }

    /**
     * The days past due interest
     */
    public function daysPastDueInterest(DaysPastDueInterestRequest $request)
    {
        $data = $request->validated();

        $daysPastDueQuery = Loan::when($data['days'] ?? null, fn($query, $value) => $query->whereBetween('due_date', [
            now()->subDays($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
            now()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
        ]))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'days_past_due_interest' => (int) ($daysPastDueQuery->sum('amount_payable') - $daysPastDueQuery->sum('amount'))
        ]);
    }

    /**
     * The penalties on loans
     */
    public function penalties(PenaltiesRequest $request)
    {
        $data = $request->validated();

        $penalties = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->sum('penalty');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'penalties' => (int) $penalties
        ]);
    }

    /**
     * The penalties due on loans
     */
    public function penaltiesDue(PenaltiesDueRequest $request)
    {
        $data = $request->validated();

        $penaltiesDue = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->sum('penalty_remaining');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'penalties_due' => (int) $penaltiesDue
        ]);
    }

    /**
     * The count of loans with penalties
     */
    public function penaltiesCount(PenaltiesCountRequest $request)
    {
        $data = $request->validated();

        $penaltiesCount = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where('penalty', '>', 0)
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'penalties_count' => (int) $penaltiesCount
        ]);
    }

    /**
     * The count of loans with penalties due
     */
    public function penaltiesDueCount(PenaltiesDueCountRequest $request)
    {
        $data = $request->validated();

        $penaltiesDueCount = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where('penalty_remaining', '>', 0)
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'penalties_due_count' => (int) $penaltiesDueCount
        ]);
    }

    /**
     * The penalties collected
     */
    public function penaltiesCollected(PenaltiesCollectedRequest $request)
    {
        $data = $request->validated();

        $penaltiesQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'penalties_collected' => (int) ($penaltiesQuery->sum('penalty') - $penaltiesQuery->sum('penalty_remaining'))
        ]);
    }

    /**
     * The total amount disbursed for the day
     */
    public function totalAmountDisbursedToday(Request $request)
    {
        $totalAmountDisbursedToday = Loan::where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
            LoanOffer::OPEN,
            LoanOffer::OVERDUE,
            LoanOffer::CLOSED
        ]))
            ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                ->where('interswitch_transaction_code', '00')
                ->whereNotNull('interswitch_transaction_reference')))
            ->whereBetween('created_at', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ])
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_amount_disbursed_today' => (int) $totalAmountDisbursedToday
        ]);
    }

    /**
     * The total successful loan applications
     */
    public function totalSuccessfulApplications(TotalSuccessfulApplicationsRequest $request)
    {
        $data = $request->validated();

        $totalSuccessfulApplications = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::OVERDUE,
                LoanOffer::CLOSED
            ]))
                ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                    ->where('interswitch_transaction_code', '00')
                    ->whereNotNull('interswitch_transaction_reference')))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_successful_applications' => (int) $totalSuccessfulApplications
        ]);
    }

    /**
     * The total failed loan applications
     */
    public function totalFailedApplications()
    {
        $totalFailedApplications = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::FAILED)))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_failed_applications' => (int) $totalFailedApplications
        ]);
    }

    /**
     * The total amount recovered
     */
    public function totalAmountRecovered(TotalAmountRecoveredRequest $request)
    {
        $data = $request->validated();

        $totalAmountRecovered = Transaction::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                ->where('interswitch_transaction_code', '00')
                ->whereNotNull('interswitch_transaction_reference'))
                ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                    ->whereNotNull('interswitch_payment_reference'))
                ->orWhere(fn($query4) => $query4->where('type', Transaction::MANUAL)))
            ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_amount_recovered' => (int) $totalAmountRecovered
        ]);
    }

    /**
     * The total interest recovered
     */
    public function totalInterestRecovered(TotalInterestRecoveredRequest $request)
    {
        $data = $request->validated();

        $totalInterestRecoveredQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::CLOSED));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_interest_recovered' => (int) (($totalInterestRecoveredQuery->sum('amount_payable') + $totalInterestRecoveredQuery->sum('penalty')) - $totalInterestRecoveredQuery->sum('amount'))
        ]);
    }

    /**
     * Get the NPL
     */
    public function npl(GetNplRequest $request)
    {
        $data = $request->validated();

        // The total amount disbursed
        $totalAmountDisbursed = Transaction::where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::CREDIT)
            ->where('interswitch_transaction_code', '00')
            ->whereNotNull('interswitch_transaction_reference'))
            ->orWhere(fn($query2) => $query2->where('type', Transaction::CREDIT)
                ->whereHas('loan.loanOffer', fn($query3) => $query3->whereIn('status', [
                    LoanOffer::OPEN,
                    LoanOffer::OVERDUE,
                    LoanOffer::CLOSED
                ]))))
            ->sum('amount');

        // The loans query
        $loansQuery = Loan::whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE))
            ->where('due_date', '<', now()->subDays($data['days']));

        // The total principal
        $totalPrincipal = $loansQuery->sum('amount');

        // The total amount payable
        $totalAmountPayable = $loansQuery->sum('amount_payable');

        // The total amount remaining
        $totalAmountRemaining = $loansQuery->sum('amount_remaining');

        // Get the total interest
        $totalInterest = $totalAmountPayable - $totalPrincipal;

        // The unpaid principal
        $unpaidPrincipal = $totalAmountRemaining - $totalInterest;

        /**
         * If the calculation of the unpaid principal is less than 0, then the unpaid principal is 0
         */
        if ($unpaidPrincipal < 0) {
            $unpaidPrincipal = 0;
        }

        // Calculate the NPL
        return $this->sendSuccess(__('app.request_successful'), 200, [
            'npl' => round((($unpaidPrincipal / ($totalAmountDisbursed ?: 1)) * 100), 2)
        ]);
    }
}
