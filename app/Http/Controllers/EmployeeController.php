<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TransferHistory; // <--- This was likely missing
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // <--- This was likely missing
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf; // <--- Add this
use Illuminate\Support\Facades\Response; // <--- Add this

class EmployeeController extends Controller
{

    // Add a new Employee
    // App\Http\Controllers\EmployeeController.php

    public function store(Request $request)
    {
        try {
            // 1. Validate Input
            $validated = $request->validate([
                'first_name' => 'required|string',
                'last_name'  => 'required|string',
                'nid_number' => 'required|unique:employees,nid_number', // Checks active users
                // MUST send an ID (e.g., 1), NOT a string (e.g., "Manager")
                'designation_id' => 'required|exists:designations,id', 
                'current_salary' => 'required|numeric',
                // Optional: Allow Admin to specify office, otherwise fallback to their own
                'office_id' => 'nullable|exists:offices,id' 
            ]);

            $user = $request->user();

            // 2. Determine Office ID
            // Priority: Request Input -> User's Office -> Error
            $targetOfficeId = $request->office_id ?? $user->office_id;

            if (!$targetOfficeId) {
                return response()->json(['message' => 'Error: No Office ID provided and Admin has no Office assigned.'], 422);
            }

            // 3. Create Employee
            $employee = Employee::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'nid_number' => $request->nid_number,
                'designation_id' => $request->designation_id,
                'current_salary' => $request->current_salary,
                'current_office_id' => $targetOfficeId,
                'status' => 'active',
                'is_verified' => false
            ]);

            return response()->json($employee, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return the specific validation error fields
            return response()->json(['message' => 'Validation Failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Log the deep error for debugging
            \Illuminate\Support\Facades\Log::error('Employee Create Error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error', 'details' => $e->getMessage()], 500);
        }
    }

