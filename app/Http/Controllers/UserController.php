<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserPasswordResetRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $users = User::with('role')->where('status', '=',1)->get();
        return view('users.list', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        $roles = Role::all();
        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request) {
        try {
            User::create($request->validated());
            return to_route('users')->with('success', 'User created successfully');
        } catch (\Exception $e) {
            Log::error('Create User Error: ', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $user = User::find($encryptedIdentifier);
        if(!$user) return back()->with('error', 'User not found');
        return view('users.view', compact('user'));
    }

    /**
     * Show the form for editing a specific user.
     */
    public function edit($encryptedIdentifier) {
        try {
            $user = User::find($encryptedIdentifier);
            if(!$user) return back()->with('error', 'User not found');
            $roles = Role::all();
            return view('users.edit', compact('user', 'roles'));
        } catch (\Exception $e) {
            Log::error('Edit User Error: ', ['error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update an existing user.
     */
    public function update(UpdateUserRequest $request, $encryptedIdentifier) {
        try {
            $user = User::findOrFail($encryptedIdentifier);
            if(!$user) return back()->withInput()->with('error', 'User not found');
            $user->update($request->validated());
        } catch (\Exception $e) {
            Log::error('Update User Error: ', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove a user from storage.
     */
    public function destroy($encryptedIdentifier) {
        try {
            $user = User::findOrFail($encryptedIdentifier);
            if(!$user) return back()->with('error', 'User not found');
            $user->delete();
            return to_route('users')->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            Log::error('Delete User Error: ', ['error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reset(UserPasswordResetRequest $request, $encryptedIdentifier) {
        try {
            $validated = $request->validated();
            $user = User::find($encryptedIdentifier);
            if(!$user) return back()->with('error', 'User not found');
            if($validated['password'] != $validated['confirm_password']) return back()->withInput()->with('error', 'Passwords do not match');
            $user->password = Hash::make($validated['password']);
            $user->save();
            $encryptedId = encryptOpenSSL($user->id);
            return to_route('user', ['encryptedIdentifier' => $encryptedId])->with('success', 'Password reset successfully');
        } catch (\Exception $e) {
            Log::error('Reset Password Error: ', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
