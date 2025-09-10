<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomException;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\LoginRequest;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Login to the application
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        // Get the user
        $user = User::with([
                        'role',
                        'department'
                    ])
                    ->users()
                    ->firstWhere('email', $data['username']);

        // Check if the user exists
        if (!isset($user)) {
            throw new CustomException(__('app.invalid_credentials'), 401);
        }

        // Check if the password matches
        if (!Hash::check($data['password'], $user->password)) {
            throw new CustomException(__('app.invalid_credentials'), 401);
        }

        // Login the user
        $token = $user->createToken('staff-token-'.uniqid(), $user->role->permissions)->plainTextToken;

        return $this->sendSuccess('Login successful.', 200, compact('user', 'token'));
    }

    /**
     * Logout from the application
     */
    public function logout(Request $request)
    {
        // Logout the user
        $request->user()->tokens()->delete();

        return $this->sendSuccess('Logout successful.');
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $data = $request->validated();

        // Change the password
        $request->user()->update([
            'password' => Hash::make($data['password'])
        ]);

        return $this->sendSuccess('Password changed successfully.');
    }
}
