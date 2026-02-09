<?php

namespace App\Http\Controllers;

use App\Models\TransferHistory;
use App\Models\PromotionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\EmployeeResource;
class HistoryController extends Controller
{
    // =========================================================
    // TRANSFER HISTORY
    // =========================================================

    /**
     * List transfer history for an employee
     */
    public function employeeTransfers(Request $request, $employeeId)
    {
        $user = $request->user();

        $transfers = TransferHistory::with([
            'fromOffice:id,name,code',
            'toOffice:id,name,code',
            'createdBy:id,name'
        ])
            ->where('employee_id', $employeeId)
            ->orderBy('transfer_date', 'desc')
            ->get();

        return response()->json($transfers);
    }

    /**
     * Show single transfer record
     */
    public function showTransfer(Request $request, $id)
    {
        $transfer = TransferHistory::with([
            'employee:id,first_name,last_name,nid_number',
            'fromOffice:id,name,code,location',
            'toOffice:id,name,code,location',
            'createdBy:id,name,email'
        ])->findOrFail($id);

        // Add attachment URL if exists
        if ($transfer->attachment_path) {
            $transfer->attachment_url = Storage::disk('public')->url($transfer->attachment_path);
        }

        return response()->json($transfer);
    }

    /**
     * Update transfer record (Super Admin only - for corrections)
     */
    public function updateTransfer(Request $request, $id)
    {
        $transfer = TransferHistory::findOrFail($id);

        $validated = $request->validate([
            'transfer_date' => 'sometimes|date',
            'order_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Handle new attachment
        if ($request->hasFile('attachment')) {
            // Delete old attachment
            if ($transfer->attachment_path) {
                Storage::disk('public')->delete($transfer->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('transfers', 'public');
        }

        unset($validated['attachment']);
        $transfer->update($validated);

        return response()->json([
            'message' => 'Transfer record updated',
            'transfer' => $transfer->fresh()
        ]);
    }

    /**
     * Delete transfer attachment
     */
    public function deleteTransferAttachment($id)
    {
        $transfer = TransferHistory::findOrFail($id);

        if ($transfer->attachment_path) {
            Storage::disk('public')->delete($transfer->attachment_path);
            $transfer->update(['attachment_path' => null]);
        }

        return response()->json(['message' => 'Attachment deleted']);
    }

    // =========================================================
    // PROMOTION HISTORY
    // =========================================================

    /**
     * List promotion history for an employee
     */
    public function employeePromotions(Request $request, $employeeId)
    {
        $promotions = PromotionHistory::with([
            'newDesignation:id,title,grade,salary_min,salary_max',
            'createdBy:id,name'
        ])
            ->where('employee_id', $employeeId)
            ->orderBy('promotion_date', 'desc')
            ->get();

        // Add previous designation info
        $promotions->transform(function ($promotion, $index) use ($promotions) {
            // Previous designation is the next item's new designation (since sorted desc)
            $nextPromotion = $promotions->get($index + 1);
            $promotion->previous_designation = $nextPromotion
                ? $nextPromotion->newDesignation
                : null;
            return $promotion;
        });

        return response()->json($promotions);
    }

    /**
     * Show single promotion record
     */
    public function showPromotion(Request $request, $id)
    {
        $promotion = PromotionHistory::with([
            'employee:id,first_name,last_name,nid_number,designation_id',
            'employee.designation:id,title,grade',
            'newDesignation:id,title,grade,salary_min,salary_max',
            'createdBy:id,name,email'
        ])->findOrFail($id);

        // Add attachment URL if exists
        if ($promotion->attachment_path) {
            $promotion->attachment_url = Storage::disk('public')->url($promotion->attachment_path);
        }

        return response()->json($promotion);
    }

    /**
     * Update promotion record (Super Admin only - for corrections)
     */
    public function updatePromotion(Request $request, $id)
    {
        $promotion = PromotionHistory::findOrFail($id);

        $validated = $request->validate([
            'promotion_date' => 'sometimes|date',
            'order_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Handle new attachment
        if ($request->hasFile('attachment')) {
            // Delete old attachment
            if ($promotion->attachment_path) {
                Storage::disk('public')->delete($promotion->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('promotions', 'public');
        }

        unset($validated['attachment']);
        $promotion->update($validated);

        return response()->json([
            'message' => 'Promotion record updated',
            'promotion' => $promotion->fresh()
        ]);
    }

    /**
     * Delete promotion attachment
     */
    public function deletePromotionAttachment($id)
    {
        $promotion = PromotionHistory::findOrFail($id);

        if ($promotion->attachment_path) {
            Storage::disk('public')->delete($promotion->attachment_path);
            $promotion->update(['attachment_path' => null]);
        }

        return response()->json(['message' => 'Attachment deleted']);
    }

    // =========================================================
    // COMBINED HISTORY (Timeline View)
    // =========================================================

    /**
     * Get combined timeline of transfers and promotions for an employee
     */
    public function employeeTimeline(Request $request, $employeeId)
    {
        $transfers = TransferHistory::with(['fromOffice:id,name', 'toOffice:id,name'])
            ->where('employee_id', $employeeId)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'transfer',
                    'id' => $item->id,
                    'date' => $item->transfer_date->format('Y-m-d'),
                    'title' => 'Transfer',
                    'description' => $item->from_office_id
                        ? "Transferred from {$item->fromOffice->name} to {$item->toOffice->name}"
                        : "Initial posting at {$item->toOffice->name}",
                    'order_number' => $item->order_number,
                    'has_attachment' => (bool) $item->attachment_path,
                    'created_at' => $item->created_at,
                ];
            });

        $promotions = PromotionHistory::with(['newDesignation:id,title,grade'])
            ->where('employee_id', $employeeId)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'promotion',
                    'id' => $item->id,
                    'date' => $item->promotion_date->format('Y-m-d'),
                    'title' => 'Promotion',
                    'description' => "Promoted to {$item->newDesignation->title} (Grade: {$item->newDesignation->grade})",
                    'order_number' => $item->order_number,
                    'has_attachment' => (bool) $item->attachment_path,
                    'created_at' => $item->created_at,
                ];
            });

        $timeline = $transfers->merge($promotions)
            ->sortByDesc('date')
            ->values();

        return response()->json($timeline);
    }
}