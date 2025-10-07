<?php

namespace App\Http\Controllers;

use App\Models\CheckCrc;
use App\Exports\CrcReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\{Crc, Loan, LoanOffer};
use App\Http\Requests\BureauCheckReportRequest;
use App\Http\Requests\{IndexCrcRequest, ReportCrcRequest, StoreCrcRequest, UpdateCrcRequest};

class CrcController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(IndexCrcRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $crcs = Crc::with(['customer'])
            ->when($data['from_date'] ?? null, fn($query, $value) => $query->where('updated_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('updated_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['delinquencies'] ?? null, fn($query, $value) => $query->where('total_delinquencies', $value))
            ->when($data['passes_check'] ?? null, fn($query, $value) => $query->where('passes_recent_check', $value))
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $crcs);
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
     * @param  \App\Http\Requests\StoreCrcRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCrcRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Crc  $crc
     * @return \Illuminate\Http\Response
     */
    public function show(Crc $crc)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Crc  $crc
     * @return \Illuminate\Http\Response
     */
    public function edit(Crc $crc)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCrcRequest  $request
     * @param  \App\Models\Crc  $crc
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCrcRequest $request, Crc $crc)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Crc  $crc
     * @return \Illuminate\Http\Response
     */
    public function destroy(Crc $crc)
    {
        //
    }

    /**
     * Generate the report for CRC
     */
    public function report(ReportCrcRequest $request)
    {
        $data = $request->validated();

        /**
         * Get the loans that fall in this category
         */
        $loans = Loan::with(['transactions', 'loanOffer.customer.crc'])
            ->where('updated_at', '>=', Carbon::parse($data['from_date'])->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())
            ->where('updated_at', '<=', Carbon::parse($data['to_date'])->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString())
            ->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::OVERDUE
            ]))
            ->get();

        // Create the filename of the report
        if ($data['from_date'] === $data['to_date']) {
            $filename = 'crc-report-for-' . $data['from_date'];
        } else {
            $filename = 'crc-report-from-' . $data['from_date'] . '-to-' . $data['to_date'];
        }

        return Excel::download(new CrcReport($loans), "{$filename}.xlsx");
    }

    public function bureauCheckReports(BureauCheckReportRequest $request)
    {
        $perPage = $request->query('per_page', config('quickfund.per_page', 15));

        $checks = CheckCrc::query()
            ->when($request->filled('from_date'), function ($query) use ($request) {
                $query->where('timestamp', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            })
            ->when($request->filled('to_date'), function ($query) use ($request) {
                $query->where('timestamp', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            })
            ->when($request->filled('bvn'), function ($query) use ($request) {
                $query->where('bvn', $request->bvn);
            })
            ->latest('timestamp')
            ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $checks);
    }
}
