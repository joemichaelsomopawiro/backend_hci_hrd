<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrDesignGrafisWork;
use App\Models\PrEditorWork;
use App\Models\PrEpisode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrDesignGrafisController extends Controller
{
    /**
     * Get list of design grafis works with filters
     */
    public function index(Request $request)
    {
        $query = PrDesignGrafisWork::with([
            'episode.program',
            'productionWork',
            'promotionWork',
            'assignedUser'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $works = $query->orderBy('created_at', 'desc')->get();

        // Add deadline calculation for each work and map file paths
        $works->each(function ($work) {
            if (!$work->deadline && $work->episode && $work->episode->air_date) {
                $work->deadline = \Carbon\Carbon::parse($work->episode->air_date)->subDay();
            }

            // Map Production Video
            if ($work->productionWork && $work->productionWork->shooting_file_links) {
                $links = explode(',', $work->productionWork->shooting_file_links);
                $work->productionWork->file_video_path = trim($links[0]);
            }

            // Map Promotion Talent Photo
            if ($work->promotionWork && !empty($work->promotionWork->file_paths)) {
                $files = $work->promotionWork->file_paths;
                // Assuming it's an array from the model cast, allow for string if not cast correctly
                if (is_string($files)) {
                    // Attempt to decode if it's a JSON string, though model cast should handle it
                    $decoded = json_decode($files, true);
                    $files = is_array($decoded) ? $decoded : [$files];
                }

                if (is_array($files)) {
                    if (isset($files['talent_photo'])) {
                        $work->promotionWork->file_talent_photo = $files['talent_photo'];
                    } elseif (isset($files[0])) {
                        $work->promotionWork->file_talent_photo = $files[0];
                    } else {
                        // Fallback to first element if array is not empty
                        $work->promotionWork->file_talent_photo = reset($files);
                    }
                } else {
                    $work->promotionWork->file_talent_photo = $files;
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => $works
        ]);
    }

    /**
     * Get detail of specific design grafis work
     */
    public function show($id)
    {
        $work = PrDesignGrafisWork::with([
            'episode.program',
            'productionWork',
            'promotionWork',
            'assignedUser'
        ])->findOrFail($id);

        // Map Production Video
        if ($work->productionWork && $work->productionWork->shooting_file_links) {
            $links = explode(',', $work->productionWork->shooting_file_links);
            $work->productionWork->file_video_path = trim($links[0]);
        }

        // Map Promotion Talent Photo
        if ($work->promotionWork && !empty($work->promotionWork->file_paths)) {
            $files = $work->promotionWork->file_paths;
            if (is_string($files)) {
                $decoded = json_decode($files, true);
                $files = is_array($decoded) ? $decoded : [$files];
            }

            if (is_array($files)) {
                if (isset($files['talent_photo'])) {
                    $work->promotionWork->file_talent_photo = $files['talent_photo'];
                } elseif (isset($files[0])) {
                    $work->promotionWork->file_talent_photo = $files[0];
                } else {
                    $work->promotionWork->file_talent_photo = reset($files);
                }
            } else {
                $work->promotionWork->file_talent_photo = $files;
            }
        }

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
            $episode = PrEpisode::findOrFail($episodeId);

            $work = PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();

            if (!$work) {
                return response()->json([
                    'success' => false,
                    'message' => 'Design grafis work not found for this episode'
                ], 404);
            }

            $work->update([
                'status' => 'in_progress',
                'assigned_to' => Auth::id(),
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Started working on episode',
                'data' => $work->load(['episode', 'productionWork', 'promotionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept a design grafis work
     */
    public function acceptWork($id)
    {
        try {
            $work = PrDesignGrafisWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is pending'
                ], 400);
            }

            $work->update([
                'status' => 'in_progress',
                'assigned_to' => Auth::id(),
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Work accepted successfully',
                'data' => $work->fresh(['episode', 'productionWork', 'promotionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update work progress - upload thumbnail links
     */
    public function updateProgress(Request $request, $id)
    {
        $request->validate([
            'youtube_thumbnail_link' => 'nullable|string',
            'bts_thumbnail_link' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        try {
            $work = PrDesignGrafisWork::findOrFail($id);

            $updateData = $request->only([
                'youtube_thumbnail_link',
                'bts_thumbnail_link',
                'notes'
            ]);

            $work->update($updateData);

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
     * Submit completed work
     */
    public function submit($id)
    {
        try {
            DB::beginTransaction();

            $work = PrDesignGrafisWork::findOrFail($id);

            // Validate required fields
            if (empty($work->youtube_thumbnail_link) || empty($work->bts_thumbnail_link)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload both YouTube and BTS thumbnails before submitting'
                ], 400);
            }

            $work->update([
                'status' => 'pending_qc', // This relies on the migration being successful
                'submitted_at' => now()
            ]);

            // Create or update QC Work
            $qcWork = \App\Models\PrQualityControlWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id],
                ['status' => 'pending']
            );

            // Update QC Work with file locations
            $qcWork->update([
                'design_grafis_file_locations' => [
                    'youtube_thumbnail' => $work->youtube_thumbnail_link,
                    'bts_thumbnail' => $work->bts_thumbnail_link,
                ]
            ]);

            // Update any 'revision' items in checklist to 'revised'
            $checklist = $qcWork->qc_checklist;
            if (is_array($checklist)) {
                $designKeys = ['thumbnail_yt', 'thumbnail_bts', 'youtube_thumbnail', 'bts_thumbnail', 'tumneil_yt', 'tumneil_bts'];
                $updated = false;
                foreach ($checklist as $key => $item) {
                    if (in_array($key, $designKeys) && isset($item['status']) && $item['status'] === 'revision') {
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
            // But if Design resubmits, QC needs to re-check.
            if (in_array($qcWork->status, ['completed', 'approved', 'rejected'])) {
                $qcWork->update(['status' => 'pending', 'qc_results' => null, 'quality_score' => null]);
            }

            // Check if all Step 6 roles are completed
            $this->checkStep6Completion($work->pr_episode_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work submitted successfully',
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
     * Check if all Step 6 works are completed
     */
    private function checkStep6Completion($episodeId)
    {
        $episode = PrEpisode::findOrFail($episodeId);

        $editorCompleted = PrEditorWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $editorPromosiCompleted = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $designGrafisCompleted = PrDesignGrafisWork::where('pr_episode_id', $episodeId)
            ->where('status', 'submitted')
            ->exists();

        // If all three are completed, mark Step 6 as completed
        if ($editorCompleted && $editorPromosiCompleted && $designGrafisCompleted) {
            $episode->update([
                'workflow_step' => 7, // Move to next step
                'status' => 'step_6_completed'
            ]);
        }
    }
}
