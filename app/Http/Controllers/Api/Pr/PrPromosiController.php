<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrPromotionWork;
use App\Models\Notification;
use App\Constants\WorkflowStep;
use App\Services\PrWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\PrActivityLogService;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\PrEpisode;
use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;
use App\Models\PrDesignGrafisWork;
use App\Constants\Role;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use Illuminate\Support\Facades\DB;
use App\Services\PrNotificationService;

class PrPromosiController extends Controller
{
    protected $activityLogService;

    public function __construct(PrActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // AUTO-SYNC & SELF-HEALING: Ensure all episodes with completed Step 4 have a Promotion Work
            // We use a more careful approach to avoid overwriting existing status/data
            $eligibleEpisodes = PrEpisodeWorkflowProgress::where('workflow_step', 4)
                ->where('status', 'completed')
                ->pluck('episode_id')
                ->unique();

            foreach ($eligibleEpisodes as $episodeId) {
                try {
                    $creativeWork = PrCreativeWork::where('pr_episode_id', $episodeId)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $work = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

                    if (!$work) {
                        PrPromotionWork::create([
                            'pr_episode_id' => $episodeId,
                            'work_type' => 'bts_video',
                            'status' => 'planning',
                            'created_by' => $creativeWork ? $creativeWork->created_by : $user->id,
                            'shooting_date' => $creativeWork ? $creativeWork->shooting_schedule : null,
                            'shooting_notes' => 'Auto-synced from Stage 4 completion'
                        ]);
                    } else {
                        // RECOVERY & UPDATE: If record exists, only update metadata if missing, and RECOVER status if lost
                        $updateData = [];

                        if (!$work->created_by && $creativeWork) {
                            $updateData['created_by'] = $creativeWork->created_by;
                        }

                        // ALWAYS SYNC: If shooting_date or shooting_time differs from creativeWork, update it
                        if ($creativeWork) {
                            $cwDate = $creativeWork->shooting_schedule ? date('Y-m-d', strtotime($creativeWork->shooting_schedule)) : null;
                            $cwTime = $creativeWork->shooting_schedule ? date('H:i:s', strtotime($creativeWork->shooting_schedule)) : null;

                            $pwDate = $work->shooting_date ? $work->shooting_date->format('Y-m-d') : null;
                            $pwTime = $work->shooting_time;

                            if ($cwDate !== $pwDate) {
                                $updateData['shooting_date'] = $cwDate;
                            }
                            if ($cwTime !== $pwTime) {
                                $updateData['shooting_time'] = $cwTime;
                            }
                            if ($creativeWork->shooting_location !== $work->location_data) {
                                $updateData['location_data'] = $creativeWork->shooting_location;
                            }
                        }

                        // RECOVERY: If status was reset to 'planning' but it was actually finished
                        $isFinished = ($work->episode && $work->episode->status === 'promoted') || !empty($work->sharing_proof);
                        if ($isFinished && $work->status === 'planning') {
                            $updateData['status'] = 'completed';
                            if (!$work->completed_at) {
                                $updateData['completed_at'] = now();
                            }
                        }

                        if (!empty($updateData)) {
                            $work->update($updateData);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Sync failed for episode $episodeId: " . $e->getMessage());
                }
            }


            // MAIN QUERY: Only show works that have passed Step 4 (Budget Approval)
            $query = PrPromotionWork::with(['episode.program', 'episode.creativeWork', 'createdBy'])
                ->whereHas('episode.workflowProgress', function ($q) {
                    $q->where('workflow_step', 4)->where('status', 'completed');
                });

            // STRICT AUTHORIZATION: Only allow assigned personnel to see tasks
            $isManager = Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER]);
            
            if (!$isManager) {
                $query->where(function ($q) use ($user) {
                    // 1. If Producer of the program
                    $q->whereHas('episode.program', function ($pq) use ($user) {
                        $pq->where('producer_id', $user->id);
                    });
                    
                    // 2. If in program crew (Matches most Promotion staff)
                    $q->orWhereHas('episode.program.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });

                    // 3. If in episode crew (Matches episode-specific helpers)
                    $q->orWhereHas('episode.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });
                });
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('work_type') && $request->work_type !== '') {
                $query->where('work_type', $request->work_type);
            }

            // Apply program filter if exists
            if ($request->has('program_id') && $request->program_id !== '') {
                $query->whereHas('episode', function ($q) use ($request) {
                    $q->where('program_id', $request->program_id);
                });
            }

            $works = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Promotion works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id)
    {
        try {
            $user = Auth::user();
            $work = PrPromotionWork::with('episode.program')->findOrFail($id);

            if (!$this->checkWorkAuthorization($work, $user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. You are not assigned to this program.'], 403);
            }

            if ($work->status !== 'planning') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when planning'], 400);
            }

            $work->update([
                'status' => 'shooting',
                'created_by' => $user->id
            ]);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'accept_promotion_work',
                "Promotion work accepted by {$user->name}",
                ['step' => 5, 'work_id' => $work->id]
            );

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function uploadContent(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = PrPromotionWork::with(['episode.program', 'equipmentLoans'])->findOrFail($id);

            if (!$this->checkWorkAuthorization($work, $user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. You are not assigned to this program.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_paths' => 'required|array|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            // Check if equipment is returned (mirror production logic)
            $unreturnedLoan = $work->equipmentLoans()
                ->whereIn('status', ['pending', 'approved', 'active', 'return_requested'])
                ->first();

            if ($unreturnedLoan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content cannot be uploaded because there are unreturned items (Loan ID: ' . $unreturnedLoan->id . ').'
                ], 400);
            }

            $work->update([
                'file_paths' => $request->file_paths,
                'status' => 'completed'
            ]);

            // Sync Step 5 progress (Shooting phase transition to Editing/Design)
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'upload_promotion_content',
                "Promotion content/files uploaded by {$user->name}",
                ['step' => 5, 'work_id' => $work->id]
            );

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Content uploaded successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function shareContent(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'platform' => 'required|in:facebook,whatsapp,instagram',
                'proof_url' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrPromotionWork::with('equipmentLoans')->findOrFail($id);

            if ($work->created_by !== $user->id && !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Check if equipment is returned (mirror production logic)
            $unreturnedLoan = $work->equipmentLoans()
                ->whereIn('status', ['pending', 'approved', 'active', 'return_requested'])
                ->first();

            if ($unreturnedLoan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content cannot be shared because there are unreturned items (Loan ID: ' . $unreturnedLoan->id . ').'
                ], 400);
            }

            $sharingProof = $work->sharing_proof ?? [];
            $sharingProof[] = [
                'platform' => $request->platform,
                'proof_url' => $request->proof_url,
                'shared_at' => now()->toDateTimeString()
            ];

            $work->update([
                'sharing_proof' => $sharingProof,
                'status' => 'completed'
            ]);

            // Sync Step 5 progress (Shooting phase transition to Editing/Design)
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            // Sync Step 10 progress
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 10);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'share_promotion_content',
                "Promotion content shared on {$request->platform} by {$user->name}",
                ['step' => 5, 'work_id' => $work->id]
            );

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Content shared successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            // Find work with relationships
            $work = PrPromotionWork::with([
                'episode.program', 
                'episode.creativeWork', 
                'createdBy',
                'equipmentLoans.loanItems.inventoryItem'
            ])->find($id);

            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $work
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:planning,shooting,editing,sharing,completed',
                'work_type' => 'sometimes|string',
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable',
                'location_data' => 'nullable',
                'shooting_notes' => 'nullable|string',
                'title' => 'nullable|string',
                'description' => 'nullable|string',
                'completion_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = $request->only([
                'status',
                'work_type',
                'shooting_date',
                'shooting_time',
                'location_data',
                'shooting_notes',
                'title',
                'description',
                'completion_notes'
            ]);

            // Handle file_paths - model has array cast, so just pass the array
            if ($request->has('file_paths')) {
                $data['file_paths'] = $request->file_paths;
            }

            // Handle location_data - model has array cast
            if ($request->has('location_data')) {
                $data['location_data'] = $request->location_data;
            }

            DB::beginTransaction();
            $work->update($data);

            $work->update($data);

            // Sync Step 5 if completed
            if ($work->status === 'completed') {
                app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work updated successfully.',
                'data' => $work->fresh(['episode', 'episode.program', 'equipmentLoans.loanItems.inventoryItem'])
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function complete(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::with('equipmentLoans')->find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            // Check if equipment is returned (mirror production logic)
            $unreturnedLoan = $work->equipmentLoans()
                ->whereIn('status', ['pending', 'approved', 'active', 'return_requested'])
                ->first();

            if ($unreturnedLoan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promotion work cannot be completed because there are unreturned items (Loan ID: ' . $unreturnedLoan->id . ').'
                ], 400);
            }

            $work->update([
                'status' => 'completed',
                'completion_notes' => $request->input('completion_notes', $request->input('notes', $work->completion_notes)),
                'file_paths' => $request->input('file_paths', $work->file_paths)
            ]);

            Log::info("Promotion Work [{$id}] completed. Syncing Step 5 for Episode [{$work->pr_episode_id}]");
            app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'complete_promotion_work',
                "Promotion work fully completed by {$user->name}",
                ['step' => 5, 'work_id' => $work->id]
            );

            // AUTO-CREATE PrEditorPromosiWork when Promotion completes
            $exists = PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->exists();

            if (!$exists) {
                // Find the main_episode editor work (may or may not be ready)
                $mainEditorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                    ->where('work_type', 'main_episode')
                    ->first();

                $editorReady = $mainEditorWork && in_array($mainEditorWork->status, ['pending_qc', 'completed']);

                PrEditorPromosiWork::create([
                    'pr_episode_id' => $work->pr_episode_id,
                    'pr_promotion_work_id' => $work->id,
                    'pr_editor_work_id' => $mainEditorWork ? $mainEditorWork->id : null,
                    'status' => $editorReady ? 'pending' : 'waiting_editor',
                ]);

                Log::info("Auto-created PrEditorPromosiWork for Episode [{$work->pr_episode_id}], status: " . ($editorReady ? 'pending' : 'waiting_editor'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Work marked as completed.',
                'data' => $work,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function cancelComplete(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrPromotionWork::with('episode')->find($id);
            if (!$work) {
                return response()->json(['success' => false, 'message' => 'Work not found.'], 404);
            }

            if ($work->status !== 'completed') {
                return response()->json(['success' => false, 'message' => 'Only completed works can be cancelled.'], 400);
            }

            // Check if the downstream Editor Promotion work has been started
            $editorPromo = PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($editorPromo && !in_array($editorPromo->status, ['pending', 'waiting_editor'])) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Cannot cancel submission because the Editor has already started working on the Promotion Promotion Editor task (Status: ' . $editorPromo->status . ').'
                ], 400);
            }

            DB::beginTransaction();

            // Revert status to shooting (editable state)
            $work->update([
                'status' => 'shooting',
                'completed_at' => null
            ]);

            // Delete the downstream task if it exists and hasn't started
            if ($editorPromo) {
                $editorPromo->delete();
                Log::info("Deleted PrEditorPromosiWork for Episode [{$work->pr_episode_id}] due to cancellation");
            }

            // Sync workflow back to "uncompleted" state for step 5
            app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 5);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'cancel_promotion_submission',
                "Promotion submission cancelled by {$user->name}. Reverted to editable state.",
                ['step' => 5, 'work_id' => $work->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Submission cancelled successfully and work has been reverted to editable state.',
                'data' => $work->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401);
            }

            // Allow staff roles OR any crew coordinator for this episode
            $isStaff = Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::PRODUCER]);
            if (!$isStaff) {
                // Check if user is a coordinator on this specific work's episode
                $work = PrPromotionWork::with('episode.crews')->find($id);
                $crew = $work?->episode?->crews?->where('user_id', $user->id)->first();
                if (!$crew || !$crew->is_coordinator) {
                    return response()->json(['success' => false, 'message' => 'Only coordinators or promotion staff can request equipment loans.'], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.inventory_item_id' => 'required|exists:inventory_items,id',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'request_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrPromotionWork::findOrFail($id);

            // Verify the work doesn't already have a pending/approved loan
            $activeLoan = $work->equipmentLoans()
                ->whereIn('status', ['pending', 'approved', 'active'])
                ->first();

            if ($activeLoan) {
                return response()->json([
                    'success' => false,
                    'message' => "This promotion work already has an active equipment request (Loan ID: {$activeLoan->id}). Please complete or cancel it first."
                ], 400);
            }

            // Update work data
            $work->equipment_list = $request->equipment_list;
            $work->save();

            // Create the Equipment Loan
            $loan = EquipmentLoan::create([
                'borrower_id' => $user->id,
                'status' => 'pending',
                'request_notes' => $request->request_notes ?? 'Promotion equipment request',
            ]);

            // Associate work to this loan
            $work->equipmentLoans()->attach($loan->id);

            // Attach loan items
            foreach ($request->equipment_list as $item) {
                EquipmentLoanItem::create([
                    'equipment_loan_id' => $loan->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'promotion_equipment_requested',
                "Equipment requested for promotion (Loan ID: {$loan->id}). Items: " . count($request->equipment_list),
                ['step' => 5, 'loan_id' => $loan->id]
            );

            // Notify Art & Set staff
            app(\App\Services\PrNotificationService::class)->notifyArtSetLoanRequested($loan);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['equipmentLoans.loanItems.inventoryItem']),
                'message' => 'Equipment requested successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function requestReturn(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401);
            }

            $work = PrPromotionWork::with(['episode.crews', 'equipmentLoans'])->findOrFail($id);

            // Check coordinator status
            $isStaff = Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::PRODUCER]);
            if (!$isStaff) {
                $crew = $work->episode->crews->where('user_id', $user->id)->first();
                if (!$crew || !$crew->is_coordinator) {
                    return response()->json(['success' => false, 'message' => 'Only coordinators can request equipment returns.'], 403);
                }
            }

            // Find the active loan (approved or active)
            $activeLoan = $work->equipmentLoans()
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
            app(\App\Services\PrNotificationService::class)->notifyArtSetReturnRequested($activeLoan);

            DB::commit();
            return response()->json(['success' => true, 'data' => $activeLoan->fresh(), 'message' => 'Equipment return request submitted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function cancelLoan(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401);
            }

            $work = PrPromotionWork::with(['episode.crews', 'equipmentLoans.loanItems'])->findOrFail($id);

            // Check coordinator status
            $isStaff = Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::PRODUCER]);
            if (!$isStaff) {
                $crew = $work->episode->crews->where('user_id', $user->id)->first();
                if (!$crew || !$crew->is_coordinator) {
                    return response()->json(['success' => false, 'message' => 'Only coordinators can cancel equipment loans.'], 403);
                }
            }

            // Only cancel loans that are pending or return_requested (not active/completed)
            $cancelableLoan = $work->equipmentLoans()
                ->whereIn('status', ['pending', 'return_requested'])
                ->first();

            if (!$cancelableLoan) {
                return response()->json(['success' => false, 'message' => 'No loans can be cancelled. Only Pending Approval status can be cancelled.'], 400);
            }

            // Restore stock for each item in the loan IF it was already approved/decremented
            // Actually 'pending' hasn't decremented stock yet usually, but 'return_requested' was active.
            // Let's check markAsBorrowed in EquipmentLoan. it decrements on 'active'.
            // So if it's 'return_requested', it was 'active', so we need to increment.
            // If it's 'pending', it NEVER reached 'active', so no increment needed?
            // Wait, in ArtController, does approve decrement?
            // Let's check PrArtController markAsBorrowed.
            if ($cancelableLoan->status === 'return_requested' || $cancelableLoan->status === 'active') {
                foreach ($cancelableLoan->loanItems as $loanItem) {
                    \App\Models\InventoryItem::where('id', $loanItem->inventory_item_id)
                        ->increment('available_quantity', $loanItem->quantity);
                }
            }

            $cancelableLoan->update(['status' => 'cancelled']);

            DB::commit();
            return response()->json(['success' => true, 'data' => $work->fresh(['equipmentLoans']), 'message' => 'Equipment loan cancelled successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Check if both promotion and production are complete for an episode,
     * and update workflow step 5 accordingly. Also create Step 6 work records.
     */
    /**
     * Get all episodes that have a promotion work (for Share Konten dropdown)
     */
    public function getEpisodes(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $episodes = PrEpisode::with(['program', 'promotionWork', 'broadcastingWork', 'editorPromosiWork'])
                ->whereHas('promotionWork')
                ->whereHas('workflowProgress', function ($query) {
                    // Step 9 is Broadcasting. Share Content (Step 10) only starts after Broadcasting is done.
                    $query->where('workflow_step', 9)->where('status', 'completed');
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($episode) {
                    $isPromoted = $episode->status === 'promoted';
                    $hasSharingTasks = !empty($episode->promotionWork?->sharing_proof['share_konten_tasks']);
                    $workStatus = $isPromoted ? 'completed' : ($hasSharingTasks ? 'in_progress' : 'pending');

                    return [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title ?? ('Episode ' . $episode->episode_number),
                        'program_name' => $episode->program?->name ?? '',
                        'youtube_link' => $episode->broadcastingWork?->youtube_url ?? null,
                        'jetstream_url' => $episode->broadcastingWork?->metadata['jetstream_url'] ?? null,
                        'work_status' => $workStatus,
                        'last_edited' => $episode->promotionWork?->updated_at ? $episode->promotionWork->updated_at->format('Y-m-d H:i') : null,
                        'status' => $episode->status,
                    ];
                });

            return response()->json(['success' => true, 'data' => $episodes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get saved Share Konten task progress for an episode
     */
    public function getShareKonten(int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $promotionWork = PrPromotionWork::where('pr_episode_id', $episodeId)->first();

            if (!$promotionWork) {
                return response()->json(['success' => false, 'message' => 'No promotion work found for this episode'], 404);
            }

            // Check if Step 9 is completed
            $broadcastingReady = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                ->where('workflow_step', 9)
                ->where('status', 'completed')
                ->exists();

            if (!$broadcastingReady) {
                return response()->json(['success' => false, 'message' => 'Broadcasting (Step 9) must be completed before sharing content.'], 403);
            }

            $sharingProof = $promotionWork->sharing_proof ?? [];
            $tasks = $sharingProof['share_konten_tasks'] ?? null;

            // Load highlight links from EditorPromosiWork
            $editorPromosi = PrEditorPromosiWork::where('pr_episode_id', $episodeId)->first();
            $igHighlightLink = $editorPromosi?->ig_highlight_link ?? null;
            $fbHighlightLink = $editorPromosi?->fb_highlight_link ?? null;

            // Inject highlight links into tasks so frontend always has fresh data from DB
            if ($tasks) {
                if (isset($tasks['story_ig'])) {
                    $tasks['story_ig']['video_link'] = $igHighlightLink;
                }
                if (isset($tasks['reels_fb'])) {
                    $tasks['reels_fb']['video_link'] = $fbHighlightLink;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'ig_highlight_link' => $igHighlightLink,
                'fb_highlight_link' => $fbHighlightLink,
                'jetstream_url' => $promotionWork->episode->broadcastingWork?->metadata['jetstream_url'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save Share Konten task progress for an episode
     */
    public function saveShareKonten(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::EDITOR_PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $promotionWork = PrPromotionWork::with(['episode.program'])->where('pr_episode_id', $episodeId)->first();

            if (!$promotionWork) {
                return response()->json(['success' => false, 'message' => 'No promotion work found for this episode'], 404);
            }

            // Check if Step 9 is completed
            $broadcastingReady = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                ->where('workflow_step', 9)
                ->where('status', 'completed')
                ->exists();

            if (!$broadcastingReady) {
                return response()->json(['success' => false, 'message' => 'Broadcasting (Step 9) must be completed before sharing content.'], 403);
            }

            $tasks = $request->input('tasks');
            $finalize = $request->boolean('finalize', true); // Default to true if not provided (old behavior)
            // Merge into sharing_proof under dedicated key to avoid overwriting other data
            $sharingProof = $promotionWork->sharing_proof ?? [];
            $sharingProof['share_konten_tasks'] = $tasks;

            $promotionWork->sharing_proof = $sharingProof;
            $promotionWork->save();

            if (!$finalize) {
                return response()->json([
                    'success' => true,
                    'message' => 'Share Konten tasks saved automatically',
                    'data' => $tasks
                ]);
            }

            // Mark Step 10 as completed
            $stepProgress = PrEpisodeWorkflowProgress::where('episode_id', $episodeId)
                ->where('workflow_step', 10)
                ->first();

            if ($stepProgress && $stepProgress->status !== 'completed') {
                $stepProgress->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);

                // Log activity
                $this->activityLogService->logEpisodeActivity(
                    $promotionWork->episode,
                    'share_konten_finish',
                    "Share Konten tasks completed.",
                    ['work_id' => $promotionWork->id],
                    $user->id
                );
            }

            // Mark episode as promoted so Step 10 shows green checkmark
            if ($promotionWork->episode) {
                // pr_episodes supports 'promoted' status
                $promotionWork->episode->update(['status' => 'promoted']);

                if ($promotionWork->episode->program) {
                    // pr_programs only supports limited enum, use 'active'
                    $promotionWork->episode->program->update(['status' => 'active']);
                }
            }

            return response()->json(['success' => true, 'message' => 'Share Konten tasks finalized successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Helper to check if user is authorized to perform actions on a Promotion Work
     */
    private function checkWorkAuthorization($work, $user): bool
    {
        if (!$user) return false;

        // 1. Administrative roles (All access)
        if (Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER])) {
            return true;
        }

        // 2. Producer (Only their own programs)
        if (Role::inArray($user->role, [Role::PRODUCER])) {
            return $work->episode && $work->episode->program && $work->episode->program->producer_id === $user->id;
        }

        // 3. Program Crew Assignment (Matches Promotion staff)
        $isCrew = \App\Models\PrProgramCrew::where('user_id', $user->id)
            ->where('program_id', $work->episode->program_id)
            ->exists();
        if ($isCrew) return true;

        // 4. Episode Crew Assignment (Matches specifically assigned helpers)
        $isEpisodeCrew = \App\Models\PrEpisodeCrew::where('user_id', $user->id)
            ->where('episode_id', $work->pr_episode_id)
            ->exists();
        if ($isEpisodeCrew) return true;

        return false;
    }
}
