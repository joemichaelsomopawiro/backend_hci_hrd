<?php

namespace App\Http\Controllers;

use App\Models\ProductionEquipment;
use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\ProgramNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ArtSetPropertiController extends Controller
{
    /**
     * Display a listing of equipment requests
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductionEquipment::with(['assignedUser', 'program', 'episode']);
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }
            
            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }
            
            $equipment = $query->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $equipment,
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
     * Store a newly created equipment request
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'required|in:camera,lighting,audio,props,set_design,other',
                'brand' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'serial_number' => 'nullable|string|max:255',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'requested_by' => 'required|exists:users,id',
                'requested_for_date' => 'required|date|after:today',
                'return_date' => 'required|date|after:requested_for_date',
                'notes' => 'nullable|string',
                'specifications' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = ProductionEquipment::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'brand' => $request->brand,
                'model' => $request->model,
                'serial_number' => $request->serial_number,
                'status' => 'requested',
                'assigned_to' => $request->requested_by,
                'program_id' => $request->program_id,
                'episode_id' => $request->episode_id,
                'notes' => $request->notes,
                'specifications' => $request->specifications,
                'requested_for_date' => $request->requested_for_date,
                'return_date' => $request->return_date
            ]);

            // Notify Art & Set Properti team
            $this->notifyArtSetPropertiTeam($equipment, 'new_request');

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['assignedUser', 'program', 'episode']),
                'message' => 'Equipment request created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified equipment request
     */
    public function show(string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::with(['assignedUser', 'program', 'episode'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment request retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified equipment request
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'sometimes|required|in:camera,lighting,audio,props,set_design,other',
                'brand' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'serial_number' => 'nullable|string|max:255',
                'status' => 'sometimes|in:available,requested,assigned,in_use,maintenance,broken,returned',
                'assigned_to' => 'nullable|exists:users,id',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'notes' => 'nullable|string',
                'specifications' => 'nullable|array',
                'requested_for_date' => 'sometimes|required|date',
                'return_date' => 'sometimes|required|date|after:requested_for_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update($request->all());
            $equipment->load(['assignedUser', 'program', 'episode']);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment request updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified equipment request
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);
            
            if ($equipment->status === 'in_use') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete equipment that is currently in use'
                ], 400);
            }

            $equipment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Equipment request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve equipment request
     */
    public function approveRequest(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);
            
            if ($equipment->status !== 'requested') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only requested equipment can be approved'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string',
                'assigned_equipment_id' => 'nullable|exists:production_equipment,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update([
                'status' => 'approved',
                'approval_notes' => $request->approval_notes,
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            // Notify requester
            $this->notifyRequester($equipment, 'approved');

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['assignedUser', 'program', 'episode']),
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
    public function rejectRequest(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);
            
            if ($equipment->status !== 'requested') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only requested equipment can be rejected'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'rejected_by' => Auth::id(),
                'rejected_at' => now()
            ]);

            // Notify requester
            $this->notifyRequester($equipment, 'rejected');

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['assignedUser', 'program', 'episode']),
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
     * Assign equipment to user
     */
    public function assignEquipment(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);
            
            if ($equipment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved equipment can be assigned'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'assigned_to' => 'required|exists:users,id',
                'assignment_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update([
                'status' => 'assigned',
                'assigned_to' => $request->assigned_to,
                'assignment_notes' => $request->assignment_notes,
                'assigned_at' => now()
            ]);

            // Notify assigned user
            $this->notifyAssignedUser($equipment);

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['assignedUser', 'program', 'episode']),
                'message' => 'Equipment assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return equipment
     */
    public function returnEquipment(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);
            
            if (!in_array($equipment->status, ['assigned', 'in_use'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only assigned or in-use equipment can be returned'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'return_condition' => 'required|in:good,damaged,needs_maintenance',
                'return_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $status = $request->return_condition === 'good' ? 'available' : 'maintenance';
            
            $equipment->update([
                'status' => $status,
                'return_condition' => $request->return_condition,
                'return_notes' => $request->return_notes,
                'returned_at' => now(),
                'returned_by' => Auth::id()
            ]);

            // Notify Art & Set Properti team
            $this->notifyArtSetPropertiTeam($equipment, 'returned');

            return response()->json([
                'success' => true,
                'data' => $equipment->load(['assignedUser', 'program', 'episode']),
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
     * Get equipment inventory
     */
    public function getInventory(Request $request): JsonResponse
    {
        try {
            $query = ProductionEquipment::query();
            
            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            $inventory = $query->selectRaw('
                category,
                status,
                COUNT(*) as count,
                AVG(CASE WHEN last_maintenance IS NOT NULL THEN DATEDIFF(NOW(), last_maintenance) ELSE NULL END) as avg_days_since_maintenance
            ')
            ->groupBy('category', 'status')
            ->get();
            
            $summary = [
                'total_equipment' => ProductionEquipment::count(),
                'available_equipment' => ProductionEquipment::where('status', 'available')->count(),
                'in_use_equipment' => ProductionEquipment::where('status', 'in_use')->count(),
                'maintenance_equipment' => ProductionEquipment::where('status', 'maintenance')->count(),
                'broken_equipment' => ProductionEquipment::where('status', 'broken')->count(),
                'inventory_by_category' => $inventory->groupBy('category'),
                'maintenance_alerts' => $this->getMaintenanceAlerts()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $summary,
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
     * Get maintenance alerts
     */
    private function getMaintenanceAlerts(): array
    {
        $alerts = [];
        
        // Equipment due for maintenance
        $dueForMaintenance = ProductionEquipment::where('next_maintenance', '<=', now()->addDays(7))
            ->where('status', '!=', 'maintenance')
            ->get();
            
        foreach ($dueForMaintenance as $equipment) {
            $alerts[] = [
                'type' => 'maintenance_due',
                'equipment_id' => $equipment->id,
                'equipment_name' => $equipment->name,
                'due_date' => $equipment->next_maintenance,
                'message' => "Equipment '{$equipment->name}' is due for maintenance on {$equipment->next_maintenance}"
            ];
        }
        
        // Overdue equipment
        $overdueEquipment = ProductionEquipment::where('return_date', '<', now())
            ->whereIn('status', ['assigned', 'in_use'])
            ->get();
            
        foreach ($overdueEquipment as $equipment) {
            $alerts[] = [
                'type' => 'overdue_return',
                'equipment_id' => $equipment->id,
                'equipment_name' => $equipment->name,
                'overdue_days' => now()->diffInDays($equipment->return_date),
                'message' => "Equipment '{$equipment->name}' is overdue for return by {$equipment->return_date}"
            ];
        }
        
        return $alerts;
    }

    /**
     * Notify Art & Set Properti team
     */
    private function notifyArtSetPropertiTeam(ProductionEquipment $equipment, string $action): void
    {
        $artSetPropertiUsers = User::where('role', 'Art & Set Properti')->get();
        
        $messages = [
            'new_request' => "New equipment request: '{$equipment->name}' from {$equipment->assignedUser->name}",
            'returned' => "Equipment '{$equipment->name}' has been returned by {$equipment->assignedUser->name}"
        ];
        
        foreach ($artSetPropertiUsers as $user) {
            ProgramNotification::create([
                'title' => 'Equipment ' . ucfirst($action),
                'message' => $messages[$action] ?? "Equipment '{$equipment->name}' {$action}",
                'type' => 'equipment_' . $action,
                'user_id' => $user->id,
                'program_id' => $equipment->program_id,
                'episode_id' => $equipment->episode_id
            ]);
        }
    }

    /**
     * Notify requester
     */
    private function notifyRequester(ProductionEquipment $equipment, string $action): void
    {
        $messages = [
            'approved' => "Your equipment request for '{$equipment->name}' has been approved",
            'rejected' => "Your equipment request for '{$equipment->name}' has been rejected"
        ];
        
        ProgramNotification::create([
            'title' => 'Equipment Request ' . ucfirst($action),
            'message' => $messages[$action] ?? "Equipment request '{$equipment->name}' {$action}",
            'type' => 'equipment_request_' . $action,
            'user_id' => $equipment->assigned_to,
            'program_id' => $equipment->program_id,
            'episode_id' => $equipment->episode_id
        ]);
    }

    /**
     * Notify assigned user
     */
    private function notifyAssignedUser(ProductionEquipment $equipment): void
    {
        ProgramNotification::create([
            'title' => 'Equipment Assigned',
            'message' => "Equipment '{$equipment->name}' has been assigned to you",
            'type' => 'equipment_assigned',
            'user_id' => $equipment->assigned_to,
            'program_id' => $equipment->program_id,
            'episode_id' => $equipment->episode_id
        ]);
    }
}
