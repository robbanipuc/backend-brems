<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    // =========================================================
    // LIST USERS
    // =========================================================
    public function index(Request $request)
    {
        $user = $request->user();

        $query = User::with(['employee:id,first_name,last_name,designation_id', 'employee.designation:id,title', 'office:id,name']);

        // Office admins can only see users from managed offices
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereIn('office_id', $officeIds);
        }

        // Role filter
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Office filter
        if ($request->filled('office_id')) {
            $query->where('office_id', $request->office_id);
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->get());
    }

    // =========================================================
    // SHOW SINGLE USER
    // =========================================================
    public function show(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::with([
            'employee.designation',
            'employee.office',
            'office'
        ])->findOrFail($id);

        // Permission check
        if (!$authUser->isSuperAdmin()) {
            $officeIds = $authUser->getManagedOfficeIds();
            if (!in_array($user->office_id, $officeIds)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        }

        return response()->json($user);
    }

    // =========================================================
    // CREATE USER (Without Employee Link - For Super Admins)
    // =========================================================
    public function store(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'office_id' => 'required|exists:offices,id',
            'role' => 'required|in:super_admin,office_admin,verified_user',
            'employee_id' => 'nullable|exists:employees,id|unique:users,employee_id',
        ]);

        // Only super admin can create super_admin or office_admin
        if (!$authUser->isSuperAdmin()) {
            if (in_array($validated['role'], ['super_admin', 'office_admin'])) {
                return response()->json([
                    'message' => 'You do not have permission to create this role'
                ], 403);
            }

            // Check office access
            $officeIds = $authUser->getManagedOfficeIds();
            if (!in_array($validated['office_id'], $officeIds)) {
                return response()->json([
                    'message' => 'You cannot create users for this office'
                ], 403);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'office_id' => $validated['office_id'],
            'role' => $validated['role'],
            'employee_id' => $validated['employee_id'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load(['office', 'employee'])
        ], 201);
    }

    // =========================================================
    // UPDATE USER
    // =========================================================
    public function update(Request $request, $id)
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        // Permission check
        if (!$authUser->isSuperAdmin()) {
            $officeIds = $authUser->getManagedOfficeIds();
            if (!in_array($user->office_id, $officeIds)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'office_id' => 'sometimes|exists:offices,id',
            'role' => 'sometimes|in:super_admin,office_admin,verified_user',
            'is_active' => 'sometimes|boolean',
        ]);

        // Only super admin can change role to super_admin or office_admin
        if (isset($validated['role']) && !$authUser->isSuperAdmin()) {
            if (in_array($validated['role'], ['super_admin', 'office_admin'])) {
                unset($validated['role']);
            }
        }

        // Check new office access for non-super admins
        if (isset($validated['office_id']) && !$authUser->isSuperAdmin()) {
            $officeIds = $authUser->getManagedOfficeIds();
            if (!in_array($validated['office_id'], $officeIds)) {
                return response()->json([
                    'message' => 'You cannot move user to this office'
                ], 403);
            }
        }

        $user->update($validated);

        // Revoke tokens if deactivated
        if (isset($validated['is_active']) && !$validated['is_active']) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()->load(['office', 'employee'])
        ]);
    }

    // =========================================================
    // RESET USER PASSWORD (Admin)
    // =========================================================
    public function resetPassword(Request $request, $id)
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        // Permission check
        if (!$authUser->isSuperAdmin()) {
            $officeIds = $authUser->getManagedOfficeIds();
            if (!in_array($user->office_id, $officeIds)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        }

        $validated = $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. User will need to login again.'
        ]);
    }

    // =========================================================
    // TOGGLE USER ACTIVE STATUS
    // =========================================================
    public function toggleActive(Request $request, $id)
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        // Cannot deactivate self
        if ($authUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account'
            ], 422);
        }

        // Permission check
        if (!$authUser->isSuperAdmin()) {
            $officeIds = $authUser->getManagedOfficeIds();
            if (!in_array($user->office_id, $officeIds)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        }

        $user->update(['is_active' => !$user->is_active]);

        // Revoke tokens if deactivated
        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "User {$status} successfully",
            'user' => $user
        ]);
    }

    // =========================================================
    // DELETE USER
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        // Cannot delete self
        if ($authUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        // Only super admin can delete users
        if (!$authUser->isSuperAdmin()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Revoke tokens
        $user->tokens()->delete();

        // Soft delete
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // =========================================================
    // GET OFFICE ADMINS
    // =========================================================
    public function officeAdmins(Request $request)
    {
        $user = $request->user();

        $query = User::with(['office', 'employee'])
            ->where('role', 'office_admin');

        if (!$user->isSuperAdmin()) {
            // Office admin can see admins of child offices
            $office = $user->office;
            $childOfficeIds = $office->getAllChildIds();
            $query->whereIn('office_id', $childOfficeIds);
        }

        return response()->json($query->get());
    }

    // =========================================================
    // ASSIGN OFFICE ADMIN
    // =========================================================
    public function assignOfficeAdmin(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'office_id' => 'required|exists:offices,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $office = Office::findOrFail($validated['office_id']);

        // Permission check
        if (!$authUser->isSuperAdmin()) {
            // Office admin can only assign admins to child offices
            $childOfficeIds = $authUser->office->getAllChildIds();
            if (!in_array($validated['office_id'], $childOfficeIds)) {
                return response()->json([
                    'message' => 'You can only assign admins to child offices'
                ], 403);
            }
        }

        // Check if office already has an admin
        if ($office->hasAdmin()) {
            return response()->json([
                'message' => 'This office already has an active admin. Deactivate the current admin first.'
            ], 422);
        }

        $user->update([
            'role' => 'office_admin',
            'office_id' => $validated['office_id'],
        ]);

        return response()->json([
            'message' => 'Office admin assigned successfully',
            'user' => $user->fresh()->load('office')
        ]);
    }
}