<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\EmployeeResource;
class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with(['employee', 'office'])->where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email.'],
            ]);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.'],
            ]);
        }

        // Check if account is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been disabled by the administrator.'
            ], 403);
        }

        // Delete old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load(['employee.designation', 'office'])
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get authenticated user with relationships
     */
    public function user(Request $request)
    {
        $user = $request->user()->load(['employee.designation', 'office']);
        
        // Add managed office IDs for frontend routing
        $user->managed_office_ids = $user->getManagedOfficeIds();
        
        return response()->json($user);
    }

    /**
     * Change own password (available to all authenticated users)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revoke all other tokens for security
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json(['message' => 'Password changed successfully']);
    }
}