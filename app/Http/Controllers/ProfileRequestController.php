<?php

namespace App\Http\Controllers;

use App\Models\ProfileRequest;
use App\Models\Employee;
use App\Models\FamilyMember;
use App\Models\Address;
use App\Models\AcademicRecord;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class ProfileRequestController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }
    // =========================================================
    // LIST REQUESTS
    // =========================================================
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ProfileRequest::with([
            'employee.designation',
            'employee.office',
            'reviewedBy'
        ])->latest();

        if ($user->isSuperAdmin()) {
            // Super admin: optional filter by office(s)
            if ($request->filled('office_id')) {
                $officeIds = is_array($request->office_id) ? $request->office_id : [$request->office_id];
                $query->whereHas('employee', function ($eq) use ($officeIds) {
                    $eq->whereIn('current_office_id', $officeIds);
                });
            }
        } elseif ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();

            $query->where(function ($q) use ($officeIds, $user) {
                $q->whereHas('employee', function ($eq) use ($officeIds) {
                    $eq->whereIn('current_office_id', $officeIds);
                });

                if ($user->employee_id) {
                    $q->orWhere('employee_id', $user->employee_id);
                }
            });
        } else {
            // Verified user sees only their own requests
            if (!$user->employee_id) {
                return response()->json([]);
            }
            $query->where('employee_id', $user->employee_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term, $search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('request_type', 'like', $term)
                    ->orWhere('details', 'like', $term)
                    ->orWhereHas('employee', function ($eq) use ($term) {
                        $eq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term]);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    // =========================================================
    // MY REQUESTS
    // =========================================================
    public function myRequests(Request $request)
    {
        $user = $request->user();

        if (!$user->employee_id) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile'
            ], 422);
        }

        $requests = ProfileRequest::with(['reviewedBy'])
            ->where('employee_id', $user->employee_id)
            ->latest()
            ->get();

        return response()->json($requests);
    }

    // =========================================================
    // PENDING REQUESTS
    // =========================================================
    public function pending(Request $request)
    {
        $user = $request->user();

        if ($user->isVerifiedUser()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = ProfileRequest::with([
            'employee.designation',
            'employee.office'
        ])
            ->where('status', 'pending')
            ->latest();

        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        return response()->json($query->get());
    }

    // =========================================================
    // SHOW SINGLE REQUEST
    // =========================================================
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::with([
            'employee.designation',
            'employee.office',
            'employee.family',
            'employee.addresses',
            'employee.academics',
            'reviewedBy'
        ])->findOrFail($id);

        if (!$this->canAccessRequest($user, $profileRequest)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Add current employee data for comparison
        $profileRequest->current_data = $this->getCurrentEmployeeData($profileRequest->employee);

        // Normalize proposed_changes
        $profileRequest->setAttribute(
            'proposed_changes',
            $this->normalizeProposedChanges($profileRequest->proposed_changes, $profileRequest)
        );

        return response()->json($profileRequest);
    }

    private function normalizeProposedChanges($proposedChanges, ProfileRequest $profileRequest): array
    {
        $data = $proposedChanges;
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }

    // =========================================================
    // STORE REQUEST
    // =========================================================
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->employee_id) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile'
            ], 422);
        }

        // Check for existing pending request
        $existingPending = ProfileRequest::where('employee_id', $user->employee_id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return response()->json([
                'message' => 'You already have a pending request. Please wait for it to be processed.',
                'existing_request_id' => $existingPending->id
            ], 422);
        }

        $validated = $request->validate([
            'request_type' => 'required|string|max:100',
            'details' => 'nullable|string|max:2000',
            'proposed_changes' => 'required',
        ]);

        try {
            $proposedChanges = is_string($validated['proposed_changes'])
                ? json_decode($validated['proposed_changes'], true)
                : $validated['proposed_changes'];

            if (!is_array($proposedChanges)) {
                return response()->json([
                    'message' => 'Invalid proposed_changes format'
                ], 422);
            }

            // Get employee for enriching pending documents with current file paths
            $employee = Employee::with(['academics', 'family'])->find($user->employee_id);

            // Enrich pending_documents with current file paths for admin comparison
            if (isset($proposedChanges['pending_documents']) && is_array($proposedChanges['pending_documents'])) {
                $proposedChanges['pending_documents'] = $this->enrichPendingDocuments(
                    $proposedChanges['pending_documents'],
                    $employee
                );
            }

            $profileRequest = ProfileRequest::create([
                'employee_id' => $user->employee_id,
                'request_type' => $validated['request_type'],
                'details' => $validated['details'] ?? null,
                'proposed_changes' => $proposedChanges,
                'status' => 'pending',
            ]);

            Log::info('Created ProfileRequest #' . $profileRequest->id . ' for employee #' . $user->employee_id);

            return response()->json([
                'message' => 'Request submitted successfully. You will be notified once it is reviewed.',
                'request' => $profileRequest->load('employee')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Profile Request Store Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enrich pending documents with current file paths for admin comparison
     */
    private function enrichPendingDocuments(array $pendingDocuments, Employee $employee): array
    {
        foreach ($pendingDocuments as $key => $doc) {
            // Employee field documents
            if (isset($doc['field'])) {
                $field = $doc['field'];
                if (in_array($field, ['profile_picture', 'nid_file_path', 'birth_file_path'])) {
                    $pendingDocuments[$key]['current_file_path'] = $employee->$field;
                }
            }
            
            // Academic certificates
            if (isset($doc['academic_id'])) {
                $academic = $employee->academics->firstWhere('id', $doc['academic_id']);
                if ($academic) {
                    $pendingDocuments[$key]['current_file_path'] = $academic->certificate_path;
                    $pendingDocuments[$key]['academic_exam_name'] = $academic->exam_name;
                }
            }
            
            // Child birth certificates
            if (isset($doc['family_member_id'])) {
                $member = $employee->family->firstWhere('id', $doc['family_member_id']);
                if ($member) {
                    $pendingDocuments[$key]['current_file_path'] = $member->birth_certificate_path;
                    $pendingDocuments[$key]['family_member_name'] = $member->name;
                }
            }
        }
        return $pendingDocuments;
    }

    // =========================================================
    // PROCESS REQUEST (Admin approves/rejects)
    // =========================================================
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::with('employee')->findOrFail($id);

        if (!$this->canProcessRequest($user, $profileRequest)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($profileRequest->status === 'processed') {
            return response()->json([
                'message' => 'This request has already been processed'
            ], 422);
        }

        $validated = $request->validate([
            'is_approved' => 'required|boolean',
            'admin_note' => 'nullable|string|max:2000',
            'approved_changes' => 'nullable|array',
        ]);

        try {
            DB::transaction(function () use ($profileRequest, $validated, $user) {
                $employee = $profileRequest->employee;

                if ($validated['is_approved']) {
                    $changesToApply = $validated['approved_changes']
                        ?? $profileRequest->proposed_changes;

                    $this->applyChanges($employee, $changesToApply);
                } else {
                    // If rejected, delete pending documents
                    $this->revertPendingDocuments($profileRequest);
                }

                $profileRequest->update([
                    'status' => 'processed',
                    'is_approved' => $validated['is_approved'],
                    'admin_note' => $validated['admin_note'] ?? null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            });

            $status = $validated['is_approved'] ? 'approved' : 'rejected';

            return response()->json([
                'message' => "Request has been {$status} successfully",
                'request' => $profileRequest->fresh()->load(['employee', 'reviewedBy'])
            ]);

        } catch (\Exception $e) {
            Log::error('Profile Request Update Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process request: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // APPLY CHANGES TO EMPLOYEE
    // =========================================================
    private function applyChanges(Employee $employee, array $changes): void
    {
        // 1. Personal Info
        if (isset($changes['personal_info']) && is_array($changes['personal_info'])) {
            $this->applyPersonalInfoChanges($employee, $changes['personal_info']);
        }

        // 2. Family
        if (isset($changes['family']) && is_array($changes['family'])) {
            $this->applyFamilyChanges($employee, $changes['family']);
        }

        // 3. Addresses
        if (isset($changes['addresses']) && is_array($changes['addresses'])) {
            $this->applyAddressChanges($employee, $changes['addresses']);
        }

        // 4. Academics (returns new academic IDs in order for pending certs by index)
        $newAcademicIdsByIndex = [];
        if (isset($changes['academics']) && is_array($changes['academics'])) {
            $newAcademicIdsByIndex = $this->applyAcademicChanges($employee, $changes['academics']);
        }

        // 5. Pending Documents (unified document handling)
        if (isset($changes['pending_documents']) && is_array($changes['pending_documents'])) {
            $this->applyPendingDocuments($employee, $changes['pending_documents'], $newAcademicIdsByIndex);
        }

        // 6. Legacy: document_update (backward compatibility)
        if (isset($changes['document_update']) && is_array($changes['document_update'])) {
            $this->applyDocumentUpdateLegacy($employee, $changes['document_update']);
        }

        // 7. Legacy: files (backward compatibility)
        if (isset($changes['files']) && is_array($changes['files'])) {
            $this->applyFileChangesLegacy($employee, $changes['files']);
        }
    }

    /**
     * Apply pending documents - Cloudinary: assign public_id; local: move from pending to final location.
     * @param array $newAcademicIdsByIndex New academic IDs in order (from applyAcademicChanges) for resolving academic_index
     */
    private function applyPendingDocuments(Employee $employee, array $pendingDocuments, array $newAcademicIdsByIndex = []): void
    {
        foreach ($pendingDocuments as $doc) {
            $pendingPath = $doc['path'] ?? null;
            if (!$pendingPath) {
                Log::warning('Pending document path is empty');
                continue;
            }

            $isCloudinary = $this->cloudinary->isCloudinaryPath($pendingPath);

            if ($isCloudinary) {
                $this->applyCloudinaryPendingDoc($employee, $doc, $pendingPath, $newAcademicIdsByIndex);
                continue;
            }

            if (!Storage::disk('public')->exists($pendingPath)) {
                Log::warning('Pending document not found: ' . $pendingPath);
                continue;
            }

            $ext = pathinfo($pendingPath, PATHINFO_EXTENSION);

            // Employee field (profile_picture, nid_file_path, birth_file_path)
            if (!empty($doc['field'])) {
                $field = $doc['field'];
                if (!in_array($field, ['profile_picture', 'nid_file_path', 'birth_file_path'])) {
                    continue;
                }

                $finalDir = match ($field) {
                    'profile_picture' => 'photos',
                    'nid_file_path' => 'documents/nid',
                    'birth_file_path' => 'documents/birth',
                };

                $prefix = match ($field) {
                    'profile_picture' => '',
                    'nid_file_path' => 'NID_',
                    'birth_file_path' => 'BIRTH_',
                };

                $finalFilename = $prefix . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . time() . '.' . $ext;
                $finalPath = $finalDir . '/' . $finalFilename;

                Storage::disk('public')->move($pendingPath, $finalPath);

                if ($employee->$field) {
                    Storage::disk('public')->delete($employee->$field);
                }

                $employee->update([$field => $finalPath]);

                Log::info("Applied pending document: {$field} -> {$finalPath}");
                continue;
            }

            // Academic certificate (academic_id or academic_index for new academics)
            $academicId = $doc['academic_id'] ?? null;
            if ($academicId === null && isset($doc['academic_index']) && array_key_exists($doc['academic_index'], $newAcademicIdsByIndex)) {
                $academicId = $newAcademicIdsByIndex[$doc['academic_index']];
            }
            if (!empty($academicId)) {
                $academic = AcademicRecord::where('employee_id', $employee->id)->find($academicId);
                if (!$academic) {
                    Log::warning('Academic record not found: ' . $academicId);
                    continue;
                }

                $examSlug = str_replace(['/', ' '], '_', $academic->exam_name ?? 'cert');
                $finalFilename = 'CERT_' . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . $examSlug . '_' . time() . '.' . $ext;
                $finalPath = 'documents/certificates/' . $finalFilename;

                Storage::disk('public')->move($pendingPath, $finalPath);

                if ($academic->certificate_path) {
                    Storage::disk('public')->delete($academic->certificate_path);
                }

                $academic->update(['certificate_path' => $finalPath]);

                Log::info("Applied pending academic certificate: {$academic->id} -> {$finalPath}");
                continue;
            }

            // Child birth certificate
            if (!empty($doc['family_member_id'])) {
                $member = FamilyMember::where('employee_id', $employee->id)->find($doc['family_member_id']);
                if (!$member) {
                    Log::warning('Family member not found: ' . $doc['family_member_id']);
                    continue;
                }

                $nameSlug = str_replace(' ', '_', $member->name ?? 'child');
                $finalFilename = 'CHILD_BIRTH_' . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . $nameSlug . '_' . time() . '.' . $ext;
                $finalPath = 'documents/children/' . $finalFilename;

                Storage::disk('public')->move($pendingPath, $finalPath);

                if ($member->birth_certificate_path) {
                    Storage::disk('public')->delete($member->birth_certificate_path);
                }

                $member->update(['birth_certificate_path' => $finalPath]);

                Log::info("Applied pending child birth certificate: {$member->id} -> {$finalPath}");
            }
        }
    }

    /**
     * Apply a single pending document that is stored in Cloudinary (path = public_id).
     * @param array $newAcademicIdsByIndex New academic IDs by index for resolving academic_index
     */
    private function applyCloudinaryPendingDoc(Employee $employee, array $doc, string $publicId, array $newAcademicIdsByIndex = []): void
    {
        $resourceType = $doc['resource_type'] ?? $this->cloudinary->getResourceType($publicId);

        if (!empty($doc['field'])) {
            $field = $doc['field'];
            if (!in_array($field, ['profile_picture', 'nid_file_path', 'birth_file_path'], true)) {
                return;
            }
            if ($employee->$field) {
                $this->cloudinary->delete($employee->$field, $this->cloudinary->getResourceType($employee->$field));
            }
            $employee->update([$field => $publicId]);
            Log::info("Applied Cloudinary pending document: {$field} -> {$publicId}");
            return;
        }

        $academicId = $doc['academic_id'] ?? null;
        if ($academicId === null && isset($doc['academic_index']) && array_key_exists($doc['academic_index'], $newAcademicIdsByIndex)) {
            $academicId = $newAcademicIdsByIndex[$doc['academic_index']];
        }
        if (!empty($academicId)) {
            $academic = AcademicRecord::where('employee_id', $employee->id)->find($academicId);
            if ($academic) {
                if ($academic->certificate_path) {
                    $this->cloudinary->delete($academic->certificate_path, $this->cloudinary->getResourceType($academic->certificate_path));
                }
                $academic->update(['certificate_path' => $publicId]);
                Log::info("Applied Cloudinary pending academic certificate: {$academic->id} -> {$publicId}");
            }
            return;
        }

        if (!empty($doc['family_member_id'])) {
            $member = FamilyMember::where('employee_id', $employee->id)->find($doc['family_member_id']);
            if ($member) {
                if ($member->birth_certificate_path) {
                    $this->cloudinary->delete($member->birth_certificate_path, $this->cloudinary->getResourceType($member->birth_certificate_path));
                }
                $member->update(['birth_certificate_path' => $publicId]);
                Log::info("Applied Cloudinary pending child birth certificate: {$member->id} -> {$publicId}");
            }
        }
    }

    /**
     * Revert/delete pending documents when request is rejected (Cloudinary or local)
     */
    private function revertPendingDocuments(ProfileRequest $profileRequest): void
    {
        $proposed = $profileRequest->proposed_changes;
        if (!is_array($proposed)) {
            return;
        }

        $deletePath = function ($path) {
            if (!$path) {
                return;
            }
            if ($this->cloudinary->isCloudinaryPath($path)) {
                $resourceType = $this->cloudinary->getResourceType($path);
                $this->cloudinary->delete($path, $resourceType);
                Log::info("Deleted rejected Cloudinary document: {$path}");
            } elseif (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info("Deleted rejected pending document: {$path}");
            }
        };

        if (isset($proposed['pending_documents']) && is_array($proposed['pending_documents'])) {
            foreach ($proposed['pending_documents'] as $doc) {
                $deletePath($doc['path'] ?? null);
            }
        }

        if (isset($proposed['document_update'])) {
            $doc = $proposed['document_update'];
            $deletePath($doc['file_path'] ?? null);
        }
    }

    /**
     * Legacy: Apply document_update (Cloudinary or local backward compatibility)
     */
    private function applyDocumentUpdateLegacy(Employee $employee, array $documentUpdate): void
    {
        $pendingPath = $documentUpdate['file_path'] ?? null;
        if (!$pendingPath) {
            return;
        }

        if ($this->cloudinary->isCloudinaryPath($pendingPath)) {
            $doc = [
                'path' => $pendingPath,
                'resource_type' => $documentUpdate['resource_type'] ?? $this->cloudinary->getResourceType($pendingPath),
                'field' => $documentUpdate['employee_field'] ?? null,
                'academic_id' => $documentUpdate['academic_id'] ?? null,
                'family_member_id' => $documentUpdate['family_member_id'] ?? null,
            ];
            $this->applyCloudinaryPendingDoc($employee, $doc, $pendingPath);
            return;
        }

        if (!Storage::disk('public')->exists($pendingPath)) {
            return;
        }

        $ext = pathinfo($pendingPath, PATHINFO_EXTENSION);

        if (!empty($documentUpdate['employee_field'])) {
            $field = $documentUpdate['employee_field'];
            if (!in_array($field, ['profile_picture', 'nid_file_path', 'birth_file_path'], true)) {
                return;
            }

            $finalDir = match ($field) {
                'profile_picture' => 'photos',
                'nid_file_path' => 'documents/nid',
                'birth_file_path' => 'documents/birth',
            };

            $prefix = match ($field) {
                'profile_picture' => '',
                'nid_file_path' => 'NID_',
                'birth_file_path' => 'BIRTH_',
            };

            $finalFilename = $prefix . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . time() . '.' . $ext;
            $finalPath = $finalDir . '/' . $finalFilename;

            Storage::disk('public')->move($pendingPath, $finalPath);

            if ($employee->$field) {
                Storage::disk('public')->delete($employee->$field);
            }

            $employee->update([$field => $finalPath]);
            return;
        }

        if (!empty($documentUpdate['academic_id'])) {
            $academic = AcademicRecord::where('employee_id', $employee->id)->find($documentUpdate['academic_id']);
            if ($academic) {
                $examSlug = str_replace(['/', ' '], '_', $academic->exam_name ?? 'cert');
                $finalFilename = 'CERT_' . ($employee->nid_number ?: $employee->id) . '_' . $examSlug . '_' . time() . '.' . $ext;
                $finalPath = 'documents/certificates/' . $finalFilename;

                Storage::disk('public')->move($pendingPath, $finalPath);

                if ($academic->certificate_path) {
                    Storage::disk('public')->delete($academic->certificate_path);
                }

                $academic->update(['certificate_path' => $finalPath]);
            }
            return;
        }

        if (!empty($documentUpdate['family_member_id'])) {
            $member = FamilyMember::where('employee_id', $employee->id)->find($documentUpdate['family_member_id']);
            if ($member) {
                $nameSlug = str_replace(' ', '_', $member->name ?? 'child');
                $finalFilename = 'CHILD_BIRTH_' . ($employee->nid_number ?: $employee->id) . '_' . $nameSlug . '_' . time() . '.' . $ext;
                $finalPath = 'documents/children/' . $finalFilename;

                Storage::disk('public')->move($pendingPath, $finalPath);

                if ($member->birth_certificate_path) {
                    Storage::disk('public')->delete($member->birth_certificate_path);
                }

                $member->update(['birth_certificate_path' => $finalPath]);
            }
        }
    }

    /**
     * Legacy: Apply file changes (backward compatibility)
     */
    private function applyFileChangesLegacy(Employee $employee, array $files): void
    {
        foreach (['nid_file' => 'nid_file_path', 'birth_file' => 'birth_file_path', 'profile_picture' => 'profile_picture'] as $key => $field) {
            if (isset($files[$key])) {
                $newPath = $this->moveToPermananentStorage($files[$key], $field === 'profile_picture' ? 'photos' : 'documents/' . str_replace('_file_path', '', $field));
                if ($newPath) {
                    if ($employee->$field) {
                        Storage::disk('public')->delete($employee->$field);
                    }
                    $employee->update([$field => $newPath]);
                }
            }
        }
    }

    private function moveToPermananentStorage(string $tempPath, string $folder): ?string
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            return null;
        }

        $filename = basename($tempPath);
        $newPath = $folder . '/' . $filename;

        Storage::disk('public')->move($tempPath, $newPath);

        return $newPath;
    }

    // =========================================================
    // APPLY PERSONAL INFO CHANGES
    // =========================================================
    private function applyPersonalInfoChanges(Employee $employee, array $personalInfo): void
    {
        $allowedFields = [
            'first_name', 'last_name', 'name_bn', 'nid_number', 'phone',
            'gender', 'dob', 'religion', 'blood_group', 'marital_status',
            'place_of_birth', 'height', 'passport', 'birth_reg',
            'cadre_type', 'batch_no'
        ];

        $updateData = [];

        foreach ($personalInfo as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateData[$field] = $this->emptyToNull($value);
            }
        }

        if (!empty($updateData)) {
            $employee->update($updateData);
        }
    }

    private function emptyToNull($value)
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    // =========================================================
    // APPLY FAMILY CHANGES
    // =========================================================
    private function applyFamilyChanges(Employee $employee, array $family): void
    {
        if (isset($family['father'])) {
            $this->upsertFamilyMember($employee, 'father', $family['father']);
        }

        if (isset($family['mother'])) {
            $this->upsertFamilyMember($employee, 'mother', $family['mother']);
        }

        if (isset($family['spouses']) && is_array($family['spouses'])) {
            $this->updateSpouses($employee, $family['spouses']);
        }

        if (isset($family['children']) && is_array($family['children'])) {
            $this->updateChildren($employee, $family['children']);
        }
    }

    private function upsertFamilyMember(Employee $employee, string $relation, array $data): void
    {
        if (empty($data['name'])) {
            return;
        }

        $payload = [
            'name' => $data['name'],
            'name_bn' => $this->emptyToNull($data['name_bn'] ?? null),
            'nid' => $this->emptyToNull($data['nid'] ?? null),
            'dob' => $this->emptyToNull($data['dob'] ?? null),
            'occupation' => $this->emptyToNull($data['occupation'] ?? null),
            'is_alive' => $data['is_alive'] ?? true,
        ];
        $employee->family()->updateOrCreate(
            ['relation' => $relation],
            $payload
        );
    }

    private function updateSpouses(Employee $employee, array $spouses): void
    {
        $activeCount = collect($spouses)->where('is_active_marriage', true)->count();
        $maxAllowed = $employee->getMaxSpouses();

        if ($activeCount > $maxAllowed) {
            throw ValidationException::withMessages([
                'spouses' => ["Maximum {$maxAllowed} active spouse(s) allowed."]
            ]);
        }

        $employee->family()->where('relation', 'spouse')->delete();

        foreach ($spouses as $spouse) {
            if (empty($spouse['name'])) continue;

            $employee->family()->create([
                'relation' => 'spouse',
                'name' => $spouse['name'],
                'name_bn' => $this->emptyToNull($spouse['name_bn'] ?? null),
                'nid' => $this->emptyToNull($spouse['nid'] ?? null),
                'dob' => $this->emptyToNull($spouse['dob'] ?? null),
                'occupation' => $this->emptyToNull($spouse['occupation'] ?? null),
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

            $employee->family()->create([
                'relation' => 'child',
                'name' => $child['name'],
                'name_bn' => $this->emptyToNull($child['name_bn'] ?? null),
                'gender' => $this->emptyToNull($child['gender'] ?? null),
                'dob' => $this->emptyToNull($child['dob'] ?? null),
                'birth_certificate_path' => $this->emptyToNull($child['birth_certificate_path'] ?? null),
                'is_alive' => $child['is_alive'] ?? true,
            ]);
        }
    }

    // =========================================================
    // APPLY ADDRESS CHANGES
    // =========================================================
    private function applyAddressChanges(Employee $employee, array $addresses): void
    {
        foreach (['present', 'permanent'] as $type) {
            if (isset($addresses[$type])) {
                $addr = $addresses[$type];

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
                }
            }
        }
    }

    // =========================================================
    // APPLY ACADEMIC CHANGES
    // =========================================================
    /**
     * Apply academic changes. Returns new academic IDs in the same order as $academics (for pending cert by index).
     */
    private function applyAcademicChanges(Employee $employee, array $academics): array
    {
        $employee->academics()->delete();
        $newIds = [];

        foreach ($academics as $record) {
            if (empty($record['exam_name'])) {
                continue;
            }

            $academic = $employee->academics()->create([
                'exam_name' => $record['exam_name'],
                'board' => $record['board'] ?? null,
                'institute' => $record['institute'] ?? null,
                'passing_year' => $record['passing_year'] ?? null,
                'result' => $record['result'] ?? null,
                'certificate_path' => $record['certificate_path'] ?? null,
            ]);
            $newIds[] = $academic->id;
        }

        return $newIds;
    }

    // =========================================================
    // CANCEL REQUEST
    // =========================================================
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::findOrFail($id);

        if ($profileRequest->employee_id !== $user->employee_id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($profileRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be cancelled'
            ], 422);
        }

        // Delete pending documents
        $this->revertPendingDocuments($profileRequest);

        $profileRequest->delete();

        return response()->json(['message' => 'Request cancelled successfully']);
    }

    // =========================================================
    // DOWNLOAD REPORT
    // =========================================================
    public function downloadReport(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::with([
            'employee.designation',
            'employee.office',
            'reviewedBy'
        ])->findOrFail($id);

        if (!$this->canAccessRequest($user, $profileRequest)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($profileRequest->status !== 'processed') {
            return response()->json([
                'message' => 'Report is only available for processed requests'
            ], 422);
        }

        $data = [
            'request' => $profileRequest,
            'employee' => $profileRequest->employee,
            'reviewer' => $profileRequest->reviewedBy,
            'date' => now()->format('d M Y'),
            'proposed_changes' => $profileRequest->proposed_changes,
            'status' => $profileRequest->is_approved ? 'APPROVED' : 'REJECTED',
        ];

        $pdf = Pdf::loadView('reports.profile_request_resolution', $data);

        $filename = 'Request_' . $profileRequest->id . '_Resolution_' . date('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================
    // HELPER METHODS
    // =========================================================

    private function canAccessRequest($user, ProfileRequest $request): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->employee_id === $request->employee_id) {
            return true;
        }

        if ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            return in_array($request->employee->current_office_id, $officeIds);
        }

        return false;
    }

    private function canProcessRequest($user, ProfileRequest $request): bool
    {
        if ($user->isVerifiedUser()) {
            return false;
        }

        if ($user->employee_id === $request->employee_id) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            return in_array($request->employee->current_office_id, $officeIds);
        }

        return false;
    }

    private function getCurrentEmployeeData(Employee $employee): array
    {
        return [
            'personal_info' => [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'name_bn' => $employee->name_bn,
                'nid_number' => $employee->nid_number,
                'phone' => $employee->phone,
                'gender' => $employee->gender,
                'dob' => $employee->dob?->format('Y-m-d'),
                'religion' => $employee->religion,
                'blood_group' => $employee->blood_group,
                'marital_status' => $employee->marital_status,
                'place_of_birth' => $employee->place_of_birth,
                'height' => $employee->height,
                'passport' => $employee->passport,
                'birth_reg' => $employee->birth_reg,
                'cadre_type' => $employee->cadre_type,
                'batch_no' => $employee->batch_no,
            ],
            'files' => [
                'profile_picture' => $employee->profile_picture,
                'nid_file_path' => $employee->nid_file_path,
                'birth_file_path' => $employee->birth_file_path,
            ],
            'family' => [
                'father' => $employee->father?->toArray(),
                'mother' => $employee->mother?->toArray(),
                'spouses' => $employee->spouses->toArray(),
                'children' => $employee->children->map(function ($child) {
                    $arr = $child->toArray();
                    $arr['birth_certificate_path'] = $child->birth_certificate_path;
                    return $arr;
                })->toArray(),
            ],
            'addresses' => [
                'present' => $employee->presentAddress?->toArray(),
                'permanent' => $employee->permanentAddress?->toArray(),
            ],
            'academics' => $employee->academics->map(function ($academic) {
                $arr = $academic->toArray();
                $arr['certificate_path'] = $academic->certificate_path;
                return $arr;
            })->toArray(),
        ];
    }
}