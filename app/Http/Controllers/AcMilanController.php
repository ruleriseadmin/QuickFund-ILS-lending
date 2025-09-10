<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcMilanRequest;
use App\Http\Requests\UpdateAcMilanRequest;
use App\Models\AcMilan;

class AcMilanController extends Controller
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
     * @param  \App\Http\Requests\StoreAcMilanRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAcMilanRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AcMilan  $acMilan
     * @return \Illuminate\Http\Response
     */
    public function show(AcMilan $acMilan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AcMilan  $acMilan
     * @return \Illuminate\Http\Response
     */
    public function edit(AcMilan $acMilan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateAcMilanRequest  $request
     * @param  \App\Models\AcMilan  $acMilan
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAcMilanRequest $request, AcMilan $acMilan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AcMilan  $acMilan
     * @return \Illuminate\Http\Response
     */
    public function destroy(AcMilan $acMilan)
    {
        //
    }
}
