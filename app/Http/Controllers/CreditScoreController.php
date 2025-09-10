<?php

namespace App\Http\Controllers;

use App\Http\Requests\{IndexCreditScoreRequest, StoreCreditScoreRequest, UpdateCreditScoreRequest};
use App\Models\CreditScore;

class CreditScoreController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(IndexCreditScoreRequest $request)
    {
        $data = $request->validated();

        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $creditScores = CreditScore::with(['customer'])
                                ->when($data['score_from'] ?? null, fn($query, $value) => $query->where('score', '>=', $value))
                                ->when($data['score_to'] ?? null, fn($query, $value) => $query->where('score', '<=', $value))
                                ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $creditScores);
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
     * @param  \App\Http\Requests\StoreCreditScoreRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCreditScoreRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CreditScore  $creditScore
     * @return \Illuminate\Http\Response
     */
    public function show(CreditScore $creditScore)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CreditScore  $creditScore
     * @return \Illuminate\Http\Response
     */
    public function edit(CreditScore $creditScore)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCreditScoreRequest  $request
     * @param  \App\Models\CreditScore  $creditScore
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCreditScoreRequest $request, CreditScore $creditScore)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CreditScore  $creditScore
     * @return \Illuminate\Http\Response
     */
    public function destroy(CreditScore $creditScore)
    {
        //
    }
}
