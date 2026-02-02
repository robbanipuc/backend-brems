<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AcademicRecord;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\EmployeeResource;
class FileController extends Controller
{
    // =========================================================
    // PROFILE PICTURE
    // =========================================================

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        // Permission check
        if (!$user->canManageEmployee($employee) && $user->employee_id !== $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Delete old photo
        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
        }

        $filename = $employee->nid_number . '_' . time() . '.' . $request->file('photo')->extension();
        $path = $request->file('photo')->storeAs('photos', $filename, 'public');

        $employee->update(['profile_picture' => $path]);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    /**
     * Delete profile picture
     */
    public function deleteProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
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
     * Upload NID document
     */
    public function uploadNidDocument(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && $user->employee_id !== $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Delete old file
        if ($employee->nid_file_path) {
            Storage::disk('public')->delete($employee->nid_file_path);
        }

        $filename = 'NID_' . $employee->nid_number . '_' . time() . '.' . $request->file('document')->extension();
        $path = $request->file('document')->storeAs('documents/nid', $filename, 'public');

        $employee->update(['nid_file_path' => $path]);

        return response()->json([
            'message' => 'NID document uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    /**
     * Upload birth certificate document
     */
    public function uploadBirthCertificate(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && $user->employee_id !== $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Delete old file
        if ($employee->birth_file_path) {
            Storage::disk('public')->delete($employee->birth_file_path);
        }

        $filename = 'BIRTH_' . $employee->nid_number . '_' . time() . '.' . $request->file('document')->extension();
        $path = $request->file('document')->storeAs('documents/birth', $filename, 'public');

        $employee->update(['birth_file_path' => $path]);

        return response()->json([
            'message' => 'Birth certificate uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    /**
     * Delete document (NID or Birth Certificate)
     */
    public function deleteDocument(Request $request, $employeeId, $type)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
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

        if (!$user->canManageEmployee($employee) && $user->employee_id !== $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)
            ->findOrFail($academicId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Delete old certificate
        if ($academic->certificate_path) {
            Storage::disk('public')->delete($academic->certificate_path);
        }

        $examSlug = str_replace(['/', ' '], '_', $academic->exam_name);
        $filename = 'CERT_' . $employee->nid_number . '_' . $examSlug . '_' . time() . '.' . $request->file('certificate')->extension();
        $path = $request->file('certificate')->storeAs('documents/certificates', $filename, 'public');

        $academic->update(['certificate_path' => $path]);

        return response()->json([
            'message' => 'Certificate uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    /**
     * Delete academic certificate
     */
    public function deleteAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
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

        if (!$user->canManageEmployee($employee) && $user->employee_id !== $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $child = FamilyMember::where('employee_id', $employeeId)
            ->where('relation', 'child')
            ->findOrFail($familyMemberId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Delete old certificate
        if ($child->birth_certificate_path) {
            Storage::disk('public')->delete($child->birth_certificate_path);
        }

        $childNameSlug = str_replace(' ', '_', $child->name);
        $filename = 'CHILD_BIRTH_' . $employee->nid_number . '_' . $childNameSlug . '_' . time() . '.' . $request->file('certificate')->extension();
        $path = $request->file('certificate')->storeAs('documents/children', $filename, 'public');

        $child->update(['birth_certificate_path' => $path]);

        return response()->json([
            'message' => 'Child birth certificate uploaded successfully',
            'path' => $path,
            'url' => Storage::disk('public')->url($path)
        ]);
    }

    /**
     * Delete child's birth certificate
     */
    public function deleteChildBirthCertificate(Request $request, $employeeId, $familyMemberId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
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