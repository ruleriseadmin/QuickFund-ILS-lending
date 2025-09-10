<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreWhitelistRequest;
use App\Http\Requests\UpdateWhitelistRequest;
use App\Models\Whitelist;
use App\Services\Phone\Nigeria as NigerianPhone;

class WhitelistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $whitelists = Whitelist::latest()->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $whitelists);
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
     * @param  \App\Http\Requests\StoreWhitelistRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWhitelistRequest $request)
    {
        $data = $request->validated();

        $whitelist = Whitelist::create([
            'phone_number' => app()->make(NigerianPhone::class)->convert($data['phone_number']),
            'type' => Whitelist::MANUALLY
        ]);

        return $this->sendSuccess('"'.$data['phone_number'].'"'.' successfully added to whitelists', 201, $whitelist);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Whitelist  $whitelist
     * @return \Illuminate\Http\Response
     */
    public function show($customerId)
    {
        $whitelist = Whitelist::where('phone_number', app()->make(NigerianPhone::class)->convert($customerId))->first();

        if (!isset($whitelist)) {
            return $this->sendErrorMessage('"'.$customerId.'"'.' is not whitelisted', 404);
        }

        return $this->sendSuccess(__('app.request_successful'), 200, $whitelist);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Whitelist  $whitelist
     * @return \Illuminate\Http\Response
     */
    public function edit(Whitelist $whitelist)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWhitelistRequest  $request
     * @param  \App\Models\Whitelist  $whitelist
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWhitelistRequest $request, Whitelist $whitelist)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Whitelist  $whitelist
     * @return \Illuminate\Http\Response
     */
    public function destroy($customerId)
    {
        $whitelist = Whitelist::where('phone_number', app()->make(NigerianPhone::class)->convert($customerId))->first();

        if (!isset($whitelist)) {
            return $this->sendErrorMessage('"'.$customerId.'"'.' is not whitelisted', 404);
        }

        $whitelist->delete();

        return $this->sendSuccess('"'.$customerId.'"'.' has just been removed from whitelists');
    }
}
