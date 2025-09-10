<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreSettingRequest, UpdateSettingRequest};
use App\Models\Setting;
use App\Services\Application as ApplicationService;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $setting = Setting::find(Setting::MAIN_ID);

        return $this->sendSuccess(__('app.request_successful'), 200, $setting);
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
     * @param  \App\Http\Requests\StoreSettingRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSettingRequest $request)
    {
        $data = $request->validated();

        // Create the setting
        $setting = Setting::updateOrCreate([
            'id' => Setting::MAIN_ID
        ], [
            'minimum_loan_amount' => $data['minimum_loan_amount'],
            'maximum_loan_amount' => $data['maximum_loan_amount'],
            'loan_tenures' => $data['loan_tenures'],
            'percentage_increase_for_loyal_customers' => $data['percentage_increase_for_loyal_customers'],
            'loan_interest' => $data['loan_interest'],
            'default_interest' => $data['default_interest'],
            'days_to_attach_late_payment_fees' => $data['default_interest'],
            'use_credit_score_check' => app()->make(ApplicationService::class)->boolifyString($data['use_credit_score_check']),
            'use_crc_check' => app()->make(ApplicationService::class)->boolifyString($data['use_crc_check']),
            'use_first_central_check' => app()->make(ApplicationService::class)->boolifyString($data['use_first_central_check']),
            'minimum_credit_score' => $data['minimum_credit_score'],
            'days_to_make_crc_check' => $data['days_to_make_crc_check'],
            'days_to_make_first_central_check' => $data['days_to_make_first_central_check'],
            'total_amount_credited_per_day' => $data['total_amount_credited_per_day'],
            'maximum_amount_for_first_timers' => $data['maximum_amount_for_first_timers'],
            'should_give_loans' => app()->make(ApplicationService::class)->boolifyString($data['should_give_loans']),
            'emails_to_report' => $data['emails_to_report'],
            'use_crc_credit_score_check' => app()->make(ApplicationService::class)->boolifyString($data['use_crc_credit_score_check']),
            'use_first_central_credit_score_check' => app()->make(ApplicationService::class)->boolifyString($data['use_first_central_credit_score_check']),
            'minimum_credit_bureau_credit_score' => $data['minimum_credit_bureau_credit_score'],
            'maximum_outstanding_loans_to_qualify' => $data['maximum_outstanding_loans_to_qualify'],
            'bucket_offers' => [
                'bucket_0_to_9' => $data['bucket_0_to_9'],
                'bucket_10_to_19' => $data['bucket_10_to_19'],
                'bucket_20_to_29' => $data['bucket_20_to_29'],
                'bucket_30_to_39' => $data['bucket_30_to_39'],
                'bucket_40_to_49' => $data['bucket_40_to_49'],
                'bucket_50_to_59' => $data['bucket_50_to_59'],
                'bucket_60_to_69' => $data['bucket_60_to_69'],
                'bucket_70_to_79' => $data['bucket_70_to_79'],
                'bucket_80_to_89' => $data['bucket_80_to_89'],
                'bucket_90_to_100' => $data['bucket_90_to_100']
            ],
            'days_to_stop_penalty_from_accruing' => $data['days_to_stop_penalty_from_accruing'],
            'minimum_days_for_demotion' => $data['minimum_days_for_demotion'],
            'maximum_days_for_demotion' => $data['maximum_days_for_demotion'],
            'days_to_blacklist_customer' => $data['days_to_blacklist_customer']
        ]);

        return $this->sendSuccess('Settings updated successfully', 200, $setting);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function show(Setting $setting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function edit(Setting $setting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSettingRequest  $request
     * @param  \App\Models\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSettingRequest $request, Setting $setting)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function destroy(Setting $setting)
    {
        //
    }
}
