<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Employee;

class CheckOfficeAccess
{
    /**
     * Handle an incoming request.
     * Checks if user can access the employee/resource based on office hierarchy.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admin bypasses all checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Get employee ID from route parameter
        $employeeId = $request->route('id') ?? $request->route('employee');

        if ($employeeId) {
            $employee = Employee::find($employeeId);

            if ($employee && !$user->canManageEmployee($employee)) {
                return response()->json([
                    'message' => 'You do not have permission to access this employee.'
                ], 403);
            }
        }

        return $next($request);
    }
}