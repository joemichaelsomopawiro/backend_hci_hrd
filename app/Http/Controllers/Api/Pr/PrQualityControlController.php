<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrQualityControlWork;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\PrActivityLogService;
use App\Models\PrEpisode;
use App\Models\PrEpisodeWorkflowProgress;
use App\Constants\WorkflowStep;
use App\Constants\Role;
use App\Services\PrWorkflowService;
use App\Models\PrEditorPromosiWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrBroadcastingWork;
use Illuminate\Support\Facades\Log;
use App\Services\PrNotificationService;

class PrQualityControlController extends Controller
{
    protected $activityLogService;
    protected $notificationService;

    public function __construct(PrActivityLogService $activityLogService, PrNotificationService $notificationService)
    {
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::QUALITY_CONTROL, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // We want to show:
            // 1. Episodes where CURRENT workflow step is 7 or 8 (Ready for QC or at QC)
            // 2. Episodes that have an existing QC work record (even if workflow step was reverted for revision)
            
            // First, find all episode IDs that already have a QC record
            $existingQcEpisodeIds = PrQualityControlWork::pluck('pr_episode_id')->toArray();

            // Next, find episodes where step 7 (QC Awal) or step 8 (QC Final) is active or completed
            $progressRecords = PrEpisodeWorkflowProgress::with('episode')
                ->whereIn('workflow_step', [7, 8])
                ->where(function($q) use ($existingQcEpisodeIds) {
                    $q->whereIn('status', ['pending', 'in_progress', 'completed'])
                      ->orWhereIn('episode_id', $existingQcEpisodeIds);
                })
                ->get();

            $validEpisodeIds = array_unique(array_merge(
                $existingQcEpisodeIds,
                $progressRecords->pluck('episode_id')->toArray()
            ));

            $workflowService = app(PrWorkflowService::class);

            // Fetch entries where QC work already exists and match valid episodes
            $query = PrQualityControlWork::with(['episode.program', 'createdBy', 'reviewedBy'])
                ->whereIn('pr_episode_id', $validEpisodeIds);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'QC works retrieved successfully']);

        } catch (\Exception $e) {
            Log::error('QC Dashboard Index Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !\App\Constants\Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::findOrFail($id);

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

            if (!$user || !Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::with(['episode.program', 'createdBy', 'reviewedBy'])->findOrFail($id);

            // Fetch related works to populate file locations
            $editorWork = PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->first();
            $designWork = PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->first();

            // Update locations with standardized keys
            $editorLocations = [];
            if ($editorWork) {
                if ($editorWork->bts_video_link) $editorLocations['bts_video'] = $editorWork->bts_video_link;
                if ($editorWork->tv_ad_link) $editorLocations['tv_ad'] = $editorWork->tv_ad_link;
                if ($editorWork->ig_highlight_link) $editorLocations['ig_highlight'] = $editorWork->ig_highlight_link;
                if ($editorWork->tv_highlight_link) $editorLocations['tv_highlight'] = $editorWork->tv_highlight_link;
                if ($editorWork->fb_highlight_link) $editorLocations['fb_highlight'] = $editorWork->fb_highlight_link;
            }

            $designLocations = [];
            if ($designWork) {
                if ($designWork->youtube_thumbnail_link) $designLocations['youtube_thumbnail'] = $designWork->youtube_thumbnail_link;
                if ($designWork->bts_thumbnail_link) $designLocations['bts_thumbnail'] = $designWork->bts_thumbnail_link;
                if ($designWork->episode_poster_link) $designLocations['episode_poster'] = $designWork->episode_poster_link;
            }

            $work->editor_promosi_file_locations = $editorLocations;
            $work->design_grafis_file_locations = $designLocations;
            $work->save();

            return response()->json(['success' => true, 'data' => $work]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateChecklistItem(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate([
                'item_key' => 'required|string', // e.g., 'bts_video', 'youtube_thumbnail'
                'status' => 'nullable|in:approved,revision',
                'note' => 'nullable|string',
            ]);

            $work = PrQualityControlWork::findOrFail($id);

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
            
            // Auto update work status to in_progress if it was pending
            if ($work->status === 'pending') {
                $work->status = 'in_progress';
                $work->reviewed_by = $user->id;
                
                // Sync status to episode workflow progress for Step 7
                PrEpisodeWorkflowProgress::where('episode_id', $work->pr_episode_id)
                    ->where('workflow_step', 7)
                    ->update(['status' => 'in_progress']);
            }
            
            $work->save();

            // Handle Revision Logic
            if ($request->status === 'revision') {
                $this->handleRevisionRequest($work, $request->item_key, $request->note);
            }

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Item updated']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function handleRevisionRequest(PrQualityControlWork $work, string $itemKey, ?string $note)
    {
        $editorKeys = ['bts_video', 'tv_ad', 'ig_highlight', 'tv_highlight', 'fb_highlight'];
        $designKeys = ['youtube_thumbnail', 'bts_thumbnail', 'episode_poster'];

        $episode = PrEpisode::with('program')->find($work->pr_episode_id);
        if (!$episode) return;

        $itemLabel = str_replace('_', ' ', $itemKey);
        $newNote = "[QC Revision: " . $itemKey . "] " . $note;

        if (in_array($itemKey, $editorKeys)) {
            $editorWork = PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($editorWork) {
                // Check if already in needs_revision, if so just append note
                $currentNotes = $editorWork->notes ? $editorWork->notes . "\n" : "";
                $editorWork->update([
                    'status' => 'needs_revision',
                    'notes' => $currentNotes . $newNote
                ]);

                if ($editorWork->assignedUser) {
                    $this->notificationService->notifyQcRevisionRequested($episode, $itemLabel, $note, $editorWork->assignedUser);
                }
            }
        } elseif (in_array($itemKey, $designKeys)) {
            $designWork = PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($designWork) {
                $currentNotes = $designWork->notes ? $designWork->notes . "\n" : "";
                $designWork->update([
                    'status' => 'needs_revision',
                    'notes' => $currentNotes . $newNote
                ]);

                if ($designWork->assignedUser) {
                    $this->notificationService->notifyQcRevisionRequested($episode, $itemLabel, $note, $designWork->assignedUser);
                }
            }
        }

        PrEpisodeWorkflowProgress::where('episode_id', $work->pr_episode_id)
            ->where('workflow_step', 6)
            ->update(['status' => WorkflowStep::STATUS_IN_PROGRESS]);

        $this->activityLogService->logEpisodeActivity(
            $episode,
            'qc_revision',
            "QC Revision requested for: {$itemKey}. Note: {$note}",
            ['step' => 7, 'work_id' => $work->id, 'item' => $itemKey, 'reason' => $note]
        );
    }

    private function handleRevisionCancelCleanup(PrQualityControlWork $work, string $itemKey)
    {
        $editorKeys = ['bts_video', 'tv_ad', 'ig_highlight', 'tv_highlight', 'fb_highlight'];
        $designKeys = ['youtube_thumbnail', 'bts_thumbnail', 'episode_poster'];
        
        $checklist = $work->qc_checklist ?? [];
        $otherRevisions = false;
        
        foreach ($checklist as $key => $item) {
            if (($item['status'] ?? '') === 'revision') {
                if (in_array($itemKey, $editorKeys) && in_array($key, $editorKeys)) $otherRevisions = true;
                if (in_array($itemKey, $designKeys) && in_array($key, $designKeys)) $otherRevisions = true;
            }
        }

        if (!$otherRevisions) {
            if (in_array($itemKey, $editorKeys)) {
                PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->update(['status' => 'submitted']);
            } elseif (in_array($itemKey, $designKeys)) {
                PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->update(['status' => 'submitted']);
            }
        }
    }

    public function cancelRevision(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::QUALITY_CONTROL, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate(['item_key' => 'required|string']);
            $work = PrQualityControlWork::findOrFail($id);
            $checklist = $work->qc_checklist ?? [];

            if (!isset($checklist[$request->item_key]) || $checklist[$request->item_key]['status'] !== 'revision') {
                return response()->json(['success' => false, 'message' => 'Item is not in revision status.'], 400);
            }

            // Remove from checklist or reset status
            unset($checklist[$request->item_key]);
            $work->qc_checklist = $checklist;
            $work->save();

            $this->handleRevisionCancelCleanup($work, $request->item_key);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Revision cancelled successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function finish(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::findOrFail($id);
            $checklist = $work->qc_checklist ?? [];

            // Define required items (based on user request)
            $requiredItems = ['bts_video', 'tv_ad', 'ig_highlight', 'fb_highlight', 'tv_highlight', 'youtube_thumbnail', 'bts_thumbnail', 'episode_poster'];

            // Identify currently present file keys to avoid failing on stale checklist items
            $presentKeys = array_merge(
                array_keys($work->editor_promosi_file_locations ?? []),
                array_keys($work->design_grafis_file_locations ?? [])
            );

            // Check if all present items are approved
            $allApproved = true;
            foreach ($presentKeys as $key) {
                if (($checklist[$key]['status'] ?? '') !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }

            if (!$allApproved || empty($presentKeys)) {
                return response()->json(['success' => false, 'message' => 'All present items must be approved before finishing.'], 400);
            }

            $work->update([
                'status' => 'completed',
                'qc_completed_at' => now(),
                'reviewed_by' => $user->id
            ]);

            PrBroadcastingWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id, 'work_type' => 'main_episode'],
                ['status' => 'preparing', 'created_by' => $user->id]
            );

            // Sync Step 8 progress
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 8);

            // Log Final QC Approval
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'qc_approved',
                "Episode passed QC review. Ready for broadcasting.",
                ['step' => 8, 'work_id' => $work->id]
            );

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'QC Work Finished']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Keep the rest (index, acceptWork) as is, or update if needed.
    // I will replace index as well to be safe with the replace block.

    // ... [index and acceptWork similar to before] ...

}
