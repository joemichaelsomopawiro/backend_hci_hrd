<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrQualityControlWork;
use App\Models\Notification;
use App\Models\User;
use App\Models\PrEpisode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Constants\Role;
use App\Services\PrActivityLogService;
use App\Services\PrWorkflowService;
use App\Services\PrNotificationService;
use Illuminate\Http\JsonResponse;

class PrEditorPromosiController extends Controller
{
    protected $activityLogService;
    protected $notificationService;

    public function __construct(
        PrActivityLogService $activityLogService,
        PrNotificationService $notificationService
    ) {
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }
    /**
     * Get list of editor promosi works with filters
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::EDITOR_PROMOTION, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $query = PrEditorPromosiWork::with([
            'episode.program',
            'editorWork',
            'promotionWork',
            'assignedUser'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Only show works where conditions are met (or if work is already in progress/completed)
        // Conditions: Editor status is 'pending_qc' OR 'completed' (in case editor moved on) AND Promotion status is 'completed'
        // But for "Pending" list (status = pending/waiting_editor), we strictly apply filters.
        // For works already assigned (in_progress, etc), we show them regardless.

        $works = $query->orderBy('created_at', 'desc')->get();
        
        // SELF-HEALING: Automatically transition 'waiting_editor' to 'pending' if Editor is now ready
        // This handles records created before the workflow trigger fix was implemented
        foreach ($works as $work) {
            if ($work->status === 'waiting_editor' || (!$work->pr_editor_work_id && $work->status === 'pending')) {
                // Try to find the editor work if it's missing or if we're waiting
                $mainEditorWork = null;
                if (!$work->pr_editor_work_id) {
                    $mainEditorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                        ->where('work_type', 'main_episode')
                        ->first();
                    if ($mainEditorWork) {
                        $work->update(['pr_editor_work_id' => $mainEditorWork->id]);
                        $work->refresh(); // Refresh relationship
                    }
                }

                $editorReady = $work->editorWork && in_array($work->editorWork->status, ['pending_qc', 'completed']);
                if ($editorReady && $work->status === 'waiting_editor') {
                    $work->update(['status' => 'pending']);
                }
            }
        }
        
        // Filter works based on readiness if status is 'pending' or 'waiting_editor'
        // This is done in memory for simplicity, or complex query joins could be used.
        // Given the requirement: "episode itu di editor statusnya sudah Sedang Proses QC ... link promosi selesai"

        $filteredWorks = $works;

        return response()->json([
            'success' => true,
            'data' => $filteredWorks->values()
        ]);
    }

    /**
     * Get detail of specific editor promosi work
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::EDITOR_PROMOTION, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $work = PrEditorPromosiWork::with([
            'episode.program',
            'editorWork',
            'promotionWork',
            'assignedUser'
        ])->findOrFail($id);

        // Check if Editor has completed their work (or is pending_qc)
        $editorReady = $work->editorWork && in_array($work->editorWork->status, ['pending_qc', 'completed']);
        $promotionReady = $work->promotionWork && $work->promotionWork->status === 'completed';

        return response()->json([
            'success' => true,
            'data' => $work,
            'editor_ready' => $editorReady,
            'promotion_ready' => $promotionReady
        ]);
    }

    /**
     * Start working on an episode
     */
    public function acceptWork(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::EDITOR_PROMOTION, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrEditorPromosiWork::findOrFail($id);

            // RECOVERY: If editor link is missing, try to find it now
            if (!$work->pr_editor_work_id) {
                $mainEditorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                    ->where('work_type', 'main_episode')
                    ->first();
                if ($mainEditorWork) {
                    $work->update(['pr_editor_work_id' => $mainEditorWork->id]);
                    $work->load('editorWork'); // Reload relationship
                }
            }

            // Check availability again
            $editorReady = $work->editorWork && in_array($work->editorWork->status, ['pending_qc', 'completed']);
            $promotionReady = $work->promotionWork && $work->promotionWork->status === 'completed';

            if (!$editorReady || !$promotionReady) {
                $reasons = [];
                if (!$editorReady) $reasons[] = "Editor must be in QC/Completed status";
                if (!$promotionReady) $reasons[] = "Promotion must be completed (BTS files uploaded)";
                
                return response()->json([
                    'success' => false,
                    'message' => 'Work is not ready yet: ' . implode(' & ', $reasons)
                ], 400);
            }

            $work->update([
                'status' => 'in_progress',
                'assigned_to' => $user->id,
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Work accepted',
                'data' => $work->load(['episode', 'editorWork', 'promotionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update work progress - upload links
     */
    public function updateProgress(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::EDITOR_PROMOTION, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $request->validate([
            'bts_video_link' => 'nullable|string',
            'tv_ad_link' => 'nullable|string',
            'ig_highlight_link' => 'nullable|string',
            'tv_highlight_link' => 'nullable|string',
            'fb_highlight_link' => 'nullable',
            'notes' => 'nullable|string'
        ]);

        try {
            $work = PrEditorPromosiWork::findOrFail($id);

            $work->update($request->only([
                'bts_video_link',
                'tv_ad_link',
                'ig_highlight_link',
                'tv_highlight_link',
                'fb_highlight_link',
                'notes'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Progress updated',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit work to QC
     * "tugasnya adalah mengsubmit ... baru setelah itu submit dan status nya sedang proses QC"
     */
    public function submit($id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::EDITOR_PROMOTION, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            DB::beginTransaction();

            $work = PrEditorPromosiWork::findOrFail($id);

            // Validate all 5 required links
            $requiredFields = ['bts_video_link', 'tv_ad_link', 'ig_highlight_link', 'tv_highlight_link'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($work->$field)) {
                    $missingFields[] = $field;
                }
            }
            
            // Validate Facebook Highlights (Array)
            $fbLinks = $work->fb_highlight_link;
            $filledFbLinks = is_array($fbLinks) ? array_filter($fbLinks, fn($link) => !empty($link)) : (!empty($fbLinks) ? [$fbLinks] : []);
            
            if (count($filledFbLinks) < 2) {
                $missingFields[] = 'fb_highlight_link (min. 2 required)';
            }

            if (count($missingFields) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please fill all required links before submitting: ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields
                ], 400);
            }

            // Validate they are URLs (basic check)
            foreach ($requiredFields as $field) {
                if (!filter_var($work->$field, FILTER_VALIDATE_URL)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Field $field is not a valid URL"
                    ], 400);
                }
            }
            
            // Validate FB URLs
            foreach ($filledFbLinks as $link) {
                if (!filter_var($link, FILTER_VALIDATE_URL)) {
                    return response()->json([
                        'success' => false,
                        'message' => "One of the Facebook Reel links is not a valid URL"
                    ], 400);
                }
            }

            $work->update([
                'status' => 'pending_qc',
                'submitted_at' => now()
            ]);

            // Centralized logic for step 6 completion
            app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 6);

            // Log submission for QC
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'editor_promosi_submitted',
                "Editor Promosi material submitted for QC review.",
                ['step' => 6, 'work_id' => $work->id]
            );

            // Update QC checklist status from 'revision' to 'revised' if it exists
            $qcWork = PrQualityControlWork::where('pr_episode_id', $work->pr_episode_id)->first();
            if ($qcWork && $qcWork->qc_checklist) {
                $checklist = $qcWork->qc_checklist;
                $updated = false;
                foreach ($checklist as $key => $item) {
                    if (isset($item['status']) && $item['status'] === 'revision') {
                        $checklist[$key]['status'] = 'revised';
                        $updated = true;
                    }
                }
                if ($updated) {
                    $qcWork->qc_checklist = $checklist;
                    // Reset status to pending so QC knows to check again if it was rejected
                    if ($qcWork->status !== 'completed') {
                        $qcWork->status = 'pending';
                    }
                    $qcWork->save();
                }
            }

            // Notify Quality Control Users
            $this->notificationService->notifyQcResubmission($work->episode, 'editor_promosi', $work->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work submitted to QC successfully',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve work (QC)
     * "kalau disetujui QC nanti dia status nya menjadi selesai"
     */
    public function approve($id)
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::PROMOTION, Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::EDITOR_PROMOTION, Role::PRODUCER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            DB::beginTransaction();

            $work = PrEditorPromosiWork::findOrFail($id);

            if ($work->status !== 'pending_qc') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in "pending_qc" status to be approved.'
                ], 400);
            }

            $work->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            // Centralized logic for step 6 completion
            app(\App\Services\PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 6);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work approved and completed',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve work: ' . $e->getMessage()
            ], 500);
        }
    }
}