    // Verify an Employee
    public function verify($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['is_verified' => true]);
        return response()->json(['message' => 'Employee Verified Successfully']);
    }

    // Transfer Employee
    public function transfer(Request $request, $id)
    {
        $request->validate([
            'target_office_id' => 'required|exists:offices,id',
            'transfer_date' => 'required|date'
        ]);

        $employee = Employee::findOrFail($id);
        $oldOffice = $employee->current_office_id;

        // Transaction ensures both happen or neither happens
        DB::transaction(function () use ($employee, $request, $oldOffice) {
            
            // 1. Create History Record
            TransferHistory::create([
                'employee_id' => $employee->id,
                'from_office_id' => $oldOffice,
                'to_office_id' => $request->target_office_id,
                'transfer_date' => $request->transfer_date,
                'order_number' => $request->order_number ?? 'N/A'
            ]);

            // 2. Move the Employee
            $employee->update([
                'current_office_id' => $request->target_office_id,
                'status' => 'active'
            ]);
        });

        return response()->json(['message' => 'Employee Transferred Successfully']);
    }

    // Get Single Employee with History
    // In EmployeeController.php
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::with(['office', 'transfers.fromOffice', 'transfers.toOffice', 'promotions'])
                            ->findOrFail($id);

        // CHECK PERMISSIONS
        
        // 1. If Regular Employee: Can ONLY see their own profile
        if ($user->role === 'verified_user') {
            if ($user->employee_id != $id) {
                return response()->json(['message' => 'Unauthorized access to this profile.'], 403);
            }
        }

        // 2. If Office Admin: Can ONLY see employees in their office
        if ($user->role === 'office_admin') {
            if ($employee->current_office_id != $user->office_id) {
                return response()->json(['message' => 'This employee belongs to another station.'], 403);
            }
        }

        return Employee::with(['office', 'user', 'transfers', 'promotions'])->findOrFail($id);
    }

    // Promote Employee
    public function promote(Request $request, $id)
    {
        // 1. Validate ID, not just string
        $request->validate([
            'new_designation_id' => 'required|exists:designations,id',
            'new_salary' => 'required|numeric',
            'promotion_date' => 'required|date'
        ]);

        $employee = Employee::with('designation')->findOrFail($id);
        
        // Find the object for the NEW designation to get its Title for history
        $newDesignationObj = \App\Models\Designation::findOrFail($request->new_designation_id);

        DB::transaction(function () use ($employee, $request, $newDesignationObj) {
            // 2. Create History
            // Note: We use the relationship $employee->designation->title for the old name
            \App\Models\PromotionHistory::create([
                'employee_id' => $employee->id,
                'old_designation' => $employee->designation->title ?? 'Unknown', // Get String
                'new_designation' => $newDesignationObj->title, // Get String
                'old_salary' => $employee->current_salary,
                'new_salary' => $request->new_salary,
                'promotion_date' => $request->promotion_date
            ]);

            // 3. Update Employee Profile
            // CRITICAL FIX: Update 'designation_id', not 'designation'
            $employee->update([
                'designation_id' => $request->new_designation_id,
                'current_salary' => $request->new_salary
            ]);
        });

        return response()->json(['message' => 'Employee Promoted Successfully']);
    }

    // Grant System Access to an Employee
    // In EmployeeController.php, update the createLogin function:

    // App\Http\Controllers\EmployeeController.php

    public function createLogin(Request $request, $id)
    {
        $request->validate(['email' => 'required|email|unique:users,email']);
        
        // Eager load the designation relationship
        $employee = Employee::with('designation')->findOrFail($id);

        // 1. Get role from the related Designation model
        // If no default_role is set, fallback to 'verified_user'
        $roleToAssign = $employee->designation->default_role ?? 'verified_user';

        $user = \App\Models\User::create([
            'name' => $employee->first_name . ' ' . $employee->last_name,
            'email' => $request->email, 
            'password' => \Illuminate\Support\Facades\Hash::make('123456'), 
            'office_id' => $employee->current_office_id,
            'role' => $roleToAssign, 
            'employee_id' => $employee->id
        ]);

        return response()->json([
            'message' => "Login Created! Assigned Role: " . strtoupper($roleToAssign),
            'email' => $user->email,
            'role' => $roleToAssign
        ]);
    }

    public function changePassword(Request $request) {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed' // requires new_password_confirmation field
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password incorrect'], 400);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    // Search Employees
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Employee::with('office');

        // CASE A: Regular Employee -> RETURN NOTHING
        if ($user->role === 'verified_user') {
            return response()->json([]); 
        }

        // CASE B: Office Admin -> ONLY THEIR OFFICE
        if ($user->role === 'office_admin') {
            $query->where('current_office_id', $user->office_id);
        }

        // CASE C: Super Admin -> ALL (No filter needed)

        // Search Logic
        if ($request->has('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('nid_number', 'like', "%{$s}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    // Upload Profile Picture
    public function uploadPhoto(Request $request, $id) {
        $request->validate(['image' => 'required|image|max:2048']);
        $employee = Employee::findOrFail($id);
        
        // Delete old photo if it exists
        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
        }
        
        $path = $request->file('image')->store('photos', 'public');
        
        $employee->update(['profile_picture' => $path]);
        return response()->json(['path' => $path]);
    }

        // Delete Employee
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        
        // 1. Delete associated User Account (if they have a login)
        $user = \App\Models\User::where('employee_id', $id)->first();
        if ($user) {
            $user->tokens()->delete(); // Remove auth tokens
            $user->delete(); // Delete the user login
        }

        // 2. Delete Profile Picture from storage
        if ($employee->profile_picture) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($employee->profile_picture);
        }

        // 3. Delete the Employee Record
        // (Cascading delete in MySQL will handle transfers/promotions automatically 
        // IF your migrations were set up correctly. If not, this forces it.)
        $employee->delete();
        
        return response()->json(['message' => 'Employee and all associated data deleted.']);
    }

        // App/Http/Controllers/EmployeeController.php

    public function manageAccess(Request $request, $id) {
        $employee = Employee::with('user', 'designation')->findOrFail($id);
        $user = $employee->user;

        // SCENARIO 1: CREATE NEW ACCOUNT
        if (!$user) {
            $request->validate(['email' => 'required|email|unique:users,email']);
            
            $roleToAssign = $employee->designation->default_role ?? 'verified_user';

            $newUser = \App\Models\User::create([
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'email' => $request->email, 
                'password' => \Illuminate\Support\Facades\Hash::make('123456'), // Default
                'office_id' => $employee->current_office_id,
                'role' => $roleToAssign,
                'employee_id' => $employee->id,
                'is_active' => true
            ]);

            return response()->json(['message' => 'Account Created Successfully', 'user' => $newUser]);
        }

        // SCENARIO 2: UPDATE EXISTING ACCOUNT
        // Validate email but ignore the current user's own ID (so they can keep their email)
        $request->validate([
            'email' => 'required|email|unique:users,email,'.$user->id,
            'is_active' => 'required|boolean',
            'password' => 'nullable|min:6'
        ]);

        // 1. Update Basic Info
        $user->email = $request->email;
        $user->is_active = $request->is_active;

        // 2. Update Password ONLY if provided
        if ($request->filled('password')) {
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $user->save();

        // If pausing account, revoke all current login tokens immediately
        if (!$request->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Access settings updated successfully', 'user' => $user]);
    }

    // EXPORT TO CSV
    public function exportCSV()
    {
        $employees = Employee::with('office', 'designation')->get();
        $filename = "employees-export-" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use ($employees) {
            $file = fopen('php://output', 'w');
            
            // Header Row
            fputcsv($file, ['ID', 'First Name', 'Last Name', 'NID', 'Designation', 'Office', 'Phone', 'Status']);

            // Data Rows
            foreach ($employees as $emp) {
                fputcsv($file, [
                    $emp->id,
                    $emp->first_name,
                    $emp->last_name,
                    $emp->nid_number,
                    $emp->designation->title ?? 'N/A',
                    $emp->office->name ?? 'Unassigned',
                    $emp->phone,
                    $emp->status
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // EXPORT TO PDF
    public function exportPDF()
    {
        $employees = Employee::with('office', 'designation')->get();
        
        $data = [
            'title' => 'Official Employee List - Bangladesh Railway',
            'date' => date('d/m/Y'),
            'employees' => $employees
        ];

        $pdf = Pdf::loadView('reports.employee_list', $data);
        return $pdf->download('railway_employees.pdf');
    }
}