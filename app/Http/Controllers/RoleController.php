<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $roles = Role::all();
        return view('roles.list', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        return view('roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request) {
        try {
            Role::create($request->validated());
            return redirect('roles')->with('success', 'Role created successfully');
        } catch (\Exception $e) {
            Log::error('Create Role Error: ', ['error' => $e->getMessage()]);
            return redirect('roles')->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($encryptedIdentifier) {
        try {
            $role = Role::findOrFail($encryptedIdentifier);
            return view('roles.edit', compact('role'));
        }catch (\Exception $e) {
            Log::error('Edit Role Error: ', ['error' => $e->getMessage()]);
            return redirect('roles')->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $encryptedIdentifier) {
        try {
            $role = Role::findOrFail($encryptedIdentifier);
            $role->update($request->validated());
            return redirect('roles')->with('success', 'Role updated successfully');
        } catch (\Exception $e) {
            Log::error('Update Role Error: ', ['error' => $e->getMessage()]);
            return redirect('roles')->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        try {
            $role = Role::findOrFail($encryptedIdentifier);
            $role->delete();
            return redirect('roles')->with('success', 'Role deleted successfully');
        } catch (\Exception $e) {
            Log::error('Delete Role Error: ', ['error' => $e->getMessage()]);
            return redirect('roles')->with('error', $e->getMessage());
        }
    }
}
