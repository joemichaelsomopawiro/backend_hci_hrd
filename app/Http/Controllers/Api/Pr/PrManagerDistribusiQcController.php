<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrManagerDistribusiQcWork;
use App\Models\PrEditorWork;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\PrActivityLogService;
use App\Models\PrBroadcastingWork;
use App\Models\PrEpisode;
use App\Services\PrWorkflowService;
use App\Constants\Role;
use App\Models\PrDesignGrafisWork;

class PrManagerDistribusiQcController extends Controller
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
            $allowedRoles = [
                Role::DISTRIBUTION_MANAGER,
                Role::PROGRAM_MANAGER,
                Role::PRODUCER,
                'Super Admin'
            ];

            if (!$user || !Role::inArray($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }



            $query = PrManagerDistribusiQcWork::with(['episode.program', 'createdBy', 'reviewedBy']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Manager Distribusi QC works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $allowedRoles = [
                Role::DISTRIBUTION_MANAGER,
                Role::PROGRAM_MANAGER,
                Role::PRODUCER,
                'Super Admin'
            ];

            if (!$user || !Role::inArray($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrManagerDistribusiQcWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when pending'], 400);
            }

            $work->markAsInProgress();
            $work->update(['reviewed_by' => $user->id]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'reviewedBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $allowedRoles = [
                Role::DISTRIBUTION_MANAGER,
                Role::PROGRAM_MANAGER,
                Role::PRODUCER,
                'Super Admin'
            ];

            if (!$user || !Role::inArray($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrManagerDistribusiQcWork::with(['episode.program', 'createdBy', 'reviewedBy'])->findOrFail($id);

            return response()->json(['success' => true, 'data' => $work]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateChecklistItem(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $allowedRoles = [
                Role::DISTRIBUTION_MANAGER,
                Role::PROGRAM_MANAGER,
                Role::PRODUCER,
                'Super Admin'
            ];

            if (!$user || !Role::inArray($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate([
                'item_key' => 'required|string', // e.g., 'video_episode'
                'status' => 'nullable|in:approved,revision',
                'note' => 'nullable|string',
            ]);

            $work = PrManagerDistribusiQcWork::findOrFail($id);

            if ($work->status === 'completed' || $work->status === 'approved') {
                return response()->json(['success' => false, 'message' => 'Cannot modify items once QC is finished.'], 400);
            }

            $checklist = $work->qc_checklist ?? [];

            if ($request->status === null) {
                // Determine if we're cancelling a revision
                $wasRevision = isset($checklist[$request->item_key]) && $checklist[$request->item_key]['status'] === 'revision';
                
                unset($checklist[$request->item_key]);
                $work->qc_checklist = $checklist;
                $work->save();

                if ($wasRevision) {
                    $this->handleRevisionCancelCleanup($work, $request->item_key);
                }

                return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Item reset to pending']);
            }
            $checklist[$request->item_key] = [
                'status' => $request->status,
                'note' => $request->note,
                'checked_at' => now()->toIso8601String(),
                'checked_by' => $user->name // or id
            ];

            $work->qc_checklist = $checklist;
            $work->save();

            // Handle Revision Logic
            if ($request->status === 'revision') {
                $this->handleRevisionRequest($work, $request->item_key, $request->note);

                // Log revision
                $this->activityLogService->logEpisodeActivity(
                    $work->episode,
                    'qc_revision',
                    "Revision requested for {$request->item_key}: {$request->note}",
                    ['step' => 7, 'item' => $request->item_key, 'note' => $request->note]
                );
            } else {
                // Log partial approval
                $this->activityLogService->logEpisodeActivity(
                    $work->episode,
                    'qc_item_approved',
                    "QC Item approved: {$request->item_key}",
                    ['step' => 7, 'item' => $request->item_key]
                );
            }

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Item updated']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function handleRevisionRequest(PrManagerDistribusiQcWork $work, string $itemKey, ?string $note)
    {
        if ($itemKey === 'episode_poster') {
            $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($designGrafisWork) {
                $newNote = "[QC Revision: Episode Poster] " . $note;
                $currentNotes = $designGrafisWork->notes ? $designGrafisWork->notes . "\n" : "";

                $designGrafisWork->update([
                    'status' => 'needs_revision',
                    'notes' => $currentNotes . $newNote
                ]);
            }
        } else {
            // Update Editor Work (main episode)
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                ->where('work_type', 'main_episode')
                ->first();
            if ($editorWork) {
                $newNote = "[QC Revision: " . $itemKey . "] " . $note;
                $currentNotes = $editorWork->review_notes ? $editorWork->review_notes . "\n" : "";

                $editorWork->update([
                    'status' => 'needs_revision',
                    'review_notes' => $currentNotes . $newNote
                ]);
            }
        }
    }

    private function handleRevisionCancelCleanup(PrManagerDistribusiQcWork $work, string $itemKey)
    {
        $checklist = $work->qc_checklist ?? [];
        $otherRevisions = false;
        
        foreach ($checklist as $key => $item) {
            if (($item['status'] ?? '') === 'revision') {
                if ($itemKey === 'episode_poster' && $key === 'episode_poster') $otherRevisions = true;
                if ($itemKey !== 'episode_poster' && $key !== 'episode_poster') $otherRevisions = true;
            }
        }

        if (!$otherRevisions) {
            if ($itemKey === 'episode_poster') {
                PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->update(['status' => 'submitted']);
            } else {
                PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                    ->where('work_type', 'main_episode')
                    ->update(['status' => 'submitted']);
            }
        }
    }

    public function finish(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $allowedRoles = [
                Role::DISTRIBUTION_MANAGER,
                Role::PROGRAM_MANAGER,
                Role::PRODUCER,
                'Super Admin'
            ];

            if (!$user || !Role::inArray($user->role, $allowedRoles)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrManagerDistribusiQcWork::findOrFail($id);
            $checklist = $work->qc_checklist ?? [];

            // Define required items for Manager Distribusi
            $requiredItems = ['video_episode'];

            // Identify present items - Manager Distribusi primarily checks the main episode video and added poster
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                ->where('work_type', 'main_episode')
                ->first();

            $presentKeys = [];
            if ($editorWork && $editorWork->file_path) {
                $presentKeys[] = 'video_episode';
            }

            $designGrafisWork = PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($designGrafisWork && $designGrafisWork->episode_poster_link) {
                $presentKeys[] = 'episode_poster';
            }

            $allApproved = true;
            foreach ($presentKeys as $key) {
                if (($checklist[$key]['status'] ?? '') !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }

            if (!$allApproved || empty($presentKeys)) {
                return response()->json(['success' => false, 'message' => 'All available items (video episode, episode poster) must be approved before finishing.'], 400);
            }

            DB::beginTransaction();

            $work->update([
                'status' => 'completed',
                'qc_completed_at' => now(),
                'reviewed_by' => $user->id
            ]);

            // Update Editor Work Status to completed
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                ->where('work_type', 'main_episode')
                ->first();
            if ($editorWork) {
                $editorWork->update(['status' => 'completed']);

                // Centralized logic for step 6 completion
                app(PrWorkflowService::class)->syncStepProgress($editorWork->pr_episode_id, 6);
            }

            // Sync Step 7 progress
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 7);

            // Auto-create Broadcasting work so it shows up in broadcasting dashboard
            PrBroadcastingWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id, 'work_type' => 'main_episode'],
                ['status' => 'preparing']
            );

            // Log final approval
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'qc_finish',
                "Manager Distribusi Quality Check Completed: All items approved.",
                ['step' => 7, 'status' => 'completed']
            );

            DB::commit();

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'QC Work Finished']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get count of pending QC works
     */
    public function pendingCount(): JsonResponse
    {
        try {
            $count = PrManagerDistribusiQcWork::where('status', 'pending')->count();
            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

}
