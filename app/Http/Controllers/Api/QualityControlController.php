<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QualityControl;
use App\Models\QualityControlWork;
use App\Models\Episode;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class QualityControlController extends Controller
{
    /**
     * Get Quality Control works for current user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $allowedQCRoles = ['Quality Control', 'QC', 'Manager Broadcasting', 'Distribution Manager'];
            if (!in_array($user->role, $allowedQCRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Optimize query with eager loading
            $query = QualityControlWork::with([
                'episode.program.managerProgram',
                'episode.program.productionTeam.members.user',
                'createdBy',
                'reviewedBy'
            ]);

            // Filter by status
            if ($request->has('status')) {
                // Support multiple statuses (comma separated)
                $statuses = explode(',', $request->status);
                if (count($statuses) > 1) {
                    $query->whereIn('status', $statuses);
                } else {
                    $query->where('status', $request->status);
                }
            }

            // Filter by QC Category (Convenience filter)
            if ($request->has('qc_category')) {
                $category = $request->qc_category;
                if ($category === 'video') {
                    $query->whereIn('qc_type', ['main_episode']);
                } elseif ($category === 'design') {
                    $query->whereIn('qc_type', ['thumbnail_yt', 'thumbnail_bts', 'graphics_ig', 'graphics_facebook']);
                } elseif ($category === 'promosi') {
                    $query->whereIn('qc_type', [
                        'bts_video', 'advertisement_tv', 'highlight_ig', 'highlight_facebook', 'highlight_tv',
                        'thumbnail_yt', 'thumbnail_bts', 'website_content', 'tiktok', 'reels_facebook', 'promotion'
                    ]);
                }
            }

            // Filter by QC type (supports multiple)
            if ($request->has('qc_type')) {
                $types = explode(',', $request->qc_type);
                if (count($types) > 1) {
                    $query->whereIn('qc_type', $types);
                } else {
                    $query->where('qc_type', $request->qc_type);
                }
            }

            $limit = $request->input('limit', 15);
            $controls = $query->orderBy('updated_at', 'desc')->orderBy('created_at', 'desc')->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $controls,
                'message' => 'Quality Control works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Quality Control works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Quality Control work by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $control = QualityControlWork::with(['episode.program', 'createdBy', 'reviewedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $control,
                'message' => 'Quality Control work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Quality Control work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start QC process
     */
    public function startQC(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $control = QualityControlWork::findOrFail($id);

            if ($control->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC can only be started for pending items.'
                ], 400);
            }

            $oldStatus = $control->status;
            
            // Use model method if available, or manual update
            $control->update([
                'status' => 'in_progress',
                'reviewed_by' => $user->id, // QualityControlWork uses reviewed_by
                'reviewed_at' => now()
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('quality_control_started', $control, [
                'episode_id' => $control->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'in_progress'
            ], $request);

            // Notify related roles
            $this->notifyRelatedRoles($control, 'qc_started');

            return response()->json([
                'success' => true,
                'data' => $control->load(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC process started successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete QC process
     */
    public function completeQC(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'quality_score' => 'required|integer|min:1|max:100',
                'improvements_needed' => 'nullable|array', // QualityControlWork uses improvements_needed
                'qc_notes' => 'nullable|string' // QualityControlWork uses qc_notes
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $control = QualityControlWork::findOrFail($id);

            if ($control->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC must be in progress to complete.'
                ], 400);
            }

            $oldStatus = $control->status;
            
            $control->update([
                'status' => 'completed',
                'quality_score' => $request->quality_score,
                'improvements_needed' => $request->improvements_needed ?? [],
                'qc_notes' => $request->qc_notes,
                'reviewed_at' => now()
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('quality_control_completed', $control, [
                'episode_id' => $control->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'completed',
                'quality_score' => $request->quality_score
            ], $request);

            // Notify related roles
            $this->notifyRelatedRoles($control, 'qc_completed');

            return response()->json([
                'success' => true,
                'data' => $control->load(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve QC
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $control = QualityControlWork::findOrFail($id);

            // QC can be approved if it is completed
            if ($control->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC must be completed to approve.'
                ], 400);
            }

            $oldStatus = $control->status;
            $control->update([
                'status' => 'approved',
                'review_notes' => $request->get('notes', 'QC Approved') // QualityControlWork uses review_notes
            ]);

            // Audit logging
            ControllerSecurityHelper::logApproval('quality_control_approved', $control, [
                'episode_id' => $control->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'approved',
                'notes' => $request->get('notes', 'QC Approved')
            ], $request);

            // Notify related roles
            $this->notifyRelatedRoles($control, 'qc_approved');

            return response()->json([
                'success' => true,
                'data' => $control->load(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject QC
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $control = QualityControlWork::findOrFail($id);

            if ($control->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC must be completed to reject.'
                ], 400);
            }

            $oldStatus = $control->status;
            $control->update([
                'status' => 'rejected', // Use rejected status
                'review_notes' => $request->reason // QualityControlWork uses review_notes
            ]);

            // Audit logging
            ControllerSecurityHelper::logApproval('quality_control_rejected', $control, [
                'episode_id' => $control->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'rejected',
                'reason' => $request->reason
            ], $request);

            // Notify related roles
            $this->notifyRelatedRoles($control, 'qc_rejected');

            return response()->json([
                'success' => true,
                'data' => $control->load(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get QC statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'QC'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $statusStats = QualityControlWork::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $pendingCounts = QualityControlWork::where('status', 'pending')
                ->selectRaw('qc_type, count(*) as count')
                ->groupBy('qc_type')
                ->pluck('count', 'qc_type');

            $designTypes = ['thumbnail_yt', 'thumbnail_bts', 'graphics_ig', 'graphics_facebook'];
            $promosiTypes = ['bts_video', 'advertisement_tv', 'highlight_ig', 'highlight_facebook', 'highlight_tv', 'thumbnail_yt', 'thumbnail_bts', 'website_content', 'tiktok', 'reels_facebook', 'promotion'];
            $videoTypes = ['main_episode'];

            $stats = [
                'total_qc' => $statusStats->sum(),
                'pending_qc' => $statusStats->get('pending', 0),
                'in_progress_qc' => $statusStats->get('in_progress', 0),
                'completed_qc' => $statusStats->get('completed', 0),
                'approved_qc' => $statusStats->get('approved', 0),
                'rejected_qc' => $statusStats->get('rejected', 0),
                
                // Granular Pending Counts for Dashboard Badges
                'video_pending' => $pendingCounts->only($videoTypes)->sum(),
                'design_pending' => $pendingCounts->only($designTypes)->sum(),
                'promosi_pending' => $pendingCounts->only($promosiTypes)->sum(),

                'qc_by_type' => QualityControlWork::selectRaw('qc_type, count(*) as count')
                    ->groupBy('qc_type')
                    ->get(),
                'recent_qc' => QualityControlWork::with(['episode.program'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'QC statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving QC statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit QC form dengan breakdown per item
     * User: "form catatan QC yang lengkap" - BTS, iklan TV, highlight IG, highlight TV, highlight FB, thumbnail YT, thumbnail BTS
     */
    public function submitQCForm(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'bts_notes' => 'nullable|string|max:1000',
                'bts_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'iklan_tv_notes' => 'nullable|string|max:1000',
                'iklan_tv_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'highlight_ig_notes' => 'nullable|string|max:1000',
                'highlight_ig_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'highlight_tv_notes' => 'nullable|string|max:1000',
                'highlight_tv_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'highlight_fb_notes' => 'nullable|string|max:1000',
                'highlight_fb_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'thumbnail_yt_notes' => 'nullable|string|max:1000',
                'thumbnail_yt_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'thumbnail_bts_notes' => 'nullable|string|max:1000',
                'thumbnail_bts_screenshot' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'overall_notes' => 'nullable|string|max:1000',
                'quality_score' => 'required|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $control = QualityControlWork::findOrFail($id);

            // Process screenshots
            $screenshots = [];
            $items = [
                'bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 
                'highlight_fb', 'thumbnail_yt', 'thumbnail_bts'
            ];

            foreach ($items as $item) {
                $notesKey = $item . '_notes';
                $notesKey = $item . '_notes';
                $screenshotLinkKey = $item . '_screenshot_link';
                
                if ($request->has($screenshotLinkKey)) {
                    $screenshots[$item] = [
                        'path' => $request->get($screenshotLinkKey), // Treat link as path
                        'name' => 'QC Screenshot Link',
                        'notes' => $request->get($notesKey)
                    ];
                } elseif ($request->has($notesKey) && $request->get($notesKey)) {
                    $screenshots[$item] = [
                        'notes' => $request->get($notesKey)
                    ];
                }

                // Physical file upload disabled
                if ($request->hasFile($item . '_screenshot')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Physical file uploads for screenshots are disabled. Please use the _screenshot_link fields.'
                    ], 405);
                }
            }

            // Build detailed checklist
            $checklist = [
                'bts' => [
                    'notes' => $request->bts_notes,
                    'screenshot' => $screenshots['bts']['path'] ?? null,
                    'checked' => $request->has('bts_notes') || isset($screenshots['bts'])
                ],
                'iklan_tv' => [
                    'notes' => $request->iklan_tv_notes,
                    'screenshot' => $screenshots['iklan_tv']['path'] ?? null,
                    'checked' => $request->has('iklan_tv_notes') || isset($screenshots['iklan_tv'])
                ],
                'highlight_ig' => [
                    'notes' => $request->highlight_ig_notes,
                    'screenshot' => $screenshots['highlight_ig']['path'] ?? null,
                    'checked' => $request->has('highlight_ig_notes') || isset($screenshots['highlight_ig'])
                ],
                'highlight_tv' => [
                    'notes' => $request->highlight_tv_notes,
                    'screenshot' => $screenshots['highlight_tv']['path'] ?? null,
                    'checked' => $request->has('highlight_tv_notes') || isset($screenshots['highlight_tv'])
                ],
                'highlight_fb' => [
                    'notes' => $request->highlight_fb_notes,
                    'screenshot' => $screenshots['highlight_fb']['path'] ?? null,
                    'checked' => $request->has('highlight_fb_notes') || isset($screenshots['highlight_fb'])
                ],
                'thumbnail_yt' => [
                    'notes' => $request->thumbnail_yt_notes,
                    'screenshot' => $screenshots['thumbnail_yt']['path'] ?? null,
                    'checked' => $request->has('thumbnail_yt_notes') || isset($screenshots['thumbnail_yt'])
                ],
                'thumbnail_bts' => [
                    'notes' => $request->thumbnail_bts_notes,
                    'screenshot' => $screenshots['thumbnail_bts']['path'] ?? null,
                    'checked' => $request->has('thumbnail_bts_notes') || isset($screenshots['thumbnail_bts'])
                ]
            ];

            // Update control
            $control->update([
                'qc_checklist' => $checklist,
                'quality_score' => $request->quality_score,
                'qc_notes' => $request->overall_notes,
                'screenshots' => $screenshots,
                'status' => 'completed',
                'qc_completed_at' => now() // Assuming we want to track this, but QualityControlWork might strictly use 'reviewed_at' by 'reviewed_by'
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($control, 'qc_form_submitted');

            return response()->json([
                'success' => true,
                'data' => $control->load(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC form submitted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting QC form: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Lokasi File dari Editor Promosi
     * POST /api/live-tv/quality-control/works/{id}/receive-editor-promosi-files
     */
    public function receiveEditorPromosiFiles(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_locations' => 'required|array|min:1',
                'file_locations.*.file_path' => 'required|string',
                'file_locations.*.file_name' => 'required|string',
                'file_locations.*.file_type' => 'nullable|string',
                'file_locations.*.notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = QualityControlWork::findOrFail($id);
            
            $work->update([
                'editor_promosi_file_locations' => $request->file_locations
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Editor Promosi file locations received successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error receiving files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Lokasi File dari Design Grafis
     * POST /api/live-tv/quality-control/works/{id}/receive-design-grafis-files
     */
    public function receiveDesignGrafisFiles(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_locations' => 'required|array|min:1',
                'file_locations.*.file_path' => 'required|string',
                'file_locations.*.file_name' => 'required|string',
                'file_locations.*.file_type' => 'nullable|string',
                'file_locations.*.notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = QualityControlWork::findOrFail($id);
            
            $work->update([
                'design_grafis_file_locations' => $request->file_locations
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Design Grafis file locations received successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error receiving files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - QC terima pekerjaan
     * POST /api/live-tv/quality-control/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = QualityControlWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is pending'
                ], 400);
            }

            $work->markAsInProgress();
            $work->update(['reviewed_by' => $user->id]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'Work accepted successfully. You can now proceed with QC.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * QC Berbagai Konten - QC video BTS, iklan episode TV, dll
     * POST /api/live-tv/quality-control/works/{id}/qc-content
     */
    public function qcContent(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'qc_results' => 'required|array',
                'qc_results.bts_video' => 'nullable|array',
                'qc_results.bts_video.status' => 'nullable|in:approved,rejected',
                'qc_results.bts_video.notes' => 'nullable|string',
                'qc_results.bts_video.score' => 'nullable|integer|min:0|max:100',
                'qc_results.iklan_episode_tv' => 'nullable|array',
                'qc_results.iklan_highlight_episode_ig' => 'nullable|array',
                'qc_results.highlight_episode_tv' => 'nullable|array',
                'qc_results.highlight_episode_face' => 'nullable|array',
                'qc_results.thumbnail_yt' => 'nullable|array',
                'qc_results.thumbnail_bts' => 'nullable|array',
                'overall_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = QualityControlWork::findOrFail($id);

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to perform QC'
                ], 400);
            }

            // Calculate overall quality score
            $scores = [];
            foreach ($request->qc_results as $type => $result) {
                if (isset($result['score'])) {
                    $scores[] = $result['score'];
                }
            }
            $overallScore = !empty($scores) ? round(array_sum($scores) / count($scores)) : null;

            $work->update([
                'qc_results' => $request->qc_results,
                'quality_score' => $overallScore ?? $work->quality_score,
                'qc_notes' => $request->overall_notes ?? $work->qc_notes,
                'status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC content completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Isi Form Catatan QC - Untuk QualityControlWork
     * POST /api/live-tv/quality-control/works/{id}/submit-qc-form
     */
    public function submitQCFormForWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'qc_notes' => 'nullable|string|max:5000',
                'quality_score' => 'nullable|integer|min:0|max:100',
                'issues_found' => 'nullable|array',
                'improvements_needed' => 'nullable|array',
                'no_revision_needed' => 'nullable|boolean', // Tidak ada revisi - Yes
                'qc_checklist' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = QualityControlWork::findOrFail($id);

            // Izinkan submit form ketika status masih pending / in_progress / completed
            if (!in_array($work->status, ['pending', 'in_progress', 'completed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress or pending to submit QC form'
                ], 400);
            }

            // Jika masih pending, anggap QC baru mulai sekarang
            if ($work->status === 'pending') {
                $work->update([
                    'status' => 'in_progress',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            // Update work dengan form QC
            $work->update([
                'qc_notes' => $request->qc_notes,
                'quality_score' => $request->quality_score ?? $work->quality_score,
                'issues_found' => $request->issues_found ?? $work->issues_found,
                'improvements_needed' => $request->improvements_needed ?? $work->improvements_needed,
                'qc_checklist' => $request->qc_checklist ?? $work->qc_checklist,
                'status' => 'completed' // Setelah isi form, status menjadi completed (siap untuk approve/reject)
            ]);

            // Jika tidak ada revisi, auto-approve
            if ($request->no_revision_needed === true) {
                // Auto-approve logic
                $this->performQCApproval($work, $user, $request->qc_notes);

                return response()->json([
                    'success' => true,
                    'data' => $work->fresh(['episode', 'createdBy', 'reviewedBy']),
                    'message' => 'QC form submitted and approved (no revision needed). Broadcasting has been notified.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy', 'reviewedBy']),
                'message' => 'QC form submitted successfully. You can now approve or reject.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting QC form: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to perform all actions associated with QC approval
     */
    private function performQCApproval(QualityControlWork $work, $user, ?string $notes): void
    {
        $work->markAsApproved();
        $work->update([
            'review_notes' => $notes ?? 'QC Approved',
            'reviewed_at' => now(),
            'reviewed_by' => $user->id
        ]);

        $episode = $work->episode;
        $hasEditorFiles = !empty($work->files_to_check);
        $hasDesignGrafisFiles = !empty($work->design_grafis_file_locations);
        $hasEditorPromosiFiles = !empty($work->editor_promosi_file_locations);

        // Update DesignGrafisWork status menjadi approved
        if ($hasDesignGrafisFiles) {
            foreach ($work->design_grafis_file_locations as $designFile) {
                if (isset($designFile['design_grafis_work_id'])) {
                    $designGrafisWork = \App\Models\DesignGrafisWork::find($designFile['design_grafis_work_id']);
                    if ($designGrafisWork) {
                        $designGrafisWork->update([
                            'status' => 'approved',
                            'reviewed_by' => $user->id,
                            'reviewed_at' => now()
                        ]);
                    }
                }
            }
        }

        // Update Editor Promosi PromotionWork status menjadi approved
        if ($hasEditorPromosiFiles) {
            foreach ($work->editor_promosi_file_locations as $editorPromosiFile) {
                if (isset($editorPromosiFile['promotion_work_id'])) {
                    $promotionWork = \App\Models\PromotionWork::find($editorPromosiFile['promotion_work_id']);
                    if ($promotionWork) {
                        $promotionWork->update([
                            'status' => 'approved',
                            'reviewed_by' => $user->id,
                            'reviewed_at' => now()
                        ]);
                    }
                }
            }
        }

        // Update EditorWork status (main editor)
        if ($hasEditorFiles) {
            foreach ($work->files_to_check as $editorFile) {
                if (isset($editorFile['editor_work_id'])) {
                    $editorWork = \App\Models\EditorWork::find($editorFile['editor_work_id']);
                    if ($editorWork) {
                        $editorWork->update([
                            'status' => 'approved',
                            'reviewed_by' => $user->id,
                            'reviewed_at' => now()
                        ]);
                    }
                }
            }
        }

        // Auto-create BroadcastingWork jika ada file dari Editor, Design Grafis, atau Editor Promosi (QC Promosi)
        $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
        $broadcastingWork = null;
        $createForBroadcasting = $broadcastingUsers->isNotEmpty() && ($hasEditorFiles || $hasDesignGrafisFiles || $hasEditorPromosiFiles);

        if ($createForBroadcasting) {
            // Get video file dari Editor (main episode)
            $videoFilePath = null;
            if ($hasEditorFiles && isset($work->files_to_check[0]['file_path'])) {
                $videoFilePath = $work->files_to_check[0]['file_path'];
            }
            if ($videoFilePath === null && $hasEditorFiles && isset($work->files_to_check[0]['file_link'])) {
                $videoFilePath = $work->files_to_check[0]['file_link'];
            }

            // Get thumbnail: Design Grafis (thumbnail YT/BTS) atau dari Editor Promosi
            $thumbnailPath = null;
            if ($hasDesignGrafisFiles) {
                foreach ($work->design_grafis_file_locations as $designFile) {
                    if (isset($designFile['work_type']) && in_array($designFile['work_type'] ?? '', ['thumbnail_youtube', 'thumbnail_yt', 'thumbnail_bts'])) {
                        $thumbnailPath = $designFile['file_path'] ?? $designFile['file_link'] ?? null;
                        break;
                    }
                }
                if (!$thumbnailPath && isset($work->design_grafis_file_locations[0]['file_path'])) {
                    $thumbnailPath = $work->design_grafis_file_locations[0]['file_path'] ?? $work->design_grafis_file_locations[0]['file_link'] ?? null;
                }
            }
            if (!$thumbnailPath && $hasEditorPromosiFiles) {
                foreach ($work->editor_promosi_file_locations ?? [] as $epFile) {
                    if (isset($epFile['work_type']) && (str_contains(strtolower($epFile['work_type'] ?? ''), 'thumbnail') || in_array($epFile['work_type'] ?? '', ['thumbnail_yt', 'thumbnail_bts']))) {
                        $thumbnailPath = $epFile['file_path'] ?? $epFile['file_link'] ?? null;
                        break;
                    }
                }
            }

            // QC-approved work: status 'pending' agar Broadcasting bisa langsung Terima Pekerjaan (tanpa approval DM)
            $broadcastingWork = \App\Models\BroadcastingWork::create([
                'episode_id' => $work->episode_id,
                'work_type' => 'main_episode',
                'title' => "Broadcasting Work - Episode {$episode->episode_number}",
                'description' => "File materi dari QC (Manager Broadcasting) & thumbnail dari QC Promosi yang telah disetujui.",
                'video_file_path' => $videoFilePath,
                'file_link' => (is_string($videoFilePath) && (str_starts_with($videoFilePath, 'http://') || str_starts_with($videoFilePath, 'https://'))) ? $videoFilePath : null,
                'thumbnail_path' => $thumbnailPath,
                'status' => 'pending',
                'created_by' => $broadcastingUsers->first()->id
            ]);

            $broadcastMessage = "Terima File materi dari QC (Manager Broadcasting). Terima thumbnail dari QC Promosi. Episode #{$episode->episode_number} telah disetujui QC. Silakan Terima Pekerjaan → Proses → Jadwal Playlist, Upload YouTube (thumbnail, deskripsi, tag, judul SEO), Upload Website, input link YT, Selesaikan Pekerjaan.";
            $broadcastData = [
                'broadcasting_work_id' => $broadcastingWork->id,
                'episode_id' => $work->episode_id,
                'qc_work_id' => $work->id,
                'has_editor_files' => $hasEditorFiles,
                'has_design_grafis_files' => $hasDesignGrafisFiles,
                'has_editor_promosi_files' => $hasEditorPromosiFiles,
                'video_file_path' => $videoFilePath,
                'thumbnail_path' => $thumbnailPath,
            ];
            if ($hasEditorPromosiFiles) {
                $broadcastData['editor_promosi_work_types'] = array_values(array_unique(array_filter(array_map(function ($f) {
                    return $f['work_type'] ?? null;
                }, $work->editor_promosi_file_locations ?? []))));
            }

            foreach ($broadcastingUsers as $broadcastingUser) {
                Notification::create([
                    'user_id' => $broadcastingUser->id,
                    'type' => 'broadcasting_work_assigned',
                    'title' => 'Terima File materi dari QC (Manager Broadcasting) & Terima thumbnail dari QC Promosi',
                    'message' => $broadcastMessage,
                    'episode_id' => $work->episode_id,
                    'data' => $broadcastData,
                ]);
            }
        }

        // Notify Promosi – Terima Link YouTube/Website nanti setelah Broadcasting selesai
        if ($hasEditorPromosiFiles) {
            $promosiUsers = \App\Models\User::whereIn('role', ['Promotion', 'Promosi'])->get();
            foreach ($promosiUsers as $promosiUser) {
                Notification::create([
                    'user_id' => $promosiUser->id,
                    'type' => 'qc_approved_editor_promosi_ready',
                    'title' => 'QC Promosi Disetujui – Siap Terima Link',
                    'message' => "QC telah menyetujui file dari Editor Promosi untuk Episode #{$episode->episode_number}. Setelah Broadcasting selesai: Terima Link YouTube, Terima Link Website, Share ke Facebook/IG/WA.",
                    'episode_id' => $work->episode_id,
                    'data' => [
                        'episode_id' => $work->episode_id,
                        'qc_work_id' => $work->id,
                        'broadcasting_work_id' => $broadcastingWork->id ?? null,
                        'editor_promosi_work_types' => array_values(array_unique(array_filter(array_map(function ($f) {
                            return $f['work_type'] ?? null;
                        }, $work->editor_promosi_file_locations ?? [])))),
                    ],
                ]);
            }
        }

        // Notify Produksi – Baca Hasil QC
        $produksiUsers = \App\Models\User::whereIn('role', ['Production', 'Produksi'])->get();
        foreach ($produksiUsers as $produksiUser) {
            Notification::create([
                'user_id' => $produksiUser->id,
                'type' => 'qc_approved_produksi_notification',
                'title' => 'QC Disetujui – Hasil QC Tersedia',
                'message' => "QC telah menyetujui materi untuk Episode #{$episode->episode_number}. Silakan baca hasil QC.",
                'episode_id' => $work->episode_id,
                'data' => [
                    'episode_id' => $work->episode_id,
                    'qc_work_id' => $work->id,
                    'quality_score' => $work->quality_score ?? null,
                    'qc_notes' => $work->qc_notes ?? null,
                ],
            ]);
        }
    }

    /**
     * Selesaikan Pekerjaan - Approve atau Reject QC
     * POST /api/live-tv/quality-control/works/{id}/finalize
     */
    public function finalize(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string',
                'review_notes' => 'nullable|string',
                'rejection_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $notes = $request->notes ?? $request->review_notes ?? ($request->action === 'reject' ? $request->rejection_reason : null);

            $work = QualityControlWork::with(['episode'])->findOrFail($id);

            if (!in_array($work->status, ['completed', 'in_progress'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress or completed before finalizing'
                ], 400);
            }

            // One-click Approve: jika status in_progress, set completed dulu lalu approve
            if ($work->status === 'in_progress' && $request->action === 'approve') {
                $work->update([
                    'status' => 'completed',
                    'qc_notes' => $notes ?? 'QC Approved',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            if ($request->action === 'approve') {
                $this->performQCApproval($work, $user, $notes);
            } else {
                // Reject - kembali ke Editor Promosi / Design Grafis
                $work->markAsFailed();
                $work->update([
                    'review_notes' => $notes ?? 'QC Rejected',
                    'reviewed_at' => now(),
                    'status' => 'revision_needed'
                ]);

                // Deteksi source file untuk menentukan role yang perlu diberi notifikasi
                $hasDesignGrafisFiles = !empty($work->design_grafis_file_locations);
                $hasEditorPromosiFiles = !empty($work->editor_promosi_file_locations);
                $hasEditorFiles = !empty($work->files_to_check); // File dari Editor (main editor)

                // Jika ada file dari Editor (main editor), notifikasi ke Editor dan update EditorWork status
                if ($hasEditorFiles) {
                    // Update EditorWork status menjadi rejected/revision_needed
                    $editorFile = $work->files_to_check[0] ?? null;
                    if ($editorFile && isset($editorFile['editor_work_id'])) {
                        $editorWork = \App\Models\EditorWork::find($editorFile['editor_work_id']);
                        if ($editorWork) {
                            $editorWork->update([
                                'status' => 'rejected',
                                'qc_feedback' => $notes,
                                'reviewed_by' => $user->id,
                                'reviewed_at' => now()
                            ]);
                        }
                    }

                    $editorUsers = \App\Models\User::where('role', 'Editor')->get();
                    $editorNotifications = [];
                    $now = now();
                    foreach ($editorUsers as $editorUser) {
                        $editorNotifications[] = [
                            'user_id' => $editorUser->id,
                            'type' => 'qc_rejected_revision_needed',
                            'title' => 'QC Ditolak - Perlu Revisi',
                            'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number}. Alasan: " . ($notes ?? ''),
                            'data' => json_encode([
                                'episode_id' => $work->episode_id,
                                'qc_work_id' => $work->id,
                                'editor_work_id' => $editorFile['editor_work_id'] ?? null,
                                'revision_notes' => $notes,
                                'qc_notes' => $work->qc_notes ?? null,
                                'source' => 'editor'
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    if (!empty($editorNotifications)) {
                        Notification::insert($editorNotifications);
                    }
                }

                // Jika ada file dari Design Grafis, notifikasi ke Design Grafis dan update status
                if ($hasDesignGrafisFiles) {
                    // Update DesignGrafisWork status menjadi rejected/revision_needed
                    foreach ($work->design_grafis_file_locations as $designFile) {
                        if (isset($designFile['design_grafis_work_id'])) {
                            $designGrafisWork = \App\Models\DesignGrafisWork::find($designFile['design_grafis_work_id']);
                            if ($designGrafisWork) {
                                $designGrafisWork->update([
                                    'status' => 'revision_needed', // Kembali ke Design Grafis untuk revisi
                                    'qc_feedback' => $notes,
                                    'reviewed_by' => $user->id,
                                    'reviewed_at' => now()
                                ]);
                            }
                        }
                    }

                    $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
                    $designNotifications = [];
                    $now = now();
                    foreach ($designGrafisUsers as $designUser) {
                        $designNotifications[] = [
                            'user_id' => $designUser->id,
                            'type' => 'qc_rejected_revision_needed',
                            'title' => 'QC Ditolak - Perlu Revisi',
'message' => "QC telah menolak thumbnail untuk Episode {$work->episode->episode_number}. Alasan: " . ($notes ?? '') . ". Silakan perbaiki dan ajukan kembali ke QC.",
                    'data' => json_encode([
                        'episode_id' => $work->episode_id,
                        'qc_work_id' => $work->id,
                        'revision_notes' => $notes,
                                'source' => 'design_grafis',
                                'design_grafis_work_ids' => array_map(function($file) {
                                    return $file['design_grafis_work_id'] ?? null;
                                }, $work->design_grafis_file_locations)
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    if (!empty($designNotifications)) {
                        Notification::insert($designNotifications);
                    }
                }

                // Jika ada file dari Editor Promosi, notifikasi ke Editor Promosi dan update status
                if ($hasEditorPromosiFiles) {
                    // Update PromotionWork status menjadi rejected/editing (kembali ke Editor Promosi untuk revisi)
                    foreach ($work->editor_promosi_file_locations as $editorPromosiFile) {
                        if (isset($editorPromosiFile['promotion_work_id'])) {
                            $promotionWork = \App\Models\PromotionWork::find($editorPromosiFile['promotion_work_id']);
                            if ($promotionWork) {
                                $promotionWork->update([
                                    'status' => 'editing', // Kembali ke Editor Promosi untuk revisi (status: editing)
                                    'review_notes' => $notes,
                                    'reviewed_by' => $user->id,
                                    'reviewed_at' => now()
                                ]);
                            }
                        }
                    }

                    $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
                    $promosiNotifications = [];
                    $now = now();
                    foreach ($editorPromosiUsers as $editorUser) {
                        $promosiNotifications[] = [
                            'user_id' => $editorUser->id,
                            'type' => 'qc_rejected_revision_needed',
                            'title' => 'QC Ditolak - Perlu Revisi',
                            'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number}. Alasan: " . ($notes ?? '') . ". Silakan perbaiki dan ajukan kembali ke QC.",
                            'data' => json_encode([
                                'episode_id' => $work->episode_id,
                                'qc_work_id' => $work->id,
                                'revision_notes' => $notes,
                                'source' => 'editor_promosi',
                                'promotion_work_ids' => array_map(function($file) {
                                    return $file['promotion_work_id'] ?? null;
                                }, $work->editor_promosi_file_locations)
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    if (!empty($promosiNotifications)) {
                        Notification::insert($promosiNotifications);
                    }
                }

                // Notifikasi ke Producer dengan catatan QC
                $episode = $work->episode;
                $productionTeam = $episode->program->productionTeam;
                if ($productionTeam && $productionTeam->producer) {
                    Notification::create([
                        'user_id' => $productionTeam->producer_id,
                        'type' => 'qc_rejected_producer_notification',
                        'title' => 'QC Ditolak - Perlu Revisi',
                        'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number}. Alasan: " . ($notes ?? ''),
                        'data' => [
                            'episode_id' => $work->episode_id,
                            'qc_work_id' => $work->id,
                            'revision_notes' => $notes,
                            'qc_notes' => $work->qc_notes ?? null,
                            'quality_score' => $work->quality_score ?? null
                        ]
                    ]);
                }

                // Notifikasi ke Manager Program ketika ada perbaikan di QC
                // Manager Program dapat mengedit deadline jika ada kebutuhan khusus
                if ($episode->program && $episode->program->manager_program_id) {
                    Notification::create([
                        'user_id' => $episode->program->manager_program_id,
                        'type' => 'qc_rejected_manager_notification',
                        'title' => 'QC Ditolak - Perlu Perbaikan',
                        'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number} dari program '{$episode->program->name}'. Alasan: " . ($notes ?? '') . ". Anda dapat mengedit deadline jika diperlukan.",
                        'data' => [
                            'episode_id' => $work->episode_id,
                            'program_id' => $episode->program->id,
                            'qc_work_id' => $work->id,
                            'revision_notes' => $notes,
                            'qc_notes' => $work->qc_notes ?? null,
                            'quality_score' => $work->quality_score ?? null,
                            'program_name' => $episode->program->name
                        ],
                        'program_id' => $episode->program->id,
                        'priority' => 'high'
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy', 'reviewedBy']),
                'message' => $request->action === 'approve' 
                    ? 'QC approved successfully. Broadcasting, Promosi, and Produksi have been notified.'
                    : 'QC rejected. Editor/Design Grafis/Editor Promosi and Producer have been notified for revision.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error finalizing QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify related roles about QC
     */
    private function notifyRelatedRoles(QualityControl $control, string $action): void
    {
        $messages = [
            'qc_started' => "QC for episode {$control->episode->episode_number} has been started",
            'qc_completed' => "QC for episode {$control->episode->episode_number} has been completed",
            'qc_approved' => "QC for episode {$control->episode->episode_number} has been approved",
            'qc_rejected' => "QC for episode {$control->episode->episode_number} has been rejected",
            'qc_form_submitted' => "QC form with detailed breakdown for episode {$control->episode->episode_number} has been submitted"
        ];

        // Notify Producer
        $producers = \App\Models\User::where('role', 'Producer')->get();
        $producerNotifications = [];
        $now = now();
        foreach ($producers as $producer) {
            $producerNotifications[] = [
                'title' => 'Quality Control ' . ucfirst($action),
                'message' => $messages[$action] ?? "QC {$action}",
                'type' => 'quality_control_' . $action,
                'user_id' => $producer->id,
                'episode_id' => $control->episode_id,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => json_encode(['episode_id' => $control->episode_id, 'action' => $action]) // Minimal data
            ];
        }

        if (!empty($producerNotifications)) {
            Notification::insert($producerNotifications);
        }

        // Notify Broadcasting
        $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
        $broadcastingNotifications = [];
        foreach ($broadcastingUsers as $user) {
            $broadcastingNotifications[] = [
                'title' => 'Quality Control ' . ucfirst($action),
                'message' => $messages[$action] ?? "QC {$action}",
                'type' => 'quality_control_' . $action,
                'user_id' => $user->id,
                'episode_id' => $control->episode_id,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => json_encode(['episode_id' => $control->episode_id, 'action' => $action])
            ];
        }

        if (!empty($broadcastingNotifications)) {
            Notification::insert($broadcastingNotifications);
        }
    }
}













