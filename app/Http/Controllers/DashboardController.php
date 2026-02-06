<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Office;
use App\Models\ProfileRequest;
use App\Models\Designation;
use App\Models\TransferHistory;
use App\Models\PromotionHistory;
use App\Http\Resources\EmployeeResource;
class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * Super Admin: All data
     * Office Admin: Own office + adminless child offices
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $officeIds = $user->getManagedOfficeIds();

        // 1. Basic Counters
        $counts = $this->getCounts($user, $officeIds);

        // 2. Office Statistics (Top 5 by employee count)
        $officeStats = $this->getOfficeStats($user, $officeIds);

        // 3. Recent Activity Feed
        $activity = $this->getRecentActivity($user, $officeIds);

        // 4. Pending Items
        $pendingItems = $this->getPendingItems($user, $officeIds);

        return response()->json([
            'counts' => $counts,
            'office_stats' => $officeStats,
            'activity' => $activity,
            'pending_items' => $pendingItems,
            'user_role' => $user->role,
            'managed_offices' => $officeIds
        ]);
    }

    /**
     * Get basic counts
     */
    private function getCounts($user, array $officeIds): array
    {
        $employeeQuery = Employee::where('status', 'active');
        $requestQuery = ProfileRequest::where('status', 'pending');

        if (!$user->isSuperAdmin()) {
            $employeeQuery->whereIn('current_office_id', $officeIds);
            $requestQuery->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        return [
            'total_employees' => $employeeQuery->count(),
            'active_employees' => (clone $employeeQuery)->where('is_verified', true)->count(),
            'unverified_employees' => (clone $employeeQuery)->where('is_verified', false)->count(),
            'offices' => $user->isSuperAdmin() ? Office::count() : count($officeIds),
            'pending_requests' => $requestQuery->count(),
            'designations' => Designation::count(),
            'released_employees' => Employee::where('status', 'released')->count(),
            'retired_employees' => Employee::where('status', 'retired')->count(),
        ];
    }

    /**
     * Get office statistics
     */
    private function getOfficeStats($user, array $officeIds)
    {
        $query = Office::withCount(['employees' => function ($q) {
            $q->where('status', 'active');
        }]);

        if (!$user->isSuperAdmin()) {
            $query->whereIn('id', $officeIds);
        }

        return $query->orderByDesc('employees_count')
            ->take(10)
            ->get()
            ->map(function ($office) {
                return [
                    'id' => $office->id,
                    'name' => $office->name,
                    'code' => $office->code,
                    'employee_count' => $office->employees_count,
                    'has_admin' => $office->hasAdmin(),
                ];
            });
    }

    /**
     * Get recent activity feed
     */
    private function getRecentActivity($user, array $officeIds)
    {
        // Recent Transfers
        $transferQuery = TransferHistory::with(['employee', 'toOffice', 'fromOffice'])
            ->latest('transfer_date')
            ->take(10);

        if (!$user->isSuperAdmin()) {
            $transferQuery->where(function ($q) use ($officeIds) {
                $q->whereIn('from_office_id', $officeIds)
                  ->orWhereIn('to_office_id', $officeIds);
            });
        }

        $transfers = $transferQuery->get()->map(function ($item) {
            $fromName = $item->fromOffice ? $item->fromOffice->name : 'Initial Posting';
            return [
                'type' => 'transfer',
                'icon' => 'arrow-right-left',
                'date' => $item->transfer_date->format('Y-m-d'),
                'title' => 'Employee Transfer',
                'description' => "{$item->employee->full_name} transferred from {$fromName} to {$item->toOffice->name}",
                'id' => $item->id,
                'employee_id' => $item->employee_id,
            ];
        });

        // Recent Promotions
        $promotionQuery = PromotionHistory::with(['employee', 'newDesignation'])
            ->latest('promotion_date')
            ->take(10);

        if (!$user->isSuperAdmin()) {
            $promotionQuery->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        $promotions = $promotionQuery->get()->map(function ($item) {
            return [
                'type' => 'promotion',
                'icon' => 'trending-up',
                'date' => $item->promotion_date->format('Y-m-d'),
                'title' => 'Employee Promotion',
                'description' => "{$item->employee->full_name} promoted to {$item->newDesignation->title}",
                'id' => $item->id,
                'employee_id' => $item->employee_id,
            ];
        });

        // Recent Profile Requests
        $requestQuery = ProfileRequest::with(['employee'])
            ->where('status', 'processed')
            ->latest('reviewed_at')
            ->take(5);

        if (!$user->isSuperAdmin()) {
            $requestQuery->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        $requests = $requestQuery->get()->map(function ($item) {
            $status = $item->is_approved ? 'approved' : 'rejected';
            return [
                'type' => 'request_' . $status,
                'icon' => $item->is_approved ? 'check-circle' : 'x-circle',
                'date' => $item->reviewed_at?->format('Y-m-d') ?? $item->updated_at->format('Y-m-d'),
                'title' => "Profile Request " . ucfirst($status),
                'description' => "{$item->employee->full_name}'s {$item->request_type} request was {$status}",
                'id' => $item->id,
                'employee_id' => $item->employee_id,
            ];
        });

        // Merge and sort by date
        return $transfers->merge($promotions)
            ->merge($requests)
            ->sortByDesc('date')
            ->values()
            ->take(15);
    }

    /**
     * Get pending items requiring attention
     */
    private function getPendingItems($user, array $officeIds): array
    {
        // Pending Profile Requests
        $requestQuery = ProfileRequest::with(['employee.designation'])
            ->where('status', 'pending')
            ->latest();

        if (!$user->isSuperAdmin()) {
            $requestQuery->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        $pendingRequests = $requestQuery->take(5)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'profile_request',
                'employee_name' => $item->employee->full_name,
                'employee_id' => $item->employee_id,
                'request_type' => $item->request_type,
                'submitted_at' => $item->created_at->format('Y-m-d H:i'),
            ];
        });

        // Unverified Employees
        $unverifiedQuery = Employee::with(['designation', 'office'])
            ->where('status', 'active')
            ->where('is_verified', false)
            ->latest();

        if (!$user->isSuperAdmin()) {
            $unverifiedQuery->whereIn('current_office_id', $officeIds);
        }

        $unverifiedEmployees = $unverifiedQuery->take(5)->get()->map(function ($emp) {
            return [
                'id' => $emp->id,
                'type' => 'unverified_employee',
                'employee_name' => $emp->full_name,
                'designation' => $emp->designation->title ?? 'N/A',
                'office' => $emp->office->name ?? 'N/A',
                'joined_at' => $emp->created_at->format('Y-m-d'),
            ];
        });

        return [
            'profile_requests' => $pendingRequests,
            'unverified_employees' => $unverifiedEmployees,
        ];
    }
}