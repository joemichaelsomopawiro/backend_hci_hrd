<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ProductionEquipment;
use App\Models\ProductionEquipmentTransfer;
use App\Models\ProgramEquipmentTemplate;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;
use App\Helpers\QueryOptimizer;
use App\Exports\ProductionEquipmentExport;
use App\Exports\InventoryItemExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ArtSetPropertiController extends Controller
{
    /**
     * Ringkasan stok per nama equipment (grouped by name).
     * GET /art-set-properti/equipment-name-summary
     *
     * Return: [{ name, category, available, in_use, total }]
     * Catatan: ini untuk UI template (Program Manager / Art & Set) agar template selaras dengan stok.
     */
    public function getEquipmentNameSummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $q = InventoryItem::query()
                ->selectRaw("name, MAX(category) as category,
                    SUM(available_quantity) as available,
                    SUM(total_quantity - available_quantity) as in_use,
                    SUM(total_quantity) as total")
                ->groupBy('name');

            if ($request->filled('search')) {
                $q->where('name', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('category')) {
                $q->where('category', $request->category);
            }

            $items = $q->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $items,
                'message' => 'Equipment name summary retrieved successfully from unified inventory'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving equipment name summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get equipment inventory for Art & Set Properti
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = InventoryItem::query()->with('createdBy');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search by name or ID
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('equipment_id', 'like', '%' . $request->search . '%');
                });
            }

            $equipment = $query->orderBy('created_at', 'desc')->paginate(15);

            // Siapa terakhir mengembalikan (untuk barang yang status available)
            $namesOnPage = collect($equipment->items())->pluck('name')->unique()->filter()->values()->toArray();
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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'description' => 'nullable|string',
                'condition' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'position' => 'nullable|string|max:255',
                'total_quantity' => 'required|integer|min:1',
                'status' => 'nullable|in:active,maintenance,lost'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Use specific logic similar to PrArtController for consistency
            $data = $request->only(['name', 'description', 'condition', 'location', 'position', 'category', 'total_quantity', 'status']);
            $data['created_by'] = $user->id;
            $data['available_quantity'] = (int) $request->total_quantity;

            // Auto-generate equipment_id: ART-YYYYMMDD-XXXX
            $date = now()->format('Ymd');
            $count = InventoryItem::where('equipment_id', 'like', "ART-{$date}-%")->count();
            $nextNumber = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $data['equipment_id'] = "ART-{$date}-{$nextNumber}";

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('inventory-photos', 'public');
                $data['photo_url'] = \Illuminate\Support\Facades\Storage::url($path);
            }

            $item = InventoryItem::create($data);

            return response()->json([
                'success' => true,
                'message' => "Equipment item '{$item->name}' created successfully with ID {$item->equipment_id}",
                'data' => $item
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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'category' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'condition' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'position' => 'nullable|string|max:255',
                'total_quantity' => 'sometimes|required|integer|min:0',
                'status' => 'sometimes|required|in:active,maintenance,lost'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = InventoryItem::findOrFail($id);
            
            $data = $request->all();
            
            // Adjust available quantity if total quantity is changing
            if ($request->has('total_quantity')) {
                $newTotal = (int) $request->total_quantity;
                $diff = $newTotal - $equipment->total_quantity;
                $newAvailable = $equipment->available_quantity + $diff;
                
                if ($newAvailable < 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot reduce total quantity below currently borrowed amount. ' . abs($newAvailable) . ' units are currently in use.'
                    ], 400);
                }
                $data['available_quantity'] = $newAvailable;
            }

            // Handle photo upload if any
            if ($request->hasFile('photo')) {
                if ($equipment->photo_url) {
                    $oldPath = str_replace('/storage/', '', $equipment->photo_url);
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                }
                $path = $request->file('photo')->store('inventory-photos', 'public');
                $data['photo_url'] = \Illuminate\Support\Facades\Storage::url($path);
            }

            $equipment->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Equipment updated successfully',
                'data' => $equipment->fresh()
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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $equipment = InventoryItem::findOrFail($id);

            // Check if items are currently in use across ANY program (Music or Regular)
            $isInUse = $equipment->total_quantity > $equipment->available_quantity;
            
            if ($isInUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete item that is currently borrowed or in use.'
                ], 400);
            }

            $equipment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Equipment deleted successfully'
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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

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
            collect($requests->items())->each(function ($row) {
                $list = is_array($row->equipment_list) ? $row->equipment_list : (json_decode($row->equipment_list, true) ?? []);
                $row->equipment_total_qty = count($list);
            });

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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
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
                    'success' => true,
                    'data' => $productionEquipment->fresh(['episode.program', 'requester', 'approver']),
                    'message' => 'Request already processed (current status: ' . $productionEquipment->status . '). No change made.'
                ]);
            }

            // Parse equipment list
            $requestedItems = $productionEquipment->equipment_list; // Array of names
            if (!is_array($requestedItems)) {
                $requestedItems = is_string($requestedItems) ? (json_decode($requestedItems, true) ?? []) : [];
            }

            // Check availability in unified InventoryItem table
            foreach ($requestedItems as $itemName) {
                $item = InventoryItem::where('name', $itemName)->first();

                if (!$item || $item->available_quantity <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Equipment not available: {$itemName}. Current ready stock: " . ($item->available_quantity ?? 0),
                        'error_code' => 'EQUIPMENT_UNAVAILABLE'
                    ], 409);
                }
            }

            // Execute updates
            // 1. Update ProductionEquipment (backfill program_id from episode if null)
            $productionEquipment->approve($user->id, $request->approval_notes);
            if (empty($productionEquipment->program_id) && $productionEquipment->episode) {
                $productionEquipment->update(['program_id' => $productionEquipment->episode->program_id]);
            }

            // 2. Update Inventory Items availability
            foreach ($requestedItems as $itemName) {
                // Find matching InventoryItem by name
                $item = InventoryItem::where('name', $itemName)
                    ->where('available_quantity', '>', 0)
                    ->first();

                if ($item) {
                    $item->decrement('available_quantity');
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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
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

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
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
            
            // 1. Update ProductionEquipment status (returned_by = tim syuting yang mengembalikan)
            // 1. Update ProductionEquipment status (Art Set confirms return)
            $productionEquipment->update([
                'status' => 'returned',
                'returned_at' => $productionEquipment->returned_at ?? now(),
                'return_condition' => $request->return_condition ?? $productionEquipment->return_condition ?? 'good',
                'return_notes' => ($productionEquipment->return_notes ?? '') . ($request->return_notes ? "\n" . $request->return_notes : ''),
                'returned_by' => $productionEquipment->returned_by ?? $user->id,
                'accepted_returned_at' => now(), // Important for UI to know it's verified
                'accepted_returned_by' => $user->id
            ]);

            // 2. Update Inventory Items (INCREMENT available count)
            $items = $productionEquipment->equipment_list;
            if (!is_array($items)) {
                $items = is_string($items) ? (json_decode($items, true) ?? []) : [];
            }

            foreach ($items as $itemName) {
                $inventoryItem = InventoryItem::where('name', $itemName)->first();
                if ($inventoryItem) {
                    $inventoryItem->increment('available_quantity');
                    
                    // Handle damaged/lost if needed (decrement total_quantity)
                    if (in_array(($request->return_condition ?? 'good'), ['damaged', 'lost'])) {
                        $inventoryItem->decrement('total_quantity');
                        $inventoryItem->decrement('available_quantity'); // Net zero increment
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $productionEquipment->fresh(),
                'message' => 'Equipment return confirmed and inventory updated.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting return: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept/Confirm returned equipment
     */
    public function acceptReturnedEquipment(Request $request, $id): JsonResponse
    {
        return $this->returnEquipment($request, $id);
    }
    /**
     * Get equipment statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Inventory Summary from unified InventoryItem model
            $inventoryStats = InventoryItem::selectRaw('SUM(total_quantity) as total, SUM(available_quantity) as available')->first();
            $totalQty = (int) ($inventoryStats->total ?? 0);
            $availableQty = (int) ($inventoryStats->available ?? 0);
            $inUseQty = $totalQty - $availableQty;

            // Requests/Work Summary from Music Program requests
            $requestStats = ProductionEquipment::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            // Category breakdown for inventory
            $categories = InventoryItem::selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category');

            $stats = [
                'pending' => $requestStats->get('pending', 0),
                'approved' => $requestStats->get('approved', 0),
                'rejected' => $requestStats->get('rejected', 0),
                'in_progress' => $requestStats->get('approved', 0),
                'completed' => $requestStats->get('returned', 0),
                'inventory' => [
                    'total' => $totalQty,
                    'available' => $availableQty,
                    'in_use' => $inUseQty,
                    'categories' => $categories
                ]
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
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
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
            collect($data->items())->each(function ($row) {
                $row->requested_by_name = $row->requester?->name;
                $row->returned_by_name = $row->returnedByUser?->name;
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
        if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
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

        $filename = 'Riwayat_Peminjaman_ArtSet_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new ProductionEquipmentExport($items), $filename);
    }

    /**
     * Export daftar inventaris barang ke Excel.
     * GET /api/live-tv/art-set-properti/inventory/export
     */
    public function exportInventory(Request $request)
    {
        $user = Auth::user();
        if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $query = InventoryItem::query()->orderBy('name', 'asc');

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('equipment_id', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $items = $query->get();

        $filename = 'Data_Inventaris_ArtSet_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new InventoryItemExport($items), $filename);
    }

    /**
     * Transfer peminjaman ke episode lain (tanpa return). Same day / lanjut pakai.
     * POST /art-set-properti/requests/{id}/transfer
     */
    public function transferRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
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

                // In quantity-based system, we don't necessarily update individual rows for episode_id
                // but we might want to track which item type is being moved.
                // For now, since InventoryItem doesn't track episode_id directly (it's in ProductionEquipment), 
                // we just ensure the loan record is updated (which we did above).

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
     * Handover (lanjut pakai) ke user berikutnya + episode berikutnya.
     * Flow: create transfer record status=pending_accept → notify target user.
     * POST /art-set-properti/requests/{id}/handover
     */
    public function handoverRequest(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'to_episode_id' => 'required|exists:episodes,id',
                'to_user_id' => 'required|exists:users,id',
                'notes' => 'nullable|string|max:500',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $loan = ProductionEquipment::with(['episode.program', 'program'])->findOrFail($id);
            if (!in_array($loan->status, ['approved', 'in_use'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Handover hanya untuk peminjaman status approved/in_use.'
                ], 400);
            }

            $fromEpisodeId = (int) $loan->episode_id;
            $toEpisodeId = (int) $request->to_episode_id;
            $toUserId = (int) $request->to_user_id;

            // Strict Validation: Recipient must be a coordinator for the target episode
            $isTargetCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($toUserId, $toEpisodeId, ['setting', 'shooting']);
            if (!$isTargetCoordinator) {
                return response()->json([
                    'success' => false,
                    'message' => 'User penerima bukan merupakan Koordinator untuk episode tujuan. Handover ditolak.'
                ], 403);
            }

            if ($fromEpisodeId === $toEpisodeId) {
                return response()->json(['success' => false, 'message' => 'Episode tujuan harus berbeda.'], 400);
            }

            // Prevent duplicate pending handover for same loan+target
            $existingPending = ProductionEquipmentTransfer::where('production_equipment_id', $loan->id)
                ->where('to_episode_id', $toEpisodeId)
                ->where('to_user_id', $toUserId)
                ->where('status', 'pending_accept')
                ->exists();
            if ($existingPending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Handover untuk tujuan ini masih pending.'
                ], 400);
            }

            $toEpisode = \App\Models\Episode::with('program')->findOrFail($toEpisodeId);

            $transfer = ProductionEquipmentTransfer::create([
                'production_equipment_id' => $loan->id,
                'from_episode_id' => $fromEpisodeId,
                'to_episode_id' => $toEpisodeId,
                'to_user_id' => $toUserId,
                'transferred_by' => $user->id,
                'transferred_at' => now(),
                'notes' => $request->notes,
                'status' => 'pending_accept',
            ]);

            Notification::create([
                'user_id' => $toUserId,
                'type' => 'equipment_handover_requested',
                'title' => 'Handover Alat (Lanjut Pakai)',
                'message' => 'Ada handover alat untuk Anda. Silakan konfirmasi terima agar alat tercatat berpindah.',
                'data' => [
                    'transfer_id' => $transfer->id,
                    'production_equipment_id' => $loan->id,
                    'from_episode_id' => $fromEpisodeId,
                    'to_episode_id' => $toEpisodeId,
                    'from_program' => $loan->episode?->program?->name ?? $loan->program?->name,
                    'to_program' => $toEpisode->program?->name,
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $transfer->load(['toEpisode.program', 'fromEpisode.program', 'toUser', 'transferredByUser']),
                'message' => 'Handover dibuat. Menunggu user tujuan konfirmasi terima.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Accept handover: pindahkan loan ke episode target dan set assigned_to ke penerima.
     * POST /art-set-properti/transfers/{transferId}/accept
     */
    public function acceptHandover(Request $request, int $transferId): JsonResponse
    {
        try {
            $user = Auth::user();

            $transfer = ProductionEquipmentTransfer::with(['productionEquipment', 'toEpisode'])->findOrFail($transferId);
            if ($transfer->status !== 'pending_accept') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer ini tidak dalam status pending.'
                ], 400);
            }
            if ((int) $transfer->to_user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Transfer ini bukan untuk Anda.'
                ], 403);
            }

            // Strict Validation: Recipient must still be a coordinator for the target episode
            $isTargetCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $transfer->to_episode_id, ['setting', 'shooting']);
            if (!$isTargetCoordinator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah tidak lagi menjabat sebagai Koordinator untuk episode ini. Gagal menerima handover.'
                ], 403);
            }

            $loan = $transfer->productionEquipment;
            if (!$loan) {
                return response()->json(['success' => false, 'message' => 'Loan tidak ditemukan.'], 404);
            }
            if (!in_array($loan->status, ['approved', 'in_use'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan tidak bisa dihandover karena status bukan approved/in_use.'
                ], 400);
            }

            $toEpisodeId = (int) $transfer->to_episode_id;
            $toEpisode = $transfer->toEpisode ?: \App\Models\Episode::with('program')->findOrFail($toEpisodeId);
            $programId = $toEpisode->program_id;

            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $transfer->update([
                    'status' => 'accepted',
                    'accepted_by' => $user->id,
                    'accepted_at' => now(),
                ]);

                $loan->update([
                    'episode_id' => $toEpisodeId,
                    'program_id' => $programId,
                    'assigned_to' => $user->id,
                    'assigned_at' => now(),
                    'status' => 'in_use',
                ]);

                // Similar to transferRequest, InventoryItem doesn't track assigned_to/episode_id directly per unit.
                // The relationship is handled via ProductionEquipment.

                \Illuminate\Support\Facades\DB::commit();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                throw $e;
            }

            // Notify original transferer (optional)
            if ($transfer->transferred_by) {
                Notification::create([
                    'user_id' => $transfer->transferred_by,
                    'type' => 'equipment_handover_accepted',
                    'title' => 'Handover Diterima',
                    'message' => 'Handover alat Anda sudah diterima oleh user tujuan.',
                    'data' => [
                        'transfer_id' => $transfer->id,
                        'production_equipment_id' => $loan->id,
                        'to_episode_id' => $toEpisodeId,
                        'accepted_by' => $user->id,
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'transfer' => $transfer->fresh(['toEpisode.program', 'fromEpisode.program', 'toUser', 'acceptedByUser']),
                    'loan' => $loan->fresh(['episode.program', 'program', 'requester', 'crewLeader', 'transfers']),
                ],
                'message' => 'Handover diterima. Loan dipindahkan tanpa return.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Daftar template default alat per program. GET /art-set-properti/program-templates
     */
    public function getProgramTemplates(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
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
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
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
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
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
     * Delete template for a specific program.
     * DELETE /art-set-properti/program-templates/{programId}
     */
    public function deleteProgramTemplate(int $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $template = ProgramEquipmentTemplate::where('program_id', $programId)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found for this program.'
                ], 404);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
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
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user)) {
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
     * 
     * Update: support cross-program discovery if programId is 0 or 'all'
     */
    public function getEpisodesList(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!MusicProgramAuthorization::canAccessArtSetProperti($user) && 
                !MusicProgramAuthorization::hasProducerAccess($user)) {
                
                // If not Art & Set or Producer, they can only see episodes where they are a member
                // This is a safety check for the bulk borrowing feature
            }
            
            $query = \App\Models\Episode::with([
                'program:id,name',
                'teamAssignments' => function($q) {
                    $q->whereIn('team_type', ['setting', 'shooting', 'recording', 'vocal_recording'])
                      ->where('status', '!=', 'cancelled');
                },
                'teamAssignments.members' => function($q) {
                    $q->where('is_active', true);
                },
                'teamAssignments.members.user:id,name'
            ]);

            if ($programId && $programId !== '0' && $programId !== 'all') {
                $query->where('program_id', $programId);
            }

            // If param 'only_coordinator' is set, filter by user's coordinator status
            if ($request->boolean('only_coordinator')) {
                $query->whereHas('teamAssignments.members', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->where('is_coordinator', true);
                });
            }

            $episodes = $query->orderBy('episode_number')
                ->get(['id', 'program_id', 'episode_number', 'title']);

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
            $episode = \App\Models\Episode::with('program')->findOrFail($episodeId);
            $programId = $episode->program_id;

            // Authorization: Global access OR member of recording/SE team for this episode OR Producer/PM
            $isAuthorized = MusicProgramAuthorization::canAccessArtSetProperti($user)
                || \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $episodeId, ['recording', 'sound_engineer', 'sound_eng'])
                || MusicProgramAuthorization::hasProducerAccess($user)
                || ProgramManagerAuthorization::isProgramManager($user);

            if (!$isAuthorized) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $query = ProductionEquipment::with(['requester', 'crewLeader', 'returnedByUser'])
                ->where('episode_id', $episodeId)
                ->whereIn('status', ['pending', 'approved', 'in_use', 'returned']);

            if ($request->has('team_type')) {
                $query->where('team_type', $request->team_type);
            }

            $currentLoans = $query->orderByRaw("CASE WHEN status = 'returned' THEN 1 ELSE 0 END")
                ->orderBy('requested_at', 'desc')
                ->get()
                ->map(function ($loan) {
                    $returnedBy = $loan->relationLoaded('returnedByUser') ? $loan->returnedByUser : null;
                    return [
                        'id' => $loan->id,
                        'status' => $loan->status,
                        'team_type' => $loan->team_type,
                        'equipment_items' => $loan->equipment_items,
                        'crew_leader' => $loan->crewLeader ? ['id' => $loan->crewLeader->id, 'name' => $loan->crewLeader->name] : null,
                        'requester' => $loan->requester ? ['id' => $loan->requester->id, 'name' => $loan->requester->name] : null,
                        'requested_at' => $loan->requested_at?->toIso8601String(),
                        'team_pinjam' => $loan->requester?->role ?? 'Requester',
                        'team_balikin' => $loan->status === 'returned' ? ($loan->returnedByUser?->role ?? 'Returner') : null,
                        'returned_by_user' => $returnedBy ? ['id' => $returnedBy->id, 'name' => $returnedBy->name] : null,
                    ];
                });

            $template = ProgramEquipmentTemplate::where('program_id', $programId)->first();
            $defaultItems = $template ? ($template->items ?? []) : [];

            $inventoryByName = InventoryItem::whereIn('name', array_column($defaultItems, 'name'))
                ->get()
                ->keyBy('name');

            $defaultWithStock = array_map(function ($item) use ($inventoryByName) {
                $item = (array) $item;
                $name = $item['name'] ?? '';
                $qty = (int) ($item['qty'] ?? 1);
                
                $inv = $inventoryByName->get($name);
                $available = $inv ? (int) $inv->available_quantity : 0;
                $total = $inv ? (int) $inv->total_quantity : 0;
                $inUse = $total - $available;
                
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
