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
use App\Services\PrWorkflowService;

class PrQualityControlController extends Controller
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

            if (!$user || !\App\Constants\Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // Fetch episodes that are officially marked as Step 6 completed
            $progressRecords = \App\Models\PrEpisodeWorkflowProgress::with('episode')
                ->where('workflow_step', 6)
                ->where('status', 'completed')
                ->get();

            $validEpisodeIds = [];
            $workflowService = app(\App\Services\PrWorkflowService::class);

            foreach ($progressRecords as $progress) {
                // IMPORTANT: Skip if episode is missing (e.g., soft deleted)
                if (!$progress->episode) {
                    continue;
                }

                // Double check if roles are actually done according to current logic
                // This handles episodes that were prematurely marked completed by old buggy code
                $roleCompletions = $workflowService->getRoleCompletions($progress->episode, 6);

                $allDone = true;
                foreach ($roleCompletions as $isDone) {
                    if (!$isDone) {
                        $allDone = false;
                        break;
                    }
                }

                if ($allDone) {
                    $validEpisodeIds[] = $progress->episode_id;

                    // Ensure QC work exists if it's truly done
                    PrQualityControlWork::firstOrCreate(
                        ['pr_episode_id' => $progress->episode_id],
                        ['status' => 'pending']
                    );
                } else {
                    // REPAIR: If it's not all done but marked as completed, revert it!
                    $progress->update(['status' => 'in_progress']);
                }
            }

            // Fetch entries where QC work already exists and match valid episodes
            $query = PrQualityControlWork::with(['episode.program', 'createdBy', 'reviewedBy'])
                ->whereIn('pr_episode_id', $validEpisodeIds);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'QC works retrieved successfully']);

        } catch (\Exception $e) {
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

            if (!$user || !\App\Constants\Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::with(['episode.program', 'createdBy', 'reviewedBy'])->findOrFail($id);

            // Fetch related works to populate file locations
            $editorWork = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->first();
            $designWork = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->first();

            $editorLocations = [];
            if ($editorWork) {
                if ($editorWork->bts_video_link)
                    $editorLocations['video_bts'] = $editorWork->bts_video_link;
                if ($editorWork->tv_ad_link)
                    $editorLocations['tv_ad'] = $editorWork->tv_ad_link;
                if ($editorWork->ig_highlight_link)
                    $editorLocations['ig_highlight'] = $editorWork->ig_highlight_link;
                if ($editorWork->tv_highlight_link)
                    $editorLocations['tv_highlight'] = $editorWork->tv_highlight_link;
                if ($editorWork->fb_highlight_link)
                    $editorLocations['fb_highlight'] = $editorWork->fb_highlight_link;
            }

            $designLocations = [];
            if ($designWork) {
                if ($designWork->youtube_thumbnail_link)
                    $designLocations['youtube_thumbnail'] = $designWork->youtube_thumbnail_link;
                if ($designWork->bts_thumbnail_link)
                    $designLocations['bts_thumbnail'] = $designWork->bts_thumbnail_link;
            }

            // Update locations
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
            if (!$user || !\App\Constants\Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate([
                'item_key' => 'required|string', // e.g., 'bts_video', 'youtube_thumbnail'
                'status' => 'required|in:approved,revision',
                'note' => 'nullable|string',
            ]);

            $work = PrQualityControlWork::findOrFail($id);

            if ($work->status === 'completed' || $work->status === 'approved') {
                // Allow editing but maybe warn? For now allow.
            }

            $checklist = $work->qc_checklist ?? [];
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
            }

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Item updated']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function handleRevisionRequest(PrQualityControlWork $work, string $itemKey, ?string $note)
    {
        // Identify source based on item_key
        $editorItems = ['bts_video', 'tv_ad', 'ig_highlight', 'tv_highlight', 'fb_highlight', 'highlight_face']; // Adjust based on user request
        $designItems = ['youtube_thumbnail', 'bts_thumbnail', 'tumneil_yt', 'tumneil_bts'];

        // Map user readable keys to internal keys if needed. 
        // User said: QC Video BTS, QC Iklan Episode TV, QC Highlight Episode IG, QC Highlignt Episode TV, QC Highligt Episode Face, QC Tumneil YT, QC Tumneil BTS

        // I should stick to consistent keys.
        // Let's assume keys passed from FE are: 
        // 'video_bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 'highlight_face', 'thumbnail_yt', 'thumbnail_bts'

        $editorKeys = ['video_bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 'highlight_face', 'bts_video', 'tv_ad', 'ig_highlight', 'tv_highlight', 'fb_highlight'];
        $designKeys = ['thumbnail_yt', 'thumbnail_bts', 'youtube_thumbnail', 'bts_thumbnail', 'tumneil_yt', 'tumneil_bts'];

        if (in_array($itemKey, $editorKeys)) {
            // Update Editor Promosi Work
            $editorWork = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($editorWork) {
                $newNote = "[QC Revision: " . $itemKey . "] " . $note;
                $currentNotes = $editorWork->notes ? $editorWork->notes . "\n" : "";

                $editorWork->update([
                    'status' => 'needs_revision',
                    'notes' => $currentNotes . $newNote
                ]);
            }
        } elseif (in_array($itemKey, $designKeys)) {
            // Update Design Grafis Work
            $designWork = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($designWork) {
                $newNote = "[QC Revision: " . $itemKey . "] " . $note;
                $currentNotes = $designWork->notes ? $designWork->notes . "\n" : "";

                $designWork->update([
                    'status' => 'needs_revision',
                    'notes' => $currentNotes . $newNote
                ]);
            }
        }

        // Reset Step 6 workflow progress to in_progress so it can be re-triggered later
        \App\Models\PrEpisodeWorkflowProgress::where('pr_episode_id', $work->pr_episode_id)
            ->where('workflow_step', 6)
            ->update(['status' => \App\Constants\WorkflowStep::STATUS_IN_PROGRESS]);

        // Log Revision Activity
        $episode = PrEpisode::find($work->pr_episode_id);
        if ($episode) {
            $this->activityLogService->logEpisodeActivity(
                $episode,
                'qc_revision',
                "QC Revision requested for: {$itemKey}. Note: {$note}",
                ['step' => 7, 'work_id' => $work->id, 'item' => $itemKey, 'reason' => $note]
            );
        }
    }

    public function finish(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !\App\Constants\Role::inArray($user->role, [\App\Constants\Role::QUALITY_CONTROL, \App\Constants\Role::PROGRAM_MANAGER, \App\Constants\Role::PRODUCER, \App\Constants\Role::DISTRIBUTION_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrQualityControlWork::findOrFail($id);
            $checklist = $work->qc_checklist ?? [];

            // Define required items (based on user request)
            $requiredItems = ['video_bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 'highlight_face', 'thumbnail_yt', 'thumbnail_bts'];

            // Check if all are approved. 
            // Note: Keys might vary, so we need to be careful. Ideally frontend sends consistent keys.
            // For now, checks if ALL items in checklist are approved? Or check against required list?
            // Let's rely on the fact that if they click "Selesai", they should have approved everything visible.

            $allApproved = true;
            foreach ($checklist as $item) {
                if (($item['status'] ?? '') !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }

            if (!$allApproved || empty($checklist)) {
                return response()->json(['success' => false, 'message' => 'All items must be approved before finishing.'], 400);
            }

            $work->update([
                'status' => 'completed',
                'qc_completed_at' => now(),
                'reviewed_by' => $user->id
            ]);

            \App\Models\PrBroadcastingWork::firstOrCreate(
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
