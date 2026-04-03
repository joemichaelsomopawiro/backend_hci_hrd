<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SoundEngineerRecording;
use App\Models\SoundEngineerEditing;
use App\Models\Episode;
use App\Models\ProductionEquipment;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\ProductionTeamMember;
use App\Models\ProductionTeamAssignment;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ProductionEquipmentTransfer;

/**
 * VocalRecordingController — Dedicated controller for Tim Rekam Vokal.
 *
 * Authorization: User must be an active member of a 'recording' team assignment,
 * OR have Producer / Program Manager oversight access.
 *
 * Workflow:
 *  1. Terima jadwal rekaman vocal (accept-schedule)
 *  2. Terima pekerjaan (accept-work)
 *  3. Pinjam barang (request-equipment → Art & Set Properti)
 *  4. Mulai & selesaikan rekaman (start-recording, upload-recording-link)
 *  5. Kembalikan barang (return-equipment)
 *  6. Selesaikan pekerjaan (complete-work → auto-create editing task for Sound Engineer)
 */
class VocalRecordingController extends Controller
{
    // ─── Authorization ───────────────────────────────────────────

    /**
     * Check if user has access to the Vocal Recording dashboard.
     * True when user is a member of ANY active 'recording' team assignment,
     * or has Producer / Program Manager oversight.
     */
    private function hasRecordingTeamAccess($user): bool
    {
        if (!$user) return false;

        // Producer / Program Manager oversight
        if (MusicProgramAuthorization::hasProducerAccess($user)) return true;
        if (ProgramManagerAuthorization::isProgramManager($user)) return true;

        // Active member of a recording team assignment
        return ProductionTeamMember::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('assignment', fn($q) => $q->where('team_type', 'recording')->where('status', '!=', 'cancelled'))
            ->exists();
    }

    /**
     * Check if user has access to a specific recording task.
     */
    private function hasAccessToRecording($user, SoundEngineerRecording $recording): bool
    {
        if (!$user || !$recording) return false;

        if (MusicProgramAuthorization::hasProducerAccess($user)) return true;
        if (ProgramManagerAuthorization::isProgramManager($user)) return true;

        // Creator
        if ($recording->created_by === $user->id) return true;

        // Member of recording team for this episode
        return ProductionTeamMember::isMemberForEpisode($user->id, $recording->episode_id, 'recording');
    }

    // ─── Dashboard ───────────────────────────────────────────────

    /**
     * GET /api/live-tv/vocal-recording/works
     * List recording tasks for the current user's recording team assignments.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->hasRecordingTeamAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke Tim Rekam Vokal. Hubungi Producer untuk ditambahkan ke tim.'
                ], 403);
            }

            $query = SoundEngineerRecording::with([
                'episode' => fn($q) => $q->withTrashed(),
                'episode.program' => fn($q) => $q->withTrashed(),
                'episode.program.productionTeam.members.user',
                'episode.creativeWorks',
                'musicArrangement.song',
                'musicArrangement.singer',
                'createdBy',
                'reviewedBy',
                'equipmentRequests' => fn($q) => $q->where('requested_by', $user->id)
                    ->where('team_type', 'vocal_recording')
                    ->with([
                        'episode' => fn($eq) => $eq->withTrashed(),
                        'episode.program' => fn($pq) => $pq->withTrashed(),
                    ]),
            ]);

            // Only show recordings for episodes with approved Creative Work
            $query->whereHas('episode', fn($eq) => $eq->whereHas('creativeWorks', fn($cq) => $cq->where('status', 'approved')));

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Restrict to episodes where user is the coordinator for Recording team (unless Producer/PM)
            $isOversight = MusicProgramAuthorization::hasProducerAccess($user) || ProgramManagerAuthorization::isProgramManager($user);
            if (!$isOversight) {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('episode.teamAssignments', function ($aq) use ($user) {
                          $aq->where('team_type', 'recording')
                             ->where('status', '!=', 'cancelled')
                             ->whereHas('members', fn($mq) => $mq->where('user_id', $user->id)
                                ->where('is_active', true)
                                ->where('is_coordinator', true)
                             );
                      });
                });
            }

            $recordings = $query->orderBy('created_at', 'desc')->paginate(15);
            $recordingsData = $recordings->items();
            
            // Fetch active loans for "Lanjut Pakai" (Continued Use)
            $activeLoans = ProductionEquipment::with(['episode.program'])
                ->where('requested_by', $user->id)
                ->where('team_type', 'vocal_recording')
                ->whereIn('status', ['approved', 'in_use', 'ready'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Add convenience fields
            collect($recordingsData)->each(function ($recording) use ($activeLoans) {
                $episode = $recording->episode;
                $program = $episode?->program;

                $recording->episode_number = $episode->episode_number ?? null;
                $recording->program_name = $program->name ?? null;
                $recording->program_id = $program->id ?? null;
                $recording->recording_link = $recording->file_link;

                if (!$episode) {
                    $recording->episode_number = '#' . ($recording->episode_id ?? 'N/A');
                    $recording->program_name = "Unknown Program";
                } elseif (!$program) {
                    $recording->program_name = "Unknown Program";
                }

                // Is coordinator?
                $recording->is_coordinator = ProductionTeamMember::isCoordinatorForEpisode(
                    Auth::id(), $recording->episode_id, 'recording'
                );
            });

            return response()->json([
                'success' => true,
                'data' => $recordings,
                'active_loans' => $activeLoans,
                'message' => 'Vocal recording tasks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vocal recording tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/live-tv/vocal-recording/works/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->hasRecordingTeamAccess($user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $recording = SoundEngineerRecording::with([
                'episode' => fn($q) => $q->withTrashed(),
                'episode.program' => fn($q) => $q->withTrashed(),
                'episode.program.productionTeam.members.user',
                'episode.creativeWorks',
                'episode.teamAssignments' => fn($q) => $q->where('team_type', 'recording')->where('status', '!=', 'cancelled'),
                'episode.teamAssignments.members.user:id,name,email',
                'musicArrangement.song',
                'musicArrangement.singer',
                'createdBy',
                'reviewedBy',
                'equipmentRequests' => fn($q) => $q->where('requested_by', $user->id),
            ])->findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Convenience fields
            $episode = $recording->episode;
            $program = $episode?->program;
            $recording->episode_number = $episode->episode_number ?? null;
            $recording->program_name = $program->name ?? null;
            $recording->program_id = $program->id ?? null;
            $recording->recording_link = $recording->file_link;
            $recording->is_coordinator = ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');

            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Vocal recording detail retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recording detail: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─── Workflow Actions ─────────────────────────────────────────

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/accept-schedule
     * Terima jadwal rekaman vocal dari Creative Work.
     */
    public function acceptSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::with(['episode.creativeWorks'])->findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Resolve schedule from Creative Work or recording itself
            $scheduleToUse = $recording->recording_schedule;
            $creativeWork = $recording->episode?->creativeWorks?->where('status', 'approved')->first();
            if ($creativeWork && $creativeWork->recording_schedule) {
                $scheduleToUse = $creativeWork->recording_schedule;
            }

