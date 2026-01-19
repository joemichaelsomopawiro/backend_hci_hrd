<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EquipmentInventory;
use App\Models\ProductionEquipment;
use App\Models\Episode;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ArtSetPropertiController extends Controller
{
    /**
     * Get equipment inventory for Art & Set Properti
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = EquipmentInventory::with(['episode', 'createdBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by equipment type
            if ($request->has('equipment_type')) {
                $query->where('equipment_type', $request->equipment_type);
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
     * Get equipment requests from Production and Sound Engineer
     */
    public function getRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get equipment requests from Production Equipment table
            $requests = ProductionEquipment::with(['episode', 'createdBy'])
                ->where('status', 'pending_approval')
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_notes' => 'nullable|string',
                'assigned_equipment' => 'nullable|array',
                'return_date' => 'nullable|date|after:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = ProductionEquipment::findOrFail($id);

            if ($equipment->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request is not pending approval'
                ], 400);
            }

            $equipment->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'equipment_notes' => $request->equipment_notes,
                'assigned_equipment' => $request->assigned_equipment,
                'return_date' => $request->return_date
            ]);

            // Create inventory record
            EquipmentInventory::create([
                'episode_id' => $equipment->episode_id,
                'equipment_type' => $equipment->equipment_type,
                'equipment_name' => $equipment->equipment_name,
                'quantity' => $equipment->quantity,
                'status' => 'assigned',
                'assigned_to' => $equipment->created_by,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'return_date' => $request->return_date,
                'notes' => $request->equipment_notes,
                'created_by' => $user->id
            ]);

            // Notify Production/Sound Engineer
            $this->notifyEquipmentApproved($equipment);

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['episode', 'createdBy', 'approvedBy']),
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
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

            $equipment = ProductionEquipment::findOrFail($id);

            if ($equipment->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request is not pending approval'
                ], 400);
            }

            $equipment->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);

            // Notify Production/Sound Engineer
            $this->notifyEquipmentRejected($equipment, $request->rejection_reason);

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['episode', 'createdBy', 'rejectedBy']),
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
     * Return equipment
     */
    public function returnEquipment(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
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

            $equipment = EquipmentInventory::findOrFail($id);

            if ($equipment->status !== 'assigned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment is not currently assigned'
                ], 400);
            }

            $equipment->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_condition' => $request->return_condition,
                'return_notes' => $request->return_notes
            ]);

            // Update Production Equipment status
            $productionEquipment = ProductionEquipment::where('episode_id', $equipment->episode_id)
                ->where('equipment_type', $equipment->equipment_type)
                ->first();

            if ($productionEquipment) {
                $productionEquipment->update([
                    'status' => 'returned',
                    'returned_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment returned successfully'
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
     * POST /api/live-tv/art-set-properti/equipment/{id}/accept-returned
     */
    public function acceptReturnedEquipment(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'verification_notes' => 'nullable|string|max:1000',
                'set_available' => 'nullable|boolean' // Jika true, set equipment jadi available lagi
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = ProductionEquipment::with(['episode', 'requestedBy'])->findOrFail($id);

            // Check if equipment is in returned status
            if ($equipment->status !== 'returned') {
                return response()->json([
                    'success' => false,
                    'message' => "Equipment is not in returned status. Current status: {$equipment->status}"
                ], 400);
            }

            // Update ProductionEquipment - add confirmation note to return_notes
            $existingNotes = $equipment->return_notes ?? '';
            $confirmationNote = "\n\n[Confirmed by Art & Set Properti - " . now()->format('Y-m-d H:i:s') . "]\n" .
                "Confirmed by: {$user->name}\n" .
                ($request->verification_notes ? "Verification notes: {$request->verification_notes}" : 'Confirmed');
            
            $equipment->update([
                'return_notes' => $existingNotes . $confirmationNote
            ]);

            // Update EquipmentInventory if exists and set to available if condition is good
            $equipmentInventory = EquipmentInventory::where('episode_id', $equipment->episode_id)
                ->where('equipment_name', is_array($equipment->equipment_list) ? $equipment->equipment_list[0] : $equipment->equipment_list)
                ->where('status', 'returned')
                ->where('assigned_to', $equipment->requested_by)
                ->first();

            if ($equipmentInventory) {
                // If equipment condition is good and set_available is true, set to available
                if ($request->boolean('set_available', false) && $equipment->return_condition === 'good') {
                    $equipmentInventory->update([
                        'status' => 'available',
                        'return_notes' => ($equipmentInventory->return_notes ?? '') . $confirmationNote
                    ]);
                } else {
                    // Just confirm the return (update notes)
                    $equipmentInventory->update([
                        'return_notes' => ($equipmentInventory->return_notes ?? '') . $confirmationNote
                    ]);
                }
            }

            // Notify Production/Sound Engineer that return has been confirmed
            if ($equipment->requestedBy) {
                Notification::create([
                    'user_id' => $equipment->requested_by,
                    'type' => 'equipment_return_confirmed',
                    'title' => 'Pengembalian Alat Dikonfirmasi',
                    'message' => "Art & Set Properti telah mengkonfirmasi pengembalian alat untuk Episode {$equipment->episode->episode_number}.",
                    'data' => [
                        'equipment_id' => $equipment->id,
                        'episode_id' => $equipment->episode_id,
                        'verification_notes' => $request->verification_notes,
                        'confirmed_by' => $user->id,
                        'confirmed_by_name' => $user->name
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logUpdate($equipment, [], [
                'return_notes' => $equipment->return_notes,
                'verified_by' => $user->id,
                'verification_notes' => $request->verification_notes
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => [
                    'equipment' => $equipment->fresh(['episode', 'requestedBy']),
                    'equipment_inventory' => $equipmentInventory ? $equipmentInventory->fresh() : null,
                    'set_to_available' => $request->boolean('set_available', false) && $equipment->return_condition === 'good'
                ],
                'message' => 'Returned equipment accepted and confirmed successfully. Equipment is now available for use again.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting returned equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get equipment statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Art & Set Properti') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_equipment' => EquipmentInventory::count(),
                'assigned_equipment' => EquipmentInventory::where('status', 'assigned')->count(),
                'available_equipment' => EquipmentInventory::where('status', 'available')->count(),
                'returned_equipment' => EquipmentInventory::where('status', 'returned')->count(),
                'pending_requests' => ProductionEquipment::where('status', 'pending_approval')->count(),
                'approved_requests' => ProductionEquipment::where('status', 'approved')->count(),
                'rejected_requests' => ProductionEquipment::where('status', 'rejected')->count()
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
        Notification::create([
            'user_id' => $equipment->created_by,
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
        Notification::create([
            'user_id' => $equipment->created_by,
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
