<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Requests\{StoreBlacklistRequest, UpdateBlacklistRequest, BlacklistCountRequest};
use App\Models\Blacklist;
use App\Services\Phone\Nigeria as NigerianPhone;

class BlacklistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $blacklists = Blacklist::latest()->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $blacklists);
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
     * @param  \App\Http\Requests\StoreBlacklistRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreBlacklistRequest $request)
    {
        $data = $request->validated();

        $blacklist = Blacklist::create([
            'phone_number' => app()->make(NigerianPhone::class)->convert($data['phone_number']),
            'type' => Blacklist::MANUALLY
        ]);

        return $this->sendSuccess('"'.$data['phone_number'].'"'.' successfully added to blacklists', 201, $blacklist);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function show($customerId)
    {
        $blacklist = Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($customerId))->first();

        if (!isset($blacklist)) {
            return $this->sendErrorMessage('"'.$customerId.'"'.' is not blacklisted', 404);
        }

        return $this->sendSuccess(__('app.request_successful'), 200, $blacklist);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function edit(Blacklist $blacklist)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateBlacklistRequest  $request
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateBlacklistRequest $request, Blacklist $blacklist)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function destroy($customerId)
    {
        $blacklist = Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($customerId))->first();

        if (!isset($blacklist)) {
            return $this->sendErrorMessage('"'.$customerId.'"'.' is not blacklisted', 404);
        }

        $blacklist->delete();

        return $this->sendSuccess('"'.$customerId.'"'.' has just been removed from blacklists');
    }

    /**
     * The count of customers added to blacklist
     */
    public function count(BlacklistCountRequest $request)
    {
        $data = $request->validated();

        $blacklistCount = Blacklist::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
                                ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'blacklists_count' => (int) $blacklistCount
        ]);
    }

    
}
