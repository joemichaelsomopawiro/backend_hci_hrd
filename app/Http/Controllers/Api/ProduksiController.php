<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProduksiWork;
use App\Models\ProductionEquipment;
use App\Models\InventoryItem;
use App\Models\ShootingRunSheet;
use App\Models\MediaFile;
use App\Models\Notification;
use App\Models\QualityControlWork;
use App\Models\DesignGrafisWork;
use App\Services\WorkAssignmentService;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class ProduksiController extends Controller
{
    /**
     * Get produksi works for current user
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
            
            // Allow if has 'Production' role OR is a member of Tim Syuting for any episode
            $isProductionRole = MusicProgramAuthorization::canUserPerformTask($user, null, 'Production');
            
            // We'll check for shooting team membership in the query itself for index

            // Build cache key based on request parameters
            $cacheKey = 'produksi_index_' . md5(json_encode([
                'user_id' => $user->id,
                'status' => $request->get('status'),
                'page' => $request->get('page', 1)
            ]));

            // Use cache with 5 minutes TTL
            $works = QueryOptimizer::rememberForUser($cacheKey, $user->id, 300, function () use ($request, $isProductionRole, $user) {
                $query = ProduksiWork::with([
                    'episode.program.productionTeam',
                    'episode.teamAssignments' => function ($q) {
                        $q->whereIn('team_type', ['setting', 'shooting'])->where('status', '!=', 'cancelled');
                    },
                    'episode.teamAssignments.members.user:id,name,email',
                    'creativeWork.latestApprovedMusicArrangement',
                    'runSheet',
                    'createdBy',
                    'equipmentRequests' => function ($q) {
                        $q->whereIn('status', ['pending', 'approved', 'in_use', 'returned'])
                            ->with(['assignedUser:id,name', 'crewLeader:id,name'])
                            ->orderBy('requested_at', 'desc');
                    }
                ]);

                // If not global Production role, show works where the user is a member of Tim Syuting OR Tim Setting for that episode
                if (!$isProductionRole) {
                    $query->where(function ($q) use ($user) {
                        $q->whereHas('episode.teamAssignments', function ($aq) use ($user) {
                            $aq->whereIn('team_type', ['shooting', 'setting'])
                                ->where('status', '!=', 'cancelled')
                                ->whereHas('members', function ($mq) use ($user) {
                                    $mq->where('user_id', $user->id)->where('is_active', true);
                                });
                        });
                    });
                }

                // Filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }

                return $query->orderBy('created_at', 'desc')->paginate(15);
            });

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Produksi works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving produksi works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific production work detail
     * GET /api/live-tv/roles/produksi/works/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $work = ProduksiWork::with([
                'episode.program:id,name',
                'episode.teamAssignments' => function ($q) {
                    $q->whereIn('team_type', ['setting', 'shooting'])->where('status', '!=', 'cancelled');
                },
                'episode.teamAssignments.members.user:id,name,email',
                'creativeWork',
                'createdBy',
                'completedBy',
                'runSheet',
                'equipmentRequests' => function ($q) {
                    $q->orderBy('requested_at', 'desc')->with(['returnedByUser:id,name', 'assignedUser:id,name', 'crewLeader:id,name']);
                }
            ])->findOrFail($id);

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            $activity = $this->buildWorkActivityTimeline($work);

            // Pastikan frontend dapat run_sheet (snake_case) dan shooting_file_links
            $data = $work->toArray();
            $data['run_sheet'] = $work->relationLoaded('runSheet') && $work->runSheet
                ? $work->runSheet->toArray()
                : null;
            $data['shooting_file_links'] = $work->shooting_file_links ?? [];
            $data['shooting_files'] = $work->shooting_files ?? [];

            return response()->json([
                'success' => true,
                'data' => $data,
                'activity' => $activity,
                'message' => 'Produksi work detail retrieved successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Produksi work not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving work detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Produksi terima pekerjaan setelah Producer approve Creative Work
     * POST /api/live-tv/roles/produksi/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $work = ProduksiWork::findOrFail($id);

            // Authorization: Production role OR member of Tim Syuting / Tim Setting for this episode





            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            // Idempotency check: If already accepted by this user, return success
            if ($work->status === 'in_progress' && $work->created_by === $user->id) {
                return response()->json([
                    'success' => true,
                    'data' => $work->fresh(['episode', 'creativeWork', 'createdBy']),
                    'message' => 'Work already accepted by you.'
                ]);
            }

            if ($work->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is pending. Current status: ' . $work->status
                ], 400);
            }

            $oldData = $work->toArray();
            $work->acceptWork($user->id);

            // Audit logging
            ControllerSecurityHelper::logCrud('produksi_work_accepted', $work, [
                'old_status' => $oldData['status'],
                'new_status' => 'in_progress',
                'assigned_to' => $user->id
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork', 'createdBy']),
                'message' => 'Work accepted successfully. You can now input equipment list and needs.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input List Alat dan Ajukan ke Art & Set Properti (pinjam barang).
     * Hanya Tim Setting (atau role Production) yang boleh memanggil ini.
     * POST /api/live-tv/roles/produksi/works/{id}/request-equipment
     */
    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $work = ProduksiWork::findOrFail($id);
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya Coordinator Tim Setting yang boleh mengajukan permintaan alat (pinjam barang).'
                ], 403);
            }

            // Attendance Check: Tim Setting must have submitted attendance
            $attendances = $work->crew_attendances ?? [];
            if (!$isProductionRole && (!isset($attendances['setting']) || empty($attendances['setting']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi Tim Setting belum dilakukan. Silakan lakukan absensi terlebih dahulu oleh Coordinator Tim Setting.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.equipment_name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.return_date' => 'nullable|date|after_or_equal:today',
                'equipment_list.*.notes' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000',
                'crew_leader_id' => 'nullable|exists:users,id',
                'crew_member_ids' => 'nullable|array',
                'crew_member_ids.*' => 'exists:users,id',
                'scheduled_date' => 'nullable|date',
                'scheduled_time' => 'nullable|date_format:H:i',
                'update_equipment_request_id' => 'nullable|integer|exists:production_equipment,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);


            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to request equipment'
                ], 400);
            }

            $updateRequestId = $request->input('update_equipment_request_id');
            if ($updateRequestId) {
                $existingRequest = ProductionEquipment::where('id', $updateRequestId)
                    ->where('episode_id', $work->episode_id)
                    ->where('status', 'pending')
                    ->first();
                if ($existingRequest) {
                    $requestedNames = array_unique(array_column($request->equipment_list, 'equipment_name'));
                    $flatList = [];
                    $notesParts = [];
                    foreach ($request->equipment_list as $equipment) {
                        $equipmentName = $equipment['equipment_name'];
                        $quantity = (int) ($equipment['quantity'] ?? 1);
                        for ($i = 0; $i < $quantity; $i++) {
                            $flatList[] = $equipmentName;
                        }
                        if (!empty($equipment['notes'])) {
                            $notesParts[] = "{$equipmentName}: " . $equipment['notes'];
                        }
                    }

                    $inventoryCounts = InventoryItem::whereIn('name', $requestedNames)
                        ->get()
                        ->pluck('available_quantity', 'name');

                    foreach ($request->equipment_list as $equipment) {
                        $equipmentName = $equipment['equipment_name'];
                        $quantity = (int) ($equipment['quantity'] ?? 1);
                        $availableCount = $inventoryCounts->get($equipmentName, 0);

                        if ($availableCount < $quantity) {
                            $unavailableEquipment[] = [
                                'equipment_name' => $equipmentName,
                                'requested_quantity' => $quantity,
                                'available_count' => $availableCount,
                                'reason' => 'Equipment tidak tersedia dalam jumlah yang cukup di stok pusat',
                            ];
                        }
                    }
                    if (!empty($unavailableEquipment)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Some equipment is not available or currently in use',
                            'unavailable_equipment' => $unavailableEquipment,
                        ], 400);
                    }
                    $requestNotes = trim(implode("\n", $notesParts) . ($request->request_notes ? "\n" . $request->request_notes : ''));
                    $existingRequest->update([
                        'equipment_list' => $flatList,
                        'request_notes' => $requestNotes ?: null,
                        'crew_leader_id' => $request->crew_leader_id,
                        'crew_member_ids' => $request->crew_member_ids ?? [],
                        'scheduled_date' => $request->scheduled_date ? \Carbon\Carbon::parse($request->scheduled_date)->format('Y-m-d') : null,
                        'scheduled_time' => $request->scheduled_time,
                        'requested_at' => now(),
                    ]);
                    $work->update(['equipment_list' => $request->equipment_list]);
                    QueryOptimizer::clearAllIndexCaches();
                    $equipmentRequestsList = ProductionEquipment::where('episode_id', $work->episode_id)->orderBy('requested_at', 'desc')->get();
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'work' => $work->fresh(['episode', 'creativeWork', 'equipmentRequests']),
                            'equipment_requests' => $equipmentRequestsList,
                            'is_update' => true,
                            'updated_request_id' => (int) $updateRequestId,
                        ],
                        'message' => 'Permintaan alat diperbarui. Masih Menunggu Art & Set Properti. Tampilan riwayat gunakan data.equipment_requests.',
                    ]);
                }
            }

            $unavailableEquipment = [];
            $useNewSchema = Schema::hasColumn('equipment_inventory', 'equipment_name');
            $nameColumn = $useNewSchema ? 'equipment_name' : 'name';

            // Build flat equipment_list (nama repeated by qty) for one loan
            $flatList = [];
            $requestedNames = [];
            $notesParts = [];
            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];
                $quantity = (int) ($equipment['quantity'] ?? 1);
                for ($i = 0; $i < $quantity; $i++) {
                    $flatList[] = $equipmentName;
                }
                $requestedNames[] = $equipmentName;
                if (!empty($equipment['notes'])) {
                    $notesParts[] = "{$equipmentName}: " . $equipment['notes'];
                }
            }
            $requestedNames = array_unique($requestedNames);

            // Check availability per name
            // Check availability per name in master inventory
            $inventoryCounts = InventoryItem::whereIn('name', $requestedNames)
                ->get()
                ->pluck('available_quantity', 'name');

            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];
                $quantity = (int) ($equipment['quantity'] ?? 1);
                $availableCount = $inventoryCounts->get($equipmentName, 0);

                if ($availableCount < $quantity) {
                    $unavailableEquipment[] = [
                        'equipment_name' => $equipmentName,
                        'requested_quantity' => $quantity,
                        'available_count' => $availableCount,
                        'reason' => 'Equipment tidak tersedia dalam jumlah yang cukup di stok pusat',
                    ];
                }
            }

            if (!empty($unavailableEquipment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some equipment is not available or currently in use',
                    'unavailable_equipment' => $unavailableEquipment,
                ], 400);
            }

            $programId = $work->episode->program_id ?? null;
            $requestNotes = trim(implode("\n", $notesParts) . ($request->request_notes ? "\n" . $request->request_notes : ''));

            // Satu peminjaman per request: program, episode, crew leader, anggota, daftar alat
            $equipmentRequest = ProductionEquipment::create([
                'episode_id' => $work->episode_id,
                'program_id' => $programId,
                'request_group_id' => $request->request_group_id ?? null,
                'equipment_list' => $flatList,
                'request_notes' => $requestNotes ?: null,
                'scheduled_date' => $request->scheduled_date ? \Carbon\Carbon::parse($request->scheduled_date)->format('Y-m-d') : null,
                'scheduled_time' => $request->scheduled_time,
                'status' => 'pending',
                'requested_by' => $user->id,
                'crew_leader_id' => $request->crew_leader_id,
                'crew_member_ids' => $request->crew_member_ids,
                'requested_at' => now(),
            ]);

            $equipmentRequests = [$equipmentRequest->id];

            // Update work
            $work->update([
                'equipment_list' => $request->equipment_list,
                'equipment_requests' => array_merge($work->equipment_requests ?? [], $equipmentRequests),
            ]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            $notificationsToInsert = [];
            $now = now();

            $equipmentCount = count($flatList ?? $request->equipment_list);
            foreach ($artSetUsers as $artSetUser) {
                $notificationsToInsert[] = [
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_request_created',
                    'title' => 'Permintaan Alat Baru',
                    'message' => "Produksi meminta {$equipmentCount} item equipment untuk Episode {$work->episode->episode_number}.",
                    'data' => json_encode([
                        'equipment_request_ids' => $equipmentRequests,
                        'episode_id' => $work->episode_id,
                        'produksi_work_id' => $work->id
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            if (!empty($notificationsToInsert)) {
                Notification::insert($notificationsToInsert);
            }

            // Audit logging
            ControllerSecurityHelper::logCrud('produksi_equipment_requested', $work, [
                'equipment_count' => count($request->equipment_list),
                'equipment_request_ids' => $equipmentRequests
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            // Return full equipment_requests for episode so frontend can refresh riwayat in one place
            $equipmentRequestsList = ProductionEquipment::where('episode_id', $work->episode_id)->orderBy('requested_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'creativeWork']),
                    'equipment_requests' => $equipmentRequestsList,
                    'is_update' => false,
                ],
                'message' => 'Equipment requests created successfully. Art & Set Properti has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Peminjaman alat sekaligus untuk beberapa episode.
     * POST /api/live-tv/roles/produksi/request-equipment-multiple
     * Body: episode_ids[] (array), equipment_list, request_notes, scheduled_date, scheduled_time, crew_leader_id, crew_member_ids
     * Membuat satu ProductionEquipment per episode (work harus in_progress).
     */
    public function requestEquipmentMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_ids' => 'required|array|min:1|max:52',
            'episode_ids.*' => 'required|integer|exists:episodes,id',
            'equipment_list' => 'required|array|min:1',
            'equipment_list.*.equipment_name' => 'required|string|max:255',
            'equipment_list.*.quantity' => 'required|integer|min:1',
            'equipment_list.*.notes' => 'nullable|string|max:1000',
            'request_notes' => 'nullable|string|max:1000',
            'crew_leader_id' => 'nullable|exists:users,id',
            'crew_member_ids' => 'nullable|array',
            'crew_member_ids.*' => 'exists:users,id',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $episodeIds = array_unique($request->episode_ids);
        $created = [];
        $skipped = [];

        $useNewSchema = Schema::hasColumn('equipment_inventory', 'equipment_name');
        $nameColumn = $useNewSchema ? 'equipment_name' : 'name';
        $flatList = [];
        $notesParts = [];
        foreach ($request->equipment_list as $equipment) {
            $equipmentName = $equipment['equipment_name'];
            $quantity = (int) ($equipment['quantity'] ?? 1);
            for ($i = 0; $i < $quantity; $i++) {
                $flatList[] = $equipmentName;
            }
            if (!empty($equipment['notes'])) {
                $notesParts[] = "{$equipmentName}: " . ($equipment['notes'] ?? '');
            }
        }
        $requestedNames = array_unique(array_column($request->equipment_list, 'equipment_name'));
        $inventoryCounts = InventoryItem::whereIn('name', $requestedNames)
            ->get()
            ->pluck('available_quantity', 'name');

        foreach ($request->equipment_list as $equipment) {
            $equipmentName = $equipment['equipment_name'];
            $quantity = (int) ($equipment['quantity'] ?? 1);
            $availableCount = $inventoryCounts->get($equipmentName, 0);
            
            // Note: Multiple episode requests should ideally check if the same stock can be reused, 
            // but for safety we check current absolute availability.
            if ($availableCount < $quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some equipment is not available or currently in use',
                    'unavailable_equipment' => [['equipment_name' => $equipmentName, 'requested_quantity' => $quantity, 'available_count' => $availableCount]],
                ], 400);
            }
        }
        $requestNotes = trim(implode("\n", $notesParts) . ($request->request_notes ? "\n" . $request->request_notes : ''));

        foreach ($episodeIds as $episodeId) {
            $work = ProduksiWork::where('episode_id', $episodeId)->first();
            if (!$work || $work->status !== 'in_progress') {
                $skipped[] = ['episode_id' => $episodeId, 'reason' => $work ? 'Work not in progress' : 'No produksi work'];
                continue;
            }
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'setting');
            if (!$isProductionRole && !$isShootingMember && !$isSettingMember) {
                $skipped[] = ['episode_id' => $episodeId, 'reason' => 'Unauthorized'];
                continue;
            }
            $programId = $work->episode->program_id ?? null;
            $equipmentRequest = ProductionEquipment::create([
                'episode_id' => $work->episode_id,
                'program_id' => $programId,
                'equipment_list' => $flatList,
                'equipment_quantities' => collect($request->equipment_list)->mapWithKeys(function ($it) {
                    $name = $it['equipment_name'] ?? null;
                    $qty = (int) ($it['quantity'] ?? 1);
                    return $name ? [$name => $qty] : [];
                })->toArray(),
                'request_notes' => $requestNotes ?: null,
                'scheduled_date' => $request->scheduled_date ? \Carbon\Carbon::parse($request->scheduled_date)->format('Y-m-d') : null,
                'scheduled_time' => $request->scheduled_time,
                'status' => 'pending',
                'requested_by' => $user->id,
                'crew_leader_id' => $request->crew_leader_id,
                'crew_member_ids' => $request->crew_member_ids ?? [],
                'requested_at' => now(),
            ]);
            $work->update([
                'equipment_list' => $request->equipment_list,
                'equipment_requests' => array_merge($work->equipment_requests ?? [], [$equipmentRequest->id]),
            ]);
            $created[] = ['episode_id' => $episodeId, 'equipment_request_id' => $equipmentRequest->id];
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            $now = now();
            $equipmentCount = count($flatList);
            foreach ($artSetUsers as $artSetUser) {
                Notification::insert([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_request_created',
                    'title' => 'Permintaan Alat Baru',
                    'message' => "Produksi meminta {$equipmentCount} item equipment untuk Episode {$work->episode->episode_number}.",
                    'data' => json_encode([
                        'equipment_request_ids' => [$equipmentRequest->id],
                        'episode_id' => $work->episode_id,
                        'produksi_work_id' => $work->id
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'total_created' => count($created),
            ],
            'message' => count($created) . ' permintaan alat dibuat.' . (count($skipped) ? ' ' . count($skipped) . ' episode dilewati.' : ''),
        ]);
    }

    /**
     * Ajukan Kebutuhan
     * POST /api/live-tv/roles/produksi/works/{id}/request-needs
     */
    public function requestNeeds(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $work = ProduksiWork::findOrFail($id);
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'needs_list' => 'required|array|min:1',
                'needs_list.*.item_name' => 'required|string|max:255',
                'needs_list.*.quantity' => 'required|integer|min:1',
                'needs_list.*.description' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to request needs'
                ], 400);
            }

            // Update work
            $work->update([
                'needs_list' => $request->needs_list,
                'needs_requests' => array_merge($work->needs_requests ?? [], [
                    'requested_at' => now()->toDateTimeString(),
                    'requested_by' => $user->id,
                    'notes' => $request->request_notes
                ])
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'produksi_needs_requested',
                    'title' => 'Permintaan Kebutuhan Produksi',
                    'message' => "Produksi telah mengajukan kebutuhan untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'needs_list' => $request->needs_list
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork']),
                'message' => 'Needs request submitted successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting needs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan — bedakan Tim Setting vs Tim Syuting.
     * POST /api/live-tv/roles/produksi/works/{id}/complete-work
     * Body: { "complete_team": "setting" | "syuting", "notes": "..." }
     * - complete_team=setting: hanya tandai bagian Tim Setting selesai (work tetap in_progress untuk Tim Syuting).
     * - complete_team=syuting atau tidak dikirim: selesaikan work penuh (wajib run sheet + link file syuting).
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = ProduksiWork::findOrFail($id);

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager || MusicProgramAuthorization::hasProducerAccess($user);
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'setting');

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is in_progress'
                ], 400);
            }

            $completeTeam = $request->input('complete_team', null);
            // Jika tidak dikirim, infer: hanya Setting → setting; hanya Syuting → syuting; keduanya → wajib kirim complete_team
            if ($completeTeam === null) {
                if ($isSettingMember && !$isShootingMember) {
                    $completeTeam = 'setting';
                } elseif ($isShootingMember && !$isSettingMember) {
                    $completeTeam = 'syuting';
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda anggota Tim Setting dan Tim Syuting. Kirim complete_team: "setting" atau "syuting" untuk memilih bagian mana yang diselesaikan.'
                    ], 400);
                }
            }
            $completeTeam = strtolower((string) $completeTeam);
            $attendances = $work->crew_attendances ?? [];

            // --- Selesai bagian Tim Setting saja (work tidak jadi completed) ---
            if ($completeTeam === 'setting') {
                if (!$isProductionRole && !$isSettingMember) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya Tim Setting (atau role Production) yang boleh menandai bagian Tim Setting selesai.'
                    ], 403);
                }

                // Attendance Check: Tim Setting must have submitted attendance
                if (!$isProductionRole && (!isset($attendances['setting']) || empty($attendances['setting']))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Absensi Tim Setting belum dilakukan. Silakan lakukan absensi terlebih dahulu oleh Coordinator Tim Setting.'
                    ], 400);
                }

                $work->completeSettingPart($user->id, $request->notes);
                QueryOptimizer::clearAllIndexCaches();
                ControllerSecurityHelper::logCrud('produksi_setting_part_completed', $work, [
                    'setting_completed_by' => $user->id,
                    'notes' => $request->notes
                ], $request);
                return response()->json([
                    'success' => true,
                    'data' => ['work' => $work->fresh(['episode', 'creativeWork'])],
                    'message' => 'Bagian Tim Setting telah ditandai selesai. Pekerjaan tetap berjalan untuk Tim Syuting (run sheet, link file, kembalikan alat, lalu selesai).'
                ]);
            }

            // --- Selesai penuh (Tim Syuting): run sheet + link file syuting wajib ---
            if ($completeTeam !== 'syuting') {
                return response()->json([
                    'success' => false,
                    'message' => 'complete_team harus "setting" atau "syuting".'
                ], 400);
            }
            if (!$isProductionRole && !$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Tim Syuting (atau role Production) yang boleh menyelesaikan pekerjaan (run sheet + link file syuting + kembalikan alat).'
                ], 403);
            }

            // Attendance Check: Tim Syuting must have submitted attendance
            if (!$isProductionRole && (!isset($attendances['shooting']) || empty($attendances['shooting']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi Tim Syuting belum dilakukan. Silakan lakukan absensi terlebih dahulu oleh Coordinator Tim Syuting sebelum menyelesaikan pekerjaan.'
                ], 400);
            }
            if (!$work->run_sheet_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Run sheet (form catatan syuting) belum diisi. Input run sheet terlebih dahulu.'
                ], 400);
            }
            $links = $work->shooting_file_links ?? [];
            if (is_string($links)) {
                $links = $links ? array_filter(explode(',', $links)) : [];
            }
            $files = $work->shooting_files ?? [];
            if (!is_array($files)) {
                $files = [];
            }
            if (empty($links) && empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link file hasil syuting belum diisi. Input link file syuting terlebih dahulu.'
                ], 400);
            }

            $oldData = $work->toArray();
            $work->completeWork($user->id, $request->notes);

            ControllerSecurityHelper::logCrud('produksi_work_completed', $work, [
                'old_status' => $oldData['status'],
                'new_status' => 'completed',
                'completed_by' => $user->id,
                'notes' => $request->notes
            ], $request);

            QueryOptimizer::clearAllIndexCaches();

            $episode = $work->episode;
        $productionTeam = $episode->program->productionTeam;
        
        // 1. Notify Producer
        $producer = $productionTeam ? $productionTeam->producer : null;
        if ($producer) {
            Notification::create([
                'user_id' => $producer->id,
                'type' => 'produksi_work_completed',
                'title' => 'Produksi Work Selesai',
                'message' => "Produksi telah menyelesaikan pekerjaan untuk Episode {$episode->episode_number}.",
                'data' => [
                    'produksi_work_id' => $work->id,
                    'episode_id' => $work->episode_id
                ]
            ]);
        }

        // 2. Notify Editor
        $editors = \App\Models\User::where('role', 'Editor')->get();
        foreach ($editors as $editor) {
            Notification::create([
                'user_id' => $editor->id,
                'type' => 'shooting_finished_editor',
                'title' => 'Selesai Syuting - File Siap Dicek',
                'message' => "Proses syuting Episode {$episode->episode_number} telah selesai. Silakan cek kelengkapan file.",
                'data' => [
                    'produksi_work_id' => $work->id,
                    'episode_id' => $work->episode_id
                ]
            ]);
        }

        // 3. Notify Design Grafis
        $designers = \App\Models\User::where('role', 'Graphic Design')->get();
        foreach ($designers as $designer) {
            Notification::create([
                'user_id' => $designer->id,
                'type' => 'shooting_finished_design',
                'title' => 'Selesai Syuting - Buat Thumbnail',
                'message' => "Proses syuting Episode {$episode->episode_number} telah selesai. Silakan ambil file untuk pembuatan thumbnail.",
                'data' => [
                    'produksi_work_id' => $work->id,
                    'episode_id' => $work->episode_id
                ]
            ]);
        }

        // Auto-create DesignGrafisWork for Thumbnail YouTube
        $existingThumbnailWork = DesignGrafisWork::where('episode_id', $work->episode_id)
            ->where('work_type', 'thumbnail_youtube')
            ->first();

        if (!$existingThumbnailWork) {
            // Determine assignee using WorkAssignmentService
            $assignedDesignerId = WorkAssignmentService::getNextAssignee(
                DesignGrafisWork::class,
                $episode->program_id,
                $episode->episode_number,
                'thumbnail_youtube',
                $user->id // Fallback
            );

            $thumbnailWork = DesignGrafisWork::create([
                'episode_id' => $work->episode_id,
                'work_type' => 'thumbnail_youtube',
                'title' => "Thumbnail YouTube - Episode {$episode->episode_number}",
                'description' => "Buat thumbnail YouTube untuk episode ini. File syuting sudah tersedia dari Produksi.",
                'status' => 'draft', // Draft makes it available for designer to accept
                'created_by' => $assignedDesignerId,
                'originally_assigned_to' => null,
                'was_reassigned' => false
            ]);

            // Notify Assigned Designer specifically
            $assignedDesigner = \App\Models\User::find($assignedDesignerId);
            if ($assignedDesigner) {
                    Notification::create([
                    'user_id' => $assignedDesigner->id,
                    'type' => 'design_work_assigned', // Specific type for assignment
                    'title' => 'Tugas Baru: Thumbnail YouTube',
                    'message' => "Tugas baru 'Thumbnail YouTube' untuk Episode {$episode->episode_number} telah dibuat otomatis setelah Produksi selesai.",
                    'data' => [
                        'design_grafis_work_id' => $thumbnailWork->id,
                        'episode_id' => $work->episode_id,
                        'work_type' => 'thumbnail_youtube'
                    ]
                ]);
            }
        }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork', 'completedBy']),
                'message' => 'Work completed successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buka kembali work yang sudah Selesai agar Tim Syuting bisa input run sheet & link file syuting.
     * POST /api/live-tv/roles/produksi/works/{id}/reopen
     */
    public function reopenWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = ProduksiWork::findOrFail($id);

            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'setting');

            if (!$isProductionRole && !$isShootingMember && !$isSettingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya anggota Tim Syuting, Tim Setting, atau role Production yang boleh membuka kembali.'
                ], 403);
            }

            if ($work->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work yang sudah Selesai yang bisa dibuka kembali.'
                ], 400);
            }

            $work->update([
                'status' => 'in_progress',
                'completed_at' => null,
                'completed_by' => null,
            ]);

            QueryOptimizer::clearAllIndexCaches();
            ControllerSecurityHelper::logCrud('produksi_work_reopened', $work, ['reopened_by' => $user->id], $request);

            return response()->json([
                'success' => true,
                'data' => ['work' => $work->fresh(['episode', 'creativeWork'])],
                'message' => 'Pekerjaan dibuka kembali. Silakan input run sheet dan link file syuting lalu selesaikan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reopening work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit Team Attendance (Absensi Tim)
     * POST /api/live-tv/roles/produksi/works/{id}/submit-attendance
     */
    public function submitAttendance(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = ProduksiWork::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'team_type' => 'required|in:setting,shooting',
                'attendances' => 'required|array|min:1',
                'attendances.*.user_id' => 'required|integer|exists:users,id',
                'attendances.*.status' => 'required|in:present,absent,late',
                'attendances.*.notes' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Authorization: Only Coordinator of the specified team OR Production role
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isCoordinator = \App\Models\ProductionTeamMember::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('is_coordinator', true)
                ->whereHas('assignment', function ($q) use ($work, $request) {
                    $q->where('episode_id', $work->episode_id)
                        ->where('team_type', $request->team_type)
                        ->where('status', '!=', 'cancelled');
                })->exists();

            if (!$isProductionRole && !$isCoordinator) {
                return response()->json([
                    'success' => false,
                    'message' => "Hanya Coordinator Tim " . ($request->team_type === 'setting' ? 'Setting' : 'Syuting') . " yang boleh melakukan absensi."
                ], 403);
            }

            $attendances = $work->crew_attendances ?? [];
            $attendances[$request->team_type] = [
                'submitted_by' => $user->id,
                'submitted_at' => now()->toDateTimeString(),
                'data' => $request->attendances
            ];

            $work->update([
                'crew_attendances' => $attendances
            ]);

            ControllerSecurityHelper::logCrud('produksi_attendance_submitted', $work, [
                'team_type' => $request->team_type,
                'submitted_by' => $user->id
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode']),
                'message' => "Absensi Tim " . ($request->team_type === 'setting' ? 'Setting' : 'Syuting') . " berhasil disimpan."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input Form Catatan Syuting (Run Sheet)
     * POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet
     */
    public function createRunSheet(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $work = ProduksiWork::findOrFail($id);
            
            // Authorization: Production role OR member of Tim Syuting / Tim Setting for this episode
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'setting');

            if (!$isProductionRole && !$isShootingMember && !$isSettingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            // Attendance Check: Tim Syuting must have submitted attendance
            $attendances = $work->crew_attendances ?? [];
            if (!$isProductionRole && (!isset($attendances['shooting']) || empty($attendances['shooting']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi Tim Syuting belum dilakukan. Silakan lakukan absensi terlebih dahulu oleh Coordinator Tim Syuting sebelum membuat Catatan Syuting.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'shooting_date' => 'required|date', // Removed after_or_equal:today to allow backdating
                'location' => 'required|string|max:255',
                'crew_list' => 'nullable|array', // Removed min:1
                'crew_list.*.name' => 'required|string|max:255',
                'crew_list.*.role' => 'nullable|string|max:100',
                'crew_list.*.contact' => 'nullable|string|max:50',
                'equipment_list' => 'nullable|array', // Removed min:1
                'equipment_list.*.name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'shooting_notes' => 'nullable|string|max:1000',
                'run_sheet_link' => 'nullable|string|max:255' // Changed from url to string for flexibility
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to create run sheet'
                ], 400);
            }

            // Create run sheet
            $runSheet = ShootingRunSheet::create([
                'episode_id' => $work->episode_id,
                'produksi_work_id' => $work->id,
                'shooting_date' => $request->shooting_date,
                'location' => $request->location,
                'crew_list' => $request->crew_list ?? [],
                'equipment_list' => $request->equipment_list ?? [],
                'shooting_notes' => $request->shooting_notes,
                'run_sheet_link' => $request->run_sheet_link,
                'status' => 'planned',
                'created_by' => $user->id
            ]);

            // Update work with run sheet ID
            $work->update([
                'run_sheet_id' => $runSheet->id
            ]);

            // Audit logging
            ControllerSecurityHelper::logCreate($runSheet, [
                'produksi_work_id' => $work->id,
                'episode_id' => $work->episode_id,
                'shooting_date' => $request->shooting_date
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'creativeWork', 'runSheet']),
                    'run_sheet' => $runSheet
                ],
                'message' => 'Run sheet created successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating run sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Hasil Syuting ke Storage
     * POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results
     */
    public function uploadShootingResults(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $work = ProduksiWork::findOrFail($id);
            
            // Authorization: Production role OR member of Tim Syuting / Tim Setting for this episode
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'setting');

            if (!$isProductionRole && !$isShootingMember && !$isSettingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|mimes:mp4,avi,mov,mkv|max:1024000', // Max 1GB per file
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::with(['episode', 'runSheet'])->findOrFail($id);

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to upload shooting results'
                ], 400);
            }

            // Physical file upload disabled
            if ($request->hasFile('files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical file uploads are disabled. Please use shooting_file_links for URL submissions.'
                ], 405);
            }

            if ($request->has('shooting_file_links')) {
                $filePaths = is_array($request->shooting_file_links) 
                    ? $request->shooting_file_links 
                    : explode(',', $request->shooting_file_links);
                
                $uploadedFiles = [];
                foreach ($filePaths as $link) {
                    $link = trim($link); // Extra safety
                    if ($link && strpos($link, 'http') !== 0) {
                        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $link)) {
                            $link = 'https://' . $link;
                        }
                    }
                    
                    $uploadedFiles[] = [
                        'original_name' => 'Shooting Result Link',
                        'file_path' => $link,
                        'url' => $link,
                        'uploaded_at' => now()->toDateTimeString()
                    ];
                }

                $work->update([
                    'shooting_files' => $uploadedFiles,
                    'shooting_file_links' => array_column($uploadedFiles, 'url')
                ]);
            }

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            // Update run sheet if exists
            if ($work->runSheet) {
                $work->runSheet->update([
                    'uploaded_files' => $uploadedFiles,
                    'completion_notes' => $request->completion_notes,
                    'status' => 'completed'
                ]);
            }

            // Auto-create EditorWork
            $existingEditorWork = \App\Models\EditorWork::where('episode_id', $work->episode_id)
                ->where('work_type', 'main_episode')
                ->first();

            if (!$existingEditorWork) {
                $editorWork = \App\Models\EditorWork::create([
                    'episode_id' => $work->episode_id,
                    'work_type' => 'main_episode',
                    'status' => 'draft',
                    'source_files' => [
                        'produksi_work_id' => $work->id,
                        'shooting_files' => $uploadedFiles,
                        'shooting_file_links' => $filePaths
                    ],
                    'file_complete' => false, // Editor perlu cek kelengkapan file
                    'created_by' => $user->id
                ]);
            } else {
                // Update existing EditorWork dengan file terbaru
                $existingSourceFiles = $existingEditorWork->source_files ?? [];
                $existingEditorWork->update([
                    'source_files' => array_merge($existingSourceFiles, [
                        'produksi_work_id' => $work->id,
                        'shooting_files' => $uploadedFiles,
                        'shooting_file_links' => $filePaths,
                        'updated_at' => now()->toDateTimeString()
                    ]),
                    'file_complete' => false // Reset status karena ada file baru
                ]);
                $editorWork = $existingEditorWork;
            }

            // Notify Editor
            $editorUsers = \App\Models\User::where('role', 'Editor')->get();
            $editorNotifications = [];
            $now = now();

            foreach ($editorUsers as $editorUser) {
                $editorNotifications[] = [
                    'user_id' => $editorUser->id,
                    'type' => 'produksi_shooting_completed',
                    'title' => 'Hasil Syuting Tersedia',
                    'message' => "Produksi telah mengupload hasil syuting untuk Episode {$work->episode->episode_number}. Siap untuk editing.",
                    'data' => json_encode([
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_work_id' => $editorWork->id
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            if (!empty($editorNotifications)) {
                Notification::insert($editorNotifications);
            }

            // Auto-create DesignGrafisWork untuk Thumbnail YouTube dan BTS
            $designGrafisWorkTypes = ['thumbnail_youtube', 'thumbnail_bts'];
            $createdDesignGrafisWorks = [];

            foreach ($designGrafisWorkTypes as $workType) {
                $existingDesignGrafisWork = \App\Models\DesignGrafisWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $workType)
                    ->first();

                if (!$existingDesignGrafisWork) {
                    $designGrafisWork = \App\Models\DesignGrafisWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => $workType,
                        'title' => $workType === 'thumbnail_youtube' 
                            ? "Thumbnail YouTube - Episode {$work->episode->episode_number}"
                            : "Thumbnail BTS - Episode {$work->episode->episode_number}",
                        'description' => "Design thumbnail untuk Episode {$work->episode->episode_number}. File referensi dari Produksi sudah tersedia.",
                        'status' => 'draft',
                        'source_files' => [
                            'produksi_work_id' => $work->id,
                            'produksi_files' => [
                                'files' => $uploadedFiles,
                                'file_links' => $filePaths
                            ],
                            'available' => true,
                            'fetched_at' => now()->toDateTimeString()
                        ],
                        'created_by' => $user->id
                    ]);
                    $createdDesignGrafisWorks[] = $designGrafisWork;
                } else {
                    // Update existing DesignGrafisWork dengan file terbaru
                    $existingSourceFiles = $existingDesignGrafisWork->source_files ?? [];
                    $existingDesignGrafisWork->update([
                        'source_files' => array_merge($existingSourceFiles, [
                            'produksi_work_id' => $work->id,
                            'produksi_files' => [
                                'files' => $uploadedFiles,
                                'file_links' => $filePaths,
                                'updated_at' => now()->toDateTimeString()
                            ]
                        ])
                    ]);
                    $createdDesignGrafisWorks[] = $existingDesignGrafisWork;
                }
            }

            // Notify Design Grafis - File dari Produksi sudah tersedia dan work sudah dibuat
            $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
            $designNotifications = [];

            foreach ($designGrafisUsers as $designUser) {
                $designNotifications[] = [
                    'user_id' => $designUser->id,
                    'type' => 'produksi_files_available',
                    'title' => 'File Produksi Tersedia',
                    'message' => "Produksi telah mengupload file hasil syuting untuk Episode {$work->episode->episode_number}. Design Grafis work untuk Thumbnail YouTube dan BTS sudah dibuat.",
                    'data' => json_encode([
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_count' => count($uploadedFiles),
                        'design_grafis_works' => array_map(function($dgWork) {
                            return [
                                'id' => $dgWork->id,
                                'work_type' => $dgWork->work_type,
                                'title' => $dgWork->title
                            ];
                        }, $createdDesignGrafisWorks)
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            if (!empty($designNotifications)) {
                Notification::insert($designNotifications);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'creativeWork', 'runSheet']),
                    'uploaded_files' => $uploadedFiles
                ],
                'message' => 'Shooting results uploaded successfully. Editor has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading shooting results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input Link File di Sistem Alamat Storage
     * POST /api/live-tv/roles/produksi/works/{id}/input-file-links
     */
    public function inputFileLinks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $work = ProduksiWork::findOrFail($id);
            
            // Authorization: Production role OR member of Tim Syuting / Tim Setting for this episode
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $work->episode_id, 'setting');

            if (!$isProductionRole && !$isShootingMember && !$isSettingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_links' => 'required|array|min:1',
                'file_links.*.url' => 'required|url',
                'file_links.*.file_name' => 'required|string|max:255',
                'file_links.*.file_size' => 'nullable|integer',
                'file_links.*.mime_type' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::with(['episode.program'])->findOrFail($id);

            if ($work->status !== 'in_progress') {
                // We'll allow link input even if not in_progress if needed, but let's stick to current status flow
            }

            // Normalize links (ensure absolute URLs for external links)
            $normalizedLinks = array_map(function($link) {
                $url = $link['url'] ?? '';
                if ($url && strpos($url, 'http') !== 0) {
                    if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $url)) {
                        $link['url'] = 'https://' . $url;
                    }
                }
                return $link;
            }, $request->file_links);

            // Update work with file links
            $work->update([
                'shooting_files' => $normalizedLinks,
                'shooting_file_links' => array_column($normalizedLinks, 'url')
            ]);

            // Auto-create EditorWork
            $existingEditorWork = \App\Models\EditorWork::where('episode_id', $work->episode_id)
                ->where('work_type', 'main_episode')
                ->first();

            if (!$existingEditorWork) {
                $editorWork = \App\Models\EditorWork::create([
                    'episode_id' => $work->episode_id,
                    'work_type' => 'main_episode',
                    'status' => 'draft',
                    'source_files' => [
                        'produksi_work_id' => $work->id,
                        'shooting_files' => $request->file_links,
                        'shooting_file_links' => array_column($request->file_links, 'url')
                    ],
                    'file_complete' => true, // Link considered complete
                    'created_by' => $user->id
                ]);
            } else {
                // Update existing EditorWork
                $existingSourceFiles = $existingEditorWork->source_files ?? [];
                $existingEditorWork->update([
                    'source_files' => array_merge($existingSourceFiles, [
                        'produksi_work_id' => $work->id,
                        'shooting_files' => $request->file_links,
                        'shooting_file_links' => array_column($request->file_links, 'url'),
                        'updated_at' => now()->toDateTimeString()
                    ]),
                    'file_complete' => true
                ]);
                $editorWork = $existingEditorWork;
            }

            // Auto-create DesignGrafisWork for Thumbnails
            $designGrafisWorkTypes = ['thumbnail_youtube', 'thumbnail_bts'];
            
            foreach ($designGrafisWorkTypes as $workType) {
                $existingDesignGrafisWork = \App\Models\DesignGrafisWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $workType)
                    ->first();

                if (!$existingDesignGrafisWork) {
                    \App\Models\DesignGrafisWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => $workType,
                        'title' => $workType === 'thumbnail_youtube' 
                            ? "Thumbnail YouTube - Episode {$work->episode->episode_number}"
                            : "Thumbnail BTS - Episode {$work->episode->episode_number}",
                        'status' => 'draft',
                        'notes' => "Auto-created from Production shooting links",
                        'priority' => 'normal',
                        'created_by' => $user->id
                    ]);
                }
            }

            // ✨ PARALLEL NOTIFICATIONS ✨
            // Produksi selesai syuting → notify 4 roles simultaneously:
            // 1. Art & Set Properti → alat kembali
            // 2. Producer → FYI
            // 3. Editor → file ready for editing
            // 4. Design Grafis → file ready for thumbnail
            
            $episode = $work->episode;
            $program = $episode->program;
            
            \App\Services\ParallelNotificationService::notifyRoles(
                ['Art & Set Properti', 'Producer', 'Editor', 'Graphic Design'],
                [
                    'type' => 'shooting_completed',
                    'title' => 'Syuting Selesai',
                    'message' => "Syuting Episode {$episode->episode_number} - {$episode->title} telah selesai dan file sudah diupload",
                    'data' => [
                        'episode_id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'produksi_work_id' => $work->id,
                        'file_count' => count($request->file_links),
                        'shooting_file_links' => $work->shooting_file_links
                    ]
                ],
                $program->id
            );

            // Audit logging
            ControllerSecurityHelper::logCrud('produksi_file_links_input', $work, [
                'file_count' => count($request->file_links),
                'parallel_notifications_sent' => 4 // Art & Set, Producer, Editor, Design Grafis
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork']),
                'message' => 'File links input successfully. 4 team members notified (Art & Set, Producer, Editor, Design Grafis).'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error inputting file links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Baca Hasil QC
     * GET /api/live-tv/roles/produksi/qc-results/{episode_id}
     */
    public function getQCResults(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Authorization: Production role OR member of Tim Syuting / Tim Setting for this episode
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $episodeId, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $episodeId, 'setting');

            if (!$isProductionRole && !$isShootingMember && !$isSettingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get standard QC results (e.g. from QC Role)
            $qcWorks = QualityControlWork::where('episode_id', $episodeId)
                ->whereIn('status', ['approved', 'revision_needed', 'failed'])
                ->with(['episode', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get Distribution Manager QC results (from BroadcastingWork)
            $broadcastingQC = BroadcastingWork::where('episode_id', $episodeId)
                ->whereIn('status', ['pending', 'rejected', 'reviewing'])
                ->with(['approvedBy', 'rejectedBy'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($work) {
                    return [
                        'id' => $work->id,
                        'status' => $work->status,
                        'approved_at' => $work->approved_at,
                        'rejected_at' => $work->rejected_at,
                        'notes' => $work->status === 'rejected' ? $work->rejection_notes : $work->approval_notes,
                        'qc_by' => $work->status === 'rejected' ? $work->rejectedBy?->name : $work->approvedBy?->name,
                        'checklist' => $work->metadata['qc_checklist'] ?? [],
                        'type' => 'Distribution Manager QC'
                    ];
                });

            // Also get EpisodeQC if exists
            $episodeQC = \App\Models\QualityControl::where('episode_id', $episodeId)
                ->with(['qcBy'])
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'qc_works' => $qcWorks,
                    'broadcasting_qc' => $broadcastingQC,
                    'episode_qc' => $episodeQC,
                    'episode_id' => $episodeId
                ],
                'message' => 'QC results retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving QC results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Producer requests
     * GET /api/live-tv/roles/produksi/producer-requests
     */
    public function getProducerRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (
                !$user
                || (($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) && !ProgramManagerAuthorization::isProgramManager($user))
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $produksiWorks = ProduksiWork::with(['episode.program.productionTeam'])
                ->whereHas('episode.program.productionTeam')
                ->whereNotNull('producer_requests')
                ->get();

            $requests = [];
            foreach ($produksiWorks as $work) {
                $producerRequests = $work->producer_requests ?? [];
                foreach ($producerRequests as $req) {
                    if (isset($req['status']) && $req['status'] === 'pending') {
                        $requests[] = [
                            'request_id' => $req['id'] ?? null,
                            'produksi_work_id' => $work->id,
                            'episode_id' => $work->episode_id,
                            'episode_number' => $work->episode->episode_number,
                            'request_type' => $req['request_type'] ?? null,
                            'reason' => $req['reason'] ?? null,
                            'missing_files' => $req['missing_files'] ?? [],
                            'shooting_schedule' => $req['shooting_schedule'] ?? null,
                            'requested_by' => $req['requested_by_name'] ?? null,
                            'requested_at' => $req['requested_at'] ?? null,
                            'work' => $work
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Producer requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving producer requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept Producer request and proceed
     * POST /api/live-tv/roles/produksi/producer-requests/{produksi_work_id}/accept
     */
    public function acceptProducerRequest(Request $request, int $produksiWorkId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (
                !$user
                || (($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) && !ProgramManagerAuthorization::isProgramManager($user))
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'request_id' => 'required|string',
                'action' => 'nullable|in:accept,reject',
                'notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $produksiWork = ProduksiWork::with(['episode.program.productionTeam'])->findOrFail($produksiWorkId);

            // Authorization check (consistent with other methods): shooting or setting member
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isShootingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $produksiWork->episode_id, 'shooting');
            $isSettingMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $produksiWork->episode_id, 'setting');

            if (
                !$isProgramManager
                && !$isProductionRole
                && !$isShootingMember
                && !$isSettingMember
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned to the shooting or setting team for this episode.'
                ], 403);
            }

            $producerRequests = $produksiWork->producer_requests ?? [];
            $requestFound = false;

            foreach ($producerRequests as &$req) {
                if (isset($req['id']) && $req['id'] === $request->request_id) {
                    $requestFound = true;
                    $req['status'] = $request->action === 'accept' ? 'accepted' : 'rejected';
                    $req['accepted_by'] = $user->id;
                    $req['accepted_by_name'] = $user->name;
                    $req['accepted_at'] = now()->toDateTimeString();
                    $req['notes'] = $request->notes;

                    // If accepted and request type is reshoot, reset shooting files
                    if ($req['status'] === 'accepted' && $req['request_type'] === 'reshoot') {
                        $produksiWork->update([
                            'shooting_files' => null,
                            'shooting_file_links' => null,
                            'status' => 'in_progress'
                        ]);
                    }

                    break;
                }
            }

            if (!$requestFound) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 404);
            }

            $produksiWork->update(['producer_requests' => $producerRequests]);

            // Notify Producer
            $producer = $produksiWork->episode->program->productionTeam->producer ?? null;
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'produksi_accepted_producer_request',
                    'title' => 'Produksi Menerima Permintaan',
                    'message' => "Produksi {$user->name} telah " . 
                        ($request->action === 'accept' ? 'menerima' : 'menolak') . 
                        " permintaan untuk Episode {$produksiWork->episode->episode_number}.",
                    'data' => [
                        'produksi_work_id' => $produksiWork->id,
                        'episode_id' => $produksiWork->episode_id,
                        'request_id' => $request->request_id,
                        'action' => $request->action,
                        'notes' => $request->notes
                    ]
                ]);
            }

            // If accepted, update work status back to in_progress
            if ($request->action === 'accept' && $produksiWork->status === 'completed') {
                $produksiWork->update(['status' => 'in_progress']);
            }

            return response()->json([
                'success' => true,
                'data' => $produksiWork->fresh(['episode']),
                'message' => 'Producer request ' . ($request->action === 'accept' ? 'accepted' : 'rejected') . ' successfully. Producer has been notified.'
            ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process producer request: ' . $e->getMessage()
                ], 500);
            }
        }
    /**
     * Return Equipment to Art & Set Properti (balikin barang).
     * Hanya Tim Syuting (atau role Production) yang boleh memanggil ini.
     * POST /api/live-tv/roles/produksi/works/{id}/return-equipment
     */
    public function returnEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $work = ProduksiWork::findOrFail($id);
            
            // Authorization: hanya Coordinator Tim Syuting (atau role Production) yang boleh mengembalikan barang (return equipment)
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $isProductionRole = $user->role === 'Production' || $isProgramManager;
            $isCoordinator = \App\Models\ProductionTeamMember::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('is_coordinator', true)
                ->whereHas('assignment', function ($q) use ($work) {
                    $q->where('episode_id', $work->episode_id)
                        ->where('team_type', 'shooting')
                        ->where('status', '!=', 'cancelled');
                })->exists();

            if (!$isProductionRole && !$isCoordinator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya Coordinator Tim Syuting yang boleh mengembalikan alat (balikin barang).'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_request_ids' => 'required|array|min:1',
                'equipment_request_ids.*' => 'required|integer|exists:production_equipment,id',
                'return_condition' => 'required|array|min:1',
                'return_condition.*.equipment_request_id' => 'required|integer',
                'return_condition.*.condition' => 'required|in:good,damaged,lost',
                'return_condition.*.notes' => 'nullable|string|max:1000',
                'return_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::with(['episode'])->findOrFail($id);

            // Re-check: hanya Coordinator Tim Syuting atau Production yang boleh return
            if (!$isProductionRole && !$isCoordinator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Coordinator Tim Syuting yang boleh mengembalikan alat (balikin barang).'
                ], 403);
            }

            $equipmentRequestIds = $request->equipment_request_ids;
            $returnConditions = collect($request->return_condition)->keyBy('equipment_request_id');
            
            $returnedEquipment = [];
            $failedEquipment = [];

            foreach ($equipmentRequestIds as $equipmentRequestId) {
                $equipment = ProductionEquipment::find($equipmentRequestId);
                
                if (!$equipment) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Equipment request not found'
                    ];
                    continue;
                }

                // Verify equipment belongs to this work's episode
                if ($equipment->episode_id !== $work->episode_id) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Equipment request does not belong to this episode'
                    ];
                    continue;
                }

                // Verify equipment belongs to this user OR user is the coordinator for this episode
                if ($equipment->requested_by !== $user->id && !$isCoordinator) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Equipment request was not created by you and you are not assigned to this episode'
                    ];
                    continue;
                }

                // Verify equipment is approved or in_use
                if ($equipment->status !== 'approved' && $equipment->status !== 'in_use') {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => "Equipment is not in approved or in_use status (current: {$equipment->status})"
                    ];
                    continue;
                }

                // Get return condition
                $conditionData = $returnConditions->get($equipmentRequestId);
                if (!$conditionData) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Return condition not provided'
                    ];
                    continue;
                }

                // Update equipment status to returned (Tim Syuting yang balikin — simpan returned_by)
                $equipment->update([
                    'status' => 'returned',
                    'return_condition' => $conditionData['condition'],
                    'return_notes' => ($conditionData['notes'] ?? '') . ($request->return_notes ? "\n" . $request->return_notes : ''),
                    'returned_at' => now(),
                    'returned_by' => $user->id
                ]);

                // NOTA: Kita TIDAK melakukan increment available_quantity di sini lagi.
                // Increment akan dilakukan oleh Art & Set Properti saat mereka 'Accept Returned Equipment'.
                // Ini untuk mencegah double-counting dan memastikan verifikasi fisik oleh tim inventory.
                
                $returnedEquipment[] = $equipment->fresh();
            }

            // Notify Art & Set Properti
            if (!empty($returnedEquipment)) {
                $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
                $equipmentNames = collect($returnedEquipment)->map(function($eq) {
                    return is_array($eq->equipment_list) ? implode(', ', $eq->equipment_list) : ($eq->equipment_list ?? 'N/A');
                })->implode('; ');

                foreach ($artSetUsers as $artSetUser) {
                    Notification::create([
                        'user_id' => $artSetUser->id,
                        'type' => 'equipment_returned',
                        'title' => 'Alat Dikembalikan oleh Produksi',
                        'message' => "Tim Produksi {$user->name} telah mengembalikan alat untuk Episode {$work->episode->episode_number}. Alat: {$equipmentNames}",
                        'data' => [
                            'produksi_work_id' => $work->id,
                            'episode_id' => $work->episode_id,
                            'equipment_request_ids' => collect($returnedEquipment)->pluck('id')->toArray(),
                            'equipment_list' => $equipmentNames,
                            'returned_by' => $user->id
                        ]
                    ]);
                }

                // Audit logging
                ControllerSecurityHelper::logCrud('produksi_equipment_returned', $work, [
                    'equipment_count' => count($returnedEquipment),
                    'equipment_request_ids' => collect($returnedEquipment)->pluck('id')->toArray(),
                    'failed_count' => count($failedEquipment)
                ], $request);
            }

            if (!empty($failedEquipment)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'work' => $work->fresh(['episode']),
                        'returned_equipment' => $returnedEquipment,
                        'failed_equipment' => $failedEquipment
                    ],
                    'message' => count($returnedEquipment) . ' equipment returned successfully. ' . count($failedEquipment) . ' equipment failed to return.',
                    'warnings' => $failedEquipment
                ], 207);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode']),
                    'returned_equipment' => $returnedEquipment
                ],
                'message' => 'Equipment returned successfully. Art & Set Properti has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error returning equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available equipment from inventory
     * GET /api/live-tv/roles/produksi/equipment/available
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (
            !$user
            || (($user->role !== 'Production' && !$user->hasAnyMusicTeamAssignment()) && !ProgramManagerAuthorization::isProgramManager($user))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        $availableEquipment = InventoryItem::where('status', 'active') // InventoryItem uses 'active' status for available
            ->select(['id', 'equipment_id', 'name', 'category', 'available_quantity', 'total_quantity'])
            ->orderBy('name')
            ->values();
            
        return response()->json([
            'success' => true,
            'data' => $availableEquipment,
            'message' => 'Available equipment retrieved successfully'
        ]);
    }

    /**
     * Build activity timeline for produksi work (untuk history Tim Setting / Tim Syuting).
     */
    private function buildWorkActivityTimeline(ProduksiWork $work): array
    {
        $activity = [];
        $work->loadMissing(['runSheet', 'equipmentRequests', 'createdBy', 'completedBy']);

        if ($work->created_at) {
            $activity[] = [
                'at' => $work->created_at->toIso8601String(),
                'type' => 'work_created',
                'label' => 'Pekerjaan diterima',
                'description' => $work->createdBy ? 'Diterima oleh ' . $work->createdBy->name : null,
            ];
        }

        foreach ($work->equipmentRequests ?? [] as $eq) {
            if ($eq->requested_at) {
                $activity[] = [
                    'at' => $eq->requested_at->toIso8601String(),
                    'type' => 'equipment_requested',
                    'label' => 'List alat diajukan ke Art & Set Properti',
                    'description' => $eq->request_notes ?: null,
                ];
            }
            if ($eq->approved_at) {
                $activity[] = [
                    'at' => $eq->approved_at->toIso8601String(),
                    'type' => 'equipment_approved',
                    'label' => 'Alat disetujui Art & Set Properti',
                    'description' => null,
                ];
            }
            if ($eq->assigned_at) {
                $activity[] = [
                    'at' => $eq->assigned_at->toIso8601String(),
                    'type' => 'equipment_received',
                    'label' => 'Barang diterima tim syuting',
                    'description' => $eq->assignedUser?->name ?? null,
                ];
            }
            if ($eq->returned_at) {
                $activity[] = [
                    'at' => $eq->returned_at->toIso8601String(),
                    'type' => 'equipment_returned',
                    'label' => 'Alat dikembalikan ke Art & Set Properti',
                    'description' => $eq->returnedByUser ? $eq->returnedByUser->name : null,
                ];
            }
        }

        if ($work->runSheet && $work->runSheet->created_at) {
            $activity[] = [
                'at' => $work->runSheet->created_at->toIso8601String(),
                'type' => 'run_sheet_created',
                'label' => 'Run sheet diisi',
                'description' => null,
            ];
        }

        if ($work->shooting_file_links && count($work->shooting_file_links) > 0) {
            $links = is_array($work->shooting_file_links) ? $work->shooting_file_links : [];
            $firstAt = null;
            foreach ($links as $item) {
                if (is_array($item) && isset($item['created_at'])) {
                    $firstAt = $item['created_at'];
                    break;
                }
            }
            $activity[] = [
                'at' => $firstAt ?? $work->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'type' => 'shooting_uploaded',
                'label' => 'Hasil syuting diupload',
                'description' => count($links) . ' file',
            ];
        }

        if ($work->completed_at) {
            $activity[] = [
                'at' => $work->completed_at->toIso8601String(),
                'type' => 'work_completed',
                'label' => 'Pekerjaan selesai',
                'description' => $work->completedBy ? 'Diselesaikan oleh ' . $work->completedBy->name : null,
            ];
        }

        usort($activity, function ($a, $b) {
            return strcmp($a['at'], $b['at']);
        });

        return array_values($activity);
    }
}

