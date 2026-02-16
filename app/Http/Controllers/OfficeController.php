<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\OfficeDesignationPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    /**
     * Get available zones for dropdowns
     */
    public function zones()
    {
        return response()->json(Office::getZonesForApi());
    }

    /**
     * List all offices
     * Available to all authenticated users (for dropdowns)
     */
    public function index(Request $request)
    {
        $query = Office::with(['parent'])
            ->withCount(['employees' => function ($q) {
                $q->where('status', 'active');
            }]);

        // Filter by zone
        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        // Filter by parent
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Only root offices
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        $offices = $query->orderBy('zone')->orderBy('name')->get();

        // Add computed fields
        $offices->each(function ($office) {
            $office->has_admin = $office->hasAdmin();
            $office->child_count = $office->children()->count();
            $office->zone_label = $office->zone_label;
        });

        return response()->json($offices);
    }

    /**
     * Get office hierarchy tree
     */
    public function tree(Request $request)
    {
        $query = Office::with(['children.children.children'])
            ->whereNull('parent_id')
            ->orderBy('zone')
            ->orderBy('name');

        // Filter by zone
        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        $offices = $query->get();

        return response()->json($this->buildTree($offices));
    }

    /**
     * Build hierarchical tree structure
     */
    private function buildTree($offices)
    {
        return $offices->map(function ($office) {
            return [
                'id' => $office->id,
                'name' => $office->name,
                'code' => $office->code,
                'zone' => $office->zone,
                'zone_label' => $office->zone_label,
                'location' => $office->location,
                'has_admin' => $office->hasAdmin(),
                'employee_count' => $office->employees()->where('status', 'active')->count(),
                'children' => $office->children->isNotEmpty() 
                    ? $this->buildTree($office->children) 
                    : [],
            ];
        });
    }

    /**
     * Show single office details
     */
    public function show($id)
    {
        $office = Office::with([
            'parent',
            'children',
            'employees' => function ($q) {
                $q->where('status', 'active')->with('designation');
            }
        ])->findOrFail($id);

        $office->has_admin = $office->hasAdmin();
        $office->zone_label = $office->zone_label;
        $office->admin = $office->users()
            ->where('role', 'office_admin')
            ->where('is_active', true)
            ->first();

        return response()->json($office);
    }

    /**
     * Create new office (Super Admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'zone' => ['nullable', Rule::in(array_keys(Office::ZONES))],
            'code' => 'required|string|max:50|unique:offices,code',
            'location' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:offices,id',
        ]);

        // If parent has a zone and no zone specified, inherit from parent
        if (empty($validated['zone']) && !empty($validated['parent_id'])) {
            $parent = Office::find($validated['parent_id']);
            $validated['zone'] = $parent->zone;
        }

        $office = Office::create($validated);

        return response()->json([
            'message' => 'Office created successfully',
            'office' => $office->load('parent')
        ], 201);
    }

    /**
     * Update office (Super Admin only)
     */
    public function update(Request $request, $id)
    {
        $office = Office::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'zone' => ['nullable', Rule::in(array_keys(Office::ZONES))],
            'code' => 'sometimes|string|max:50|unique:offices,code,' . $id,
            'location' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:offices,id',
        ]);

        // Prevent circular reference
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json([
                'message' => 'An office cannot be its own parent'
            ], 422);
        }

        // Prevent setting child as parent
        if (isset($validated['parent_id'])) {
            $childIds = $office->getAllChildIds();
            if (in_array($validated['parent_id'], $childIds)) {
                return response()->json([
                    'message' => 'Cannot set a child office as parent'
                ], 422);
            }
        }

        $office->update($validated);

        // Optionally update children zones if this is a root office
        if (isset($validated['zone']) && $request->boolean('update_children_zone')) {
            $office->children()->update(['zone' => $validated['zone']]);
        }

        $fresh = $office->fresh()->load('parent');
        $fresh->zone_label = $fresh->zone_label;

        return response()->json([
            'message' => 'Office updated successfully',
            'office' => $fresh
        ]);
    }

    /**
     * Delete office (Super Admin only)
     */
    public function destroy($id)
    {
        $office = Office::withCount('employees')->findOrFail($id);

        if ($office->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete office with assigned employees. Transfer employees first.'
            ], 422);
        }

        if ($office->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete office with child offices. Delete or reassign children first.'
            ], 422);
        }

        $office->delete();

        return response()->json(['message' => 'Office deleted successfully']);
    }

    /**
     * Get vacant posts report for an office.
     * Returns designations with Total Post (sanctioned), Posted (current employees), Vacant.
     */
    public function vacantPosts(Request $request, $id)
    {
        $user = $request->user();
        $office = Office::findOrFail($id);

        if (!$user->isSuperAdmin() && !in_array((int) $office->id, $user->getManagedOfficeIds())) {
            return response()->json(['message' => 'Access denied to this office'], 403);
        }

        return response()->json($this->getVacantPostsData($id));
    }

    /**
     * Update sanctioned posts (total_posts) for designations in an office.
     * Body: { "posts": [ { "designation_id": 1, "total_posts": 5 }, ... ] }
     */
    public function updateDesignationPosts(Request $request, $id)
    {
        $user = $request->user();
        $office = Office::findOrFail($id);

        if (!$user->isSuperAdmin() && !in_array((int) $office->id, $user->getManagedOfficeIds())) {
            return response()->json(['message' => 'Access denied to this office'], 403);
        }

        $validated = $request->validate([
            'posts' => 'required|array',
            'posts.*.designation_id' => 'required|exists:designations,id',
            'posts.*.total_posts' => 'required|integer|min:0',
        ]);

        foreach ($validated['posts'] as $item) {
            OfficeDesignationPost::updateOrCreate(
                [
                    'office_id' => $id,
                    'designation_id' => $item['designation_id'],
                ],
                ['total_posts' => $item['total_posts']]
            );
        }

        $data = $this->getVacantPostsData($id);
        return response()->json([
            'message' => 'Designation posts updated successfully',
            'office' => $data['office'],
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Build vacant posts data for an office (used by vacantPosts and updateDesignationPosts).
     */
    private function getVacantPostsData($officeId): array
    {
        $office = Office::findOrFail($officeId);
        $designationIds = Designation::where(function ($q) use ($officeId) {
            $q->where('office_id', $officeId)->orWhereNull('office_id');
        })->pluck('id');

        $postsMap = OfficeDesignationPost::where('office_id', $officeId)
            ->whereIn('designation_id', $designationIds)
            ->get()
            ->keyBy('designation_id');

        $postedCounts = Employee::where('current_office_id', $officeId)
            ->where('status', 'active')
            ->whereIn('designation_id', $designationIds)
            ->select('designation_id', DB::raw('count(*) as posted'))
            ->groupBy('designation_id')
            ->pluck('posted', 'designation_id');

        $designations = Designation::whereIn('id', $designationIds)
            ->orderBy('title')
            ->get(['id', 'title', 'title_bn', 'grade']);

        $rows = [];
        $totalSanctioned = 0;
        $totalPosted = 0;

        foreach ($designations as $d) {
            $totalPosts = (int) ($postsMap->get($d->id)?->total_posts ?? 0);
            $posted = (int) ($postedCounts->get($d->id) ?? 0);
            $vacant = max(0, $totalPosts - $posted);

            $rows[] = [
                'designation_id' => $d->id,
                'designation_name' => $d->title,
                'designation_name_bn' => $d->title_bn,
                'grade' => $d->grade,
                'total_posts' => $totalPosts,
                'posted' => $posted,
                'vacant' => $vacant,
            ];

            $totalSanctioned += $totalPosts;
            $totalPosted += $posted;
        }

        return [
            'office' => [
                'id' => $office->id,
                'name' => $office->name,
                'code' => $office->code,
            ],
            'rows' => $rows,
            'totals' => [
                'total_posts' => $totalSanctioned,
                'posted' => $totalPosted,
                'vacant' => max(0, $totalSanctioned - $totalPosted),
            ],
        ];
    }

    /**
     * Get offices managed by current user
     */
    public function managed(Request $request)
    {
        $user = $request->user();
        $officeIds = $user->getManagedOfficeIds();

        $offices = Office::whereIn('id', $officeIds)
            ->with('parent')
            ->withCount(['employees' => function ($q) {
                $q->where('status', 'active');
            }])
            ->orderBy('zone')
            ->orderBy('name')
            ->get();

        $offices->each(function ($office) {
            $office->zone_label = $office->zone_label;
        });

        return response()->json($offices);
    }
}