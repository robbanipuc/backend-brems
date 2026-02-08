<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AcademicRecord;
use App\Models\FamilyMember;
use App\Models\ProfileRequest;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    // =========================================================
    // PROFILE PICTURE
    // =========================================================

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

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            // Delete old photo from Cloudinary
            if ($employee->profile_picture) {
                $this->cloudinary->delete($employee->profile_picture, 'image');
            }

            // Upload new photo
            $result = $this->cloudinary->uploadImage(
                $request->file('photo'),
                'photos/employee_' . $employee->id
            );

            if (empty($result['success'])) {
                $message = isset($result['error']) && is_string($result['error'])
                    ? $result['error']
                    : 'Upload failed';
                return response()->json(['message' => 'Upload failed: ' . $message], 500);
            }

            $employee->update(['profile_picture' => $result['public_id']]);

            return response()->json([
                'message' => 'Photo uploaded successfully',
                'path' => $result['public_id'],
                'url' => $result['url'],
                'applied' => true,
            ]);
        }

        // Employee uploading own profile: save to pending folder
        $result = $this->cloudinary->uploadImage(
            $request->file('photo'),
            'pending/employee_' . $employee->id . '/photos'
        );

        if (empty($result['success'])) {
            $message = isset($result['error']) && is_string($result['error'])
                ? $result['error']
                : 'Upload failed';
            return response()->json(['message' => 'Upload failed: ' . $message], 500);
        }

        $this->createDocumentUpdateRequestIfOwnProfile(
            $employee,
            'Profile picture',
            $result['public_id'],
            ['employee_field' => 'profile_picture'],
            'image'
        );

        return response()->json([
            'message' => 'Photo submitted for admin approval. It will appear once approved.',
            'path' => $result['public_id'],
            'url' => $result['url'],
            'pending' => true,
            'field' => 'profile_picture',
            'document_type' => 'Profile Picture',
        ]);
    }

    public function deleteProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete profile pictures directly'], 403);
        }

        if ($employee->profile_picture) {
            $this->cloudinary->delete($employee->profile_picture, 'image');
            $employee->update(['profile_picture' => null]);
        }

        return response()->json(['message' => 'Photo deleted successfully']);
    }

    // =========================================================
    // DOCUMENT UPLOADS (NID, Birth Certificate)
    // =========================================================

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

        $file = $request->file('document');
        $isPdf = $file->getClientOriginalExtension() === 'pdf';

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($employee->nid_file_path) {
                $resourceType = $this->cloudinary->getResourceType($employee->nid_file_path);
                $this->cloudinary->delete($employee->nid_file_path, $resourceType);
            }

            $result = $isPdf
                ? $this->cloudinary->uploadDocument($file, 'documents/nid/employee_' . $employee->id)
                : $this->cloudinary->uploadImage($file, 'documents/nid/employee_' . $employee->id);

            if (!$result['success']) {
                return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
            }

            $employee->update(['nid_file_path' => $result['public_id']]);

            return response()->json([
                'message' => 'NID document uploaded successfully',
                'path' => $result['public_id'],
                'url' => $result['url'],
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $result = $isPdf
            ? $this->cloudinary->uploadDocument($file, 'pending/employee_' . $employee->id . '/nid')
            : $this->cloudinary->uploadImage($file, 'pending/employee_' . $employee->id . '/nid');

        if (!$result['success']) {
            return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
        }

        $resourceType = $isPdf ? 'raw' : 'image';
        $this->createDocumentUpdateRequestIfOwnProfile(
            $employee,
            'NID document',
            $result['public_id'],
            ['employee_field' => 'nid_file_path'],
            $resourceType
        );

        return response()->json([
            'message' => 'Document submitted for admin approval.',
            'path' => $result['public_id'],
            'url' => $result['url'],
            'pending' => true,
            'field' => 'nid_file_path',
            'document_type' => 'NID Document',
        ]);
    }

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

        $file = $request->file('document');
        $isPdf = $file->getClientOriginalExtension() === 'pdf';

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($employee->birth_file_path) {
                $resourceType = $this->cloudinary->getResourceType($employee->birth_file_path);
                $this->cloudinary->delete($employee->birth_file_path, $resourceType);
            }

            $result = $isPdf
                ? $this->cloudinary->uploadDocument($file, 'documents/birth/employee_' . $employee->id)
                : $this->cloudinary->uploadImage($file, 'documents/birth/employee_' . $employee->id);

            if (!$result['success']) {
                return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
            }

            $employee->update(['birth_file_path' => $result['public_id']]);

            return response()->json([
                'message' => 'Birth certificate uploaded successfully',
                'path' => $result['public_id'],
                'url' => $result['url'],
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $result = $isPdf
            ? $this->cloudinary->uploadDocument($file, 'pending/employee_' . $employee->id . '/birth')
            : $this->cloudinary->uploadImage($file, 'pending/employee_' . $employee->id . '/birth');

        if (!$result['success']) {
            return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
        }

        $resourceType = $isPdf ? 'raw' : 'image';
        $this->createDocumentUpdateRequestIfOwnProfile(
            $employee,
            'Birth certificate',
            $result['public_id'],
            ['employee_field' => 'birth_file_path'],
            $resourceType
        );

        return response()->json([
            'message' => 'Document submitted for admin approval.',
            'path' => $result['public_id'],
            'url' => $result['url'],
            'pending' => true,
            'field' => 'birth_file_path',
            'document_type' => 'Birth Certificate',
        ]);
    }

    public function deleteDocument(Request $request, $employeeId, $type)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete documents directly'], 403);
        }

        $column = $type === 'nid' ? 'nid_file_path' : 'birth_file_path';

        if ($employee->$column) {
            $resourceType = $this->cloudinary->getResourceType($employee->$column);
            $this->cloudinary->delete($employee->$column, $resourceType);
            $employee->update([$column => null]);
        }

        return response()->json(['message' => 'Document deleted successfully']);
    }

    // =========================================================
    // ACADEMIC CERTIFICATE UPLOADS
    // =========================================================

    public function uploadAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)->findOrFail($academicId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $file = $request->file('certificate');
        $isPdf = $file->getClientOriginalExtension() === 'pdf';

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($academic->certificate_path) {
                $resourceType = $this->cloudinary->getResourceType($academic->certificate_path);
                $this->cloudinary->delete($academic->certificate_path, $resourceType);
            }

            $result = $isPdf
                ? $this->cloudinary->uploadDocument($file, 'documents/certificates/employee_' . $employee->id)
                : $this->cloudinary->uploadImage($file, 'documents/certificates/employee_' . $employee->id);

            if (!$result['success']) {
                return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
            }

            $academic->update(['certificate_path' => $result['public_id']]);

            return response()->json([
                'message' => 'Certificate uploaded successfully',
                'path' => $result['public_id'],
                'url' => $result['url'],
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $result = $isPdf
            ? $this->cloudinary->uploadDocument($file, 'pending/employee_' . $employee->id . '/certificates')
            : $this->cloudinary->uploadImage($file, 'pending/employee_' . $employee->id . '/certificates');

        if (!$result['success']) {
            return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
        }

        $resourceType = $isPdf ? 'raw' : 'image';
        $this->createDocumentUpdateRequestIfOwnProfile(
            $employee,
            'Academic certificate: ' . ($academic->exam_name ?? 'certificate'),
            $result['public_id'],
            ['academic_id' => $academic->id],
            $resourceType
        );

        return response()->json([
            'message' => 'Certificate submitted for admin approval.',
            'path' => $result['public_id'],
            'url' => $result['url'],
            'pending' => true,
            'academic_id' => $academic->id,
            'document_type' => 'Academic Certificate: ' . ($academic->exam_name ?? 'Certificate'),
        ]);
    }

    public function deleteAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only admins can delete certificates directly'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)->findOrFail($academicId);

        if ($academic->certificate_path) {
            $resourceType = $this->cloudinary->getResourceType($academic->certificate_path);
            $this->cloudinary->delete($academic->certificate_path, $resourceType);
            $academic->update(['certificate_path' => null]);
        }

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    // =========================================================
    // CHILD BIRTH CERTIFICATE UPLOADS
    // =========================================================

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

        $file = $request->file('certificate');
        $isPdf = $file->getClientOriginalExtension() === 'pdf';

        $isAdminUploadingForEmployee = ($user->isSuperAdmin() || $user->isOfficeAdmin())
            && (int) $user->employee_id !== (int) $employee->id;

        if ($isAdminUploadingForEmployee) {
            if ($child->birth_certificate_path) {
                $resourceType = $this->cloudinary->getResourceType($child->birth_certificate_path);
                $this->cloudinary->delete($child->birth_certificate_path, $resourceType);
            }

            $result = $isPdf
                ? $this->cloudinary->uploadDocument($file, 'documents/children/employee_' . $employee->id)
                : $this->cloudinary->uploadImage($file, 'documents/children/employee_' . $employee->id);

            if (!$result['success']) {
                return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
            }

            $child->update(['birth_certificate_path' => $result['public_id']]);

            return response()->json([
                'message' => 'Child birth certificate uploaded successfully',
                'path' => $result['public_id'],
                'url' => $result['url'],
                'applied' => true,
            ]);
        }

        // Employee: save to pending
        $result = $isPdf
            ? $this->cloudinary->uploadDocument($file, 'pending/employee_' . $employee->id . '/children')
            : $this->cloudinary->uploadImage($file, 'pending/employee_' . $employee->id . '/children');

        if (!$result['success']) {
            return response()->json(['message' => 'Upload failed: ' . $result['error']], 500);
        }

        $resourceType = $isPdf ? 'raw' : 'image';
        $this->createDocumentUpdateRequestIfOwnProfile(
            $employee,
            'Child birth certificate: ' . ($child->name ?? 'child'),
            $result['public_id'],
            ['family_member_id' => $child->id],
            $resourceType
        );

        return response()->json([
            'message' => 'Certificate submitted for admin approval.',
            'path' => $result['public_id'],
            'url' => $result['url'],
            'pending' => true,
            'family_member_id' => $child->id,
            'document_type' => 'Child Birth Certificate: ' . ($child->name ?? 'Child'),
        ]);
    }

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
            $resourceType = $this->cloudinary->getResourceType($child->birth_certificate_path);
            $this->cloudinary->delete($child->birth_certificate_path, $resourceType);
            $child->update(['birth_certificate_path' => null]);
        }

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    // =========================================================
    // PENDING FILES MANAGEMENT
    // =========================================================

    public function deletePendingFile(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if ((int) $user->employee_id !== (int) $employee->id && !$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        // Security: ensure the path is within the employee's pending folder
        $expectedPrefix = 'brems/pending/employee_' . $employee->id;
        if (!str_contains($path, $expectedPrefix)) {
            return response()->json(['message' => 'Invalid file path'], 403);
        }

        $resourceType = $this->cloudinary->getResourceType($path);
        $deleted = $this->cloudinary->delete($path, $resourceType);

        if ($deleted) {
            Log::info("Deleted pending file: {$path}");
            return response()->json(['message' => 'Pending file deleted successfully']);
        }

        return response()->json(['message' => 'Failed to delete file'], 500);
    }

    // =========================================================
    // FILE UTILITIES
    // =========================================================

    public function getFileUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;
        $resourceType = $this->cloudinary->getResourceType($path);
        $url = $this->cloudinary->getUrl($path, $resourceType);

        if (!$url) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->json([
            'url' => $url,
            'exists' => true,
        ]);
    }

    public function downloadFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;
        $resourceType = $this->cloudinary->getResourceType($path);
        $url = $this->cloudinary->getUrl($path, $resourceType);

        if (!$url) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Redirect to Cloudinary URL for download
        return redirect($url);
    }

    /**
     * Create a "Document Update" profile request when an employee uploads their own document.
     * Admin can then approve (assign public_id to employee) or reject (delete from Cloudinary).
     */
    private function createDocumentUpdateRequestIfOwnProfile(
        Employee $employee,
        string $documentType,
        string $publicId,
        array $revertInfo,
        string $resourceType = 'image'
    ): void {
        try {
            ProfileRequest::create([
                'employee_id' => $employee->id,
                'request_type' => 'Document Update',
                'details' => 'Uploaded: ' . $documentType,
                'proposed_changes' => [
                    'document_update' => array_merge([
                        'type' => $documentType,
                        'uploaded_at' => now()->toIso8601String(),
                        'file_path' => $publicId,
                        'resource_type' => $resourceType,
                    ], $revertInfo),
                ],
                'status' => 'pending',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create Document Update profile request: ' . $e->getMessage());
        }
    }
}