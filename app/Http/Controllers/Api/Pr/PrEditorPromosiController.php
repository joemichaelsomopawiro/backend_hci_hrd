<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrEditorPromosiWork;
use App\Models\PrEditorWork;
use App\Models\PrPromotionWork;
use App\Models\PrEpisode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrEditorPromosiController extends Controller
{
    /**
     * Get list of editor promosi works with filters
     */
    public function index(Request $request)
    {
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

        // Filter works based on readiness if status is 'pending' or 'waiting_editor'
        // This is done in memory for simplicity, or complex query joins could be used.
        // Given the requirement: "episode itu di editor statusnya sudah Sedang Proses QC ... link promosi selesai"

        $filteredWorks = $works->filter(function ($work) use ($request) {
            // If specific status requested (e.g. in_progress, completed), return true
            if ($request->has('status') && !in_array($request->status, ['pending', 'waiting_editor'])) {
                return true;
            }

            // If user wants 'pending' works, we only show those ready to be picked up
            if ($work->status === 'pending' || $work->status === 'waiting_editor' || !$request->has('status')) {
                $editorReady = $work->editorWork && in_array($work->editorWork->status, ['pending_qc', 'completed']);
                $promotionReady = $work->promotionWork && $work->promotionWork->status === 'completed';

                // If work is already assigned/started, show it. If not, check conditions.
                if ($work->status !== 'pending' && $work->status !== 'waiting_editor') {
                    return true;
                }

                return $editorReady && $promotionReady;
            }

            return true;
        });

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
            $work = PrEditorPromosiWork::findOrFail($id);
            $user = Auth::user();

            // Check availability again
            $editorReady = $work->editorWork && in_array($work->editorWork->status, ['pending_qc', 'completed']);
            $promotionReady = $work->promotionWork && $work->promotionWork->status === 'completed';

            if (!$editorReady || !$promotionReady) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work is not ready yet. Editor must be in QC and Promotion must be completed.'
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
        $request->validate([
            'bts_video_link' => 'nullable|string',
            'tv_ad_link' => 'nullable|string',
            'ig_highlight_link' => 'nullable|string',
            'tv_highlight_link' => 'nullable|string',
            'fb_highlight_link' => 'nullable|string',
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
            DB::beginTransaction();

            $work = PrEditorPromosiWork::findOrFail($id);

            // Validate all 5 required links
            $requiredFields = ['bts_video_link', 'tv_ad_link', 'ig_highlight_link', 'tv_highlight_link', 'fb_highlight_link'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($work->$field)) {
                    $missingFields[] = $field;
                }
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

            $work->update([
                'status' => 'pending_qc',
                'submitted_at' => now()
            ]);

            // Create or update QC Work
            $qcWork = \App\Models\PrQualityControlWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id],
                ['status' => 'pending']
            );

            // Update QC Work with file locations
            $qcWork->update([
                'editor_promosi_file_locations' => [
                    'bts_video' => $work->bts_video_link,
                    'tv_ad' => $work->tv_ad_link,
                    'ig_highlight' => $work->ig_highlight_link,
                    'tv_highlight' => $work->tv_highlight_link,
                    'fb_highlight' => $work->fb_highlight_link,
                ],
            ]);

            // Update any 'revision' items in checklist to 'revised'
            $checklist = $qcWork->qc_checklist;
            if (is_array($checklist)) {
                $editorKeys = ['video_bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 'highlight_face', 'bts_video', 'tv_ad', 'ig_highlight', 'tv_highlight', 'fb_highlight'];
                $updated = false;
                foreach ($checklist as $key => $item) {
                    if (in_array($key, $editorKeys) && isset($item['status']) && $item['status'] === 'revision') {
                        $checklist[$key]['status'] = 'revised';
                        $updated = true;
                    }
                }
                if ($updated) {
                    $qcWork->qc_checklist = $checklist;
                    $qcWork->save();
                }
            }

            // If QC Status is completed or approved, maybe we shouldn't reset it? 
            // But if Editor resubmits, QC needs to re-check.
            if (in_array($qcWork->status, ['completed', 'approved', 'rejected'])) {
                $qcWork->update(['status' => 'pending', 'qc_results' => null, 'quality_score' => null]);
            }

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

            // Check if workflow can proceed
            // $this->checkStep6Completion($work->pr_episode_id);

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
