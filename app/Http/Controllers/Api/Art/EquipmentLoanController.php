<?php

namespace App\Http\Controllers\Api\Art;

use App\Http\Controllers\Controller;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryItem;
use App\Models\PrProduksiWork;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EquipmentLoanController extends Controller
{
    // List loans (for Art Prop dashboard)
    public function index(Request $request): JsonResponse
    {
        try {
            $query = EquipmentLoan::with(['loanItems.inventoryItem', 'borrower', 'produksiWork.episode', 'produksiWork.program']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $loans = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['success' => true, 'data' => $loans, 'message' => 'Loans retrieved successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Create a loan request (Called by Production)
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'pr_produksi_work_id' => 'required|exists:pr_produksi_works,id',
                'items' => 'required|array|min:1',
                'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'loan_date' => 'required|date',
                'return_date' => 'required|date|after_or_equal:loan_date',
                'request_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();

            $loan = EquipmentLoan::create([
                'pr_produksi_work_id' => $request->pr_produksi_work_id,
                'borrower_id' => $user->id,
                'status' => 'pending',
                'loan_date' => $request->loan_date,
                'return_date' => $request->return_date,
                'request_notes' => $request->request_notes
            ]);

            foreach ($request->items as $itemData) {
                // Check availability
                $inventoryItem = InventoryItem::find($itemData['inventory_item_id']);
                if ($inventoryItem->available_quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient quantity for item: " . $inventoryItem->name);
                }

                EquipmentLoanItem::create([
                    'equipment_loan_id' => $loan->id,
                    'inventory_item_id' => $itemData['inventory_item_id'],
                    'quantity' => $itemData['quantity']
                ]);
            }

            // Also update the Production Work status
            PrProduksiWork::where('id', $request->pr_produksi_work_id)->update(['status' => 'equipment_requested']);

            DB::commit();

            return response()->json(['success' => true, 'data' => $loan->load('loanItems.inventoryItem'), 'message' => 'Equipment request submitted successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Approve a loan (Called by Art Prop)
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $loan = EquipmentLoan::with('loanItems')->findOrFail($id);

            if ($loan->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Loan is not pending approval'], 400);
            }

            DB::beginTransaction();

            // Deduct inventory
            // Deduct inventory
            foreach ($loan->loanItems as $loanItem) {
                $inventoryItem = InventoryItem::lockForUpdate()->find($loanItem->inventory_item_id);

                if ($inventoryItem->available_quantity < $loanItem->quantity) {
                    // Auto-reject (Delete request) if insufficient stock
                    $prodWorkId = $loan->pr_produksi_work_id;
                    $borrowerId = $loan->borrower_id;
                    $programName = $loan->produksiWork->program->name;
                    $episodeTitle = $loan->produksiWork->episode->title;

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
                        'message' => "Your request for {$programName} - {$episodeTitle} was returned due to insufficient stock for '{$inventoryItem->name}'. Please adjust your request.",
                        'related_type' => PrProduksiWork::class,
                        'related_id' => $prodWorkId,
                        'priority' => 'high',
                        'status' => 'unread'
                    ]);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'data' => $loan->fresh(),
                        'message' => "Loan auto-rejected due to insufficient stock for {$inventoryItem->name}"
                    ]);
                }

                $inventoryItem->decrement('available_quantity', $loanItem->quantity);
            }

            $loan->update([
                'status' => 'approved',
                'approver_id' => $user->id,
                'approval_notes' => $request->approval_notes
            ]);

            // Update associated Production Work status to in_progress (Sedang Syuting)
            if ($loan->pr_produksi_work_id) {
                PrProduksiWork::where('id', $loan->pr_produksi_work_id)->update([
                    'status' => 'in_progress'
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $loan->fresh(), 'message' => 'Loan approved and inventory deducted']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Reject a loan (Called by Art Prop)
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $loan = EquipmentLoan::findOrFail($id);

            if ($loan->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Loan is not pending approval'], 400);
            }

            // No inventory deduction needed for rejection

            $loan->update([
                'status' => 'rejected',
                'approver_id' => $user->id,
                'approval_notes' => $request->approval_notes
            ]);

            return response()->json(['success' => true, 'data' => $loan->fresh(), 'message' => 'Loan rejected']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Mark as Active (Picked Up)
    public function pickup(int $id): JsonResponse
    {
        try {
            $loan = EquipmentLoan::findOrFail($id);
            if ($loan->status !== 'approved') {
                return response()->json(['success' => false, 'message' => 'Loan must be approved before pickup'], 400);
            }

            $loan->update(['status' => 'active']);
            return response()->json(['success' => true, 'message' => 'Loan marked as active/picked up']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Return items (Called by Art Prop)
    public function returnLoan(Request $request, int $id): JsonResponse
    {
        try {
            $loan = EquipmentLoan::with('loanItems')->findOrFail($id);

            \Illuminate\Support\Facades\Log::info("Trying to return loan ID: {$id}. Status: {$loan->status}");

            if (!in_array($loan->status, ['active', 'approved'])) {
                \Illuminate\Support\Facades\Log::warning("Return failed: Loan status is {$loan->status}");
                return response()->json(['success' => false, 'message' => 'Loan is not active'], 400);
            }

            DB::beginTransaction();

            // Restore inventory
            foreach ($loan->loanItems as $loanItem) {
                $inventoryItem = InventoryItem::find($loanItem->inventory_item_id);
                $inventoryItem->increment('available_quantity', $loanItem->quantity);
            }

            $loan->update([
                'status' => 'returned',
                'return_notes' => $request->return_notes,
                'return_date' => now()
            ]);

            // Update associated Production Work status to completed
            if ($loan->pr_produksi_work_id) {
                PrProduksiWork::where('id', $loan->pr_produksi_work_id)->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completion_notes' => 'Completed automatically upon Art & Set Property equipment return.'
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Loan returned and inventory restored']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
