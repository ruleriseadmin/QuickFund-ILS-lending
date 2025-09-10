<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use App\Http\Requests\{IndexCrcHistoryRequest, StoreCrcHistoryRequest, UpdateCrcHistoryRequest};
use App\Models\CrcHistory;

class CrcHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(IndexCrcHistoryRequest $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $data = $request->validated();

        $crcHistories = CrcHistory::with(['crc.customer'])
                                ->when($data['from_date'] ?? null, fn($query, $value) => $query->whereDate('date', '>=', $value))
                                ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereDate('date', '<=', $value))
                                ->latest()
                                ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $crcHistories);
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
     * @param  \App\Http\Requests\StoreCrcHistoryRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCrcHistoryRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CrcHistory  $crcHistory
     * @return \Illuminate\Http\Response
     */
    public function show(CrcHistory $crcHistory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CrcHistory  $crcHistory
     * @return \Illuminate\Http\Response
     */
    public function edit(CrcHistory $crcHistory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCrcHistoryRequest  $request
     * @param  \App\Models\CrcHistory  $crcHistory
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCrcHistoryRequest $request, CrcHistory $crcHistory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CrcHistory  $crcHistory
     * @return \Illuminate\Http\Response
     */
    public function destroy(CrcHistory $crcHistory)
    {
        //
    }
}
