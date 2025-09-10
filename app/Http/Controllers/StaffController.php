<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use App\Http\Requests\{StoreStaffRequest, UpdateStaffRequest};
use App\Models\User;
use App\Services\Phone\Nigeria as NigerianPhone;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page') ?? config('quickfund.per_page');
            
        $staff = User::with([
                        'role',
                        'department',
                    ])
                    ->staff()
                    ->latest()
                    ->paginate($perPage);

        return $this->sendSuccess(__('app.request_successful'), 200, $staff);
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreStaffRequest $request)
    {
        $data = $request->validated();

        $staff = DB::transaction(function() use ($data) {
            // Create the user
            $staff = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => app()->make(NigerianPhone::class)->convert($data['phone']),
                'password' => Hash::make($data['password'])
            ]);

            // Assign the role and the department to the staff
            $staff->forceFill([
                'role_id' => $data['role_id'],
                'department_id' => $data['department_id'],
            ])->save();

            // Verify the staff
            $staff->markEmailAsVerified();

            return $staff;
        });

        return $this->sendSuccess('Staff created successfully.', 201, $staff->load([
            'role',
            'department',
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($userId)
    {
        $staff = User::with([
                        'role',
                        'department'
                    ])
                    ->staff()
                    ->find($userId);

        if (!isset($staff)) {
            throw new CustomException('Staff not found.', 404);
        }

        return $this->sendSuccess(__('app.request_successful'), 200, $staff);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateStaffRequest $request, $userId)
    {
        $staff = User::with([
                    'role',
                    'department'
                ])
                ->staff()
                ->find($userId);

        if (!isset($staff)) {
            throw new CustomException('Staff not found.', 404);
        }

        $data = $request->validated();

        // Update the staff details
        $staff->forceFill([
            'role_id' => $data['role_id'],
            'department_id' => $data['department_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => app()->make(NigerianPhone::class)->convert($data['phone']),
            'password' => Hash::make($data['password'])
        ])->save();

        return $this->sendSuccess('Staff updated successfully.', 200, $staff);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($userId)
    {
        $staff = User::with([
                        'role',
                        'department'
                    ])
                    ->staff()
                    ->find($userId);

        if (!isset($staff)) {
            throw new CustomException('Staff not found.', 404);
        }

        $staff->delete();

        return $this->sendSuccess('Staff deleted successfully.');
    }
}
