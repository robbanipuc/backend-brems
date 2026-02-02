<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\EmployeeResource;
class FormController extends Controller
{
    // =========================================================
    // LIST FORMS
    // =========================================================
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Form::with(['fields' => function ($q) {
            $q->orderBy('order');
        }, 'createdBy:id,name']);

        // For verified users, only show active forms
        if ($user->isVerifiedUser()) {
            $query->where('is_active', true);
        }

        // Add submission count for admins
        if ($user->isSuperAdmin() || $user->isOfficeAdmin()) {
            $query->withCount('submissions');
        }

        return response()->json($query->latest()->get());
    }

    // =========================================================
    // SHOW SINGLE FORM
    // =========================================================
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $form = Form::with(['fields' => function ($q) {
            $q->orderBy('order');
        }, 'createdBy:id,name'])->findOrFail($id);

        // Check if form is active for regular users
        if ($user->isVerifiedUser() && !$form->is_active) {
            return response()->json(['message' => 'This form is not available'], 404);
        }

        // Check if user has already submitted
        if ($user->employee_id) {
            $form->user_submitted = FormSubmission::where('form_id', $id)
                ->where('employee_id', $user->employee_id)
                ->exists();

            $form->user_submission = FormSubmission::where('form_id', $id)
                ->where('employee_id', $user->employee_id)
                ->first();
        }

        return response()->json($form);
    }

    // =========================================================
    // CREATE FORM (Super Admin Only)
    // =========================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'fields' => 'required|array|min:1',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,number,date,select,file,textarea,checkbox,radio',
            'fields.*.options' => 'nullable|array',
            'fields.*.required' => 'boolean',
            'fields.*.order' => 'nullable|integer',
        ]);

        try {
            $form = DB::transaction(function () use ($validated, $request) {
                $form = Form::create([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($validated['fields'] as $index => $field) {
                    $form->fields()->create([
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'options' => $field['options'] ?? null,
                        'required' => $field['required'] ?? true,
                        'order' => $field['order'] ?? $index,
                    ]);
                }

                return $form;
            });

            return response()->json([
                'message' => 'Form created successfully',
                'form' => $form->load('fields')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Form Create Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create form: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // UPDATE FORM (Super Admin Only)
    // =========================================================
    public function update(Request $request, $id)
    {
        $form = Form::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'fields' => 'sometimes|array|min:1',
            'fields.*.id' => 'nullable|exists:form_fields,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,number,date,select,file,textarea,checkbox,radio',
            'fields.*.options' => 'nullable|array',
            'fields.*.required' => 'boolean',
            'fields.*.order' => 'nullable|integer',
        ]);

        try {
            DB::transaction(function () use ($form, $validated) {
                $form->update([
                    'title' => $validated['title'] ?? $form->title,
                    'description' => $validated['description'] ?? $form->description,
                    'is_active' => $validated['is_active'] ?? $form->is_active,
                ]);

                if (isset($validated['fields'])) {
                    // Delete old fields and create new ones
                    $form->fields()->delete();

                    foreach ($validated['fields'] as $index => $field) {
                        $form->fields()->create([
                            'label' => $field['label'],
                            'type' => $field['type'],
                            'options' => $field['options'] ?? null,
                            'required' => $field['required'] ?? true,
                            'order' => $field['order'] ?? $index,
                        ]);
                    }
                }
            });

            return response()->json([
                'message' => 'Form updated successfully',
                'form' => $form->fresh()->load('fields')
            ]);

        } catch (\Exception $e) {
            Log::error('Form Update Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update form: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // DELETE FORM (Super Admin Only)
    // =========================================================
    public function destroy($id)
    {
        $form = Form::withCount('submissions')->findOrFail($id);

        if ($form->submissions_count > 0) {
            return response()->json([
                'message' => 'Cannot delete form with existing submissions. Deactivate it instead.'
            ], 422);
        }

        $form->delete();

        return response()->json(['message' => 'Form deleted successfully']);
    }

    // =========================================================
    // TOGGLE FORM ACTIVE STATUS
    // =========================================================
    public function toggleActive($id)
    {
        $form = Form::findOrFail($id);

        $form->update(['is_active' => !$form->is_active]);

        $status = $form->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "Form {$status} successfully",
            'form' => $form
        ]);
    }

    // =========================================================
    // SUBMIT FORM (Employee)
    // =========================================================
    public function submit(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->employee_id) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile'
            ], 422);
        }

        $form = Form::with('fields')->findOrFail($id);

        if (!$form->is_active) {
            return response()->json([
                'message' => 'This form is not accepting submissions'
            ], 422);
        }

        // Check for existing submission
        $existingSubmission = FormSubmission::where('form_id', $id)
            ->where('employee_id', $user->employee_id)
            ->first();

        if ($existingSubmission) {
            return response()->json([
                'message' => 'You have already submitted this form',
                'submission' => $existingSubmission
            ], 422);
        }

        // Validate required fields
        $rules = [];
        foreach ($form->fields as $field) {
            $key = 'data.' . $field->id;
            if ($field->required) {
                $rules[$key] = 'required';
            }
        }

        $request->validate($rules);

        try {
            // Handle file uploads in data
            $data = $request->input('data', []);
            $files = [];

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $fieldId => $file) {
                    $path = $file->store('form_submissions/' . $id, 'public');
                    $files[$fieldId] = $path;
                }
            }

            // Merge file paths into data
            $data = array_merge($data, $files);

            $submission = FormSubmission::create([
                'form_id' => $id,
                'employee_id' => $user->employee_id,
                'data' => $data,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Form submitted successfully',
                'submission' => $submission
            ], 201);

        } catch (\Exception $e) {
            Log::error('Form Submit Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit form: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // LIST SUBMISSIONS
    // =========================================================
    public function submissions(Request $request, $formId = null)
    {
        $user = $request->user();

        $query = FormSubmission::with([
            'form:id,title',
            'employee:id,first_name,last_name,current_office_id',
            'employee.office:id,name'
        ]);

        if ($formId) {
            $query->where('form_id', $formId);
        }

        // Filter by office for office admins
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    // =========================================================
    // SHOW SINGLE SUBMISSION
    // =========================================================
    public function showSubmission(Request $request, $id)
    {
        $user = $request->user();

        $submission = FormSubmission::with([
            'form.fields',
            'employee.office',
            'employee.designation'
        ])->findOrFail($id);

        // Permission check
        if ($user->isVerifiedUser()) {
            if ($submission->employee_id !== $user->employee_id) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        } elseif ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            if (!in_array($submission->employee->current_office_id, $officeIds)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        }

        // Format data with field labels
        $formattedData = [];
        foreach ($submission->form->fields as $field) {
            $value = $submission->data[$field->id] ?? null;

            // If it's a file, provide URL
            if ($field->type === 'file' && $value) {
                $value = Storage::disk('public')->url($value);
            }

            $formattedData[] = [
                'field_id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'value' => $value,
            ];
        }

        $submission->formatted_data = $formattedData;

        return response()->json($submission);
    }

    // =========================================================
    // UPDATE SUBMISSION STATUS (Admin)
    // =========================================================
    public function updateSubmissionStatus(Request $request, $id)
    {
        $user = $request->user();

        $submission = FormSubmission::with('employee')->findOrFail($id);

        // Permission check
        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            if (!in_array($submission->employee->current_office_id, $officeIds)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $submission->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Submission status updated',
            'submission' => $submission
        ]);
    }

    // =========================================================
    // MY SUBMISSIONS (For employees)
    // =========================================================
    public function mySubmissions(Request $request)
    {
        $user = $request->user();

        if (!$user->employee_id) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile'
            ], 422);
        }

        $submissions = FormSubmission::with('form:id,title,description')
            ->where('employee_id', $user->employee_id)
            ->latest()
            ->get();

        return response()->json($submissions);
    }
}