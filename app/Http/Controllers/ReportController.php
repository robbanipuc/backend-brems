<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Office;
use App\Models\Designation;
use App\Models\TransferHistory;
use App\Models\PromotionHistory;
use App\Models\ProfileRequest;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Resources\EmployeeResource;
class ReportController extends Controller
{
    // =========================================================
    // EMPLOYEE STATISTICS REPORT
    // =========================================================
    public function employeeStatistics(Request $request)
    {
        $user = $request->user();
        $officeIds = $user->isSuperAdmin() ? null : $user->getManagedOfficeIds();

        // Base query
        $query = Employee::query();
        if ($officeIds) {
            $query->whereIn('current_office_id', $officeIds);
        }

        // Total counts
        $totalEmployees = (clone $query)->count();
        $activeEmployees = (clone $query)->where('status', 'active')->count();

        // By Status
        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // By Gender
        $byGender = (clone $query)
            ->whereNotNull('gender')
            ->where('status', 'active')
            ->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        // By Religion
        $byReligion = (clone $query)
            ->whereNotNull('religion')
            ->where('status', 'active')
            ->select('religion', DB::raw('count(*) as count'))
            ->groupBy('religion')
            ->orderByDesc('count')
            ->pluck('count', 'religion')
            ->toArray();

        // By Blood Group
        $byBloodGroup = (clone $query)
            ->whereNotNull('blood_group')
            ->where('status', 'active')
            ->select('blood_group', DB::raw('count(*) as count'))
            ->groupBy('blood_group')
            ->pluck('count', 'blood_group')
            ->toArray();

        // By Marital Status
        $byMaritalStatus = (clone $query)
            ->whereNotNull('marital_status')
            ->where('status', 'active')
            ->select('marital_status', DB::raw('count(*) as count'))
            ->groupBy('marital_status')
            ->pluck('count', 'marital_status')
            ->toArray();

        // By Designation (Top 15)
        $byDesignation = (clone $query)
            ->where('status', 'active')
            ->join('designations', 'employees.designation_id', '=', 'designations.id')
            ->select('designations.title', 'designations.grade', DB::raw('count(*) as count'))
            ->groupBy('designations.title', 'designations.grade')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'title' => $item->title,
                    'grade' => $item->grade,
                    'count' => $item->count,
                ];
            });

        // By Office (Top 15)
        $byOffice = (clone $query)
            ->where('status', 'active')
            ->join('offices', 'employees.current_office_id', '=', 'offices.id')
            ->select('offices.name', 'offices.code', DB::raw('count(*) as count'))
            ->groupBy('offices.name', 'offices.code')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'code' => $item->code,
                    'count' => $item->count,
                ];
            });

        // Verification Status
        $verificationStatus = [
            'verified' => (clone $query)->where('status', 'active')->where('is_verified', true)->count(),
            'unverified' => (clone $query)->where('status', 'active')->where('is_verified', false)->count(),
        ];

        // Age Distribution
        $ageDistribution = $this->getAgeDistribution(clone $query);

        // Joining Trends (Last 12 months)
        $joiningTrends = $this->getJoiningTrends(clone $query);

        // Salary Distribution by Grade
        $salaryByGrade = Designation::select('grade', DB::raw('AVG(salary_min) as avg_salary'), DB::raw('MIN(salary_min) as min_salary'), DB::raw('MAX(salary_max) as max_salary'))
            ->groupBy('grade')
            ->orderBy('grade')
            ->get();

        return response()->json([
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'by_status' => $byStatus,
            'by_gender' => $byGender,
            'by_religion' => $byReligion,
            'by_blood_group' => $byBloodGroup,
            'by_marital_status' => $byMaritalStatus,
            'by_designation' => $byDesignation,
            'by_office' => $byOffice,
            'verification_status' => $verificationStatus,
            'age_distribution' => $ageDistribution,
            'joining_trends' => $joiningTrends,
            'salary_by_grade' => $salaryByGrade,
        ]);
    }

    /**
     * Get age distribution of employees
     */
    private function getAgeDistribution($query)
    {
        $employees = (clone $query)
            ->where('status', 'active')
            ->whereNotNull('dob')
            ->select('dob')
            ->get();

        $distribution = [
            '18-25' => 0,
            '26-30' => 0,
            '31-35' => 0,
            '36-40' => 0,
            '41-45' => 0,
            '46-50' => 0,
            '51-55' => 0,
            '56-59' => 0,
            '60+' => 0,
        ];

        foreach ($employees as $emp) {
            $age = Carbon::parse($emp->dob)->age;

            if ($age >= 18 && $age <= 25) {
                $distribution['18-25']++;
            } elseif ($age >= 26 && $age <= 30) {
                $distribution['26-30']++;
            } elseif ($age >= 31 && $age <= 35) {
                $distribution['31-35']++;
            } elseif ($age >= 36 && $age <= 40) {
                $distribution['36-40']++;
            } elseif ($age >= 41 && $age <= 45) {
                $distribution['41-45']++;
            } elseif ($age >= 46 && $age <= 50) {
                $distribution['46-50']++;
            } elseif ($age >= 51 && $age <= 55) {
                $distribution['51-55']++;
            } elseif ($age >= 56 && $age <= 59) {
                $distribution['56-59']++;
            } else {
                $distribution['60+']++;
            }
        }

        return $distribution;
    }

    /**
     * Get joining trends for last 12 months
     */
    private function getJoiningTrends($query)
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $count = (clone $query)
                ->whereBetween('joining_date', [$monthStart, $monthEnd])
                ->count();

            $trends[] = [
                'month' => $date->format('M Y'),
                'count' => $count,
            ];
        }

        return $trends;
    }

    // =========================================================
    // TRANSFER REPORT
    // =========================================================
    public function transferReport(Request $request)
    {
        $user = $request->user();

        $query = TransferHistory::with([
            'employee:id,first_name,last_name,nid_number',
            'fromOffice:id,name,code',
            'toOffice:id,name,code',
            'createdBy:id,name'
        ]);

        // Filter by managed offices
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->where(function ($q) use ($officeIds) {
                $q->whereIn('from_office_id', $officeIds)
                    ->orWhereIn('to_office_id', $officeIds);
            });
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('transfer_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transfer_date', '<=', $request->to_date);
        }

        // Office filter
        if ($request->filled('office_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('from_office_id', $request->office_id)
                    ->orWhere('to_office_id', $request->office_id);
            });
        }

        // Employee filter
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $transfers = $query->orderBy('transfer_date', 'desc')->get();

        // Summary statistics
        $summary = [
            'total_transfers' => $transfers->count(),
            'transfers_in' => $transfers->whereNotNull('from_office_id')->count(),
            'initial_postings' => $transfers->whereNull('from_office_id')->count(),
            'unique_employees' => $transfers->pluck('employee_id')->unique()->count(),
        ];

        // Transfers by month (last 6 months)
        $byMonth = $this->getTransfersByMonth($query);

        // Most active offices (transfers in/out)
        $officeActivity = $this->getOfficeTransferActivity($transfers);

        return response()->json([
            'summary' => $summary,
            'by_month' => $byMonth,
            'office_activity' => $officeActivity,
            'transfers' => $transfers,
        ]);
    }

    /**
     * Get transfers grouped by month
     */
    private function getTransfersByMonth($query)
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $count = (clone $query)
                ->whereBetween('transfer_date', [$monthStart, $monthEnd])
                ->count();

            $trends[] = [
                'month' => $date->format('M Y'),
                'count' => $count,
            ];
        }

        return $trends;
    }

    /**
     * Get office transfer activity
     */
    private function getOfficeTransferActivity($transfers)
    {
        $officeStats = [];

        foreach ($transfers as $transfer) {
            // Transfers out
            if ($transfer->from_office_id) {
                $fromOffice = $transfer->fromOffice;
                if ($fromOffice) {
                    if (!isset($officeStats[$fromOffice->id])) {
                        $officeStats[$fromOffice->id] = [
                            'name' => $fromOffice->name,
                            'code' => $fromOffice->code,
                            'transfers_out' => 0,
                            'transfers_in' => 0,
                        ];
                    }
                    $officeStats[$fromOffice->id]['transfers_out']++;
                }
            }

            // Transfers in
            $toOffice = $transfer->toOffice;
            if ($toOffice) {
                if (!isset($officeStats[$toOffice->id])) {
                    $officeStats[$toOffice->id] = [
                        'name' => $toOffice->name,
                        'code' => $toOffice->code,
                        'transfers_out' => 0,
                        'transfers_in' => 0,
                    ];
                }
                $officeStats[$toOffice->id]['transfers_in']++;
            }
        }

        // Sort by total activity
        usort($officeStats, function ($a, $b) {
            return ($b['transfers_in'] + $b['transfers_out']) - ($a['transfers_in'] + $a['transfers_out']);
        });

        return array_slice($officeStats, 0, 10);
    }

    // =========================================================
    // PROMOTION REPORT
    // =========================================================
    public function promotionReport(Request $request)
    {
        $user = $request->user();

        $query = PromotionHistory::with([
            'employee:id,first_name,last_name,nid_number,current_office_id',
            'employee.office:id,name,code',
            'newDesignation:id,title,grade,salary_min,salary_max',
            'createdBy:id,name'
        ]);

        // Filter by managed offices
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('promotion_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('promotion_date', '<=', $request->to_date);
        }

        // Designation filter
        if ($request->filled('designation_id')) {
            $query->where('new_designation_id', $request->designation_id);
        }

        // Employee filter
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $promotions = $query->orderBy('promotion_date', 'desc')->get();

        // Summary
        $summary = [
            'total_promotions' => $promotions->count(),
            'unique_employees' => $promotions->pluck('employee_id')->unique()->count(),
            'unique_designations' => $promotions->pluck('new_designation_id')->unique()->count(),
        ];

        // By designation
        $byDesignation = $promotions->groupBy(function ($item) {
            return $item->newDesignation->title ?? 'Unknown';
        })->map(fn($items) => $items->count())->toArray();

        // By month (last 6 months)
        $byMonth = $this->getPromotionsByMonth($query);

        // By grade
        $byGrade = $promotions->groupBy(function ($item) {
            return $item->newDesignation->grade ?? 'Unknown';
        })->map(fn($items) => $items->count())->toArray();

        return response()->json([
            'summary' => $summary,
            'by_designation' => $byDesignation,
            'by_grade' => $byGrade,
            'by_month' => $byMonth,
            'promotions' => $promotions,
        ]);
    }

    /**
     * Get promotions grouped by month
     */
    private function getPromotionsByMonth($query)
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $count = (clone $query)
                ->whereBetween('promotion_date', [$monthStart, $monthEnd])
                ->count();

            $trends[] = [
                'month' => $date->format('M Y'),
                'count' => $count,
            ];
        }

        return $trends;
    }

    // =========================================================
    // PROFILE REQUEST REPORT
    // =========================================================
    public function profileRequestReport(Request $request)
    {
        $user = $request->user();

        $query = ProfileRequest::with([
            'employee:id,first_name,last_name,current_office_id',
            'employee.office:id,name',
            'reviewedBy:id,name'
        ]);

        // Filter by managed offices
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        // Summary
        $summary = [
            'total' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'processed' => $requests->where('status', 'processed')->count(),
            'approved' => $requests->where('status', 'processed')->where('is_approved', true)->count(),
            'rejected' => $requests->where('status', 'processed')->where('is_approved', false)->count(),
        ];

        // Approval rate
        $processed = $requests->where('status', 'processed');
        $summary['approval_rate'] = $processed->count() > 0
            ? round(($processed->where('is_approved', true)->count() / $processed->count()) * 100, 1)
            : 0;

        // By type
        $byType = $requests->groupBy('request_type')
            ->map(fn($items) => [
                'total' => $items->count(),
                'approved' => $items->where('status', 'processed')->where('is_approved', true)->count(),
                'rejected' => $items->where('status', 'processed')->where('is_approved', false)->count(),
                'pending' => $items->where('status', 'pending')->count(),
            ])->toArray();

        // Average processing time (in days)
        $processedRequests = $requests->where('status', 'processed')->filter(fn($r) => $r->reviewed_at);
        $avgProcessingTime = $processedRequests->count() > 0
            ? round($processedRequests->avg(fn($r) => Carbon::parse($r->created_at)->diffInDays($r->reviewed_at)), 1)
            : 0;
        $summary['avg_processing_days'] = $avgProcessingTime;

        // By month
        $byMonth = $this->getRequestsByMonth($query);

        return response()->json([
            'summary' => $summary,
            'by_type' => $byType,
            'by_month' => $byMonth,
            'requests' => $requests,
        ]);
    }

    /**
     * Get requests grouped by month
     */
    private function getRequestsByMonth($query)
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $monthQuery = (clone $query)->whereBetween('created_at', [$monthStart, $monthEnd]);

            $trends[] = [
                'month' => $date->format('M Y'),
                'total' => (clone $monthQuery)->count(),
                'approved' => (clone $monthQuery)->where('status', 'processed')->where('is_approved', true)->count(),
                'rejected' => (clone $monthQuery)->where('status', 'processed')->where('is_approved', false)->count(),
            ];
        }

        return $trends;
    }

    // =========================================================
    // OFFICE REPORT
    // =========================================================
    public function officeReport(Request $request)
    {
        $user = $request->user();

        $query = Office::with('parent:id,name')
            ->withCount([
                'employees as total_employees' => function ($q) {
                    $q->where('status', 'active');
                },
                'employees as verified_employees' => function ($q) {
                    $q->where('status', 'active')->where('is_verified', true);
                },
                'employees as unverified_employees' => function ($q) {
                    $q->where('status', 'active')->where('is_verified', false);
                },
                'children'
            ]);

        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereIn('id', $officeIds);
        }

        // Parent filter
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Root offices only
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        $offices = $query->orderBy('name')->get()->map(function ($office) {
            $office->has_admin = $office->hasAdmin();
            $office->admin_name = $office->users()
                ->where('role', 'office_admin')
                ->where('is_active', true)
                ->value('name');
            return $office;
        });

        // Summary
        $summary = [
            'total_offices' => $offices->count(),
            'offices_with_admin' => $offices->where('has_admin', true)->count(),
            'offices_without_admin' => $offices->where('has_admin', false)->count(),
            'total_employees' => $offices->sum('total_employees'),
            'total_verified' => $offices->sum('verified_employees'),
            'total_unverified' => $offices->sum('unverified_employees'),
            'root_offices' => $offices->whereNull('parent_id')->count(),
        ];

        // Offices with most employees
        $topOffices = $offices->sortByDesc('total_employees')->take(10)->values();

        // Offices without admin
        $adminlessOffices = $offices->where('has_admin', false)->values();

        return response()->json([
            'summary' => $summary,
            'top_offices' => $topOffices,
            'adminless_offices' => $adminlessOffices,
            'offices' => $offices,
        ]);
    }

    // =========================================================
    // FORM SUBMISSION REPORT
    // =========================================================
    public function formSubmissionReport(Request $request)
    {
        $user = $request->user();

        $query = FormSubmission::with([
            'form:id,title',
            'employee:id,first_name,last_name,current_office_id',
            'employee.office:id,name'
        ]);

        // Filter by managed offices
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        // Form filter
        if ($request->filled('form_id')) {
            $query->where('form_id', $request->form_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $submissions = $query->orderBy('created_at', 'desc')->get();

        // Summary
        $summary = [
            'total' => $submissions->count(),
            'pending' => $submissions->where('status', 'pending')->count(),
            'approved' => $submissions->where('status', 'approved')->count(),
            'rejected' => $submissions->where('status', 'rejected')->count(),
        ];

        // By form
        $byForm = $submissions->groupBy(function ($item) {
            return $item->form->title ?? 'Unknown';
        })->map(fn($items) => [
            'total' => $items->count(),
            'pending' => $items->where('status', 'pending')->count(),
            'approved' => $items->where('status', 'approved')->count(),
            'rejected' => $items->where('status', 'rejected')->count(),
        ])->toArray();

        return response()->json([
            'summary' => $summary,
            'by_form' => $byForm,
            'submissions' => $submissions,
        ]);
    }

    // =========================================================
    // EXPORT REPORTS AS PDF
    // =========================================================
    public function exportStatisticsPdf(Request $request)
    {
        $user = $request->user();

        // Get statistics data
        $statsResponse = $this->employeeStatistics($request);
        $stats = json_decode($statsResponse->getContent(), true);

        $data = [
            'title' => 'Employee Statistics Report',
            'subtitle' => 'Bangladesh Railway - Employee Management System',
            'generated_by' => $user->name,
            'generated_at' => now()->format('d M Y, h:i A'),
            'stats' => $stats,
        ];

        $pdf = Pdf::loadView('reports.statistics', $data);

        return $pdf->download('employee_statistics_' . date('Ymd_His') . '.pdf');
    }

    public function exportTransfersPdf(Request $request)
    {
        $user = $request->user();

        $transferResponse = $this->transferReport($request);
        $transferData = json_decode($transferResponse->getContent(), true);

        $data = [
            'title' => 'Transfer History Report',
            'subtitle' => 'Bangladesh Railway - Employee Management System',
            'generated_by' => $user->name,
            'generated_at' => now()->format('d M Y, h:i A'),
            'from_date' => $request->from_date ?? 'All Time',
            'to_date' => $request->to_date ?? 'Present',
            'summary' => $transferData['summary'],
            'transfers' => $transferData['transfers'],
        ];

        $pdf = Pdf::loadView('reports.transfers', $data)->setPaper('a4', 'landscape');

        return $pdf->download('transfer_report_' . date('Ymd_His') . '.pdf');
    }

    public function exportPromotionsPdf(Request $request)
    {
        $user = $request->user();

        $promotionResponse = $this->promotionReport($request);
        $promotionData = json_decode($promotionResponse->getContent(), true);

        $data = [
            'title' => 'Promotion History Report',
            'subtitle' => 'Bangladesh Railway - Employee Management System',
            'generated_by' => $user->name,
            'generated_at' => now()->format('d M Y, h:i A'),
            'from_date' => $request->from_date ?? 'All Time',
            'to_date' => $request->to_date ?? 'Present',
            'summary' => $promotionData['summary'],
            'by_designation' => $promotionData['by_designation'],
            'promotions' => $promotionData['promotions'],
        ];

        $pdf = Pdf::loadView('reports.promotions', $data)->setPaper('a4', 'landscape');

        return $pdf->download('promotion_report_' . date('Ymd_His') . '.pdf');
    }

    public function exportOfficesPdf(Request $request)
    {
        $user = $request->user();

        $officeResponse = $this->officeReport($request);
        $officeData = json_decode($officeResponse->getContent(), true);

        $data = [
            'title' => 'Office Directory Report',
            'subtitle' => 'Bangladesh Railway - Employee Management System',
            'generated_by' => $user->name,
            'generated_at' => now()->format('d M Y, h:i A'),
            'summary' => $officeData['summary'],
            'offices' => $officeData['offices'],
        ];

        $pdf = Pdf::loadView('reports.offices', $data)->setPaper('a4', 'landscape');

        return $pdf->download('office_report_' . date('Ymd_His') . '.pdf');
    }

    public function exportProfileRequestsPdf(Request $request)
    {
        $user = $request->user();

        $requestResponse = $this->profileRequestReport($request);
        if ($requestResponse->getStatusCode() !== 200) {
            return $requestResponse;
        }
        $requestData = json_decode($requestResponse->getContent(), true);
        if (!is_array($requestData)) {
            return response()->json(['message' => 'Failed to generate report data'], 500);
        }

        $data = [
            'title' => 'Profile Request Report',
            'subtitle' => 'Bangladesh Railway - Employee Management System',
            'generated_by' => $user->name ?? 'System',
            'generated_at' => now()->format('d M Y, h:i A'),
            'from_date' => $request->from_date ?? 'All Time',
            'to_date' => $request->to_date ?? 'Present',
            'summary' => $requestData['summary'] ?? [],
            'by_type' => $requestData['by_type'] ?? [],
            'requests' => $requestData['requests'] ?? [],
        ];

        $pdf = Pdf::loadView('reports.profile_requests', $data)->setPaper('a4', 'landscape');

        return $pdf->download('profile_requests_report_' . date('Ymd_His') . '.pdf');
    }
}