<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionEquipment;
use App\Models\EquipmentInventory;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductionEquipmentController extends Controller
{
    /**
     * Get equipment list for Production role
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $query = ProductionEquipment::with(['episode', 'requester', 'approver', 'assignedUser']);

        // Filter: Production role sees all, team members see requests for their episodes or their own requests
        if ($user->role !== 'Production') {
            $query->where(function($q) use ($user) {
                $q->where('requested_by', $user->id)
                  ->orWhereHas('episode.teamAssignments.members', function($mq) use ($user) {
                      $mq->where('user_id', $user->id)
                         ->where('is_active', true);
                  });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }

        $equipment = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $equipment
        ]);
    }

    /**
     * Get available equipment for Production role to view before requesting
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        // Group by name to show total available quantity for each type/model
        // We assume 'name' is the identifier for the type of equipment users request
        $availableEquipment = EquipmentInventory::where('status', 'available')
            ->select('name', 'category', \DB::raw('count(*) as available_quantity'))
            ->groupBy('name', 'category')
            ->orderBy('name')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $availableEquipment,
            'message' => 'Available equipment retrieved successfully'
        ]);
    }

    /**
     * Request equipment from Art & Set Properti
     */
    public function requestEquipment(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'equipment_type' => 'required|string|max:255',
            'equipment_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'return_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // CRITICAL VALIDATION: Prevent double-booking
        // Check if equipment is currently in_use
        $inUseCount = ProductionEquipment::where('equipment_name', $request->equipment_name)
            ->where('status', 'in_use')
            ->count();

        if ($inUseCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Equipment "' . $request->equipment_name . '" is currently in use and unavailable. Please wait until it is returned or choose different equipment.',
                'details' => [
                    'equipment_name' => $request->equipment_name,
                    'status' => 'in_use',
                    'in_use_count' => $inUseCount
                ]
            ], 400);
        }

        // Optional: Check against inventory quantity if inventory system exists
        $inventoryItem = EquipmentInventory::where('name', $request->equipment_name)->first();
        if ($inventoryItem) {
            // Count all approved and in_use equipment
            $totalInUse = ProductionEquipment::where('equipment_name', $request->equipment_name)
                ->whereIn('status', ['approved', 'in_use'])
                ->sum('quantity');
            
            $requestedQuantity = $request->quantity;
            $availableQuantity = $inventoryItem->quantity - $totalInUse;

            if ($requestedQuantity > $availableQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient equipment quantity. Requested: ' . $requestedQuantity . ', Available: ' . $availableQuantity,
                    'details' => [
                        'equipment_name' => $request->equipment_name,
                        'total_inventory' => $inventoryItem->quantity,
                        'currently_in_use' => $totalInUse,
                        'available' => $availableQuantity,
                        'requested' => $requestedQuantity
                    ]
                ], 400);
            }
        }

        // If validation passes, create the request
        $equipment = ProductionEquipment::create([
            'episode_id' => $request->episode_id,
            'equipment_type' => $request->equipment_type,
            'equipment_name' => $request->equipment_name,
            'quantity' => $request->quantity,
            'return_date' => $request->return_date,
            'notes' => $request->notes,
            'status' => 'pending_approval',
            'requested_by' => $user->id
        ]);

        // Notifikasi ke Art & Set Properti
        $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
        foreach ($artSetUsers as $artSetUser) {
            Notification::create([
                'user_id' => $artSetUser->id,
                'type' => 'equipment_request',
                'title' => 'Permintaan Alat Baru',
                'message' => "Production meminta alat: {$request->equipment_name} x{$request->quantity}",
                'data' => [
                    'equipment_id' => $equipment->id,
                    'equipment_name' => $request->equipment_name,
                    'quantity' => $request->quantity,
                    'requested_by' => $user->name
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan alat berhasil diajukan',
            'data' => $equipment
        ]);
    }

    /**
     * Notify Art & Set Properti that equipment has been returned physically
     * POST /production/equipment/{id}/notify-return
     */
    public function notifyReturn(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Allow Production & Sound Engineer roles
            // Allow Production & Sound Engineer roles OR users with music assignment
            if (!in_array($user->role, ['Production', 'Sound Engineer']) && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $equipment = ProductionEquipment::find($id);
            
            if (!$equipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request not found.'
                ], 404);
            }

            // Verify ownership or assignment OR shooting team membership for episode
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $equipment->episode_id, 'shooting');
            if ($equipment->requested_by !== $user->id && $equipment->assigned_to !== $user->id && !$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This equipment request is not associated with you and you are not in the shooting team for this episode.'
                ], 403);
            }

            // Verify status
            if ($equipment->status !== 'in_use' && $equipment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment must be in "approved" or "in_use" status to notify return.'
                ], 400);
            }

            // Update return notes to indicate user return
            $currentNotes = $equipment->return_notes ?? '';
            $timestamp = now()->format('Y-m-d H:i');
            $newNote = "[User Return Notification] User {$user->name} reported equipment returned at {$timestamp}.";
            
            $equipment->update([
                'return_notes' => $currentNotes ? $currentNotes . "\n" . $newNote : $newNote
            ]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_return_notification',
                    'title' => 'Pengembalian Alat (User Reported)',
                    'message' => "User {$user->name} melaporkan telah mengembalikan alat: {$equipment->equipment_name} (ID: {$equipment->id}). Harap cek fisik & konfirmasi return.",
                    'data' => [
                        'equipment_id' => $equipment->id,
                        'equipment_name' => $equipment->equipment_name,
                        'reported_by' => $user->name,
                        'role' => $user->role
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi pengembalian berhasil dikirim ke Art & Set Properti. Harap tunggu konfirmasi final mereka.',
                'data' => $equipment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending return notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload files after shooting
     */
    public function uploadFiles(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $equipment = ProductionEquipment::findOrFail($id);

        $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $equipment->episode_id, 'shooting');
        if ($equipment->requested_by !== $user->id && !$isShootingMember) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this equipment.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:mp4,avi,mov,jpg,jpeg,png|max:102400', // 100MB max
            'shooting_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedFiles = [];
        foreach ($request->file('files') as $file) {
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('production_files', $filename, 'public');
            
            $uploadedFiles[] = [
                'filename' => $filename,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ];
        }

        $equipment->update([
            'status' => 'completed',
            'file_paths' => $uploadedFiles,
            'shooting_notes' => $request->shooting_notes,
            'completed_at' => now()
        ]);

        // Notifikasi ke Editor
        $editors = \App\Models\User::where('role', 'Editor')->get();
        foreach ($editors as $editor) {
            Notification::create([
                'user_id' => $editor->id,
                'type' => 'shooting_completed',
                'title' => 'Hasil Shooting Selesai',
                'message' => "Production telah menyelesaikan shooting untuk episode {$equipment->episode->title}",
                'data' => [
                    'equipment_id' => $equipment->id,
                    'episode_id' => $equipment->episode_id,
                    'file_count' => count($uploadedFiles)
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diupload',
            'data' => $equipment
        ]);
    }

    /**
     * Return equipment to Art & Set Properti
     */
    public function returnEquipment(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Production', 'Sound Engineer']) && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $equipment = ProductionEquipment::findOrFail($id);

        $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $equipment->episode_id, 'shooting');
        if ($equipment->requested_by !== $user->id && !$isShootingMember) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this equipment.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'return_condition' => 'required|in:good,damaged,lost',
            'return_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $equipment->update([
            'status' => 'returned',
            'return_condition' => $request->return_condition,
            'return_notes' => $request->return_notes,
            'returned_at' => now()
        ]);

        // Update EquipmentInventory if exists
        $equipmentInventory = \App\Models\EquipmentInventory::where('episode_id', $equipment->episode_id)
            ->where('equipment_name', is_array($equipment->equipment_list) ? $equipment->equipment_list[0] : ($equipment->equipment_list ?? $equipment->equipment_name))
            ->where('status', 'assigned')
            ->first();

        if ($equipmentInventory) {
            $equipmentInventory->update([
                'status' => 'returned',
                'return_condition' => $request->return_condition,
                'return_notes' => $request->return_notes ?? null,
                'returned_at' => now()
            ]);
        }
        // Notifikasi ke Art & Set Properti
        $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
        $equipmentListStr = is_array($equipment->equipment_list) 
            ? implode(', ', $equipment->equipment_list) 
            : ($equipment->equipment_list ?? 'N/A');
        
        foreach ($artSetUsers as $artSetUser) {
            Notification::create([
                'user_id' => $artSetUser->id,
                'type' => 'equipment_returned',
                'title' => 'Alat Dikembalikan',
                'message' => "Production telah mengembalikan alat: {$equipmentListStr}",
                'data' => [
                    'equipment_id' => $equipment->id,
                    'equipment_list' => $equipment->equipment_list,
                    'return_condition' => $request->return_condition
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Alat berhasil dikembalikan',
            'data' => $equipment
        ]);
    }

    /**
     * Get statistics for Production
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $stats = [
            'total_requests' => ProductionEquipment::where('requested_by', $user->id)->count(),
            'pending_approval' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'pending_approval')->count(),
            'approved' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'approved')->count(),
            'completed' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'completed')->count(),
            'returned' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'returned')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}













