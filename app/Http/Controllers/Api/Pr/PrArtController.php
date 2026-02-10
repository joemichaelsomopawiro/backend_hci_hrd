<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrArtController extends Controller
{
    /**
     * GET /api/pr/art/inventory
     * List all inventory items with availability status
     */
    public function getInventory(Request $request)
    {
        $query = InventoryItem::query()->with('createdBy');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $inventory = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $inventory
        ]);
    }

    /**
     * POST /api/pr/art/inventory
     * Create new inventory item
     */
    public function createInventoryItem(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_quantity' => 'required|integer|min:0',
            'status' => 'nullable|in:active,maintenance,damaged,lost',
            'photo' => 'nullable|image|max:5120', // 5MB max
        ]);

        $data = $request->only(['name', 'description', 'total_quantity', 'status']);
        $data['created_by'] = auth()->id();
        $data['available_quantity'] = $request->total_quantity; // Initially all available

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('inventory-photos', 'public');
            $data['photo_url'] = Storage::url($path);
        }

        $item = InventoryItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Inventory item created successfully',
            'data' => $item
        ], 201);
    }

    /**
     * PUT /api/pr/art/inventory/{id}
     * Update inventory item
     */
    public function updateInventoryItem(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'total_quantity' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,maintenance,damaged,lost',
            'photo' => 'nullable|image|max:5120',
        ]);

        $data = $request->only(['name', 'description', 'total_quantity', 'status']);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($item->photo_url) {
                $oldPath = str_replace('/storage/', '', $item->photo_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('photo')->store('inventory-photos', 'public');
            $data['photo_url'] = Storage::url($path);
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Inventory item updated successfully',
            'data' => $item->fresh()
        ]);
    }

    /**
     * DELETE /api/pr/art/inventory/{id}
     * Delete inventory item
     */
    public function deleteInventoryItem($id)
    {
        $item = InventoryItem::findOrFail($id);

        // Check if item is currently borrowed
        $activeLoan = $item->loanItems()->whereHas('loan', function ($q) {
            $q->where('status', 'active');
        })->exists();

        if ($activeLoan) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete item that is currently borrowed'
            ], 400);
        }

        // Delete photo if exists
        if ($item->photo_url) {
            $oldPath = str_replace('/storage/', '', $item->photo_url);
            Storage::disk('public')->delete($oldPath);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory item deleted successfully'
        ]);
    }

    /**
     * GET /api/pr/art/loans
     * List all equipment loan requests
     */
    public function getLoans(Request $request)
    {
        $query = EquipmentLoan::with([
            'loanItems.inventoryItem',
            'borrower',
            'approver',
            'produksiWork.episode.program',
            'produksiWork.episode'
        ]);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $loans = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $loans->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'produksi_work' => [
                        'id' => $loan->produksiWork->id ?? null,
                        'program' => $loan->produksiWork->episode->program ?? null,
                        'episode' => $loan->produksiWork->episode ?? null,
                    ],
                    'borrower' => $loan->borrower,
                    'approver' => $loan->approver,
                    'status' => $loan->status,
                    'loan_date' => $loan->loan_date,
                    'return_date' => $loan->return_date,
                    'request_notes' => $loan->request_notes,
                    'approval_notes' => $loan->approval_notes,
                    'return_notes' => $loan->return_notes,
                    'loan_items' => $loan->loanItems,
                    'created_at' => $loan->created_at,
                ];
            })
        ]);
    }

    /**
     * POST /api/pr/art/loans/{id}/approve
     * Approve a loan request
     */
    public function approveLoan(Request $request, $id)
    {
        $loan = EquipmentLoan::findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending loans can be approved'
            ], 400);
        }

        $loan->update([
            'status' => 'approved',
            'approver_id' => auth()->id(),
            'approval_notes' => $request->input('approval_notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan approved successfully',
            'data' => $loan->fresh()
        ]);
    }

    /**
     * POST /api/pr/art/loans/{id}/reject
     * Reject a loan request
     */
    public function rejectLoan(Request $request, $id)
    {
        $loan = EquipmentLoan::findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending loans can be rejected'
            ], 400);
        }

        $loan->update([
            'status' => 'rejected',
            'approver_id' => auth()->id(),
            'approval_notes' => $request->input('approval_notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan rejected',
            'data' => $loan->fresh()
        ]);
    }

    /**
     * POST /api/pr/art/loans/{id}/borrow
     * Mark equipment as borrowed (tick action)
     */
    public function markAsBorrowed(Request $request, $id)
    {
        $loan = EquipmentLoan::with('loanItems.inventoryItem')->findOrFail($id);

        if ($loan->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved loans can be marked as borrowed'
            ], 400);
        }

        try {
            $loan->markAsBorrowed(
                auth()->id(),
                $request->input('description')
            );

            return response()->json([
                'success' => true,
                'message' => 'Equipment marked as borrowed successfully',
                'data' => $loan->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as borrowed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/pr/art/loans/{id}/return
     * Mark equipment as returned (untick action)
     */
    public function markAsReturned(Request $request, $id)
    {
        $loan = EquipmentLoan::with('loanItems.inventoryItem')->findOrFail($id);

        if ($loan->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Only active loans can be marked as returned'
            ], 400);
        }

        try {
            $loan->markAsReturned(
                auth()->id(),
                $request->input('return_notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Equipment marked as returned successfully',
                'data' => $loan->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as returned: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/pr/art/loan-history
     * Get loan history with descriptions
     */
    public function getLoanHistory(Request $request)
    {
        $query = EquipmentLoanHistory::with([
            'loan.loanItems.inventoryItem',
            'loan.produksiWork.episode.program',
            'performedBy'
        ]);

        // Filter by loan_id if provided
        if ($request->has('loan_id')) {
            $query->where('equipment_loan_id', $request->loan_id);
        }

        $history = $query->orderBy('action_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * POST /api/pr/art/loan-history/{id}/description
     * Update description of a history entry
     */
    public function updateHistoryDescription(Request $request, $id)
    {
        $history = EquipmentLoanHistory::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:1000'
        ]);

        $history->update([
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'History description updated',
            'data' => $history->fresh()
        ]);
    }
}
