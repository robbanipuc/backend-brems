<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    /**
     * List all designations
     * Available to all authenticated users (for dropdowns)
     * When office_id is provided: returns designations for that office (office_id = X or office_id is null for "global" designations).
     */
    public function index(Request $request)
    {
        $query = Designation::with(['office:id,name,code'])->withCount('employees');

        // Filter by office: designations belonging to this office OR global (null office_id)
        if ($request->filled('office_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('office_id', $request->office_id)->orWhereNull('office_id');
            });
        }

        // Optional: Sort by grade
        if ($request->boolean('sort_by_grade')) {
            $query->orderBy('grade', 'asc');
        } else {
            $query->orderBy('title', 'asc');
        }

        return response()->json($query->get());
    }

    /**
     * Show single designation
     */
    public function show($id)
    {
        $designation = Designation::with(['office:id,name,code'])->with(['employees' => function ($q) {
            $q->where('status', 'active')
              ->select('id', 'first_name', 'last_name', 'designation_id', 'current_office_id')
              ->with('office:id,name');
        }])->findOrFail($id);

        return response()->json($designation);
    }

    /**
     * Create new designation (Super Admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'office_id' => 'nullable|exists:offices,id',
            'title' => 'required|string|max:255',
            'title_bn' => 'nullable|string|max:255',
            'grade' => 'required|string|max:50',
            'salary_min' => 'required|numeric|min:0',
            'salary_max' => 'required|numeric|min:0|gte:salary_min',
            'method_of_recruitment' => 'nullable|string|max:2000',
            'qualifications' => 'nullable|string|max:2000',
        ]);

        $designation = Designation::create($validated);

        return response()->json([
            'message' => 'Designation created successfully',
            'designation' => $designation
        ], 201);
    }

    /**
     * Update designation (Super Admin only)
     */
    public function update(Request $request, $id)
    {
        $designation = Designation::findOrFail($id);

        $validated = $request->validate([
            'office_id' => 'nullable|exists:offices,id',
            'title' => 'sometimes|string|max:255',
            'title_bn' => 'nullable|string|max:255',
            'grade' => 'sometimes|string|max:50',
            'salary_min' => 'sometimes|numeric|min:0',
            'salary_max' => 'sometimes|numeric|min:0|gte:salary_min',
            'method_of_recruitment' => 'nullable|string|max:2000',
            'qualifications' => 'nullable|string|max:2000',
        ]);

        $designation->update($validated);

        return response()->json([
            'message' => 'Designation updated successfully',
            'designation' => $designation->fresh()
        ]);
    }

    /**
     * Delete designation (Super Admin only)
     * Only if no employees have this designation
     */
    public function destroy($id)
    {
        $designation = Designation::withCount('employees')->findOrFail($id);

        if ($designation->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete designation with assigned employees. Reassign employees first.'
            ], 422);
        }

        $designation->delete();

        return response()->json(['message' => 'Designation deleted successfully']);
    }
}