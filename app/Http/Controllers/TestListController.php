<?php

namespace App\Http\Controllers;

use App\Models\TestList;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Requests\StoreTestListRequest;
use App\Http\Requests\TestListCountRequest;
use App\Services\Phone\Nigeria as NigerianPhone;
use App\Http\Requests\{StoreBlacklistRequest, UpdateBlacklistRequest, BlacklistCountRequest};

class TestListController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $testLists = TestList::latest()->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $testLists);
    }

    public function store(StoreTestListRequest $request)
    {
        $data = $request->validated();

        $testList = TestList::create([
            'phone_number' => app()->make(NigerianPhone::class)->convert($data['phone_number']),
            'type' => TestList::MANUALLY
        ]);

        return $this->sendSuccess('"' . $data['phone_number'] . '"' . ' successfully added to test list', 201, $testList);
    }

    public function show($customerId)
    {
        $testList = TestList::where('phone_number', app()->make(NigerianPhone::class)->convert($customerId))->first();

        if (!isset($testList)) {
            return $this->sendErrorMessage('"' . $customerId . '"' . ' is not in the test list', 404);
        }

        return $this->sendSuccess(__('app.request_successful'), 200, $testList);
    }

    public function destroy($customerId)
    {
        $testList = TestList::where('phone_number', app()->make(NigerianPhone::class)->convert($customerId))->first();

        if (!isset($testList)) {
            return $this->sendErrorMessage('"' . $customerId . '"' . ' is not in the test list', 404);
        }

        $testList->delete();

        return $this->sendSuccess('"' . $customerId . '"' . ' has just been removed from test list');
    }

    public function count(TestListCountRequest $request)
    {
        $data = $request->validated();

        $count = TestList::when($data['from_date'] ?? null, fn($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->when($data['to_date'] ?? null, fn($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()))
            ->count();

        return $this->sendSuccess(__('app.request_successful'), 200, [
            'test_list_count' => (int) $count
        ]);
    }
}
