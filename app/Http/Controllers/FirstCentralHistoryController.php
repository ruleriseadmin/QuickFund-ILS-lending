<?php

namespace App\Http\Controllers;

use App\Http\Requests\{IndexFirstCentralHistoryRequest, StoreFirstCentralHistoryRequest, UpdateFirstCentralHistoryRequest};
use App\Models\FirstCentralHistory;

class FirstCentralHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(IndexFirstCentralHistoryRequest $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $data = $request->validated();

        $firstCentralHistories = FirstCentralHistory::with(['firstCentral.customer'])
                                                    ->when($data['from_date'] ?? null, fn($query, $value) => $query->whereDate('date', '>=', $value))
                                                    ->when($data['to_date'] ?? null, fn($query, $value) => $query->whereDate('date', '<=', $value))
                                                    ->latest()
                                                    ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $firstCentralHistories);
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
     * @param  \App\Http\Requests\StoreFirstCentralHistoryRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreFirstCentralHistoryRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FirstCentralHistory  $firstCentralHistory
     * @return \Illuminate\Http\Response
     */
    public function show(FirstCentralHistory $firstCentralHistory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FirstCentralHistory  $firstCentralHistory
     * @return \Illuminate\Http\Response
     */
    public function edit(FirstCentralHistory $firstCentralHistory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateFirstCentralHistoryRequest  $request
     * @param  \App\Models\FirstCentralHistory  $firstCentralHistory
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateFirstCentralHistoryRequest $request, FirstCentralHistory $firstCentralHistory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FirstCentralHistory  $firstCentralHistory
     * @return \Illuminate\Http\Response
     */
    public function destroy(FirstCentralHistory $firstCentralHistory)
    {
        //
    }
}
