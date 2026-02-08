<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileRequestController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\FileController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'app' => 'Bangladesh Railway EMS API'
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Require Authentication)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // =====================================================
    // AUTHENTICATION
    // =====================================================
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    // Legacy support (keeping old routes working)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // =====================================================
    // DASHBOARD
    // =====================================================
    Route::middleware('role:super_admin,office_admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard-stats', [DashboardController::class, 'index']); // Alias
    });

    // =====================================================
    // OFFICES
    // =====================================================
    // Public routes (for dropdowns)
    Route::get('/offices', [OfficeController::class, 'index']);
    Route::get('/offices/tree', [OfficeController::class, 'tree']);
    Route::get('/offices/managed', [OfficeController::class, 'managed']);
    Route::get('/offices/{id}', [OfficeController::class, 'show']);

    // Super Admin Only
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/offices', [OfficeController::class, 'store']);
        Route::put('/offices/{id}', [OfficeController::class, 'update']);
        Route::delete('/offices/{id}', [OfficeController::class, 'destroy']);
    });

    // =====================================================
    // DESIGNATIONS
    // =====================================================
    // Public routes (for dropdowns)
    Route::get('/designations', [DesignationController::class, 'index']);
    Route::get('/designations/{id}', [DesignationController::class, 'show']);

    // Super Admin Only
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/designations', [DesignationController::class, 'store']);
        Route::put('/designations/{id}', [DesignationController::class, 'update']);
        Route::delete('/designations/{id}', [DesignationController::class, 'destroy']);
    });

    // =====================================================
    // EMPLOYEES
    // =====================================================

    // Admin Only - List & Export
    Route::middleware('role:super_admin,office_admin')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/employees/released', [EmployeeController::class, 'releasedEmployees']);
        Route::get('/employees/export-csv', [EmployeeController::class, 'exportCSV']);
        Route::get('/employees/export-pdf', [EmployeeController::class, 'exportPDF']);
    });

    // All Users - View (permission checked in controller)
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::get('/employees/{id}/download-pdf', [EmployeeController::class, 'downloadProfilePdf']);

    // Admin Only - Create & Manage
    Route::middleware('role:super_admin,office_admin')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::post('/employees/{id}/update-full', [EmployeeController::class, 'updateFullProfile']);
        Route::put('/employees/{id}/verify', [EmployeeController::class, 'verify']);
        Route::post('/employees/{id}/release', [EmployeeController::class, 'release']);
        Route::post('/employees/{id}/transfer', [EmployeeController::class, 'transfer']);
        Route::post('/employees/{id}/access', [EmployeeController::class, 'manageAccess']);
        // Photo/document uploads are handled by FileController (allows own-employee for verified users)
    });

    // Super Admin Only
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/employees/{id}/promote', [EmployeeController::class, 'promote']);
        Route::post('/employees/{id}/retire', [EmployeeController::class, 'retire']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    });

    // =====================================================
    // PROFILE REQUESTS
    // =====================================================

    // All Users - Submit & View Own
    Route::get('/profile-requests/my', [ProfileRequestController::class, 'myRequests']);
    Route::post('/profile-requests', [ProfileRequestController::class, 'store']);
    Route::delete('/profile-requests/{id}/cancel', [ProfileRequestController::class, 'cancel']);

    // All Users - View single (permission checked in controller)
    Route::get('/profile-requests/{id}', [ProfileRequestController::class, 'show']);

    // Admin Only - Review & Process
    Route::middleware('role:super_admin,office_admin')->group(function () {
        Route::get('/profile-requests', [ProfileRequestController::class, 'index']);
        Route::get('/profile-requests/pending', [ProfileRequestController::class, 'pending']);
        Route::put('/profile-requests/{id}', [ProfileRequestController::class, 'update']);
        Route::get('/profile-requests/{id}/report', [ProfileRequestController::class, 'downloadReport']);
    });

    // =====================================================
    // FORMS
    // =====================================================

    // All Users - View active forms
    Route::get('/forms', [FormController::class, 'index']);
    Route::get('/forms/{id}', [FormController::class, 'show']);

    // Employees - Submit forms & view own submissions
    Route::post('/forms/{id}/submit', [FormController::class, 'submit']);
    Route::get('/my-submissions', [FormController::class, 'mySubmissions']);

    // Super Admin Only - Form Management
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/forms', [FormController::class, 'store']);
        Route::put('/forms/{id}', [FormController::class, 'update']);
        Route::delete('/forms/{id}', [FormController::class, 'destroy']);
        Route::post('/forms/{id}/toggle-active', [FormController::class, 'toggleActive']);
    });

    // Admin - View all submissions
    Route::middleware('role:super_admin,office_admin')->group(function () {
        Route::get('/form-submissions', [FormController::class, 'submissions']);
        Route::get('/form-submissions/form/{formId}', [FormController::class, 'submissions']);
        Route::get('/form-submissions/{id}', [FormController::class, 'showSubmission']);
        Route::put('/form-submissions/{id}/status', [FormController::class, 'updateSubmissionStatus']);
    });

    // =====================================================
    // USERS
    // =====================================================

    // Admin Only
    Route::middleware('role:super_admin,office_admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/office-admins', [UserController::class, 'officeAdmins']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::post('/users/assign-office-admin', [UserController::class, 'assignOfficeAdmin']);
    });

    // Super Admin Only
    Route::middleware('role:super_admin')->group(function () {
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // =====================================================
    // HISTORY (Transfer & Promotion)
    // =====================================================

    // All Users - View history (permission checked in controller)
    Route::get('/employees/{employeeId}/transfers', [HistoryController::class, 'employeeTransfers']);
    Route::get('/employees/{employeeId}/promotions', [HistoryController::class, 'employeePromotions']);
    Route::get('/employees/{employeeId}/timeline', [HistoryController::class, 'employeeTimeline']);
    Route::get('/transfers/{id}', [HistoryController::class, 'showTransfer']);
    Route::get('/promotions/{id}', [HistoryController::class, 'showPromotion']);

    // Super Admin Only - Edit history
    Route::middleware('role:super_admin')->group(function () {
        Route::put('/transfers/{id}', [HistoryController::class, 'updateTransfer']);
        Route::delete('/transfers/{id}/attachment', [HistoryController::class, 'deleteTransferAttachment']);
        Route::put('/promotions/{id}', [HistoryController::class, 'updatePromotion']);
        Route::delete('/promotions/{id}/attachment', [HistoryController::class, 'deletePromotionAttachment']);
    });

    // =====================================================
    // FILES
    // =====================================================

    // Profile Pictures
    Route::post('/employees/{employeeId}/photo', [FileController::class, 'uploadProfilePicture']);
    Route::delete('/employees/{employeeId}/photo', [FileController::class, 'deleteProfilePicture']);

    // Documents (NID, Birth Certificate)
    Route::post('/employees/{employeeId}/documents/nid', [FileController::class, 'uploadNidDocument']);
    Route::post('/employees/{employeeId}/documents/birth', [FileController::class, 'uploadBirthCertificate']);
    Route::delete('/employees/{employeeId}/documents/{type}', [FileController::class, 'deleteDocument']);

    // Academic Certificates
    Route::post('/employees/{employeeId}/academics/{academicId}/certificate', [FileController::class, 'uploadAcademicCertificate']);
    Route::delete('/employees/{employeeId}/academics/{academicId}/certificate', [FileController::class, 'deleteAcademicCertificate']);

    // Child Birth Certificates
    Route::post('/employees/{employeeId}/children/{familyMemberId}/certificate', [FileController::class, 'uploadChildBirthCertificate']);
    Route::delete('/employees/{employeeId}/children/{familyMemberId}/certificate', [FileController::class, 'deleteChildBirthCertificate']);

    // General file utilities
    Route::post('/files/url', [FileController::class, 'getFileUrl']);
    Route::get('/files/download', [FileController::class, 'downloadFile']);

    // =====================================================
    // REPORTS
    // =====================================================
    Route::middleware('role:super_admin,office_admin')->prefix('reports')->group(function () {
        // JSON Reports
        Route::get('/employee-statistics', [ReportController::class, 'employeeStatistics']);
        Route::get('/transfers', [ReportController::class, 'transferReport']);
        Route::get('/promotions', [ReportController::class, 'promotionReport']);
        Route::get('/profile-requests', [ReportController::class, 'profileRequestReport']);
        Route::get('/offices/zones', [OfficeController::class, 'zones']);
        Route::get('/offices', [ReportController::class, 'officeReport']);
        Route::get('/form-submissions', [ReportController::class, 'formSubmissionReport']);

        // PDF Exports
        Route::get('/employee-statistics/pdf', [ReportController::class, 'exportStatisticsPdf']);
        Route::get('/transfers/pdf', [ReportController::class, 'exportTransfersPdf']);
        Route::get('/promotions/pdf', [ReportController::class, 'exportPromotionsPdf']);
        Route::get('/offices/pdf', [ReportController::class, 'exportOfficesPdf']);
        Route::get('/profile-requests/pdf', [ReportController::class, 'exportProfileRequestsPdf']);
        // Pending files management (inside auth:sanctum group)
        Route::get('/employees/{employee}/pending-files', [FileController::class, 'getPendingFiles']);
        Route::delete('/employees/{employee}/pending-files', [FileController::class, 'deletePendingFile']);
    });

    // Temporary debug endpoint - REMOVE AFTER DEBUGGING
    Route::get('/debug/logs', function () {
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            return response()->json(['error' => 'Log file not found', 'path' => $logFile]);
        }
        
        // Get last 200 lines
        $lines = [];
        $file = new \SplFileObject($logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - 200);
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $lines[] = $file->fgets();
        }
        
        return response()->json([
            'total_lines' => $totalLines,
            'showing_from' => $startLine,
            'logs' => implode('', $lines),
        ]);
    });

    // Temporary debug route - REMOVE in production (requires auth)
    Route::get('/debug/cloudinary', function () {
        try {
            $cloudinaryUrl = env('CLOUDINARY_URL');
            $url = $cloudinaryUrl !== null && $cloudinaryUrl !== '' ? (string) $cloudinaryUrl : '';

            return response()->json([
                'cloudinary_url_exists' => $url !== '',
                'cloudinary_url_valid' => $url !== '' && str_starts_with($url, 'cloudinary://'),
                'app_env' => config('app.env'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    });

    Route::get('/debug/test-service', function () {
        try {
            $service = new \App\Services\CloudinaryService();
            return response()->json([
                'service_created' => true,
                'is_configured' => $service->isConfigured(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    });

    Route::get('/debug/test-upload', function () {
        try {
            $cloudinary = app(\App\Services\CloudinaryService::class);
            return response()->json([
                'service_created' => true,
                'is_configured' => $cloudinary->isConfigured(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    });
});