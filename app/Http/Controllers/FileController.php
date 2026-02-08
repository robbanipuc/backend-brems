<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AcademicRecord;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    // =========================================================
    // PROFILE PICTURE
    // =========================================================

    /**
     * Upload profile picture.
     * Admin: applied immediately.
     * Employee (own profile): stored in pending, path returned for inclusion in profile update request.
     */
    public function uploadProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $ext = $request->file('photo')->extension();
        $filename = ($employee->nid_number ?: 'emp' . $employee->id) . '_' . time() . '.' . $ext;

        // Admin uploading for employee: apply immediately
        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($employee->profile_picture) {
                Storage::disk('public')->delete($employee->profile_picture);
            }
            $path = $request->file('photo')->storeAs('photos', $filename, 'public');
            $employee->update(['profile_picture' => $path]);
            return response()->json([
                'message' => 'Photo uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'applied' => true,
            ]);
        }

        // Employee uploading own profile: save to pending, return path
        // DO NOT create ProfileRequest here - let the profile edit form handle it
        $path = $request->file('photo')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'profile_picture_' . time() . '.' . $ext,
            'public'
        );

        Log::info("Profile picture uploaded to pending: {$path} for employee #{$employee->id}");

        return response()->json([
            'message' => 'Photo uploaded to pending. Submit your profile changes for review.',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'pending' => true,
            'field' => 'profile_picture',
            'document_type' => 'Profile Picture',
        ]);
    }

    /**
     * Delete profile picture - Admin only for direct delete
     */
    public function deleteProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        // Only admins can delete directly
        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete profile pictures directly'], 403);
        }

        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
            $employee->update(['profile_picture' => null]);
        }

        return response()->json(['message' => 'Photo deleted successfully']);
    }

    // =========================================================
    // DOCUMENT UPLOADS (NID, Birth Certificate)
    // =========================================================

    /**
     * Upload NID document.
     * Admin: applied immediately.
     * Employee (own profile): stored in pending, path returned.
     */
    public function uploadNidDocument(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $ext = $request->file('document')->extension();
        $filename = 'NID_' . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($employee->nid_file_path) {
                Storage::disk('public')->delete($employee->nid_file_path);
            }
            $path = $request->file('document')->storeAs('documents/nid', $filename, 'public');
            $employee->update(['nid_file_path' => $path]);
            return response()->json([
                'message' => 'NID document uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $path = $request->file('document')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'nid_' . time() . '.' . $ext,
            'public'
        );

        Log::info("NID document uploaded to pending: {$path} for employee #{$employee->id}");

        return response()->json([
            'message' => 'Document uploaded to pending. Submit your profile changes for review.',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'pending' => true,
            'field' => 'nid_file_path',
            'document_type' => 'NID Document',
        ]);
    }

    /**
     * Upload birth certificate document.
     */
    public function uploadBirthCertificate(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $ext = $request->file('document')->extension();
        $filename = 'BIRTH_' . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($employee->birth_file_path) {
                Storage::disk('public')->delete($employee->birth_file_path);
            }
            $path = $request->file('document')->storeAs('documents/birth', $filename, 'public');
            $employee->update(['birth_file_path' => $path]);
            return response()->json([
                'message' => 'Birth certificate uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $path = $request->file('document')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'birth_' . time() . '.' . $ext,
            'public'
        );

        Log::info("Birth certificate uploaded to pending: {$path} for employee #{$employee->id}");

        return response()->json([
            'message' => 'Document uploaded to pending. Submit your profile changes for review.',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'pending' => true,
            'field' => 'birth_file_path',
            'document_type' => 'Birth Certificate',
        ]);
    }

    /**
     * Delete document (NID or Birth Certificate) - Admin only
     */
    public function deleteDocument(Request $request, $employeeId, $type)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete documents directly'], 403);
        }

        $column = $type === 'nid' ? 'nid_file_path' : 'birth_file_path';

        if ($employee->$column) {
            Storage::disk('public')->delete($employee->$column);
            $employee->update([$column => null]);
        }

        return response()->json(['message' => 'Document deleted successfully']);
    }

    // =========================================================
    // ACADEMIC CERTIFICATE UPLOADS
    // =========================================================

    /**
     * Upload academic certificate
     */
    public function uploadAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)
            ->findOrFail($academicId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $examSlug = str_replace(['/', ' '], '_', $academic->exam_name ?? 'cert');
        $ext = $request->file('certificate')->extension();
        $filename = 'CERT_' . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . $examSlug . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($academic->certificate_path) {
                Storage::disk('public')->delete($academic->certificate_path);
            }
            $path = $request->file('certificate')->storeAs('documents/certificates', $filename, 'public');
            $academic->update(['certificate_path' => $path]);
            return response()->json([
                'message' => 'Certificate uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $path = $request->file('certificate')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'academic_' . $academic->id . '_' . time() . '.' . $ext,
            'public'
        );

        Log::info("Academic certificate uploaded to pending: {$path} for employee #{$employee->id}, academic #{$academic->id}");

        return response()->json([
            'message' => 'Certificate uploaded to pending. Submit your profile changes for review.',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'pending' => true,
            'academic_id' => $academic->id,
            'document_type' => 'Academic Certificate: ' . ($academic->exam_name ?? 'Certificate'),
        ]);
    }

    /**
     * Delete academic certificate - Admin only
     */
    public function deleteAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete certificates directly'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)
            ->findOrFail($academicId);

        if ($academic->certificate_path) {
            Storage::disk('public')->delete($academic->certificate_path);
            $academic->update(['certificate_path' => null]);
        }

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    // =========================================================
    // CHILD BIRTH CERTIFICATE UPLOADS
    // =========================================================

    /**
     * Upload child's birth certificate
     */
    public function uploadChildBirthCertificate(Request $request, $employeeId, $familyMemberId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $child = FamilyMember::where('employee_id', $employeeId)
            ->where('relation', 'child')
            ->findOrFail($familyMemberId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $childNameSlug = str_replace(' ', '_', $child->name ?? 'child');
        $ext = $request->file('certificate')->extension();
        $filename = 'CHILD_BIRTH_' . ($employee->nid_number ?: 'emp' . $employee->id) . '_' . $childNameSlug . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($child->birth_certificate_path) {
                Storage::disk('public')->delete($child->birth_certificate_path);
            }
            $path = $request->file('certificate')->storeAs('documents/children', $filename, 'public');
            $child->update(['birth_certificate_path' => $path]);
            return response()->json([
                'message' => 'Child birth certificate uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $path = $request->file('certificate')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'child_birth_' . $child->id . '_' . time() . '.' . $ext,
            'public'
        );

        Log::info("Child birth certificate uploaded to pending: {$path} for employee #{$employee->id}, child #{$child->id}");

        return response()->json([
            'message' => 'Certificate uploaded to pending. Submit your profile changes for review.',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'pending' => true,
            'family_member_id' => $child->id,
            'document_type' => 'Child Birth Certificate: ' . ($child->name ?? 'Child'),
        ]);
    }

    /**
     * Delete child's birth certificate - Admin only
     */
    public function deleteChildBirthCertificate(Request $request, $employeeId, $familyMemberId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete certificates directly'], 403);
        }

        $child = FamilyMember::where('employee_id', $employeeId)
            ->where('relation', 'child')
            ->findOrFail($familyMemberId);

        if ($child->birth_certificate_path) {
            Storage::disk('public')->delete($child->birth_certificate_path);
            $child->update(['birth_certificate_path' => null]);
        }

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    // =========================================================
    // CLEANUP PENDING FILES
    // =========================================================

    /**
     * Delete a specific pending file (e.g., when user removes before submitting)
     */
    public function deletePendingFile(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        // Only the employee themselves or admin can delete their pending files
        if ((int) $user->employee_id !== (int) $employee->id && !$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        // Security: ensure the path is within the employee's pending folder
        $expectedPrefix = 'documents/pending/employee_' . $employee->id . '/';
        if (!str_starts_with($path, $expectedPrefix)) {
            return response()->json(['message' => 'Invalid file path'], 403);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            Log::info("Deleted pending file: {$path}");
            return response()->json(['message' => 'Pending file deleted successfully']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }

    /**
     * Get list of pending files for an employee
     */
    public function getPendingFiles(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if ((int) $user->employee_id !== (int) $employee->id && !$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $pendingDir = 'documents/pending/employee_' . $employee->id;
        $files = [];

        if (Storage::disk('public')->exists($pendingDir)) {
            $allFiles = Storage::disk('public')->files($pendingDir);
            foreach ($allFiles as $filePath) {
                $files[] = [
                    'path' => $filePath,
                    'url' => Storage::disk('public')->url($filePath),
                    'name' => basename($filePath),
                    'size' => Storage::disk('public')->size($filePath),
                    'last_modified' => Storage::disk('public')->lastModified($filePath),
                ];
            }
        }

        return response()->json(['files' => $files]);
    }

    // =========================================================
    // FILE DOWNLOAD / VIEW
    // =========================================================

    /**
     * Get file URL for viewing
     */
    public function getFileUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'exists' => true
        ]);
    }

    /**
     * Download file
     */
    public function downloadFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($path);
    }
}