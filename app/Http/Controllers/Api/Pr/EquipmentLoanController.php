<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryItem;
use App\Models\PrProduksiWork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EquipmentLoanController extends Controller
{
    // List loans (with filters)
    public function index(Request $request)
    {
        $query = EquipmentLoan::with(['loanItems.inventoryItem', 'produksiWork.episode.program', 'produksiWork.episode', 'borrower']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $loans = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $loans
        ]);
    }

    // Create Loan Request (Production)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pr_produksi_work_id' => 'required|exists:pr_produksi_works,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Check if loan already exists for this work? Maybe allow multiple?
            // For simplicity, one active loan per production work for now, or allow multiple.
            // Requirement says "Create a loan request".

            $loan = EquipmentLoan::create([
                'pr_produksi_work_id' => $request->pr_produksi_work_id,
                'borrower_id' => Auth::id(),
                'status' => 'pending',
                'request_notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($request->items as $item) {
                // Check availability
                $inventory = InventoryItem::lockForUpdate()->find($item['id']);
                // Note: We don't deduct stock on REQUEST, only on APPROVAL or PICKUP?
                // Usually stock is reserved on Approval or Pickup. 
                // Let's deduct available_quantity on APPROVED. 
                // But we should check if we have enough total? 

                EquipmentLoanItem::create([
                    'equipment_loan_id' => $loan->id,
                    'inventory_item_id' => $item['id'],
                    'quantity' => $item['quantity']
                ]);
            }

            // Also update the PrProduksiWork status to 'equipment_requested'
            $work = PrProduksiWork::find($request->pr_produksi_work_id);
            if ($work && $work->status === 'pending') {
                $work->status = 'equipment_requested';
                $work->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Loan request submitted successfully',
                'data' => $loan
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Approve/Reject Loan (Art)
    public function respond(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'approval_notes' => 'nullable|string'
        ]);

        $loan = EquipmentLoan::with(['loanItems', 'produksiWork.episode.program', 'produksiWork.episode'])->find($id);
        if (!$loan)
            return response()->json(['message' => 'Loan not found'], 404);

        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Loan is not pending'], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->action === 'approve') {
                $loan->status = 'approved';
                $loan->approver_id = Auth::id();
                $loan->approval_notes = $request->approval_notes;

                // Deduct available quantity
                foreach ($loan->loanItems as $loanItem) {
                    $inventory = InventoryItem::lockForUpdate()->find($loanItem->inventory_item_id);

                    // Check if inventory is missing OR insufficient stock
                    if (!$inventory || $inventory->available_quantity < $loanItem->quantity) {
                        // Auto-reject (Delete request) if insufficient stock or missing item
                        $prodWorkId = $loan->pr_produksi_work_id;
                        $borrowerId = $loan->borrower_id;
                        $itemName = $inventory ? $inventory->name : 'Unknown Item (Deleted)';

                        // Delete the loan request entirely
                        $loan->delete();

                        // Revert Production Work status to 'pending'
                        if ($prodWorkId) {
                            PrProduksiWork::where('id', $prodWorkId)->update([
                                'status' => 'pending',
                                'equipment_requests' => null
                            ]);
                        }

                        // Notify Borrower
                        \App\Models\Notification::create([
                            'user_id' => $borrowerId,
                            'type' => 'equipment_rejected',
                            'title' => 'Equipment Request Returned',
                            'message' => "Your request was returned because stock for '{$itemName}' is insufficient. Please adjust your request.",
                            'related_type' => PrProduksiWork::class,
                            'related_id' => $prodWorkId,
                            'priority' => 'high',
                            'status' => 'unread'
                        ]);

                        DB::commit();

                        return response()->json([
                            'success' => true,
                            'message' => "maaf, ternyata barang yang ingin dipinjam stok nya sudah habis, Produksi silahkan memilih kembali alat yang dibutuhkan. Terima kasih."
                        ], 200);
                    }

                    $inventory->available_quantity -= $loanItem->quantity;
                    $inventory->save();
                }

                // Update associated Production Work status to in_progress (Sedang Syuting)
                if ($loan->pr_produksi_work_id) {
                    PrProduksiWork::where('id', $loan->pr_produksi_work_id)->update([
                        'status' => 'in_progress'
                    ]);
                }
            } else {
                $loan->status = 'rejected';
                $loan->approver_id = Auth::id();
                $loan->approval_notes = $request->approval_notes;
            }

            $loan->save();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Loan ' . $request->action . 'd']);

        } catch (\Exception $e) {
            DB::rollBack();

            // Fallback for unexpected errors: Reset the state so user can try again
            try {
                // Find loan again to delete it (since rollback might have revived it if we deleted it before error)
                // Actually, rollback undoes changes, so the loan is back to 'pending'.
                // We want to DELETE it and reset the Flow.

                $loanForReset = EquipmentLoan::find($id);
                if ($loanForReset) {
                    $prodWorkId = $loanForReset->pr_produksi_work_id;
                    $borrowerId = $loanForReset->borrower_id;

                    $loanForReset->delete();

                    if ($prodWorkId) {
                        PrProduksiWork::where('id', $prodWorkId)->update([
                            'status' => 'pending',
                            'equipment_requests' => null
                        ]);
                    }

                    \App\Models\Notification::create([
                        'user_id' => $borrowerId,
                        'type' => 'equipment_error',
                        'title' => 'Equipment Request Error',
                        'message' => "An error occurred during approval. Your request has been reset. Please try again.",
                        'related_id' => $prodWorkId,
                        'status' => 'unread'
                    ]);
                }
            } catch (\Exception $ex) {
                // Ignore secondary errors
            }

            return response()->json([
                'success' => false,
                'message' => 'maaf, terjadi kesalahan sistem. Permintaan telah di-reset, silahkan coba kembali.',
                'debug' => $e->getMessage()
            ], 200); // Return 200 so frontend displays message instead of error
        }
    }

    // Mark as Picked Up (Art)
    public function pickup($id)
    {
        $loan = EquipmentLoan::find($id);
        if (!$loan)
            return response()->json(['message' => 'Loan not found'], 404);

        if ($loan->status !== 'approved') {
            return response()->json(['message' => 'Loan must be approved first'], 400);
        }

        $loan->status = 'active'; // In Use
        $loan->loan_date = now();
        $loan->save();

        return response()->json(['success' => true, 'message' => 'Loan marked as active/picked up']);
    }

    // Process Return (Art)
    public function processReturn(Request $request, $id)
    {
        $loan = EquipmentLoan::with('loanItems')->find($id);
        if (!$loan)
            return response()->json(['message' => 'Loan not found'], 404);

        if (!in_array($loan->status, ['active', 'approved'])) {
            return response()->json(['message' => 'Loan is not active'], 400);
        }

        DB::beginTransaction();
        try {
            $loan->status = 'returned';
            $loan->return_date = now();
            $loan->return_notes = $request->return_notes;
            $loan->save();

            // Restore available quantity
            foreach ($loan->loanItems as $loanItem) {
                $inventory = InventoryItem::lockForUpdate()->find($loanItem->inventory_item_id);
                $inventory->available_quantity += $loanItem->quantity;
                $inventory->save();
            }

            // Update associated Production Work status to completed
            if ($loan->pr_produksi_work_id) {
                PrProduksiWork::where('id', $loan->pr_produksi_work_id)->update([
                    'status' => 'finished_shooting',
                    // 'completed_at' => now(), // Removed because it's not fully completed yet
                    'completion_notes' => 'Completed automatically upon Art & Set Property equipment return.'
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Loan returned successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