            if (!$scheduleToUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal rekaman vokal belum tersedia. Creative harus mengisi jadwal rekaman di Creative Work lalu submit ke Producer.'
                ], 400);
            }

            $recording->update([
                'recording_schedule' => $scheduleToUse,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement']),
                'message' => 'Jadwal rekaman vokal diterima. Tanggal rekaman: ' . \Carbon\Carbon::parse($scheduleToUse)->format('d M Y')
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/accept-work
     * Terima pekerjaan rekaman vokal.
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Idempotency
            if ($recording->status === 'in_progress') {
                return response()->json([
                    'success' => true,
                    'data' => $recording->fresh(['episode', 'musicArrangement', 'createdBy']),
                    'message' => 'Pekerjaan sudah diterima sebelumnya.'
                ]);
            }

            if (!in_array($recording->status, ['draft', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan hanya bisa diterima saat status draft atau pending. Status saat ini: ' . $recording->status
                ], 400);
            }

            $recording->update(['status' => 'in_progress']);

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement', 'createdBy']),
                'message' => 'Pekerjaan diterima. Silakan input list alat dan lanjut proses rekaman.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─── Equipment ───────────────────────────────────────────────

    /**
     * GET /api/live-tv/vocal-recording/equipment/available
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$this->hasRecordingTeamAccess($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $availableEquipment = InventoryItem::where('status', 'active')
            ->select(['id', 'equipment_id', 'name', 'category', 'available_quantity', 'total_quantity'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availableEquipment,
            'message' => 'Available equipment retrieved successfully'
        ]);
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/request-equipment
     * Pinjam barang ke Art & Set Properti.
     */
    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Only coordinator or Producer/PM can request equipment
            $isCoordinator = ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');
            $isOversight = MusicProgramAuthorization::hasProducerAccess($user) || ProgramManagerAuthorization::isProgramManager($user);

            if (!$isCoordinator && !$isOversight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Koordinator Tim Rekam Vokal yang dapat meminjam alat.'
                ], 403);
            }

            // Must accept work first
            if (!in_array($recording->status, ['in_progress', 'recording', 'ready', 'completed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan harus diterima (Accept Work) terlebih dahulu sebelum dapat meminjam alat.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.equipment_name' => 'required|string|max:255',
                'equipment_list.*.equipment_id' => 'nullable|integer|exists:equipment_inventory,id',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.return_date' => 'required|date|after_or_equal:today',
                'equipment_list.*.notes' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000',
                'request_group_id' => 'nullable|string|max:64',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            // Normalize equipment quantities
            $normalizedItems = [];
            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];
                if (!empty($equipment['equipment_id'])) {
                    $inv = InventoryItem::find($equipment['equipment_id']);
                    if ($inv) $equipmentName = $inv->name;
                }
                $qty = (int)($equipment['quantity'] ?? 0);
                if ($qty < 1) continue;
                $normalizedItems[] = ['name' => $equipmentName, 'quantity' => $qty, 'notes' => $equipment['notes'] ?? null];
            }

            $qtyByName = [];
            foreach ($normalizedItems as $it) {
                $qtyByName[$it['name']] = ($qtyByName[$it['name']] ?? 0) + $it['quantity'];
            }

            // Check availability
            $inventoryCounts = InventoryItem::whereIn('name', array_keys($qtyByName))
                ->get()->pluck('available_quantity', 'name');

            $unavailable = [];
            foreach ($qtyByName as $name => $qty) {
                $avail = $inventoryCounts->get($name, 0);
                if ($avail < $qty) {
                    $unavailable[] = ['equipment_name' => $name, 'requested_quantity' => $qty, 'available_count' => $avail];
                }
            }

            if (!empty($unavailable)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa alat tidak tersedia dalam jumlah yang cukup',
                    'unavailable_equipment' => $unavailable
                ], 400);
            }

            // Build flat list
            $equipmentList = [];
            foreach ($qtyByName as $name => $qty) {
                for ($i = 0; $i < $qty; $i++) $equipmentList[] = $name;
            }

            $notesLines = [];
            foreach ($normalizedItems as $it) {
                if (!empty($it['notes'])) $notesLines[] = "{$it['name']}: {$it['notes']}";
            }
            if (!empty($request->request_notes)) $notesLines[] = (string) $request->request_notes;

            $scheduleDt = $recording->recording_schedule ? \Carbon\Carbon::parse($recording->recording_schedule) : null;

            // Merge into existing pending request if one exists
            $existingPending = ProductionEquipment::where('episode_id', $recording->episode_id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingPending) {
                $existingVal = $existingPending->equipment_list;
                $existingList = is_array($existingVal)
                    ? $existingVal
                    : (is_string($existingVal) ? json_decode($existingVal, true) : []);
                $existingList = $existingList ?? [];

                $mergedList = array_values(array_merge($existingList, $equipmentList));
                $appendNotes = !empty($notesLines) ? implode("\n", $notesLines) : null;
                $mergedNotes = $existingPending->request_notes;
                if (!empty($appendNotes)) {
                    $mergedNotes = trim((string) $mergedNotes);
                    $mergedNotes = $mergedNotes !== '' ? ($mergedNotes . "\n" . $appendNotes) : $appendNotes;
                }

                $existingQtyVal = $existingPending->equipment_quantities;
                $existingQtyMap = is_array($existingQtyVal)
                    ? $existingQtyVal
                    : (is_string($existingQtyVal) ? json_decode($existingQtyVal, true) : []);
                foreach ($qtyByName as $k => $v) {
                    $existingQtyMap[$k] = (int)($existingQtyMap[$k] ?? 0) + (int)$v;
                }

                $existingPending->update([
                    'program_id' => $existingPending->program_id ?: ($recording->episode ? $recording->episode->program_id : null),
                    'request_group_id' => $existingPending->request_group_id ?: ($request->request_group_id ?: null),
                    'equipment_list' => $mergedList,
                    'equipment_quantities' => $existingQtyMap,
                    'request_notes' => $mergedNotes ?: null,
                    'scheduled_date' => $existingPending->scheduled_date ?: ($scheduleDt ? $scheduleDt->toDateString() : null),
                    'scheduled_time' => $existingPending->scheduled_time ?: ($scheduleDt ? $scheduleDt->format('H:i:s') : null),
                ]);
                $equipmentRequest = $existingPending->fresh();
            } else {
                $equipmentRequest = ProductionEquipment::create([
                    'episode_id' => $recording->episode_id,
                    'program_id' => $recording->episode ? $recording->episode->program_id : null,
                    'request_group_id' => $request->request_group_id ?: null,
                    'equipment_list' => $equipmentList,
                    'equipment_quantities' => $qtyByName,
                    'request_notes' => !empty($notesLines) ? implode("\n", $notesLines) : null,
                    'scheduled_date' => $scheduleDt ? $scheduleDt->toDateString() : null,
                    'scheduled_time' => $scheduleDt ? $scheduleDt->format('H:i:s') : null,
                    'status' => 'pending',
                    'requested_by' => $user->id,
                    'requested_at' => now(),
                ]);
            }

            // Update recording with equipment list
            $recording->update(['equipment_used' => $request->equipment_list]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => $existingPending ? 'equipment_request_updated' : 'equipment_request_created',
                    'title' => $existingPending ? 'Update Permintaan Alat' : 'Permintaan Alat Baru',
                    'message' => "Tim Rekam Vokal meminta equipment untuk rekaman vokal Episode {$recording->episode->episode_number}.",
                    'data' => [
                        'equipment_request_ids' => [$equipmentRequest->id],
                        'episode_id' => $recording->episode_id,
                        'recording_id' => $recording->id,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recording' => $recording->fresh(['episode', 'musicArrangement']),
                    'equipment_requests' => ProductionEquipment::whereIn('id', [$equipmentRequest->id])->get(),
                ],
                'message' => 'Permintaan alat berhasil. Art & Set Properti telah diberitahu.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/live-tv/vocal-recording/equipment-requests/{id}
     * Batalkan permintaan alat (hanya pending & milik user).
     */
    public function cancelEquipmentRequest(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->hasRecordingTeamAccess($user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $equipment = ProductionEquipment::where('id', $id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$equipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan tidak ditemukan atau sudah tidak dapat dibatalkan.'
                ], 404);
            }

            $equipment->delete();
            return response()->json(['success' => true, 'message' => 'Permintaan alat berhasil dibatalkan.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/equipment/{id}/notify-return
     * Notify Art & Set Properti bahwa alat sudah dikembalikan secara fisik.
     */
    public function notifyReturn(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->hasRecordingTeamAccess($user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $equipment = ProductionEquipment::find($id);
            if (!$equipment) {
                return response()->json(['success' => false, 'message' => 'Equipment request not found.'], 404);
            }

            if ($equipment->status !== 'in_use' && $equipment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment harus berstatus "approved" atau "in_use" untuk notify return.'
                ], 400);
            }

            $timestamp = now()->format('Y-m-d H:i');
            $currentNotes = $equipment->return_notes ?? '';
            $newNote = "[User Return Notification] Tim Rekam Vokal ({$user->name}) reported equipment returned at {$timestamp}.";

            $equipment->update([
                'return_notes' => $currentNotes ? $currentNotes . "\n" . $newNote : $newNote
            ]);

            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_return_notification',
                    'title' => 'Pengembalian Alat (Tim Rekam Vokal)',
                    'message' => "Tim Rekam Vokal ({$user->name}) melaporkan telah mengembalikan alat (ID: {$equipment->id}). Harap cek fisik & konfirmasi return.",
                    'data' => [
                        'equipment_id' => $equipment->id,
                        'reported_by' => $user->name,
                        'role' => 'Tim Rekam Vokal',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi pengembalian berhasil dikirim ke Art & Set Properti.',
                'data' => $equipment,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/return-equipment
     * Kembalikan alat ke Art & Set Properti.
     */
    public function returnEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_request_ids' => 'required|array|min:1',
                'equipment_request_ids.*' => 'required|integer|exists:production_equipment,id',
                'return_condition' => 'required|array|min:1',
                'return_condition.*.equipment_request_id' => 'required|integer',
                'return_condition.*.condition' => 'required|in:good,damaged,lost',
                'return_condition.*.notes' => 'nullable|string|max:1000',
                'return_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $equipmentRequestIds = $request->equipment_request_ids;
            $returnConditions = collect($request->return_condition)->keyBy('equipment_request_id');

            // Authorization: borrower for all OR coordinator
            $isCoordinator = ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');
            $borrowerCount = ProductionEquipment::whereIn('id', $equipmentRequestIds)->where('requested_by', $user->id)->count();
            $isBorrowerForAll = $borrowerCount === count($equipmentRequestIds);

            if (!$isBorrowerForAll && !$isCoordinator && !MusicProgramAuthorization::hasProducerAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda hanya dapat mengembalikan alat yang Anda pinjam sendiri, atau sebagai Koordinator Tim Rekam Vokal.'
                ], 403);
            }

            $returnedEquipment = [];
            $failedEquipment = [];

            foreach ($equipmentRequestIds as $eqId) {
                $equipment = ProductionEquipment::find($eqId);
                if (!$equipment) {
                    $failedEquipment[] = ['equipment_request_id' => $eqId, 'reason' => 'Not found'];
                    continue;
                }
                if ($equipment->episode_id !== $recording->episode_id) {
                    $failedEquipment[] = ['equipment_request_id' => $eqId, 'reason' => 'Not same episode'];
                    continue;
                }
                if ($equipment->status !== 'approved' && $equipment->status !== 'in_use') {
                    $failedEquipment[] = ['equipment_request_id' => $eqId, 'reason' => "Status: {$equipment->status}"];
                    continue;
                }

                $conditionData = $returnConditions->get($eqId);
                if (!$conditionData) {
                    $failedEquipment[] = ['equipment_request_id' => $eqId, 'reason' => 'No return condition'];
                    continue;
                }

                $equipment->update([
                    'status' => 'returned',
                    'return_condition' => $conditionData['condition'],
                    'return_notes' => ($conditionData['notes'] ?? '') . ($request->return_notes ? "\n" . $request->return_notes : ''),
                    'returned_at' => now(),
                    'returned_by' => $user->id,
                ]);

                $returnedEquipment[] = $equipment->fresh();
            }

            // Notify Art & Set Properti
            if (!empty($returnedEquipment)) {
                $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
                $equipmentNames = collect($returnedEquipment)->map(fn($eq) => is_array($eq->equipment_list) ? implode(', ', $eq->equipment_list) : ($eq->equipment_list ?? 'N/A'))->implode('; ');

                foreach ($artSetUsers as $artSetUser) {
                    Notification::create([
                        'user_id' => $artSetUser->id,
                        'type' => 'equipment_returned',
                        'title' => 'Alat Dikembalikan oleh Tim Rekam Vokal',
                        'message' => "Tim Rekam Vokal ({$user->name}) mengembalikan alat Episode {$recording->episode->episode_number}. Alat: {$equipmentNames}",
                        'data' => [
                            'recording_id' => $recording->id,
                            'episode_id' => $recording->episode_id,
                            'equipment_request_ids' => collect($returnedEquipment)->pluck('id')->toArray(),
                        ],
                    ]);
                }
            }

            ControllerSecurityHelper::logCrud('vocal_recording_equipment_returned', $recording, [
                'equipment_count' => count($returnedEquipment),
                'failed_count' => count($failedEquipment),
            ], $request);

            QueryOptimizer::clearAllIndexCaches();

            $statusCode = !empty($failedEquipment) ? 207 : 200;
            return response()->json([
                'success' => true,
                'data' => [
                    'recording' => $recording->fresh(['episode']),
                    'returned_equipment' => $returnedEquipment,
                    'failed_equipment' => $failedEquipment,
                ],
                'message' => count($returnedEquipment) . ' alat berhasil dikembalikan.' . (!empty($failedEquipment) ? ' ' . count($failedEquipment) . ' gagal.' : ''),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/transfer-equipment
     * Transfer and reuse equipment from another episode (Lanjut Pakai).
     */
    public function transferEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $targetRecording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            if (!$this->hasAccessToRecording($user, $targetRecording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_request_id' => 'required|integer|exists:production_equipment,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $loan = ProductionEquipment::findOrFail($request->equipment_request_id);

            // Authorization: User must be the borrower or coordinator of both source and target
            $isCoordinatorTarget = ProductionTeamMember::isCoordinatorForEpisode($user->id, $targetRecording->episode_id, 'recording');
            $isBorrower = $loan->requested_by === $user->id;
            $isOversight = MusicProgramAuthorization::hasProducerAccess($user) || ProgramManagerAuthorization::isProgramManager($user);

            if (!$isBorrower && !$isCoordinatorTarget && !$isOversight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda hanya dapat memindahkan alat yang Anda pinjam sendiri, atau sebagai Koordinator Tim Rekam Vokal.'
                ], 403);
            }

            if ($loan->status !== 'approved' && $loan->status !== 'in_use') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya peminjaman dengan status "approved" atau "in_use" yang dapat dipindahkan.'
                ], 400);
            }

            if ($loan->episode_id === $targetRecording->episode_id) {
                return response()->json(['success' => false, 'message' => 'Alat sudah berada di episode ini.'], 400);
            }

            // Target must be accepted
            if (!in_array($targetRecording->status, ['in_progress', 'recording', 'ready'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan tujuan harus diterima (Accept Work) terlebih dahulu sebelum dapat memindahkan alat.'
                ], 400);
            }

            $fromEpisodeId = $loan->episode_id;
            $toEpisodeId = $targetRecording->episode_id;
            $toProgramId = $targetRecording->episode ? $targetRecording->episode->program_id : null;

            DB::beginTransaction();
            try {
                // Create transfer record
                ProductionEquipmentTransfer::create([
                    'production_equipment_id' => $loan->id,
                    'from_episode_id' => $fromEpisodeId,
                    'to_episode_id' => $toEpisodeId,
                    'transferred_by' => $user->id,
                    'transferred_at' => now(),
                    'notes' => $request->notes ?? 'Lanjut pakai dari Tim Rekam Vokal'
                ]);

                // Update loan record
                $loan->update([
                    'episode_id' => $toEpisodeId,
                    'program_id' => $toProgramId,
                    'status' => 'in_use', // Ensure status is in_use after transfer
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Notify Art & Set Properti about the transfer (info only)
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_transferred',
                    'title' => 'Pemindahan Alat (Lanjut Pakai)',
                    'message' => "Tim Rekam Vokal memindahkan alat dari Episode {$fromEpisodeId} ke Episode {$targetRecording->episode->episode_number} (Lanjut Pakai).",
                    'data' => [
                        'equipment_request_id' => $loan->id,
                        'from_episode_id' => $fromEpisodeId,
                        'to_episode_id' => $toEpisodeId,
                        'transferred_by' => $user->name,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recording' => $targetRecording->fresh(['episode']),
                    'equipment_request' => $loan->fresh(),
                ],
                'message' => 'Alat berhasil dipindahkan (Lanjut Pakai) ke episode ini.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/handover
     * Serah terima alat ke user lain (bisa beda tim/dept).
     */
    public function handoverEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $loan = ProductionEquipment::with('episode.program')->findOrFail($id);

            // Authorization: User must be the borrower or coordinator
            $isBorrower = $loan->requested_by === $user->id;
            $isCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $loan->episode_id, 'recording');
            
            if (!$isBorrower && !$isCoordinator && !MusicProgramAuthorization::hasProducerAccess($user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if (!in_array($loan->status, ['approved', 'in_use'])) {
                return response()->json(['success' => false, 'message' => 'Alat tidak dalam status dapat diserah-terimakan.'], 400);
            }

            $validator = Validator::make($request->all(), [
                'to_user_id' => 'required|exists:users,id',
                'to_episode_id' => 'required|exists:episodes,id',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $toUserId = (int) $request->to_user_id;
            if ($toUserId === $user->id) {
                return response()->json(['success' => false, 'message' => 'Tidak bisa serah terima ke diri sendiri. Gunakan "Lanjut Pakai" untuk pindah episode.'], 400);
            }

            // Same Day Validation
            $toEpisodeId = (int) $request->to_episode_id;
            $fromRecording = SoundEngineerRecording::where('episode_id', $loan->episode_id)->first();
            $toRecording = SoundEngineerRecording::where('episode_id', $toEpisodeId)->first();

            if ($fromRecording && $toRecording) {
                $fromDate = \Carbon\Carbon::parse($fromRecording->recording_schedule)->toDateString();
                $toDate = \Carbon\Carbon::parse($toRecording->recording_schedule)->toDateString();
                
                if ($fromDate !== $toDate) {
                    return response()->json([
                        'success' => false, 
                        'message' => "Hanya diperbolehkan serah terima untuk jadwal di hari yang sama ({$fromDate}). Jadwal tujuan adalah {$toDate}."
                    ], 400);
                }
            }

            DB::beginTransaction();
            try {
                $transfer = ProductionEquipmentTransfer::create([
                    'production_equipment_id' => $loan->id,
                    'from_episode_id' => $loan->episode_id,
                    'to_episode_id' => $request->to_episode_id ?? $loan->episode_id,
                    'to_user_id' => $toUserId,
                    'transferred_by' => $user->id,
                    'transferred_at' => now(),
                    'notes' => $request->notes,
                    'status' => 'pending_accept'
                ]);

                // Notify target user
                Notification::create([
                    'user_id' => $toUserId,
                    'type' => 'equipment_handover_requested',
                    'title' => 'Serah Terima Alat (Equipment Handover)',
                    'message' => "Ada serah terima alat dari {$user->name} untuk Anda. Silakan konfirmasi terima di dashboard.",
                    'data' => [
                        'transfer_id' => $transfer->id,
                        'equipment_request_id' => $loan->id,
                        'from_user' => $user->name,
                    ]
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Permintaan serah terima telah dikirim. Menunggu user tujuan menerima.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/handovers/{transferId}/accept
     * Konfirmasi terima alat dari user lain.
     */
    public function acceptHandover(int $transferId): JsonResponse
    {
        try {
            $user = Auth::user();
            $transfer = ProductionEquipmentTransfer::with('productionEquipment')->findOrFail($transferId);

            if ((int) $transfer->to_user_id !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized (Bukan tujuan serah terima)'], 403);
            }

            if ($transfer->status !== 'pending_accept') {
                return response()->json(['success' => false, 'message' => 'Serah terima ini sudah diproses.'], 400);
            }

            // Authorization: Only Coordinator, Producer, or Promotion can accept
            // 1. Check if user has explicit Producer or Promotion role in User table
            $isProducerRole = in_array($user->role, ['Producer', 'Program Manager']);
            $isPromotionRole = in_array($user->role, ['Promotion', 'Editor Promotion']);
            
            // 2. Check if user is a coordinator for the target episode
            $isCoordinatorForEpisode = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $transfer->to_episode_id);

            if (!$isProducerRole && !$isPromotionRole && !$isCoordinatorForEpisode) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Hanya Koordinator Tim, Produser, atau tim Promosi yang berhak mengonfirmasi serah terima alat ini.'
                ], 403);
            }

            $loan = $transfer->productionEquipment;
            if (!$loan || !in_array($loan->status, ['approved', 'in_use'])) {
                return response()->json(['success' => false, 'message' => 'Peminjaman alat asli sudah tidak valid.'], 400);
            }

            DB::beginTransaction();
            try {
                $transfer->update([
                    'status' => 'accepted',
                    'accepted_by' => $user->id,
                    'accepted_at' => now()
                ]);

                $loan->update([
                    'episode_id' => $transfer->to_episode_id,
                    'assigned_to' => $user->id,
                    'assigned_at' => now(),
                    'status' => 'in_use'
                ]);

                // Notify original sender
                if ($transfer->transferred_by) {
                    Notification::create([
                        'user_id' => $transfer->transferred_by,
                        'type' => 'equipment_handover_accepted',
                        'title' => 'Serah Terima Diterima',
                        'message' => "Serah terima alat kepada {$user->name} telah dikonfirmasi.",
                        'data' => [
                            'transfer_id' => $transfer->id,
                            'accepted_by' => $user->name
                        ]
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Alat berhasil diterima. Sekarang alat tercatat dalam tanggung jawab Anda.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/live-tv/vocal-recording/episodes/{id}/team
     * Mengambil daftar tim dan koordinator untuk episode tertentu (untuk serah terima).
     */
    public function getEpisodeTeam(int $id): JsonResponse
    {
        try {
            $episode = Episode::with(['teamAssignments.members.user'])->findOrFail($id);
            
            $teamMembers = [];
            foreach ($episode->teamAssignments as $assignment) {
                foreach ($assignment->members as $member) {
                    if (!$member->user) continue;
                    
                    $userId = $member->user_id;
                    if (!isset($teamMembers[$userId])) {
                        $teamMembers[$userId] = [
                            'id' => $userId,
                            'name' => $member->user->name,
                            'role' => $member->user->role,
                            'team_roles' => [],
                            'is_coordinator' => false
                        ];
                    }
                    
                    $teamTypeLabel = $assignment->getTeamTypeLabel(); // e.g. "Tim Setting (Art & Set)"
                    $cleanType = str_replace(['Tim ', ' (Art & Set)'], '', $teamTypeLabel);
                    $memberRoleLabel = $member->is_coordinator ? "Koordinator {$cleanType}" : $cleanType;
                    
                    $teamMembers[$userId]['team_roles'][] = $memberRoleLabel;
                    if ($member->is_coordinator) {
                        $teamMembers[$userId]['is_coordinator'] = true;
                    }
                }
            }

            // Also check for Producers who might not be in specific assignments (optional but good)
            // Or prioritize showing them as they have authority to accept.
            $producers = \App\Models\User::where('role', 'Producer')->get();
            foreach ($producers as $p) {
                if (!isset($teamMembers[$p->id])) {
                    $teamMembers[$p->id] = [
                        'id' => $p->id,
                        'name' => $p->name,
                        'role' => $p->role,
                        'team_roles' => ['Producer Program'],
                        'is_coordinator' => true // Producers are treated as authorities
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($teamMembers)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─── Recording ───────────────────────────────────────────────

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/start-recording
     * Mulai sesi rekaman vokal.
     */
    public function startRecording(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $allowedStatuses = ['draft', 'pending', 'scheduled', 'in_progress', 'ready'];
            if (!in_array($recording->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rekaman tidak dapat dimulai dari status saat ini: ' . $recording->status
                ], 400);
            }

            $recording->startRecording();

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(),
                'message' => 'Rekaman dimulai.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/upload-recording-link
     * Input link file hasil rekaman vocal.
     */
    public function uploadRecordingLink(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'recording_link' => 'required|string|max:2048',
                'recording_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $recording->update([
                'file_link' => $request->recording_link,
                'recording_notes' => $request->recording_notes ?? $recording->recording_notes,
            ]);

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement']),
                'message' => 'Link rekaman vokal berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/vocal-recording/works/{id}/complete-work
     * Selesaikan pekerjaan rekaman vokal.
     * Auto-create SoundEngineerEditing task dan kirim link ke Sound Engineer.
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail($id);

            if (!$this->hasAccessToRecording($user, $recording)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Allow completing from recording or in_progress status
            if (!in_array($recording->status, ['recording', 'in_progress', 'ready'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan hanya bisa diselesaikan saat status recording/in_progress/ready. Status saat ini: ' . $recording->status
                ], 400);
            }

            // Resolve recording link
            $recordingLink = $request->recording_link
                ?? $request->vocal_file_link
                ?? $recording->file_link;

            if (!$recordingLink) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link file rekaman vokal wajib diisi sebelum menyelesaikan pekerjaan.'
                ], 422);
            }

            // Update recording as completed
            $recording->update([
                'status' => 'completed',
                'recording_completed_at' => now(),
                'file_link' => $recordingLink,
                'recording_notes' => $request->recording_notes ?? $request->completion_notes ?? $recording->recording_notes,
            ]);

            // Log workflow
            try {
                $workflowService = app(\App\Services\WorkflowStateService::class);
                $workflowService->updateWorkflowState(
                    $recording->episode,
                    'sound_engineering',
                    'vocal_recording',
                    $user->id,
                    "Tim Rekam Vokal ({$user->name}) menyelesaikan rekaman vokal",
                    $user->id,
                    ['action' => 'vocal_recording_completed', 'file_link' => $recordingLink]
                );
            } catch (\Throwable $e) {
                Log::warning('VocalRecording completeWork: workflow state update failed', [
                    'recording_id' => $recording->id, 'error' => $e->getMessage()
                ]);
            }

            // Auto-create Sound Engineer Editing task
            $existingEditing = SoundEngineerEditing::where('episode_id', $recording->episode_id)
                ->where('sound_engineer_recording_id', $recording->id)
                ->first();

            $episode = $recording->episode;
            $program = $episode ? $episode->program : null;
            $productionTeam = ($program && $program->productionTeam) ? $program->productionTeam : null;

            if (!$existingEditing) {
                // Find Sound Engineer for this episode
                $assignedSoundEngId = null;
                if ($productionTeam) {
                    $seMember = $productionTeam->members()
                        ->whereIn('role', ['sound_eng', 'sound_engineer'])
                        ->where('is_active', true)
                        ->first();
                    if ($seMember) $assignedSoundEngId = $seMember->user_id;
                }

                $editing = SoundEngineerEditing::create([
                    'episode_id' => $recording->episode_id,
                    'sound_engineer_recording_id' => $recording->id,
                    'sound_engineer_id' => $assignedSoundEngId,
                    'vocal_file_path' => $recording->file_path ?? null,
                    'vocal_file_link' => $recordingLink,
                    'editing_notes' => "Recording oleh Tim Rekam Vokal: {$user->name}. Notes: " . ($request->recording_notes ?? 'N/A'),
                    'status' => 'in_progress',
                    'created_by' => $user->id,
                ]);

                // Notify Producer
                if ($productionTeam && $productionTeam->producer_id) {
                    Notification::create([
                        'user_id' => $productionTeam->producer_id,
                        'type' => 'sound_engineer_recording_completed',
                        'title' => 'Rekaman Vokal Selesai',
                        'message' => "Tim Rekam Vokal ({$user->name}) telah menyelesaikan rekaman vokal Episode {$episode->episode_number}.",
                        'data' => [
                            'recording_id' => $recording->id,
                            'editing_id' => $editing->id,
                            'episode_id' => $recording->episode_id,
                        ],
                    ]);
                }

                // Notify Sound Engineer to start editing
                if ($assignedSoundEngId && $assignedSoundEngId !== $user->id) {
                    Notification::create([
                        'user_id' => $assignedSoundEngId,
                        'type' => 'vocal_editing_task_created',
                        'title' => 'Tugas Edit Vokal Baru',
                        'message' => "Rekaman vokal Episode {$episode->episode_number} telah selesai oleh Tim Rekam Vokal. Silakan mulai proses editing.",
                        'data' => [
                            'recording_id' => $recording->id,
                            'editing_id' => $editing->id,
                            'episode_id' => $recording->episode_id,
                        ],
                    ]);
                }

                // Try to advance workflow state
                try {
                    if ($episode && in_array($episode->current_workflow_state, ['production', 'production_planning', 'shooting_recording'])) {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $episode, 'sound_engineering', 'sound_eng', null,
                            'Vocal recording completed, proceeding to editing'
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('VocalRecording completeWork: workflow advance failed', ['error' => $e->getMessage()]);
                }
            }

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $recording->load(['episode', 'musicArrangement', 'createdBy']),
                'message' => 'Rekaman vokal selesai. Link file telah dikirim ke Sound Engineer untuk proses editing.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─── Statistics ───────────────────────────────────────────────

    /**
     * GET /api/live-tv/vocal-recording/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->hasRecordingTeamAccess($user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Get episode IDs where user is in recording team
            $isOversight = MusicProgramAuthorization::hasProducerAccess($user) || ProgramManagerAuthorization::isProgramManager($user);

            $query = SoundEngineerRecording::query();

            if (!$isOversight) {
                $episodeIds = ProductionTeamAssignment::where('team_type', 'recording')
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('members', fn($q) => $q->where('user_id', $user->id)->where('is_active', true))
                    ->pluck('episode_id');

                $query->where(fn($q) => $q->whereIn('episode_id', $episodeIds)->orWhere('created_by', $user->id));
            }

            // Get incoming handovers for this user
            $incomingHandovers = ProductionEquipmentTransfer::with(['fromEpisode.program', 'productionEquipment'])
                ->where('to_user_id', $user->id)
                ->where('status', 'pending_accept')
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'from_user_name' => $t->transferredByUser->name ?? 'User',
                        'from_program' => $t->fromEpisode?->program?->name ?? 'Program',
                        'from_episode' => $t->fromEpisode?->episode_number ?? $t->from_episode_id,
                        'equipment_list' => is_array($t->productionEquipment?->equipment_list) 
                            ? $t->productionEquipment->equipment_list 
                            : (json_decode($t->productionEquipment?->equipment_list, true) ?? []),
                        'notes' => $t->notes,
                        'transferred_at' => $t->transferred_at?->toIso8601String()
                    ];
                });

            $statistics = [
                'total' => (clone $query)->count(),
                'draft' => (clone $query)->where('status', 'draft')->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
                'recording' => (clone $query)->where('status', 'recording')->count(),
                'ready' => (clone $query)->where('status', 'ready')->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
                'reviewed' => (clone $query)->where('status', 'reviewed')->count(),
                'incoming_handovers' => $incomingHandovers
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Vocal recording statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
