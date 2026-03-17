<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrDesignGrafisWork;
use App\Models\PrEditorWork;
use App\Models\PrEditorPromosiWork;
use App\Models\PrQualityControlWork;
use App\Models\PrEpisode;
use App\Models\PrEpisodeWorkflowProgress;
use App\Constants\WorkflowStep;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Constants\Role;
use App\Services\PrActivityLogService;
use App\Services\PrWorkflowService;
use App\Services\PrNotificationService;

class PrDesignGrafisController extends Controller
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
     * Get list of design grafis works with filters
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::DESIGN_GRAFIS, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::MANAGER_DISTRIBUSI])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        // SELF-HEALING: Ensure all episodes with completed Step 5 have a Design Grafis Work
        $eligibleEpisodes = PrEpisodeWorkflowProgress::where('workflow_step', 5)
            ->where('status', 'completed')
            ->pluck('episode_id')
            ->unique();

        foreach ($eligibleEpisodes as $episodeId) {
            $work = PrDesignGrafisWork::where('pr_episode_id', $episodeId)->first();
            if (!$work) {
                $produksiWork = \App\Models\PrProduksiWork::where('pr_episode_id', $episodeId)->first();
                $promosiWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episodeId)->first();

                if ($produksiWork && $promosiWork) {
                    PrDesignGrafisWork::create([
                        'pr_episode_id' => $episodeId,
                        'pr_production_work_id' => $produksiWork->id,
                        'pr_promotion_work_id' => $promosiWork->id,
                        'status' => 'pending'
                    ]);
                }
            }
        }

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
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::DESIGN_GRAFIS, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::MANAGER_DISTRIBUSI])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

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
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::DESIGN_GRAFIS, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::MANAGER_DISTRIBUSI])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

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
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::DESIGN_GRAFIS, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::MANAGER_DISTRIBUSI])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

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
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::DESIGN_GRAFIS, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::MANAGER_DISTRIBUSI])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

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
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::DESIGN_GRAFIS, Role::PROGRAM_MANAGER, Role::PRODUCER, Role::MANAGER_DISTRIBUSI])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

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


            // Log submission for QC
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'design_grafis_submitted',
                "Design Grafis (Thumbnail) submitted for QC review.",
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
            $this->notificationService->notifyQcResubmission($work->episode, 'design_grafis', $work->id);

            // Centralized logic for step 6 completion
            app(PrWorkflowService::class)->syncStepProgress($work->pr_episode_id, 6);

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

}
