<?php

namespace App\Http\Controllers;

use App\Models\Punishment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PunishmentController extends Controller
{
    /**
     * List punishments for an employee.
     */
    public function index(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canViewEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $punishments = Punishment::with(['createdBy:id,name'])
            ->where('employee_id', $employeeId)
            ->orderBy('order_date', 'desc')
            ->get();

        // Add order_copy_url for each record
        $punishments->each(function ($p) {
            if ($p->order_copy_path) {
                $p->order_copy_url = Storage::disk('public')->url($p->order_copy_path);
            }
        });

        return response()->json($punishments);
    }

    /**
     * Show a single punishment record.
     */
    public function show(Request $request, $id)
    {
        $punishment = Punishment::with([
            'employee:id,first_name,last_name,nid_number',
            'createdBy:id,name,email'
        ])->findOrFail($id);

        $user = $request->user();
        if (!$user->canViewEmployee($punishment->employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($punishment->order_copy_path) {
            $punishment->order_copy_url = Storage::disk('public')->url($punishment->order_copy_path);
        }

        $punishment->type_label = $punishment->type_label;

        return response()->json($punishment);
    }

    /**
     * Store a new punishment (Office Admin / Super Admin).
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isOfficeAdmin()) {
            return response()->json(['message' => 'Only office admin or super admin can add punishments'], 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'order_date' => 'required|date',
            'punishment_type' => 'required|string|in:' . implode(',', array_keys(Punishment::TYPES)),
            'comment' => 'nullable|string|max:2000',
            'order_copy' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'You cannot add punishments for this employee'], 403);
        }

        $orderCopyPath = null;
        if ($request->hasFile('order_copy')) {
            $orderCopyPath = $request->file('order_copy')->store('punishments', 'public');
        }

        $punishment = Punishment::create([
            'employee_id' => $employee->id,
            'order_date' => $validated['order_date'],
            'order_copy_path' => $orderCopyPath,
            'punishment_type' => $validated['punishment_type'],
            'comment' => $validated['comment'] ?? null,
            'created_by' => $user->id,
        ]);

        $punishment->load('createdBy:id,name');
        if ($punishment->order_copy_path) {
            $punishment->order_copy_url = Storage::disk('public')->url($punishment->order_copy_path);
        }
        $punishment->type_label = $punishment->type_label;

        return response()->json([
            'message' => 'Punishment record added successfully',
            'punishment' => $punishment,
        ], 201);
    }

    /**
     * Update a punishment record (Office Admin / Super Admin).
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $punishment = Punishment::with('employee')->findOrFail($id);

        if (!$user->canManageEmployee($punishment->employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validated = $request->validate([
            'order_date' => 'sometimes|date',
            'punishment_type' => 'sometimes|string|in:' . implode(',', array_keys(Punishment::TYPES)),
            'comment' => 'nullable|string|max:2000',
            'order_copy' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('order_copy')) {
            if ($punishment->order_copy_path) {
                Storage::disk('public')->delete($punishment->order_copy_path);
            }
            $validated['order_copy_path'] = $request->file('order_copy')->store('punishments', 'public');
        }

        unset($validated['order_copy']);
        $punishment->update($validated);

        $punishment->load('createdBy:id,name');
        if ($punishment->order_copy_path) {
            $punishment->order_copy_url = Storage::disk('public')->url($punishment->order_copy_path);
        }
        $punishment->type_label = $punishment->type_label;

        return response()->json([
            'message' => 'Punishment record updated',
            'punishment' => $punishment,
        ]);
    }

    /**
     * Delete a punishment record.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $punishment = Punishment::with('employee')->findOrFail($id);

        if (!$user->canManageEmployee($punishment->employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($punishment->order_copy_path) {
            Storage::disk('public')->delete($punishment->order_copy_path);
        }

        $punishment->delete();

        return response()->json(['message' => 'Punishment record deleted']);
    }

    /**
     * Get punishment types for dropdowns.
     */
    public function types()
    {
        return response()->json([
            'types' => Punishment::TYPES,
        ]);
    }
}
