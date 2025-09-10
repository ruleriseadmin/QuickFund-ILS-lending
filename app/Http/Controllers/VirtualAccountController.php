<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualAccountRequest;
use App\Http\Requests\UpdateVirtualAccountRequest;
use App\Models\VirtualAccount;

class VirtualAccountController extends Controller
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
     * @param  \App\Http\Requests\StoreVirtualAccountRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreVirtualAccountRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\VirtualAccount  $virtualAccount
     * @return \Illuminate\Http\Response
     */
    public function show(VirtualAccount $virtualAccount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\VirtualAccount  $virtualAccount
     * @return \Illuminate\Http\Response
     */
    public function edit(VirtualAccount $virtualAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateVirtualAccountRequest  $request
     * @param  \App\Models\VirtualAccount  $virtualAccount
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateVirtualAccountRequest $request, VirtualAccount $virtualAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\VirtualAccount  $virtualAccount
     * @return \Illuminate\Http\Response
     */
    public function destroy(VirtualAccount $virtualAccount)
    {
        //
    }
}
