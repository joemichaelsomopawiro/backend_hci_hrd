<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrEditorWork;
use App\Models\PrEditorRevisionNote;
use App\Models\PrEpisode;
use App\Models\PrProduksiWork;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Constants\Role;
use App\Services\PrActivityLogService;
use App\Services\PrWorkflowService;
use Illuminate\Http\JsonResponse;

class PrEditorController extends Controller
{
    protected $activityLogService;

    public function __construct(PrActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }
    /**
     * Get list of editor works with filters
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::EDITOR, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $query = PrEditorWork::with([
            'episode.program',
            'episode.productionWork',
            'episode.creativeWork',
            'assignedUser',
            'revisionNotes'
        ]);

        // Filter by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $works = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $works
        ]);
    }

    /**
     * Get detail of specific editor work
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::EDITOR, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $work = PrEditorWork::with([
            'episode.program',
            'episode.creativeWork',
            'episode.productionWork', // Load via episode
            'assignedUser',
            'revisionNotes.creator',
            'revisionNotes.approver'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $work
        ]);
    }

    /**
     * Start working on an episode
     */
    public function start($episodeId)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::EDITOR, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $episode = PrEpisode::findOrFail($episodeId);

            $work = PrEditorWork::where('pr_episode_id', $episodeId)->first();

            if (!$work) {
                return response()->json([
                    'success' => false,
                    'message' => 'Editor work not found for this episode'
                ], 404);
            }

            $work->update([
                'status' => 'editing',
                'assigned_to' => Auth::id(),
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Started working on episode',
                'data' => $work->load(['episode', 'productionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update file completeness check
     */
    public function updateFileCheck(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::EDITOR, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $request->validate([
            'files_complete' => 'required|boolean'
        ]);

        try {
            $work = PrEditorWork::findOrFail($id);

            $work->update([
                'files_complete' => $request->files_complete,
                'status' => 'editing'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File check updated',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update file check: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request missing files from Production
     */
    public function requestFiles(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::EDITOR, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $request->validate([
            'notes' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $work = PrEditorWork::findOrFail($id);
            $episodeId = $work->pr_episode_id;

            // 1. Update Editor Work Status
            $work->update([
                'status' => 'revision_requested',
                'file_notes' => $request->notes,
                'files_complete' => false
            ]);

            // 2. Find and Update Production Work
            $productionWork = PrProduksiWork::where('pr_episode_id', $episodeId)->first();

            if ($productionWork) {
                // Determine if we should append notes or replace
                // For now, let's append with a timestamp to keep history in the same field
                // or just overwrite if it's a "current status" field. 
                // Let's overwrite `shooting_notes` or maybe we need a specific field.
                // The prompt says "mengisi catatan revisi". 
                // Let's use `shooting_notes` or append to it, but better to have a dedicated flow.
                // Since we don't have a separate `revision_notes` column in PrProduksiWork in the plan,
                // I'll append to `shooting_notes` or just rely on the Editor's `file_notes` being visible if linked.
                // But to make it show up for Production status, we definitely need to change status.

                $productionWork->update([
                    'status' => 'revision_requested',
                    // We can optionally copy the notes to production work if needed, 
                    // but PrEditorWork.file_notes is the source of truth for the request.
                ]);
            }

            // 3. Create a revision note record for history (optional but good practice)
            PrEditorRevisionNote::create([
                'pr_editor_work_id' => $work->id,
                'pr_episode_id' => $episodeId,
                'created_by' => Auth::id(),
                'notes' => "Requesting missing files: " . $request->notes,
                'status' => 'revision_requested'
            ]);

            // Log activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'file_request_editor',
                "Editor requested missing files: " . $request->notes,
                ['step' => 6, 'work_id' => $work->id, 'reason' => $request->notes]
            );

            // Notify Production team about file request
            $notificationService = app(\App\Services\PrNotificationService::class);
            $notificationService->notifyWorkflowStepReady($work->pr_episode_id, 5); // Step 5 is Production

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Files requested from Production',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to request files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update work progress (notes)
     */
    /**
     * Update editor work details
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::EDITOR, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $request->validate([
            'work_type' => 'nullable|string',
            'file_complete' => 'nullable|boolean',
            'file_notes' => 'nullable|string',
            'editing_notes' => 'nullable|string',
            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'status' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $work = PrEditorWork::findOrFail($id);

            $updateData = $request->only([
                'work_type',
                'file_complete',
                'file_notes',
                'editing_notes',
                'file_path',
                'file_name',
                'file_size',
                'status'
            ]);

            // If status is specifically set to pending_qc
            if (($request->status ?? '') === 'pending_qc') {
                $updateData['submitted_at'] = now();

                // Find associated Manager Distribusi QC Work and update checklist
                $qcWork = \App\Models\PrManagerDistribusiQcWork::where('pr_episode_id', $work->pr_episode_id)->first();
                if ($qcWork && $qcWork->qc_checklist) {
                    $checklist = $qcWork->qc_checklist;
                    $checklistUpdated = false;

                    foreach ($checklist as $key => $item) {
                        if (($item['status'] ?? '') === 'revision') {
                            $checklist[$key]['status'] = 'revised';
                            $checklistUpdated = true;
                        }
                    }

                    if ($checklistUpdated) {
                        $qcWork->qc_checklist = $checklist;
                        $qcWork->save();
                    }
                }

                // Log submission for QC
                $this->activityLogService->logEpisodeActivity(
                    $work->episode,
                    'editor_submitted',
                    "Video editing submitted for QC review.",
                    ['step' => 6, 'work_id' => $work->id]
                );

                // Notify QC team that editing is complete and ready for review
                $notificationService = app(\App\Services\PrNotificationService::class);
                $notificationService->notifyWorkflowStepReady($work->pr_episode_id, 7); // Step 7 is QC
            }

            $work->update($updateData);

            // Centralized logic for step 6 completion
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 6);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work updated successfully',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update work: ' . $e->getMessage()
            ], 500);
        }
    }

}
