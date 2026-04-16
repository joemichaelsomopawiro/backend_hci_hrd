<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrProduksiWork;
use App\Models\PrEpisode;
use App\Models\ProductionEquipment;
use App\Models\ShootingRunSheet;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryItem;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Constants\Role;
use App\Models\PrEditorWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrEditorPromosiWork;
use App\Services\PrWorkflowService;
use App\Services\PrActivityLogService;
use App\Services\PrNotificationService;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrCreativeWork;
use App\Models\PrEpisodeCrew;
use App\Models\PrWorkflowStep;
use Illuminate\Support\Facades\Log;

class PrProduksiController extends Controller
{
    protected $activityLogService;
    protected $syncService;

    public function __construct(PrActivityLogService $activityLogService, \App\Services\PrProductionSyncService $syncService)
    {
        $this->activityLogService = $activityLogService;
        $this->syncService = $syncService;
    }
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            // AUTO-SYNC: Ensure all episodes with completed Step 4 have a Produksi Work
            // This matches the same logic in PrPromosiController
            $eligibleEpisodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
                ->where('status', 'completed')
                ->pluck('episode_id')->toArray();

            if (!empty($eligibleEpisodes)) {
                $existingWorks = PrProduksiWork::whereIn('pr_episode_id', $eligibleEpisodes)
                    ->pluck('pr_episode_id')->toArray();

                $missingEpisodes = array_diff($eligibleEpisodes, $existingWorks);

                foreach ($missingEpisodes as $episodeId) {
                    try {
                        // Get creative work to link if available
                        $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        PrProduksiWork::create([
                            'pr_episode_id' => $episodeId,
                            'pr_creative_work_id' => $creativeWork ? $creativeWork->id : null,
                            'status' => 'pending',
                            'created_by' => $user->id,
                            'shooting_notes' => 'Auto-created from dashboard sync'
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Auto-sync failed for episode $episodeId: " . $e->getMessage());
                    }
                }
            }

            $query = PrProduksiWork::with([
                'episode.program',
                'episode.workflowProgress', // Add this to check step 4 for locking logic
                'episode.files', // Load files for the episode (scripts etc)
                'episode.creativeWork', // Load the fallback creative work data from the episode
                'episode.crews.user', // Load episode crew assignments with coordinator flag
                'creativeWork',
                'createdBy',
                'equipmentLoans.loanItems.inventoryItem',
                'equipmentLoans.produksiWorks.episode',
                'editorWork'
            ]);

            // Check if user is a producer (sees all), or has access as a crew member
            $userRoleStr = strtolower($user->role ?? '');
            $isProducer = in_array($userRoleStr, ['producer']) || Role::inArray($user->role, [Role::PRODUCER]);
            $isProgramManager = Role::inArray($user->role, [Role::PROGRAM_MANAGER]);
            $isProductionStaff = in_array($userRoleStr, ['produksi', 'production']) || Role::inArray($user->role, [Role::PRODUCTION]);

            // Check if user is assigned as crew member (shooting_team or setting_team) in any episode
            $isAssignedCrew = \App\Models\PrEpisodeCrew::where('user_id', $user->id)->exists();

            // Auth: allow all authenticated users
            // (non-assigned users will simply get an empty list via the filter below)

            // bundle_mode=1: skip crew filter, return ALL non-completed works
            // (used by equipment loan bundling to let coordinators pick any episode)
            $bundleMode = $request->boolean('bundle_mode');

            if ($bundleMode) {
                $query->whereNotIn('status', ['completed']);
            } elseif (!$isProgramManager && !Role::inArray($user->role, [Role::DISTRIBUTION_MANAGER])) {
                // STRICT AUTHORIZATION: Filter to episodes where the user is assigned or is the producer
                $query->where(function ($q) use ($user) {
                    // 1. If Producer of the program
                    $q->whereHas('episode.program', function ($pq) use ($user) {
                        $pq->where('producer_id', $user->id);
                    });
                    
                    // 2. If in program crew
                    $q->orWhereHas('episode.program.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });

                    // 3. If in episode crew
                    $q->orWhereHas('episode.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });
                });
            }

            // ONLY show works if the episode has completed Step 4
            $query->whereHas('episode.workflowProgress', function ($q) {
                $q->where('workflow_step', 4)
                    ->where('status', 'completed');
            });

            if ($request->has('status') && !empty($request->status) && !$bundleMode) {

                $query->where('status', $request->status);
            }

            if ($request->has('program_id') && !empty($request->program_id)) {
                $query->whereHas('episode', function ($q) use ($request) {
                    $q->where('program_id', $request->program_id);
                });
            }

            $perPage = min((int) ($request->per_page ?? 15), 500);
            $works = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Produksi works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all episodes eligible for equipment bundling.
     * Queries PrEpisode directly (not PrProduksiWork) so episodes without
     * an existing produksi work record are also included.
     * Auto-creates a PrProduksiWork for any episode that doesn't have one yet.
     */
    public function getBundleEpisodes(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            // Exclude the current work's episode if provided
            $excludeWorkId = (int) ($request->exclude_work_id ?? 0);
            $excludeEpisodeId = null;
            if ($excludeWorkId) {
                $excludeEpisodeId = PrProduksiWork::find($excludeWorkId)?->pr_episode_id;
            }

            $query = \App\Models\PrProduksiWork::with(['episode.program', 'equipmentLoans', 'episode.creativeWork'])
                ->where('status', '!=', 'completed')
                ->whereHas('episode', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->whereHas('episode.workflowProgress', function ($q) {
                    $q->where('workflow_step', 4)->where('status', 'completed');
                });

            if ($excludeEpisodeId) {
                $query->where('pr_episode_id', '!=', $excludeEpisodeId);
            }

            $works = $query->get();

            // Format response to match previous structure
            $results = $works->map(function ($work) {
                return [
                    'id' => $work->id,
                    'status' => $work->status,
                    'pr_episode_id' => $work->pr_episode_id,
                    'episode' => [
                        'id' => $work->episode->id,
                        'episode_number' => $work->episode->episode_number,
                        'title' => $work->episode->title,
                        'program_id' => $work->episode->program_id,
                        'program' => $work->episode->program ? ['id' => $work->episode->program->id, 'name' => $work->episode->program->name] : null,
                    ],
                    'equipment_loans' => $work->equipmentLoans ?? [],
                ];
            });

            return response()->json(['success' => true, 'data' => $results]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = PrProduksiWork::with('episode.program')->findOrFail($id);

            // Check if user is authorized to work on this program
            if (!$this->checkWorkAuthorization($work, $user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. You are not assigned to this program.'], 403);
            }

            if ($work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when pending'], 400);
            }

            $work->acceptWork($user->id);
            
            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'accept_production_work',
                "Production work accepted by {$user->name}",
                ['step' => 5, 'work_id' => $work->id]
            );

            // Notify crew members that production work has been accepted
            $notificationService = app(\App\Services\PrNotificationService::class);
            $notificationService->notifyWorkflowStepReady($work->pr_episode_id, 5);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = PrProduksiWork::with('episode.program')->findOrFail($id);

            if (!$this->checkWorkAuthorization($work, $user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. You are not assigned to this program.'], 403);
            }

            // Relaxed ownership check: Allow any Production user or Coordinator to update.
            // If we want to track who updated, we could add updated_by field later.
            // For now, if user has role 'Production', they can edit.

            if ($work->status === 'pending' && $work->created_by !== $user->id) {
                // Optional: Take ownership if pending? Or just allow edit.
                // $work->created_by = $user->id; 
                // $work->save();
            }

            // Check if user is trying to update shooting results but still has unreturned equipment
            $isUpdatingResults = $request->has('shooting_file_links') || 
                               $request->has('shooting_files') || 
                               $request->has('shooting_notes');

            if ($isUpdatingResults && $this->hasUnreturnedEquipment($work)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Harap kembalikan semua alat yang dipinjam ke Art & Set Properti sebelum mengunggah hasil syuting dan catatan.'
                ], 403);
            }

            $work->update($request->all());

            // Check if shooting files are uploaded, if so, maybe trigger next step logic?
            // Original `uploadShootingResults` triggered PrEditorWork creation.
            // We should replicate that check here if it's a completion update.

            if ($request->has('shooting_file_links') && !empty($request->shooting_file_links) && $work->status !== 'completed') {
                $work->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by' => $user->id
                ]);
                $workflowStep = PrWorkflowStep::where('pr_episode_id', $work->pr_episode_id)
                    ->where('step_number', 5)
                    ->first();

                if ($workflowStep && !$workflowStep->is_completed) {
                    $workflowStep->markAsCompleted($user->id, 'Produksi completed with links');
                }

                // Also update the KPI-relevant PrEpisodeWorkflowProgress model
                $workflowProgress = PrEpisodeWorkflowProgress::where('episode_id', $work->pr_episode_id)
                    ->where('workflow_step', 5)
                    ->first();

                if ($workflowProgress && $workflowProgress->status !== 'completed') {
                    $workflowProgress->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'assigned_user_id' => $user->id
                    ]);
                }

                // Log activity
                $this->activityLogService->logEpisodeActivity(
                    $work->episode,
                    'upload_shooting_results',
                    "Shooting results submitted via links.",
                    ['step' => 5, 'work_id' => $work->id]
                );

                // Notify Editor team that shooting is complete and editing can start
                $notificationService = app(\App\Services\PrNotificationService::class);
                $notificationService->notifyWorkflowStepReady($work->pr_episode_id, 6); // Step 6 is Editing
            }

            // Always ensure Editor work is ready when Production work is updated
            // This handles the case where revision was requested and Production is resubmitting
            $this->ensureEditorWorkReady($work->pr_episode_id, $user->id);

            // SYNC: Propagate updates to any sibling episodes sharing the same equipment loan
            $this->syncService->syncBundledWorks($work);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Production work updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $work = PrProduksiWork::with(['episode.program', 'episode.crews'])->findOrFail($id);

            if (!$this->checkWorkAuthorization($work, $user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. You are not assigned to this program.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.inventory_item_id' => 'required|exists:inventory_items,id',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                // Optional: additional episode IDs to bundle into the same loan
                'additional_produksi_work_ids' => 'nullable|array',
                'additional_produksi_work_ids.*' => 'integer|exists:pr_produksi_works,id',
                'request_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            // Primary work (the episode that triggered the request)
            $primaryWork = PrProduksiWork::findOrFail($id);

            // Collect ALL produksi work IDs (primary + additional)
            $additionalIds = $request->additional_produksi_work_ids ?? [];
            $allWorkIds = array_unique(array_merge([$primaryWork->id], $additionalIds));

            // Fetch all involved works
            $allWorks = PrProduksiWork::whereIn('id', $allWorkIds)->get();

            // Verify none of the works already has a pending/approved loan
            foreach ($allWorks as $work) {
                $activeLoan = $work->equipmentLoans()
                    ->whereIn('status', ['pending', 'approved', 'active'])
                    ->first();

                if ($activeLoan) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Episode #{$work->pr_episode_id} sudah memiliki permintaan alat yang sedang aktif (ID Loan: {$activeLoan->id}). Harap selesaikan atau batalkan terlebih dahulu."
                    ], 400);
                }
            }

            // Take ownership of primary work
            if ($primaryWork->created_by !== $user->id) {
                $primaryWork->created_by = $user->id;
            }
            $primaryWork->equipment_list = $request->equipment_list;
            $primaryWork->status = 'equipment_requested';
            $primaryWork->save();

            // Create the shared Equipment Loan (no direct work FK anymore)
            $loan = EquipmentLoan::create([
                'borrower_id' => $user->id,
                'status' => 'pending',
                'request_notes' => $request->request_notes ?? null,
            ]);

            // Attach loan items
            foreach ($request->equipment_list as $item) {
                EquipmentLoanItem::create([
                    'equipment_loan_id' => $loan->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Associate ALL works to this loan via pivot and sync their status/equipment list
            $pivotIds = [];
            foreach ($allWorks as $work) {
                $pivotIds[$work->id] = [
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Sync equipment_list and status on each linked episode
                if ($work->id !== $primaryWork->id) {
                    $work->equipment_list = $request->equipment_list;
                    $work->status = 'equipment_requested';
                    $work->save();
                }
            }

            $loan->produksiWorks()->sync($pivotIds);

            // Log activity for EACH episode involved
            foreach ($allWorks as $w) {
                $this->activityLogService->logEpisodeActivity(
                    $w->episode,
                    'equipment_request',
                    "Equipment requested for production (Loan ID: {$loan->id}). Items: " . count($request->equipment_list),
                    ['step' => 5, 'loan_id' => $loan->id]
                );
            }

            // ── Auto-sync crew from primary episode to each bundled episode ──
            // So the bundled episodes appear in every crew member's work list and teams stay identical.
            foreach ($allWorks as $work) {
                if ($work->id !== $primaryWork->id) {
                    $this->syncService->syncCrews($primaryWork, $work);
                }
            }

            // Notify Art & Set staff
            $notificationService = app(\App\Services\PrNotificationService::class);
            $notificationService->notifyArtSetLoanRequested($loan);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $primaryWork->fresh(['equipmentLoans.loanItems']),
                'message' => 'Equipment requested successfully for ' . count($allWorkIds) . ' episode(s)',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }



    /**
     * GET /api/pr/produksi/available-equipment
     * Get list of equipment with availability status
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::PRODUCTION, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $equipment = InventoryItem::where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'photo_url' => $item->photo_url,
                        'total_quantity' => $item->total_quantity,
                        'available_quantity' => $item->available_quantity,
                        'is_available' => $item->available_quantity > 0,
                        'status' => $item->status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Available equipment retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/pr/produksi/works/{id}/complete
     * Mark production work as completed and auto-complete Step 5
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $work = PrProduksiWork::with('episode')->findOrFail($id);

            $isStaff = Role::inArray($user->role, [Role::PRODUCTION, Role::PROGRAM_MANAGER, Role::PRODUCER]);
            $isCoordinator = false;

            if (!$isStaff && $user) {
                $crew = \App\Models\PrEpisodeCrew::where('episode_id', $work->pr_episode_id)
                    ->where('user_id', $user->id)
                    ->where('is_coordinator', true)
                    ->first();
                if ($crew) {
                    $isCoordinator = true;
                }
            }

            if (!$user || (!$isStaff && !$isCoordinator)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. Only coordinators or production staff can complete.'], 403);
            }

            // Lock: Cannot complete work if equipment is not returned
            if ($this->hasUnreturnedEquipment($work)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Harap kembalikan semua alat yang dipinjam ke Art & Set Properti sebelum menyelesaikan produksi.'
                ], 403);
            }

            // Validate that shooting files have been uploaded
            if (empty($work->shooting_file_links)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete work without uploading shooting files'
                ], 422);
            }

            // Mark work as completed
            $work->completeWork($user->id, $request->input('completion_notes'));

            // Log Final Production Completion
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'production_completed',
                "Production phase completed and shots submitted to editing.",
                ['step' => 5, 'work_id' => $work->id]
            );

            // Check if both production and promotion are complete, then
            if ($work->status === 'completed') {
                app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);
                
                // Notify Editor team that production is complete and editing can start
                $notificationService = app(\App\Services\PrNotificationService::class);
                $notificationService->notifyWorkflowStepReady($work->pr_episode_id, 6); // Step 6 is Editing
            }
            // Auto-create PrEditorWork for next step
            $this->ensureEditorWorkReady($work->pr_episode_id, $user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'equipmentLoan']),
                'message' => 'Production work completed successfully.',
                'workflow_updated' => true
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to ensure Editor work is ready for editing/review.
     * If status is 'revision_requested', reset to 'revised'.
     * If not exists, create as 'draft'.
     */
    public function ensureEditorWorkReady($episodeId, $userId)
    {
        $editorWork = \App\Models\PrEditorWork::where('pr_episode_id', $episodeId)->first();

        if ($editorWork) {
            // If the work was revision requested, we move it to 'revised' status
            if ($editorWork->status === 'revision_requested') {
                $editorWork->update([
                    'status' => 'revised', // Set to 'revised' to distinguish from fresh 'draft' work
                    'files_complete' => true, // Assuming now it is complete as per production submission
                    'file_notes' => null
                ]);
            }
        } else {
            // Auto-create PrEditorWork if not exists
            \App\Models\PrEditorWork::firstOrCreate(
                ['pr_episode_id' => $episodeId, 'work_type' => 'main_episode'],
                ['status' => 'draft', 'created_by' => $userId]
            );
        }
    }


    /**
     * POST /api/pr/produksi/works/{id}/attendance
     * Submit attendance data (only Coordinator can do this)
     */
    public function submitAttendance(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401);
            }

            $work = PrProduksiWork::with('episode.crews')->findOrFail($id);

            // Check coordinator status
            $crew = $work->episode->crews->where('user_id', $user->id)->first();
            if (!$crew || !$crew->is_coordinator) {
                if (!Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                    return response()->json(['success' => false, 'message' => 'Hanya koordinator yang dapat mengisi absen.'], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'attendances' => 'required|array',
                'attendances.*.user_id' => 'required|integer|exists:users,id',
                'attendances.*.status' => 'required|in:hadir,telat,tidak_hadir',
                // clock_in wajib diisi untuk status hadir atau telat
                'attendances.*.clock_in' => 'required_if:attendances.*.status,hadir,telat|nullable|string',
            ], [
                'attendances.*.clock_in.required_if' => 'Jam masuk wajib diisi untuk anggota yang hadir atau telat.',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $existing = $work->crew_attendances ?? [];
            foreach ($request->attendances as $att) {
                $existing[$att['user_id']] = [
                    'clock_in' => $att['clock_in'] ?? null,
                    'status' => $att['status'],
                    'recorded_by' => $user->id,
                    'recorded_at' => now()->toISOString(),
                ];
            }

            $work->update(['crew_attendances' => $existing]);

            // SYNC: Propagate attendance to siblings
            $this->syncService->syncBundledWorks($work);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Absen berhasil disimpan.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/pr/produksi/works/{id}/request-return
     * Request equipment return (only Coordinator can do this)
     */
    public function requestReturn(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401);
            }

            $work = PrProduksiWork::with(['episode.crews', 'equipmentLoans'])->findOrFail($id);

            // Check coordinator status
            $crew = $work->episode->crews->where('user_id', $user->id)->first();
            if (!$crew || !$crew->is_coordinator) {
                if (!Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                    return response()->json(['success' => false, 'message' => 'Hanya koordinator yang dapat mengajukan pengembalian barang.'], 403);
                }
            }

            // Find the active loan (approved or active)
            $activeLoan = $work->equipmentLoans
                ->whereIn('status', ['approved', 'active'])
                ->first();

            if (!$activeLoan) {
                return response()->json(['success' => false, 'message' => 'Tidak ada peminjaman alat yang aktif untuk episode ini.'], 400);
            }

            $activeLoan->update([
                'status' => 'return_requested',
                'return_notes' => $request->input('return_notes'),
            ]);

            // Notify Art & Set staff
            $notificationService = app(\App\Services\PrNotificationService::class);
            $notificationService->notifyArtSetReturnRequested($activeLoan);

            DB::commit();
            return response()->json(['success' => true, 'data' => $activeLoan->fresh(), 'message' => 'Permintaan pengembalian barang berhasil diajukan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/pr/produksi/works/{id}/cancel-loan
     * Cancel a pending equipment loan (only coordinator or producer/program manager)
     */
    public function cancelLoan(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401);
            }

            $work = PrProduksiWork::with(['episode.crews', 'equipmentLoans.loanItems'])->findOrFail($id);

            // Check coordinator status
            $crew = $work->episode->crews->where('user_id', $user->id)->first();
            if (!$crew || !$crew->is_coordinator) {
                if (!Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                    return response()->json(['success' => false, 'message' => 'Hanya koordinator yang dapat membatalkan peminjaman alat.'], 403);
                }
            }

            // Only cancel loans that are pending or return_requested (not active/completed)
            $cancelableLoan = $work->equipmentLoans
                ->whereIn('status', ['pending', 'return_requested'])
                ->first();

            if (!$cancelableLoan) {
                return response()->json(['success' => false, 'message' => 'Tidak ada peminjaman yang bisa dibatalkan. Hanya status Menunggu Persetujuan yang bisa dibatalkan.'], 400);
            }

            // Restore stock for each item in the loan
            foreach ($cancelableLoan->loanItems as $loanItem) {
                \App\Models\InventoryItem::where('id', $loanItem->inventory_item_id)
                    ->increment('available_quantity', $loanItem->quantity);
            }

            $cancelableLoan->update(['status' => 'cancelled']);

            DB::commit();
            return response()->json(['success' => true, 'data' => $work->fresh(['equipmentLoans']), 'message' => 'Peminjaman alat berhasil dibatalkan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if the production work has any equipment loans that are not yet returned or cancelled.
     */
    private function hasUnreturnedEquipment(PrProduksiWork $work): bool
    {
        return $work->equipmentLoans()
            ->whereNotIn('status', ['returned', 'cancelled', 'completed'])
            ->exists();
    }

    /**
     * Helper to check if user is authorized to perform actions on a Produksi Work
     */
    private function checkWorkAuthorization($work, $user): bool
    {
        if (!$user) return false;

        // 1. Administrative roles
        if (Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER])) {
            return true;
        }

        // 2. Producer (Only their own programs)
        if (Role::inArray($user->role, [Role::PRODUCER])) {
            return $work->episode && $work->episode->program && $work->episode->program->producer_id === $user->id;
        }

        // 3. Program Crew Assignment
        $isCrew = \App\Models\PrProgramCrew::where('user_id', $user->id)
            ->where('program_id', $work->episode->program_id)
            ->exists();
        if ($isCrew) return true;

        // 4. Episode Crew Assignment
        $isEpisodeCrew = \App\Models\PrEpisodeCrew::where('user_id', $user->id)
            ->where('episode_id', $work->pr_episode_id)
            ->exists();
        if ($isEpisodeCrew) return true;

        return false;
    }
}
