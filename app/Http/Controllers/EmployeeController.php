<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TransferHistory;
use App\Models\PromotionHistory;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Designation;
use App\Models\AcademicRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Resources\EmployeeResource;
class EmployeeController extends Controller
{
    // =========================================================
    // LIST EMPLOYEES
    // =========================================================
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Employee::with(['office', 'designation', 'user']);

        // Apply office filter based on role
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereIn('current_office_id', $officeIds);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('name_bn', 'like', "%{$search}%")
                  ->orWhere('nid_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Office filter
        if ($request->filled('office_id')) {
            if (!$user->isSuperAdmin() && !$user->canManageOffice($request->office_id)) {
                return response()->json(['message' => 'Access denied to this office'], 403);
            }
            $query->where('current_office_id', $request->office_id);
        }

        // Designation filter
        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->designation_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Verification filter
        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->get()
        );
    }

    // =========================================================
    // GET RELEASED EMPLOYEES (For Transfer Pickup)
    // =========================================================
    public function releasedEmployees(Request $request)
    {
        $employees = Employee::with(['office', 'designation'])
            ->where('status', 'released')
            ->whereNotNull('released_at')
            ->orderBy('released_at', 'desc')
            ->get();

        return response()->json($employees);
    }

    // =========================================================
    // CREATE EMPLOYEE
    // =========================================================
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'nid_number' => 'required|string|unique:employees,nid_number',
            'designation_id' => 'required|exists:designations,id',
            'office_id' => 'nullable|exists:offices,id',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'religion' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:5',
            'marital_status' => 'nullable|string|max:20',
            'place_of_birth' => 'nullable|string|max:255',
            'height' => 'nullable|string|max:20',
            'passport' => 'nullable|string|max:50',
            'birth_reg' => 'nullable|string|max:50',
            'joining_date' => 'nullable|date',
        ]);

        // Determine target office
        $targetOfficeId = $request->office_id ?? $user->office_id;

        if (!$targetOfficeId) {
            return response()->json(['message' => 'No office specified'], 422);
        }

        // Check permission
        if (!$user->canManageOffice($targetOfficeId)) {
            return response()->json(['message' => 'You cannot add employees to this office'], 403);
        }

        try {
            $employee = DB::transaction(function () use ($validated, $targetOfficeId, $user) {
                $employee = Employee::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'name_bn' => $validated['name_bn'] ?? null,
                    'nid_number' => $validated['nid_number'],
                    'designation_id' => $validated['designation_id'],
                    'current_office_id' => $targetOfficeId,
                    'phone' => $validated['phone'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'religion' => $validated['religion'] ?? null,
                    'blood_group' => $validated['blood_group'] ?? null,
                    'marital_status' => $validated['marital_status'] ?? null,
                    'place_of_birth' => $validated['place_of_birth'] ?? null,
                    'height' => $validated['height'] ?? null,
                    'passport' => $validated['passport'] ?? null,
                    'birth_reg' => $validated['birth_reg'] ?? null,
                    'joining_date' => $validated['joining_date'] ?? now(),
                    'status' => 'active',
                    'is_verified' => false,
                ]);

                // Create initial transfer history (first posting)
                TransferHistory::create([
                    'employee_id' => $employee->id,
                    'from_office_id' => null,
                    'to_office_id' => $targetOfficeId,
                    'transfer_date' => $validated['joining_date'] ?? now(),
                    'order_number' => 'INITIAL-POSTING',
                    'remarks' => 'Initial posting',
                    'created_by' => $user->id,
                ]);

                return $employee;
            });

            return response()->json([
                'message' => 'Employee created successfully',
                'employee' => $employee->load(['office', 'designation'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Employee Create Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create employee: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    // SHOW SINGLE EMPLOYEE
    // =========================================================
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $employee = Employee::with([
            'office',
            'designation',
            'user',
            'family',
            'addresses',
            'academics',
            'transfers.fromOffice',
            'transfers.toOffice',
            'transfers.createdBy',
            'promotions.newDesignation',
            'promotions.createdBy',
        ])->findOrFail($id);

        // Permission check
        if (!$user->canViewEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Add computed fields
        $employee->current_salary = $employee->current_salary;
        $employee->can_add_spouse = $employee->canAddSpouse();
        $employee->max_spouses = $employee->getMaxSpouses();
        $employee->active_spouse_count = $employee->activeSpouses()->count();

        return response()->json($employee);
    }

    // =========================================================
    // UPDATE EMPLOYEE (Basic Info)
    // =========================================================
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        // Permission check
        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'nid_number' => 'sometimes|string|unique:employees,nid_number,' . $id,
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'religion' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:5',
            'marital_status' => 'nullable|string|max:20',
            'place_of_birth' => 'nullable|string|max:255',
            'height' => 'nullable|string|max:20',
            'passport' => 'nullable|string|max:50',
            'birth_reg' => 'nullable|string|max:50',
        ]);

        $employee->update($validated);

        return response()->json([
            'message' => 'Employee updated successfully',
            'employee' => $employee->fresh()->load(['office', 'designation'])
        ]);
    }

    // =========================================================
    // UPDATE FULL PROFILE (Bio + Family + Address + Academics)
    // =========================================================
    public function updateFullProfile(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        // Permission check
        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        try {
            DB::transaction(function () use ($employee, $request) {
                // 1. Update basic info
                $basicFields = $request->only([
                    'first_name', 'last_name', 'name_bn', 'nid_number', 'phone',
                    'gender', 'dob', 'religion', 'blood_group', 'marital_status',
                    'place_of_birth', 'height', 'passport', 'birth_reg'
                ]);
                
                if (!empty($basicFields)) {
                    $employee->update($basicFields);
                }

                // 2. Update Family Members
                if ($request->has('family')) {
                    $this->updateFamilyMembers($employee, $request->family);
                }

                // 3. Update Addresses
                if ($request->has('addresses')) {
                    $this->updateAddresses($employee, $request->addresses);
                }

                // 4. Update Academics
                if ($request->has('academics')) {
                    $this->updateAcademics($employee, $request->academics);
                }
            });

            return response()->json([
                'message' => 'Profile updated successfully',
                'employee' => $employee->fresh()->load(['family', 'addresses', 'academics', 'office', 'designation'])
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Employee Full Update Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    // FAMILY MEMBER MANAGEMENT
    // =========================================================
    private function updateFamilyMembers(Employee $employee, array $familyData): void
    {
        // Father
        if (isset($familyData['father'])) {
            $this->upsertParent($employee, 'father', $familyData['father']);
        }

        // Mother
        if (isset($familyData['mother'])) {
            $this->upsertParent($employee, 'mother', $familyData['mother']);
        }

        // Spouses
        if (isset($familyData['spouses']) && is_array($familyData['spouses'])) {
            $this->updateSpouses($employee, $familyData['spouses']);
        }

        // Children
        if (isset($familyData['children']) && is_array($familyData['children'])) {
            $this->updateChildren($employee, $familyData['children']);
        }
    }

    private function upsertParent(Employee $employee, string $relation, array $data): void
    {
        if (empty($data['name'])) {
            // If name is empty, delete existing record
            $employee->family()->where('relation', $relation)->delete();
            return;
        }

        $employee->family()->updateOrCreate(
            ['relation' => $relation],
            [
                'name' => $data['name'],
                'name_bn' => $data['name_bn'] ?? null,
                'nid' => $data['nid'] ?? null,
                'dob' => $data['dob'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'is_alive' => $data['is_alive'] ?? true,
            ]
        );
    }

    private function updateSpouses(Employee $employee, array $spouses): void
    {
        // Count active marriages
        $activeCount = collect($spouses)->where('is_active_marriage', true)->count();
        $maxAllowed = $employee->getMaxSpouses();

        if ($activeCount > $maxAllowed) {
            throw ValidationException::withMessages([
                'spouses' => ["Maximum {$maxAllowed} active spouse(s) allowed for {$employee->gender} employees."]
            ]);
        }

        // Delete existing spouses and recreate
        $employee->family()->where('relation', 'spouse')->delete();

        foreach ($spouses as $spouse) {
            if (empty($spouse['name'])) continue;

            $employee->family()->create([
                'relation' => 'spouse',
                'name' => $spouse['name'],
                'name_bn' => $spouse['name_bn'] ?? null,
                'nid' => $spouse['nid'] ?? null,
                'dob' => $spouse['dob'] ?? null,
                'occupation' => $spouse['occupation'] ?? null,
                'is_active_marriage' => $spouse['is_active_marriage'] ?? true,
                'is_alive' => $spouse['is_alive'] ?? true,
            ]);
        }
    }

    private function updateChildren(Employee $employee, array $children): void
    {
        $employee->family()->where('relation', 'child')->delete();

        foreach ($children as $child) {
            if (empty($child['name'])) continue;

            if (empty($child['gender'])) {
                throw ValidationException::withMessages([
                    'children' => ['Gender must be specified for each child.']
                ]);
            }

            $employee->family()->create([
                'relation' => 'child',
                'name' => $child['name'],
                'name_bn' => $child['name_bn'] ?? null,
                'gender' => $child['gender'],
                'dob' => $child['dob'] ?? null,
                'birth_certificate_path' => $child['birth_certificate_path'] ?? null,
                'is_alive' => $child['is_alive'] ?? true,
            ]);
        }
    }

    // =========================================================
    // ADDRESS MANAGEMENT
    // =========================================================
    private function updateAddresses(Employee $employee, array $addresses): void
    {
        foreach (['present', 'permanent'] as $type) {
            if (isset($addresses[$type])) {
                $addr = $addresses[$type];
                
                // Check if address has any data
                $hasData = !empty($addr['division']) || !empty($addr['district']) || 
                           !empty($addr['village_road']) || !empty($addr['house_no']);

                if ($hasData) {
                    $employee->addresses()->updateOrCreate(
                        ['type' => $type],
                        [
                            'division' => $addr['division'] ?? null,
                            'district' => $addr['district'] ?? null,
                            'upazila' => $addr['upazila'] ?? null,
                            'post_office' => $addr['post_office'] ?? null,
                            'house_no' => $addr['house_no'] ?? null,
                            'village_road' => $addr['village_road'] ?? null,
                        ]
                    );
                } else {
                    // If no data, delete existing address
                    $employee->addresses()->where('type', $type)->delete();
                }
            }
        }
    }

    // =========================================================
    // ACADEMIC RECORDS MANAGEMENT
    // =========================================================
    private function updateAcademics(Employee $employee, array $academics): void
    {
        $employee->academics()->delete();

        foreach ($academics as $record) {
            if (empty($record['exam_name'])) continue;

            // Validate exam name
            if (!AcademicRecord::isValidExamName($record['exam_name'])) {
                throw ValidationException::withMessages([
                    'academics' => ["Invalid exam name: {$record['exam_name']}. Allowed values: " . implode(', ', AcademicRecord::EXAM_NAMES)]
                ]);
            }

            $employee->academics()->create([
                'exam_name' => $record['exam_name'],
                'institute' => $record['institute'] ?? null,
                'passing_year' => $record['passing_year'] ?? null,
                'result' => $record['result'] ?? null,
                'certificate_path' => $record['certificate_path'] ?? null,
            ]);
        }
    }

    // =========================================================
    // VERIFY EMPLOYEE
    // =========================================================
    public function verify(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $employee->update(['is_verified' => true]);

        return response()->json([
            'message' => 'Employee verified successfully',
            'employee' => $employee
        ]);
    }

    // =========================================================
    // RELEASE EMPLOYEE (For Transfer)
    // =========================================================
    public function release(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($employee->status !== 'active') {
            return response()->json(['message' => 'Only active employees can be released'], 422);
        }

        $validated = $request->validate([
            'release_date' => 'required|date',
            'remarks' => 'nullable|string|max:500',
        ]);

        $employee->update([
            'status' => 'released',
            'released_at' => $validated['release_date'],
        ]);

        return response()->json([
            'message' => 'Employee released for transfer',
            'employee' => $employee
        ]);
    }

    // =========================================================
    // TRANSFER EMPLOYEE
    // =========================================================
    public function transfer(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'to_office_id' => 'required|exists:offices,id',
            'transfer_date' => 'required|date',
            'order_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Super admin can transfer anyone
        // Office admin can only pick up released employees from other offices
        if (!$user->isSuperAdmin()) {
            // Check if admin can manage the target office
            if (!$user->canManageOffice($validated['to_office_id'])) {
                return response()->json(['message' => 'You cannot transfer to this office'], 403);
            }

            // If employee is from another office, they must be released
            if ($employee->current_office_id !== $user->office_id) {
                if ($employee->status !== 'released') {
                    return response()->json(['message' => 'Employee must be released by their current office first'], 422);
                }
            }
        }

        try {
            DB::transaction(function () use ($employee, $validated, $user, $request) {
                $attachmentPath = null;
                if ($request->hasFile('attachment')) {
                    $attachmentPath = $request->file('attachment')->store('transfers', 'public');
                }

                // Create transfer record
                TransferHistory::create([
                    'employee_id' => $employee->id,
                    'from_office_id' => $employee->current_office_id,
                    'to_office_id' => $validated['to_office_id'],
                    'transfer_date' => $validated['transfer_date'],
                    'order_number' => $validated['order_number'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'remarks' => $validated['remarks'] ?? null,
                    'created_by' => $user->id,
                ]);

                // Update employee
                $employee->update([
                    'current_office_id' => $validated['to_office_id'],
                    'status' => 'active',
                    'released_at' => null,
                ]);

                // Update user's office if exists
                if ($employee->user) {
                    $employee->user->update(['office_id' => $validated['to_office_id']]);
                }
            });

            return response()->json([
                'message' => 'Employee transferred successfully',
                'employee' => $employee->fresh()->load(['office', 'designation'])
            ]);

        } catch (\Exception $e) {
            Log::error('Transfer Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to transfer employee'], 500);
        }
    }

    // =========================================================
    // PROMOTE EMPLOYEE (Super Admin Only)
    // =========================================================
    public function promote(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::with('designation')->findOrFail($id);

        $validated = $request->validate([
            'new_designation_id' => 'required|exists:designations,id',
            'promotion_date' => 'required|date',
            'order_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Check if it's actually a promotion (new designation must be different)
        if ($employee->designation_id == $validated['new_designation_id']) {
            return response()->json(['message' => 'New designation must be different from current'], 422);
        }

        try {
            DB::transaction(function () use ($employee, $validated, $user, $request) {
                $attachmentPath = null;
                if ($request->hasFile('attachment')) {
                    $attachmentPath = $request->file('attachment')->store('promotions', 'public');
                }

                // Create promotion record
                PromotionHistory::create([
                    'employee_id' => $employee->id,
                    'new_designation_id' => $validated['new_designation_id'],
                    'promotion_date' => $validated['promotion_date'],
                    'order_number' => $validated['order_number'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'remarks' => $validated['remarks'] ?? null,
                    'created_by' => $user->id,
                ]);

                // Update employee's designation
                $employee->update([
                    'designation_id' => $validated['new_designation_id'],
                ]);
            });

            return response()->json([
                'message' => 'Employee promoted successfully',
                'employee' => $employee->fresh()->load(['office', 'designation'])
            ]);

        } catch (\Exception $e) {
            Log::error('Promotion Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to promote employee'], 500);
        }
    }

    // =========================================================
    // RETIRE EMPLOYEE (Super Admin Only)
    // =========================================================
    public function retire(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        if ($employee->status === 'retired') {
            return response()->json(['message' => 'Employee is already retired'], 422);
        }

        $employee->update(['status' => 'retired']);

        // Disable user account if exists
        if ($employee->user) {
            $employee->user->update(['is_active' => false]);
            $employee->user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Employee retired successfully',
            'employee' => $employee
        ]);
    }

    // =========================================================
    // USER ACCOUNT MANAGEMENT
    // =========================================================
    public function manageAccess(Request $request, $id)
    {
        $authUser = $request->user();
        $employee = Employee::with('user')->findOrFail($id);

        if (!$authUser->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $existingUser = $employee->user;

        // CREATE NEW ACCOUNT
        if (!$existingUser) {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'nullable|string|min:6',
                'role' => 'required|in:office_admin,verified_user',
            ]);

            // Office admins can only create verified_user accounts
            if (!$authUser->isSuperAdmin() && $validated['role'] === 'office_admin') {
                return response()->json(['message' => 'Only super admin can create office admin accounts'], 403);
            }

            $newUser = User::create([
                'employee_id' => $employee->id,
                'office_id' => $employee->current_office_id,
                'name' => $employee->full_name,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password'] ?? '123456'),
                'role' => $validated['role'],
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'User account created successfully',
                'user' => $newUser
            ], 201);
        }

        // UPDATE EXISTING ACCOUNT
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email,' . $existingUser->id,
            'is_active' => 'required|boolean',
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|in:super_admin,office_admin,verified_user',
        ]);

        // Only super admin can change roles
        if (isset($validated['role']) && !$authUser->isSuperAdmin()) {
            unset($validated['role']);
        }

        $existingUser->email = $validated['email'];
        $existingUser->is_active = $validated['is_active'];

        if (isset($validated['role'])) {
            $existingUser->role = $validated['role'];
        }

        if (!empty($validated['password'])) {
            $existingUser->password = Hash::make($validated['password']);
        }

        $existingUser->save();

        // Revoke tokens if account disabled
        if (!$validated['is_active']) {
            $existingUser->tokens()->delete();
        }

        return response()->json([
            'message' => 'User account updated successfully',
            'user' => $existingUser
        ]);
    }

    // =========================================================
    // PHOTO UPLOAD
    // =========================================================
    public function uploadPhoto(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Delete old photo
        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
        }

        $path = $request->file('photo')->store('photos', 'public');
        $employee->update(['profile_picture' => $path]);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    // =========================================================
    // DOCUMENT UPLOAD (NID, Birth Certificate)
    // =========================================================
    public function uploadDocument(Request $request, $id)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($id);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validated = $request->validate([
            'document_type' => 'required|in:nid,birth_certificate',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $column = $validated['document_type'] === 'nid' ? 'nid_file_path' : 'birth_file_path';
        $folder = $validated['document_type'] === 'nid' ? 'documents/nid' : 'documents/birth';

        // Delete old file
        if ($employee->$column) {
            Storage::disk('public')->delete($employee->$column);
        }

        $path = $request->file('document')->store($folder, 'public');
        $employee->update([$column => $path]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    // =========================================================
    // DELETE EMPLOYEE (Super Admin Only)
    // =========================================================
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        // Delete user account if exists
        if ($employee->user) {
            $employee->user->tokens()->delete();
            $employee->user->delete();
        }

        // Delete profile picture
        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
        }

        // Soft delete employee
        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    // =========================================================
    // EXPORT FUNCTIONS
    // =========================================================
    public function exportCSV(Request $request)
    {
        $user = $request->user();
        $query = Employee::with(['office', 'designation']);

        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereIn('current_office_id', $officeIds);
        }

        // Apply filters
        if ($request->filled('office_id')) {
            $query->where('current_office_id', $request->office_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $employees = $query->orderBy('first_name')->get();

        $filename = "employees_export_" . date('Y-m-d_His') . ".csv";
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($employees) {
            $file = fopen('php://output', 'w');
            
            // BOM for UTF-8 (for Bangla support)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                'ID', 'Name (English)', 'Name (Bangla)', 'Designation', 'Office', 
                'NID', 'Phone', 'Gender', 'Date of Birth', 'Religion', 
                'Blood Group', 'Status', 'Verified', 'Joining Date'
            ]);

            foreach ($employees as $emp) {
                fputcsv($file, [
                    $emp->id,
                    $emp->full_name,
                    $emp->name_bn ?? '',
                    $emp->designation->title ?? 'N/A',
                    $emp->office->name ?? 'N/A',
                    $emp->nid_number,
                    $emp->phone ?? '',
                    ucfirst($emp->gender ?? ''),
                    $emp->dob?->format('Y-m-d') ?? '',
                    $emp->religion ?? '',
                    $emp->blood_group ?? '',
                    ucfirst($emp->status),
                    $emp->is_verified ? 'Yes' : 'No',
                    $emp->joining_date?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPDF(Request $request)
    {
        $user = $request->user();
        $query = Employee::with(['office', 'designation']);

        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereIn('current_office_id', $officeIds);
        }

        if ($request->filled('office_id')) {
            $query->where('current_office_id', $request->office_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $employees = $query->orderBy('first_name')->get();

        $data = [
            'title' => 'Employee Directory - Bangladesh Railway',
            'date' => date('d M Y'),
            'generated_by' => $user->name,
            'employees' => $employees,
            'total_count' => $employees->count(),
        ];

        $pdf = Pdf::loadView('reports.employee_list', $data)->setPaper('a4', 'landscape');
        return $pdf->download('employee_directory_' . date('Y-m-d') . '.pdf');
    }

    public function downloadProfilePdf($id)
    {
        $user = request()->user();
        $employee = Employee::with([
            'designation', 'office', 'academics', 'family', 
            'addresses', 'transfers.toOffice', 'promotions.newDesignation'
        ])->findOrFail($id);

        if (!$user->canViewEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $data = [
            'title' => 'Employee Service Profile',
            'date' => date('d M Y'),
            'emp' => $employee,
        ];

        $pdf = Pdf::loadView('reports.employee_profile', $data);
        return $pdf->download($employee->nid_number . '_profile.pdf');
    }
}