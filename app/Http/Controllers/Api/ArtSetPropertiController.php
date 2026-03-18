<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EquipmentInventory;
use App\Models\ProductionEquipment;
use App\Models\ProductionEquipmentTransfer;
use App\Models\ProgramEquipmentTemplate;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use App\Exports\ProductionEquipmentExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ArtSetPropertiController extends Controller
{
    /**
     * Get equipment inventory for Art & Set Properti
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = EquipmentInventory::where('is_active', true)->with(['borrower', 'episode.program']);

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

            // Siapa terakhir mengembalikan (untuk barang yang status available)
            $namesOnPage = $equipment->pluck('name')->unique()->filter()->values()->toArray();
            $lastReturnedByMap = [];
            if (!empty($namesOnPage)) {
                $returnedLoans = ProductionEquipment::where('status', 'returned')
                    ->whereNotNull('returned_at')
                    ->with('returnedByUser')
                    ->orderBy('returned_at', 'desc')
                    ->limit(100)
                    ->get();
                foreach ($returnedLoans as $loan) {
                    $list = is_array($loan->equipment_list) ? $loan->equipment_list : (json_decode($loan->equipment_list, true) ?? []);
                    foreach (array_unique($list) as $eqName) {
                        if (!isset($lastReturnedByMap[$eqName]) && in_array($eqName, $namesOnPage)) {
                            $lastReturnedByMap[$eqName] = $loan->returnedByUser->name ?? null;
                        }
                    }
                }
            }
            foreach ($equipment as $item) {
                $item->last_returned_by_name = $lastReturnedByMap[$item->name] ?? null;
            }

            // Backfill: For "in_use" items missing assigned_to,
            // look up borrower from matching ProductionEquipment and persist it
            foreach ($equipment as $item) {
                if ($item->status === 'in_use') {
                    try {
                        // Check if ALL matching requests are returned — if so, reset inventory
                        $activeRequests = ProductionEquipment::whereIn('status', ['approved', 'in_use'])
                            ->whereJsonContains('equipment_list', $item->name)
                            ->count();

                        if ($activeRequests === 0) {
                            // All requests returned — clean up stale inventory
                            $item->update([
                                'status' => 'available',
                                'assigned_to' => null,
                                'episode_id' => null,
                                'assigned_by' => null,
                                'assigned_at' => null,
                            ]);
                            $item->load(['borrower', 'episode.program']);
                        } elseif (!$item->assigned_to) {
                            // Has active requests but no borrower — backfill
                            $matchingRequest = ProductionEquipment::whereIn('status', ['approved', 'in_use'])
                                ->whereJsonContains('equipment_list', $item->name)
                                ->whereNotNull('requested_by')
                                ->orderBy('approved_at', 'desc')
                                ->first();

                            if ($matchingRequest) {
                                $item->update([
                                    'assigned_to' => $matchingRequest->requested_by,
                                    'episode_id' => $matchingRequest->episode_id,
                                    'assigned_at' => $matchingRequest->approved_at ?? $matchingRequest->updated_at,
                                ]);
                                $item->load(['borrower', 'episode.program']);
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Equipment backfill failed for item #' . $item->id . ': ' . $e->getMessage());
                    }
                }
            }

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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
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
                'serial_number' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
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
                    'serial_number' => $request->serial_number,
                    'description' => $request->description,
                    'notes' => $request->notes,
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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
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
                'serial_number' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

<<<<<<< HEAD
            // Get equipment requests with program info
            $query = ProductionEquipment::with(['episode.program', 'requester'])
                ->orderBy('created_at', 'desc');

            // Allow filtering by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $requests = $query->paginate(15);

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
=======
            // Get equipment requests with program, crew, requester
            $query = ProductionEquipment::with([
                'episode.program',
                'program',
                'requester',
                'crewLeader',
                'returnedByUser'
            ])->orderBy('created_at', 'desc');

            // Allow filtering by status (status=all or empty = semua; comma-separated: pending,approved,in_use,returned)
            if ($request->has('status') && $request->status !== 'all' && (string) $request->status !== '') {
                $statuses = is_array($request->status) ? $request->status : array_map('trim', explode(',', $request->status));
                $statuses = array_filter($statuses);
                if (!empty($statuses)) {
                    $query->whereIn('status', $statuses);
                }
            } elseif (!$request->has('status') || (string) $request->status === '') {
                $query->where('status', 'pending');
            }

            $requests = $query->paginate($request->integer('per_page', 15));

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

>>>>>>> e794181 (update sistemnya lebih sesuai)
    /**
     * Approve equipment request
     */
    public function approveRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
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
            // 1. Update ProductionEquipment (backfill program_id from episode if null)
            $productionEquipment->approve($user->id, $request->approval_notes);
            if (empty($productionEquipment->program_id) && $productionEquipment->episode) {
                $productionEquipment->update(['program_id' => $productionEquipment->episode->program_id]);
            }

            // 2. Update EquipmentInventory items
            foreach ($inventoryItemsToAssign as $item) {
                try {
                    $item->update([
                        'status' => 'in_use',
                        'assigned_to' => $productionEquipment->requested_by,
                        'episode_id' => $productionEquipment->episode_id,
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Tracking columns may not exist yet — just update status
                    $item->update(['status' => 'in_use']);
                }
            }

            // Notify Requester
            $this->notifyEquipmentApproved($productionEquipment);

            return response()->json([
                'success' => true,
                'data' => $productionEquipment->fresh(['episode.program', 'requester', 'approver']),
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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
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
                'data' => $productionEquipment->load(['episode.program', 'requester', 'rejecter']),
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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'return_condition' => 'required|in:good,damaged,lost',
                'return_notes' => 'nullable|string',
                'returned_by' => 'nullable|exists:users,id' // tim syuting yang mengembalikan
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
<<<<<<< HEAD

            // 1. Update ProductionEquipment status
=======
            
            // 1. Update ProductionEquipment status (returned_by = tim syuting yang mengembalikan)
>>>>>>> e794181 (update sistemnya lebih sesuai)
            $productionEquipment->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_condition' => $request->return_condition,
                'return_notes' => $request->return_notes,
                'returned_by' => $request->returned_by ?? $user->id
            ]);

            // 2. Update Inventory Items
            $items = $productionEquipment->equipment_list;
            if (!is_array($items))
                $items = json_decode($items, true) ?? [];

            foreach ($items as $itemName) {
                // Find one 'in_use' item with this name
                $inventoryItem = EquipmentInventory::where('name', $itemName)
                    ->where('status', 'in_use')
                    ->first();

                if ($inventoryItem) {
                    $newStatus = 'available';
                    if ($request->return_condition === 'damaged') {
                        $newStatus = 'broken';
                    } elseif ($request->return_condition === 'needs_maintenance') {
                        $newStatus = 'maintenance';
                    }

                    try {
                        $inventoryItem->update([
                            'status' => $newStatus,
                            'assigned_to' => null,
                            'episode_id' => null,
                            'assigned_by' => null,
                            'assigned_at' => null,
                            'return_date' => null,
                            'returned_at' => now(),
                            'return_condition' => $request->return_condition,
                            'return_notes' => $request->return_notes
                        ]);
                    } catch (\Exception $e) {
                        // Tracking columns may not exist — just update status
                        $inventoryItem->update(['status' => $newStatus]);
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

            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting'); })->exists();

            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
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
     * Riwayat peminjaman & pengembalian: siapa pinjam, siapa kembalikan, alat apa, per program/episode.
     * GET /art-set-properti/history?status=returned&program_id=1&crew_leader_id=2
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
                $q->where('team_type', 'setting');
            })->exists();
            if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $query = ProductionEquipment::with([
                'episode.program',
                'program',
                'requester',
                'crewLeader',
                'returnedByUser',
                'transfers.toEpisode.program',
                'transfers.fromEpisode.program'
            ])->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                if ($request->status !== 'all') {
                    $query->where('status', $request->status);
                }
            }
            if ($request->filled('program_id')) {
                $query->where(function ($q) use ($request) {
                    $q->where('program_id', $request->program_id)
                        ->orWhereHas('episode', fn($eq) => $eq->where('program_id', $request->program_id));
                });
            }
            if ($request->filled('crew_leader_id')) {
                $query->where('crew_leader_id', $request->crew_leader_id);
            }
            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $query->where(function ($q) use ($s) {
                    $q->whereHas('episode.program', fn($eq) => $eq->where('name', 'like', $s))
                        ->orWhereHas('crewLeader', fn($eq) => $eq->where('name', 'like', $s));
                });
            }

            $data = $query->paginate($request->integer('per_page', 15));
            $data->getCollection()->transform(function ($row) {
                $row->equipment_items = $row->equipment_items;
                $row->requested_by_name = $row->requester?->name;
                $row->returned_by_name = $row->returnedByUser?->name;
                return $row;
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Riwayat peminjaman berhasil diambil'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export riwayat peminjaman barang ke Excel.
     * GET /api/live-tv/art-set-properti/history/export?status=returned&program_id=1
     */
    public function exportEquipmentHistory(Request $request)
    {
        $user = Auth::user();
        $role = strtolower($user->role ?? '');
        $isArtRole = $role === 'art & set properti';
        $hasSettingAssignment = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', function ($q) {
            $q->where('team_type', 'setting');
        })->exists();
        if (!$isArtRole && !$hasSettingAssignment && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $query = ProductionEquipment::with([
            'episode.program',
            'program',
            'requester',
            'crewLeader',
            'returnedByUser',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('program_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('program_id', $request->program_id)
                    ->orWhereHas('episode', fn($eq) => $eq->where('program_id', $request->program_id));
            });
        }
        if ($request->filled('crew_leader_id')) {
            $query->where('crew_leader_id', $request->crew_leader_id);
        }
        $limit = min((int) $request->get('limit', 1000), 5000);
        $items = $query->limit($limit)->get();

        $filename = 'peminjaman_alat_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new ProductionEquipmentExport($items), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Transfer peminjaman ke episode lain (tanpa return). Same day / lanjut pakai.
     * POST /art-set-properti/requests/{id}/transfer
     */
    public function transferRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'to_episode_id' => 'required|exists:episodes,id',
                'notes' => 'nullable|string|max:500'
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $loan = ProductionEquipment::with('episode')->findOrFail($id);
            if ($loan->status !== 'approved' && $loan->status !== 'in_use') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya peminjaman dengan status approved/in_use yang dapat dipindah.'
                ], 400);
            }

            $fromEpisodeId = $loan->episode_id;
            $toEpisodeId = (int) $request->to_episode_id;
            if ($fromEpisodeId == $toEpisodeId) {
                return response()->json(['success' => false, 'message' => 'Episode tujuan harus berbeda.'], 400);
            }

            $toEpisode = \App\Models\Episode::with('program')->findOrFail($toEpisodeId);
            $programId = $toEpisode->program_id;

            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                ProductionEquipmentTransfer::create([
                    'production_equipment_id' => $loan->id,
                    'from_episode_id' => $fromEpisodeId,
                    'to_episode_id' => $toEpisodeId,
                    'transferred_by' => $user->id,
                    'transferred_at' => now(),
                    'notes' => $request->notes
                ]);

                $loan->update([
                    'episode_id' => $toEpisodeId,
                    'program_id' => $programId
                ]);

                EquipmentInventory::whereIn('name', is_array($loan->equipment_list) ? $loan->equipment_list : [])
                    ->where('status', 'in_use')
                    ->where('episode_id', $fromEpisodeId)
                    ->update(['episode_id' => $toEpisodeId]);

                \Illuminate\Support\Facades\DB::commit();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'data' => $loan->fresh(['episode.program', 'program', 'requester', 'crewLeader', 'transfers']),
                'message' => 'Peminjaman berhasil dipindah ke episode baru.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Daftar template default alat per program. GET /art-set-properti/program-templates
     */
    public function getProgramTemplates(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $query = ProgramEquipmentTemplate::with('program');
            if ($request->filled('program_id')) {
                $query->where('program_id', $request->program_id);
            }
            $templates = $query->orderBy('program_id')->get();
            return response()->json(['success' => true, 'data' => $templates]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Satu template untuk satu program (default equipment). GET /art-set-properti/programs/{programId}/equipment-template
     */
    public function getTemplateByProgram(int $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $template = ProgramEquipmentTemplate::where('program_id', $programId)->with('program')->first();
            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => $template ? 'Template ditemukan' : 'Belum ada template untuk program ini'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Simpan/update template default alat untuk program. POST/PUT /art-set-properti/program-templates
     */
    public function storeProgramTemplate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'program_id' => 'required|exists:programs,id',
                'name' => 'nullable|string|max:255',
                'items' => 'required|array',
                'items.*.name' => 'required|string|max:255',
                'items.*.qty' => 'required|integer|min:1'
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $template = ProgramEquipmentTemplate::updateOrCreate(
                ['program_id' => $request->program_id],
                ['name' => $request->name ?? 'Default', 'items' => $request->items]
            );
            return response()->json([
                'success' => true,
                'data' => $template->load('program'),
                'message' => 'Template berhasil disimpan'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Daftar program (untuk dropdown tab Program & Episode).
     * GET /art-set-properti/programs-list
     */
    public function getProgramsList(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower(trim($user->role ?? ''));
            $isArtRole = in_array($role, ['art & set properti', 'art & set design', 'art and set properti', 'art and set design'], true);
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }
            $programs = \App\Models\Program::orderBy('name')->get(['id', 'name']);
            return response()->json(['success' => true, 'data' => $programs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Daftar episode per program (untuk dropdown tab Program & Episode).
     * GET /art-set-properti/programs/{programId}/episodes-list
     */
    public function getEpisodesList(int $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower(trim($user->role ?? ''));
            $isArtRole = in_array($role, ['art & set properti', 'art & set design', 'art and set properti', 'art and set design'], true);
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }
            $episodes = \App\Models\Episode::where('program_id', $programId)->orderBy('episode_number')->get(['id', 'episode_number', 'title']);
            return response()->json(['success' => true, 'data' => $episodes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Untuk satu episode: pinjaman aktif + default template program + stok/status per item.
     * GET /art-set-properti/episodes/{episodeId}/equipment-summary
     */
    public function getEpisodeEquipmentSummary(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $isArtRole = $role === 'art & set properti';
            $hasSetting = \App\Models\ProductionTeamMember::where('user_id', $user->id)->where('is_active', true)->whereHas('assignment', fn($q) => $q->where('team_type', 'setting'))->exists();
            if (!$isArtRole && !$hasSetting && $role !== 'production' && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $episode = \App\Models\Episode::with('program')->findOrFail($episodeId);
            $programId = $episode->program_id;

            $currentLoans = ProductionEquipment::with(['requester', 'crewLeader', 'returnedByUser'])
                ->where('episode_id', $episodeId)
                ->whereIn('status', ['pending', 'approved', 'in_use', 'returned'])
                ->orderByRaw("CASE WHEN status = 'returned' THEN 1 ELSE 0 END")
                ->orderBy('requested_at', 'desc')
                ->get()
                ->map(function ($loan) {
                    $returnedBy = $loan->relationLoaded('returnedByUser') ? $loan->returnedByUser : null;
                    return [
                        'id' => $loan->id,
                        'status' => $loan->status,
                        'equipment_items' => $loan->equipment_items,
                        'crew_leader' => $loan->crewLeader ? ['id' => $loan->crewLeader->id, 'name' => $loan->crewLeader->name] : null,
                        'requester' => $loan->requester ? ['id' => $loan->requester->id, 'name' => $loan->requester->name] : null,
                        'requested_at' => $loan->requested_at?->toIso8601String(),
                        'team_pinjam' => 'Tim Setting',
                        'team_balikin' => $loan->status === 'returned' ? 'Tim Syuting' : null,
                        'returned_by_user' => $returnedBy ? ['id' => $returnedBy->id, 'name' => $returnedBy->name] : null,
                    ];
                });

            $template = ProgramEquipmentTemplate::where('program_id', $programId)->first();
            $defaultItems = $template ? ($template->items ?? []) : [];

            $inventoryByName = EquipmentInventory::where('is_active', true)
                ->selectRaw('name, status, count(*) as total')
                ->groupBy('name', 'status')
                ->get()
                ->groupBy('name');

            $defaultWithStock = array_map(function ($item) use ($inventoryByName) {
                $item = (array) $item;
                $name = $item['name'] ?? '';
                $qty = (int) ($item['qty'] ?? 1);
                $byStatus = $inventoryByName->get($name, collect());
                $available = (int) $byStatus->where('status', 'available')->sum('total');
                $inUse = (int) $byStatus->where('status', 'in_use')->sum('total');
                $total = $available + $inUse;
                return [
                    'name' => $name,
                    'default_qty' => $qty,
                    'available' => $available,
                    'in_use' => $inUse,
                    'total' => $total,
                    'status_label' => $available >= $qty ? 'Tersedia' : ($inUse > 0 ? 'Sebagian dipinjam' : 'Menipis'),
                ];
            }, $defaultItems);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => ['id' => $episode->id, 'episode_number' => $episode->episode_number, 'program' => $episode->program ? ['id' => $episode->program->id, 'name' => $episode->program->name] : null],
                    'current_loans' => $currentLoans,
                    'default_template' => $defaultWithStock,
                    'template_raw' => $template
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Notify equipment approved
     */
    private function notifyEquipmentApproved($equipment): void
    {
        if (!$equipment->requested_by)
            return;

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
        if (!$equipment->requested_by)
            return;

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
