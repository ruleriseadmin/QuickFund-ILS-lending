<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\{StoreCollectionRemarkRequest, UpdateCollectionRemarkRequest};
use App\Models\CollectionRemark;

class CollectionRemarkController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');

        $collectionRemarks = CollectionRemark::paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $collectionRemarks);
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
     * @param  \App\Http\Requests\StoreCollectionRemarkRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCollectionRemarkRequest $request)
    {
        $data = $request->validated();

        $collectionRemark = CollectionRemark::create($data);

        return $this->sendSuccess('Collection remark created successfully.', 201, $collectionRemark);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CollectionRemark  $collectionRemark
     * @return \Illuminate\Http\Response
     */
    public function show(CollectionRemark $collectionRemark)
    {
        return $this->sendSuccess(__('app.request_successful'), 200, $collectionRemark);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CollectionRemark  $collectionRemark
     * @return \Illuminate\Http\Response
     */
    public function edit(CollectionRemark $collectionRemark)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCollectionRemarkRequest  $request
     * @param  \App\Models\CollectionRemark  $collectionRemark
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCollectionRemarkRequest $request, CollectionRemark $collectionRemark)
    {
        $data = $request->validated();

        $collectionRemark->update($data);

        return $this->sendSuccess('Collection remark updated successfully.', 200, $collectionRemark);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CollectionRemark  $collectionRemark
     * @return \Illuminate\Http\Response
     */
    public function destroy(CollectionRemark $collectionRemark)
    {
        $collectionRemark->delete();

        return $this->sendSuccess('Collection remark deleted successfully.');
    }
}
