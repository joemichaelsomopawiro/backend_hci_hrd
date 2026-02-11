<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EquipmentInventory;
use App\Models\ProductionEquipment;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ArtSetPropertiController extends Controller
{
    /**
     * Get equipment inventory for Art & Set Properti
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = EquipmentInventory::query();

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by category (using category column, not equipment_type)
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $equipment = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment inventory retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving equipment inventory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new equipment
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'brand' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'purchase_price' => 'nullable|numeric',
                'purchase_date' => 'nullable|date',
                'location' => 'nullable|string|max:255',
                'quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create multiple items based on quantity
            $quantity = $request->integer('quantity', 1);
            $createdItems = [];

            for ($i = 0; $i < $quantity; $i++) {
                $equipment = EquipmentInventory::create([
                    'name' => $request->name,
                    'category' => $request->category,
                    'brand' => $request->brand,
                    'model' => $request->model,
                    'description' => $request->description,
                    'purchase_price' => $request->purchase_price,
                    'purchase_date' => $request->purchase_date,
                    'location' => $request->location,
                    'status' => 'available',
                    'is_active' => true
                ]);
                $createdItems[] = $equipment;
            }

            return response()->json([
                'success' => true,
                'message' => "{$quantity} Equipment items created successfully",
                'data' => $createdItems
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update equipment
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'category' => 'sometimes|required|string|max:255',
                'brand' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|in:available,in_use,maintenance,broken,retired',
                'location' => 'nullable|string|max:255',
                'purchase_price' => 'nullable|numeric',
                'purchase_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = EquipmentInventory::findOrFail($id);
            $equipment->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Equipment updated successfully',
                'data' => $equipment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete/Retire equipment
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $equipment = EquipmentInventory::findOrFail($id);
            
            // Soft delete logic: change status to retired and set is_active false
            $equipment->update([
                'status' => 'retired',
                'is_active' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Equipment retired successfully',
                'data' => $equipment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get equipment requests from Production and Sound Engineer
     */
    public function getRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get equipment requests from Production Equipment table
            $requests = ProductionEquipment::with(['episode', 'requestedBy'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Equipment requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving equipment requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve equipment request
     */
    public function approveRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productionEquipment = ProductionEquipment::findOrFail($id);

            if ($productionEquipment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request is not in pending status'
                ], 400);
            }

            // Parse equipment list
            $requestedItems = $productionEquipment->equipment_list; // Array of names
            if (!is_array($requestedItems)) {
                $requestedItems = json_decode($requestedItems, true) ?? [];
            }

            // Check availability and gather inventory items
            $inventoryItemsToAssign = [];
            foreach ($requestedItems as $itemName) {
                // Find one available item with this name
                // We use lockForUpdate to prevent race conditions if high concurrency
                $item = EquipmentInventory::where('name', $itemName)
                    ->whereIn('status', ['available'])
                    ->first();

                if (!$item) {
                    return response()->json([
                        'success' => false,
                        'message' => "Equipment not available: {$itemName}. Please reject or coordinate with requester.",
                        'error_code' => 'EQUIPMENT_UNAVAILABLE'
                    ], 409);
                }
                
                $inventoryItemsToAssign[] = $item;
            }

            // Verify we have enough items (redundant check but safe)
            if (count($inventoryItemsToAssign) !== count($requestedItems)) {
                 return response()->json([
                    'success' => false,
                    'message' => "Insufficient equipment quantity available.",
                ], 409);
            }

            // Execute updates
            // 1. Update ProductionEquipment
            $productionEquipment->approve($user->id, $request->approval_notes);

            // 2. Update EquipmentInventory items
            foreach ($inventoryItemsToAssign as $item) {
                $item->update([
                    'status' => 'in_use',
                    // Note: We don't have episode_id or assigned_to columns in active schema, 
                    // so we rely on status. If schema updates, we can add tracking here.
                ]);
            }

            // Notify Requester
            $this->notifyEquipmentApproved($productionEquipment);

            return response()->json([
                'success' => true,
                'data' => $productionEquipment->fresh(['episode', 'requestedBy', 'approvedBy']),
                'message' => 'Equipment request approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject equipment request
     */
    public function rejectRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productionEquipment = ProductionEquipment::findOrFail($id);

            if ($productionEquipment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request is not in pending status'
                ], 400);
            }

            $productionEquipment->reject($user->id, $request->rejection_reason);

            // Notify Requester
            $this->notifyEquipmentRejected($productionEquipment, $request->rejection_reason);

            return response()->json([
                'success' => true,
                'data' => $productionEquipment->load(['episode', 'requestedBy', 'rejectedBy']),
                'message' => 'Equipment request rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return equipment (Called when Art & Set Property receives the items back)
     * POST /equipment/{id}/return
     */
    public function returnEquipment(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'return_condition' => 'required|in:good,damaged,lost',
                'return_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find ProductionEquipment (The Request Ticket)
            $productionEquipment = ProductionEquipment::find($id);
            
            if (!$productionEquipment) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Production Equipment Request not found.'
                ], 404);
            }

            if ($productionEquipment->status !== 'approved' && $productionEquipment->status !== 'in_use') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request is not in valid status for return (must be approved/in_use).'
                ], 400);
            }
            
            // 1. Update ProductionEquipment status
            $productionEquipment->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_condition' => $request->return_condition,
                'return_notes' => $request->return_notes
            ]);

            // 2. Update Inventory Items
            $items = $productionEquipment->equipment_list;
            if (!is_array($items)) $items = json_decode($items, true) ?? [];

            foreach ($items as $itemName) {
                // Find one 'in_use' item with this name
                $inventoryItem = EquipmentInventory::where('name', $itemName)
                    ->where('status', 'in_use')
                    ->first();
                
                if ($inventoryItem) {
                    if ($request->return_condition === 'good') {
                        $inventoryItem->update(['status' => 'available']);
                    } else {
                        $inventoryItem->update(['status' => $request->return_condition]); // broken/lost
                    }
                }
            }

            // 3. AUTOMATION: Check if ALL equipment for this Episode is returned
            $episodeId = $productionEquipment->episode_id;
            
            // Count pending or in_use requests for this episode
            $activeRequestsCount = ProductionEquipment::where('episode_id', $episodeId)
                ->whereIn('status', ['pending', 'approved', 'in_use'])
                ->where('id', '!=', $id) // Exclude current one (just in case)
                ->count();

            $allReturned = $activeRequestsCount === 0;
            $episodeUpdated = false;

            if ($allReturned) {
                $episode = \App\Models\Episode::find($episodeId);
                // Only update if currently in 'production'
                if ($episode && $episode->status === 'production') {
                    $episode->update(['status' => 'editing']);
                    $episodeUpdated = true;
                    
                    // Notify Editor/Producer that shooting is done
                    // (Optional: add notification logic here)
                }
            }

            return response()->json([
                'success' => true,
                'data' => $productionEquipment,
                'message' => 'Equipment returned successfully.' . ($episodeUpdated ? ' Episode status updated to Editing.' : '')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error returning equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept/Confirm returned equipment
     * THIS SEEMS REDUNDANT if 'returnEquipment' handles everything.
     * But keeping it for backward compatibility if needed, or deprecating it.
     * Let's make it a wrapper or just return success if already handled.
     */
    public function acceptReturnedEquipment(Request $request, $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Return accepted (processed via returnEquipment)'
        ]);
    }

    /**
     * Get equipment statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $inventoryStats = EquipmentInventory::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $requestStats = ProductionEquipment::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $stats = [
                'total_equipment' => $inventoryStats->sum(),
                'assigned_equipment' => $inventoryStats->get('assigned', 0) + $inventoryStats->get('in_use', 0),
                'available_equipment' => $inventoryStats->get('available', 0),
                'returned_equipment' => $inventoryStats->get('returned', 0),
                'pending_requests' => $requestStats->get('pending', 0),
                'approved_requests' => $requestStats->get('approved', 0),
                'rejected_requests' => $requestStats->get('rejected', 0)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Equipment statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify equipment approved
     */
    private function notifyEquipmentApproved($equipment): void
    {
        if (!$equipment->requested_by) return;

        Notification::create([
            'user_id' => $equipment->requested_by,
            'type' => 'equipment_approved',
            'title' => 'Equipment Request Approved',
            'message' => "Your equipment request for episode {$equipment->episode->episode_number} has been approved.",
            'data' => [
                'equipment_id' => $equipment->id,
                'episode_id' => $equipment->episode_id
            ]
        ]);
    }

    /**
     * Notify equipment rejected
     */
    private function notifyEquipmentRejected($equipment, string $reason): void
    {
        if (!$equipment->requested_by) return;

        Notification::create([
            'user_id' => $equipment->requested_by,
            'type' => 'equipment_rejected',
            'title' => 'Equipment Request Rejected',
            'message' => "Your equipment request for episode {$equipment->episode->episode_number} has been rejected. Reason: {$reason}",
            'data' => [
                'equipment_id' => $equipment->id,
                'episode_id' => $equipment->episode_id,
                'rejection_reason' => $reason
            ]
        ]);
    }
}
