<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomException;
use App\Http\Requests\{StoreRoleRequest, UpdateRoleRequest};
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::latest()
                    ->get();

        return $this->sendSuccess(__('app.request_successful'), 200, $roles);
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
     * @param  \App\Http\Requests\StoreRoleRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();

        // Create the role
        $role = Role::create([
            'name' => $data['name'],
            'permissions' => array_unique(array_merge(config('quickfund.default_permissions'), $data['permissions']))
        ]);

        return $this->sendSuccess('Role created successfully.', 201, $role);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        return $this->sendSuccess(__('app.request_successful'), 200, $role);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit(Role $role)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateRoleRequest  $request
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        // We prevent the update of "administrator" role
        if (in_array($role->id, [
            Role::ADMINISTRATOR
        ])) {
            throw new CustomException('Cannot update role', 403);
        }

        $data = $request->validated();

        $role->update([
            'name' => $data['name'],
            'permissions' => array_unique(array_merge(config('quickfund.default_permissions'), $data['permissions']))
        ]);

        return $this->sendSuccess('Role updated successfully.', 200, $role);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        // We prevent the delete of "administrator" role
        if (in_array($role->id, [
            Role::ADMINISTRATOR
        ])) {
            throw new CustomException('Cannot delete role', 403);
        }

        $role->delete();

        return $this->sendSuccess('Role deleted successfully.');
    }
}
