<?php

namespace App\Http\Controllers;

use App\Exceptions\ForbiddenException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Requests\{AssignCollectionCaseRequest, CasesAllottedArrearsRequest, CasesAllottedRequest, CasesWorkedOnRequest, PtpCasesRequest, SearchCollectionCaseRequest, StoreCollectionCaseRequest, TotalCasesAllottedArrearsRequest, TotalCasesAllottedRequest, TotalCasesWorkedOnRequest, TotalPtpCasesRequest, UpdateCollectionCaseRequest};
use App\Models\{CollectionCase, Loan, LoanOffer, Role, Transaction, User};
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\UserAlreadyAssignedCollectionCaseException;

class CollectionCaseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        // Depending on the permissions of the user, we determine the 
        if ($request->user()->tokenCan('super-collector')) {
            $collectionCases = CollectionCase::with([
                                                'user',
                                                'loanOffer.loan',
                                                'loanOffer.customer.virtualAccount',
                                                'collectionCaseRemarks' => fn($query) => $query->latest(),
                                                'collectionCaseRemarks.user'
                                            ])
                                            ->paginate($perPage);
        } else {
            $collectionCases = CollectionCase::with([
                                                'user',
                                                'loanOffer.loan',
                                                'loanOffer.customer.virtualAccount',
                                                'collectionCaseRemarks' => fn($query) => $query->latest(),
                                                'collectionCaseRemarks.user'
                                            ])
                                            ->where('user_id', $request->user()->id)
                                            ->paginate($perPage);
        }

        return $this->sendSuccess(__('app.request_successful'), 200, $collectionCases);
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
     * @param  \App\Http\Requests\StoreCollectionCaseRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCollectionCaseRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CollectionCase  $collectionCase
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, CollectionCase $collectionCase)
    {
        // Check if the current user has access to the collection case
        if (!$request->user()->tokenCan('super-collector') &&
            $collectionCase->user_id !== $request->user()->id) {
            throw new ModelNotFoundException($collectionCase);
        }

        $collectionCase->load([
            'user',
            'loanOffer.loan',
            'loanOffer.customer.virtualAccount',
            'collectionCaseRemarks' => fn($query) => $query->latest(),
            'collectionCaseRemarks.user'
        ]);

        return $this->sendSuccess(__('app.request_successful'), 200, $collectionCase);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CollectionCase  $collectionCase
     * @return \Illuminate\Http\Response
     */
    public function edit(CollectionCase $collectionCase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCollectionCaseRequest  $request
     * @param  \App\Models\CollectionCase  $collectionCase
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCollectionCaseRequest $request, CollectionCase $collectionCase)
    {
        $data = $request->validated();

        // Check if the current user has access to the collection case
        if ($collectionCase->user_id !== $request->user()->id) {
            throw new ModelNotFoundException($collectionCase);
        }

        // Update the collection case details
        $collectionCase->collectionCaseRemarks()->create([
            'user_id' => $request->user()->id,
            'remark' => $data['remark'],
            'remarked_at' => now(),
            'comment' => $data['comment'] ?? null,
            'promised_to_pay_at' => $data['promised_to_pay_at'] ?? null,
            'already_paid_at' => $data['already_paid_at'] ?? null
        ]);

        return $this->sendSuccess('Collection case updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CollectionCase  $collectionCase
     * @return \Illuminate\Http\Response
     */
    public function destroy(CollectionCase $collectionCase)
    {
        //
    }

    /**
     * The total cases allotted 
     */
    public function totalAllotted(TotalCasesAllottedRequest $request)
    {
        $data = $request->validated();

        $totalAllotted = CollectionCase::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                    ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                    ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_allotted' => (int) $totalAllotted
        ]);
    }

    /**
     * The total cases worked on
     */
    public function totalWorkedOn(TotalCasesWorkedOnRequest $request)
    {
        $data = $request->validated();

        $totalWorkedOn = CollectionCase::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                    ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                    ->whereHas('collectionCaseRemarks', fn($query) => $query->whereNotNull('remark'))
                                    ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_worked_on' => (int) $totalWorkedOn
        ]);
    }

    /**
     * The total cases that customers promised to pay
     */
    public function totalPtp(TotalPtpCasesRequest $request)
    {
        $data = $request->validated();

        $totalPtp = CollectionCase::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->whereHas('collectionCaseRemarks', fn($query) => $query->whereNotNull('promised_to_pay_at'))
                                ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_ptp' => (int) $totalPtp
        ]);
    }

    /**
     * The total cases that customers promised to pay today
     */
    public function totalPtpToday(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $totalPtpToday = CollectionCase::with([
                                        'user',
                                        'loanOffer.loan',
                                        'loanOffer.customer.virtualAccount',
                                        'collectionCaseRemarks' => fn($query) => $query->latest(),
                                        'collectionCaseRemarks.user'
                                    ])
                                    ->whereHas('collectionCaseRemarks', fn($query) => $query->whereDate('promised_to_pay_at', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
                                    ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $totalPtpToday);
    }

    /**
     * The total cases allotted arrears
     */
    public function totalAllottedArrears(TotalCasesAllottedArrearsRequest $request)
    {
        $data = $request->validated();

        $totalAllottedArrearsQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
                                        ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
                                        ->has('loanOffer.collectionCase')
                                        ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_allotted_arrears' => (int) ($totalAllottedArrearsQuery->sum('amount_remaining') + $totalAllottedArrearsQuery->sum('penalty_remaining'))
        ]);
    }

    /**
     * The cases with transactions that were paid today
     */
    public function totalPaidToday(Request $request)
    {
        $totalPaidToday = Transaction::has('loan.loanOffer.collectionCase')
                                    ->whereBetween('updated_at', [
                                        today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                                        today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
                                    ])
                                    ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                                                                                            ->where('interswitch_transaction_code', '00')
                                                                                            ->whereNotNull('interswitch_transaction_reference'))
                                                                ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                                                                                            ->whereNotNull('interswitch_payment_reference'))
                                                                ->orWhere(fn($query4) => $query4->where('type', Transaction::MANUAL)))
                                    ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_paid_today' => (int) $totalPaidToday
        ]);
    }

    /**
     * The count of the cases with transactions that were paid today
     */
    public function totalPaidTodayCount(Request $request)
    {
        $totalPaidTodayCount = Transaction::has('loan.loanOffer.collectionCase')
                                    ->whereBetween('updated_at', [
                                        today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                                        today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
                                    ])
                                    ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                                                                                            ->where('interswitch_transaction_code', '00')
                                                                                            ->whereNotNull('interswitch_transaction_reference'))
                                                                ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                                                                                            ->whereNotNull('interswitch_payment_reference'))
                                                                ->orWhere(fn($query4) => $query4->where('type', Transaction::MANUAL)))
                                    ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'total_paid_today_count' => (int) $totalPaidTodayCount
        ]);
    }

    /**
     * The cases allotted for a user
     */
    public function allotted(CasesAllottedRequest $request)
    {
        $data = $request->validated();

        $allotted = CollectionCase::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->where('user_id', $request->user()->id)
                                ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'allotted' => (int) $allotted
        ]);
    }

    /**
     * The cases worked on for a user
     */
    public function workedOn(CasesWorkedOnRequest $request)
    {
        $data = $request->validated();

        $workedOn = CollectionCase::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->where('user_id', $request->user()->id)
                                ->whereHas('collectionCaseRemarks', fn($query) => $query->where('user_id', $request->user()->id)
                                                                                        ->whereNotNull('remark'))
                                ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'worked_on' => (int) $workedOn
        ]);
    }

    /**
     * The cases that customers promised to pay for a user
     */
    public function ptp(PtpCasesRequest $request)
    {
        $data = $request->validated();

        $ptp = CollectionCase::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                            ->where('user_id', $request->user()->id)
                            ->whereHas('collectionCaseRemarks', fn($query) => $query->where('user_id', $request->user()->id)
                                                                                    ->whereNotNull('promised_to_pay_at'))
                            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'ptp' => (int) $ptp
        ]);
    }

    /**
     * The cases with customers who promised to pay today
     */
    public function ptpToday(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $ptpToday = CollectionCase::with([
                                    'user',
                                    'loanOffer.loan',
                                    'loanOffer.customer.virtualAccount',
                                    'collectionCaseRemarks' => fn($query) => $query->latest(),
                                    'collectionCaseRemarks.user'
                                ])
                                ->whereHas('collectionCaseRemarks', fn($query) => $query->where('user_id', $request->user()->id)
                                                                                        ->whereDate('promised_to_pay_at', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
                                ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $ptpToday);
    }

    /**
     * The cases allotted arrears for a user
     */
    public function allottedArrears(CasesAllottedArrearsRequest $request)
    {
        $data = $request->validated();

        $allottedArrearsQuery = Loan::when($data['from_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
                                    ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereHas('loanOffer', fn($query2) => $query2->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())))
                                    ->whereHas('loanOffer.collectionCase', fn($query) => $query->where('user_id', $request->user()->id))
                                    ->whereHas('loanOffer', fn($query) => $query->where('status', LoanOffer::OVERDUE));

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'allotted_arrears' => (int) ($allottedArrearsQuery->sum('amount_remaining') + $allottedArrearsQuery->sum('penalty_remaining'))
        ]);
    }

    /**
     * The cases with transactions that were paid today for a user
     */
    public function paidToday(Request $request)
    {
        $paidToday = Transaction::whereHas('loan.loanOffer.collectionCase', fn($query) => $query->where('user_id', $request->user()->id))
                                ->whereBetween('updated_at', [
                                    today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                                    today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
                                ])
                                ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                                                                                        ->where('interswitch_transaction_code', '00')
                                                                                        ->whereNotNull('interswitch_transaction_reference'))
                                                            ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                                                                                        ->whereNotNull('interswitch_payment_reference'))
                                                            ->orWhere(fn($query4) => $query4->where('type', Transaction::MANUAL)))
                                ->sum('amount');

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'paid_today' => (int) $paidToday
        ]);
    }

    /**
     * The count of the cases with transactions that were paid today for a user
     */
    public function paidTodayCount(Request $request)
    {
        $paidTodayCount = Transaction::whereHas('loan.loanOffer.collectionCase', fn($query) => $query->where('user_id', $request->user()->id))
                                    ->whereBetween('updated_at', [
                                        today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                                        today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
                                    ])
                                    ->where(fn($query) => $query->where(fn($query2) => $query2->where('type', Transaction::DEBIT)
                                                                                            ->where('interswitch_transaction_code', '00')
                                                                                            ->whereNotNull('interswitch_transaction_reference'))
                                                                ->orWhere(fn($query3) => $query3->where('type', Transaction::PAYMENT)
                                                                                            ->whereNotNull('interswitch_payment_reference'))
                                                                ->orWhere(fn($query4) => $query4->where('type', Transaction::MANUAL)))
                                    ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'paid_today_count' => (int) $paidTodayCount
        ]);
    }

    /**
     * Assign a user to a case
     */
    public function assign(AssignCollectionCaseRequest $request, CollectionCase $collectionCase)
    {
        $data = $request->validated();

        // Get the user to make sure that the user is a collector
        $user = User::whereHas('role', fn($query) => $query->where('permissions', 'LIKE', '%collection-cases%'))
                    ->findOrFail($data['user_id']);

        // Check if the user has already been assigned to the case
        if ($collectionCase->user_id === $user->id) {
            throw new UserAlreadyAssignedCollectionCaseException;
        }

        // Assign the collection case to user
        $collectionCase->forceFill([
            'user_id' => $user->id,
            'assigned_at' => now()
        ])->save();

        return $this->sendSuccess('Collection case assigned successfully to "'.$user->first_name.' '.$user->last_name.'"');
    }

    /**
     * Search for a collection case
     */
    public function search(SearchCollectionCaseRequest $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $data = $request->validated();

        // Depending on the permissions of the user, we determine the 
        if ($request->user()->tokenCan('super-collector')) {
            $collectionCases = CollectionCase::with([
                                                'user',
                                                'loanOffer.loan',
                                                'loanOffer.customer.virtualAccount',
                                                'collectionCaseRemarks' => fn($query) => $query->latest(),
                                                'collectionCaseRemarks.user'
                                            ])
                                            ->when($data['collector_id'] ?? null, fn($query, $value) => $query->where('user_id', $value)
                                                                                                            ->whereHas('user.role', fn($query) => $query->where('permissions', 'LIKE', '%collection-cases%')))
                                            ->when($data['remark'] ?? null, fn($query, $value) => $query->whereHas('collectionCaseRemarks', fn($query2) => $query2->where('remark', $value)))
                                            ->when($data['assigned_date'] ?? null, fn($query, $value) => $query->whereDate('assigned_at', $value))
                                            ->when($data['dpd_from'] ?? null, fn($query, $value) => $query->whereHas('loanOffer.loan', fn($query) => $query->whereDate('due_date', '<=', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->subDays($value)->toDateTimeString()))))
                                            ->when($data['dpd_to'] ?? null, fn($query, $value) => $query->whereHas('loanOffer.loan', fn($query) => $query->whereDate('due_date', '>=', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->subDays($value)->toDateTimeString()))))
                                            ->when($data['ptp_from'] ?? null, fn($query, $value) => $query->whereHas('collectionCaseRemarks', fn($query) => $query->where('promised_to_pay_at', '>=', $value)))
                                            ->when($data['ptp_to'] ?? null, fn($query, $value) => $query->whereHas('collectionCaseRemarks', fn($query) => $query->where('promised_to_pay_at', '<=', $value)))
                                            ->paginate($perPage);
        } else {
            $collectionCases = CollectionCase::with([
                                                'user',
                                                'loanOffer.loan',
                                                'loanOffer.customer.virtualAccount',
                                                'collectionCaseRemarks' => fn($query) => $query->latest(),
                                                'collectionCaseRemarks.user'
                                            ])
                                            ->where('user_id', $request->user()->id)
                                            ->when($data['collector_id'] ?? null, fn($query, $value) => $query->where('user_id', $value)
                                                                                                            ->whereHas('user.role', fn($query) => $query->where('permissions', 'LIKE', '%collection-cases%')))
                                            ->when($data['remark'] ?? null, fn($query, $value) => $query->whereHas('collectionCaseRemarks', fn($query2) => $query2->where('remark', $value)))
                                            ->when($data['assigned_date'] ?? null, fn($query, $value) => $query->whereDate('assigned_at', $value))
                                            ->when($data['dpd_from'] ?? null, fn($query, $value) => $query->whereHas('loanOffer.loan', fn($query) => $query->whereDate('due_date', '<=', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->subDays($value)->toDateTimeString()))))
                                            ->when($data['dpd_to'] ?? null, fn($query, $value) => $query->whereHas('loanOffer.loan', fn($query) => $query->whereDate('due_date', '>=', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->subDays($value)->toDateTimeString()))))
                                            ->when($data['ptp_from'] ?? null, fn($query, $value) => $query->whereHas('collectionCaseRemarks', fn($query) => $query->where('promised_to_pay_at', '>=', $value)))
                                            ->when($data['ptp_to'] ?? null, fn($query, $value) => $query->whereHas('collectionCaseRemarks', fn($query) => $query->where('promised_to_pay_at', '<=', $value)))
                                            ->paginate($perPage);
        }

        return $this->sendSuccess(__('app.request_successful'), 200, $collectionCases);
    }
}
