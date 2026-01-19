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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Optimize query with eager loading
            $query = QualityControl::with([
                'episode.program.managerProgram',
                'episode.program.productionTeam.members.user',
                'createdBy',
                'qcBy'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by QC type
            if ($request->has('qc_type')) {
                $query->where('qc_type', $request->qc_type);
            }

            $controls = $query->orderBy('created_at', 'desc')->paginate(15);

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
            $control = QualityControl::with(['episode', 'createdBy', 'qcBy'])
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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $control = QualityControl::findOrFail($id);

            if ($control->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC can only be started for pending items.'
                ], 400);
            }

            $oldStatus = $control->status;
            $control->startQC($user->id);

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
                'data' => $control->load(['episode', 'createdBy', 'qcBy']),
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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'quality_score' => 'required|integer|min:1|max:100',
                'improvement_areas' => 'nullable|array',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $control = QualityControl::findOrFail($id);

            if ($control->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC must be in progress to complete.'
                ], 400);
            }

            $oldStatus = $control->status;
            $control->completeQC(
                $request->quality_score,
                $request->improvement_areas ?? [],
                $request->notes
            );

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
                'data' => $control->load(['episode', 'createdBy', 'qcBy']),
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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $control = QualityControl::findOrFail($id);

            if ($control->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC must be completed to approve.'
                ], 400);
            }

            $oldStatus = $control->status;
            $control->update([
                'status' => 'approved',
                'qc_result_notes' => $request->get('notes', 'QC Approved')
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
                'data' => $control->load(['episode', 'createdBy', 'qcBy']),
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
            
            if ($user->role !== 'Quality Control') {
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

            $control = QualityControl::findOrFail($id);

            if ($control->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'QC must be completed to reject.'
                ], 400);
            }

            $oldStatus = $control->status;
            $control->update([
                'status' => 'rejected',
                'qc_result_notes' => $request->reason
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
                'data' => $control->load(['episode', 'createdBy', 'qcBy']),
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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_qc' => QualityControl::count(),
                'pending_qc' => QualityControl::where('status', 'pending')->count(),
                'in_progress_qc' => QualityControl::where('status', 'in_progress')->count(),
                'completed_qc' => QualityControl::where('status', 'completed')->count(),
                'approved_qc' => QualityControl::where('status', 'approved')->count(),
                'rejected_qc' => QualityControl::where('status', 'rejected')->count(),
                'qc_by_type' => QualityControl::selectRaw('qc_type, count(*) as count')
                    ->groupBy('qc_type')
                    ->get(),
                'recent_qc' => QualityControl::with(['episode'])
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
            
            if ($user->role !== 'Quality Control') {
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

            $control = QualityControl::findOrFail($id);

            // Process screenshots
            $screenshots = [];
            $items = [
                'bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 
                'highlight_fb', 'thumbnail_yt', 'thumbnail_bts'
            ];

            foreach ($items as $item) {
                $notesKey = $item . '_notes';
                $screenshotKey = $item . '_screenshot';
                
                if ($request->hasFile($screenshotKey)) {
                    $file = $request->file($screenshotKey);
                    $filePath = $file->store('qc/screenshots', 'public');
                    $screenshots[$item] = [
                        'path' => $filePath,
                        'name' => $file->getClientOriginalName(),
                        'notes' => $request->get($notesKey)
                    ];
                } elseif ($request->has($notesKey) && $request->get($notesKey)) {
                    $screenshots[$item] = [
                        'notes' => $request->get($notesKey)
                    ];
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
                'qc_completed_at' => now()
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($control, 'qc_form_submitted');

            return response()->json([
                'success' => true,
                'data' => $control->load(['episode', 'createdBy', 'qcBy']),
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
            
            if ($user->role !== 'Quality Control') {
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
            
            if ($user->role !== 'Quality Control') {
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
            
            if ($user->role !== 'Quality Control') {
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
            
            if ($user->role !== 'Quality Control') {
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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'qc_notes' => 'required|string|max:5000',
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

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to submit QC form'
                ], 400);
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
                $work->markAsApproved();
                $work->update([
                    'review_notes' => $request->qc_notes,
                    'reviewed_at' => now(),
                    'reviewed_by' => $user->id
                ]);

                // Auto-create BroadcastingWork
                $this->createBroadcastingWork($work);

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
     * Helper method untuk create BroadcastingWork
     */
    private function createBroadcastingWork(QualityControlWork $qcWork): void
    {
        $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
        if ($broadcastingUsers->isNotEmpty()) {
            // Get file dari Editor
            $editorFile = null;
            if (!empty($qcWork->files_to_check)) {
                $editorFile = $qcWork->files_to_check[0] ?? null;
            }

            // Get thumbnail dari Design Grafis
            $thumbnailPath = null;
            if (!empty($qcWork->design_grafis_file_locations)) {
                $thumbnailPath = $qcWork->design_grafis_file_locations[0]['file_path'] ?? null;
            }

            $broadcastingWork = \App\Models\BroadcastingWork::create([
                'episode_id' => $qcWork->episode_id,
                'work_type' => 'main_episode',
                'title' => "Broadcasting Work - Episode {$qcWork->episode->episode_number}",
                'description' => "File materi dari QC yang telah disetujui",
                'video_file_path' => $editorFile['file_path'] ?? null,
                'thumbnail_path' => $thumbnailPath,
                'status' => 'preparing',
                'created_by' => $broadcastingUsers->first()->id
            ]);

            // Notify Broadcasting
            foreach ($broadcastingUsers as $broadcastingUser) {
                Notification::create([
                    'user_id' => $broadcastingUser->id,
                    'type' => 'broadcasting_work_assigned',
                    'title' => 'Tugas Broadcasting Baru',
                    'message' => "QC telah menyetujui materi untuk Episode {$qcWork->episode->episode_number}. Silakan proses upload ke YouTube dan website.",
                    'data' => [
                        'broadcasting_work_id' => $broadcastingWork->id,
                        'episode_id' => $qcWork->episode_id,
                        'qc_work_id' => $qcWork->id,
                        'video_file_path' => $editorFile['file_path'] ?? null,
                        'thumbnail_path' => $thumbnailPath
                    ]
                ]);
            }
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
            
            if ($user->role !== 'Quality Control') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = QualityControlWork::with(['episode'])->findOrFail($id);

            if ($work->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be completed before finalizing'
                ], 400);
            }

            if ($request->action === 'approve') {
                $work->markAsApproved();
                $work->update([
                    'review_notes' => $request->notes ?? 'QC Approved',
                    'reviewed_at' => now()
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

                // Auto-create BroadcastingWork (hanya jika ada file dari Editor atau Design Grafis)
                $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
                if ($broadcastingUsers->isNotEmpty() && ($hasEditorFiles || $hasDesignGrafisFiles)) {
                    // Get video file dari Editor
                    $videoFilePath = null;
                    if ($hasEditorFiles && isset($work->files_to_check[0]['file_path'])) {
                        $videoFilePath = $work->files_to_check[0]['file_path'];
                    }

                    // Get thumbnail dari Design Grafis (prioritaskan thumbnail_youtube)
                    $thumbnailPath = null;
                    if ($hasDesignGrafisFiles) {
                        foreach ($work->design_grafis_file_locations as $designFile) {
                            if (isset($designFile['work_type']) && $designFile['work_type'] === 'thumbnail_youtube') {
                                $thumbnailPath = $designFile['file_path'] ?? null;
                                break;
                            }
                        }
                        // Fallback ke design file pertama jika tidak ada thumbnail_youtube
                        if (!$thumbnailPath && isset($work->design_grafis_file_locations[0]['file_path'])) {
                            $thumbnailPath = $work->design_grafis_file_locations[0]['file_path'];
                        }
                    }

                    $broadcastingWork = \App\Models\BroadcastingWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => 'main_episode',
                        'title' => "Broadcasting Work - Episode {$episode->episode_number}",
                        'description' => "File materi dari QC yang telah disetujui",
                        'video_file_path' => $videoFilePath,
                        'thumbnail_path' => $thumbnailPath,
                        'status' => 'preparing',
                        'created_by' => $broadcastingUsers->first()->id
                    ]);

                    // Notify Broadcasting
                    foreach ($broadcastingUsers as $broadcastingUser) {
                        $broadcastMessage = "QC telah menyetujui materi untuk Episode {$episode->episode_number}.";
                        $broadcastData = [
                            'broadcasting_work_id' => $broadcastingWork->id,
                            'episode_id' => $work->episode_id,
                            'qc_work_id' => $work->id,
                            'has_editor_files' => $hasEditorFiles,
                            'has_design_grafis_files' => $hasDesignGrafisFiles,
                            'has_editor_promosi_files' => $hasEditorPromosiFiles
                        ];
                        
                        if ($hasEditorFiles) {
                            $broadcastMessage .= " File materi dari Editor sudah tersedia.";
                            $broadcastData['video_file_path'] = $videoFilePath;
                        }
                        
                        if ($hasDesignGrafisFiles) {
                            $broadcastMessage .= " Thumbnail dari Design Grafis sudah tersedia.";
                            $broadcastData['thumbnail_path'] = $thumbnailPath;
                        }
                        
                        if ($hasEditorPromosiFiles) {
                            $broadcastMessage .= " File dari Editor Promosi (BTS, Highlight, Iklan TV) juga sudah tersedia untuk referensi.";
                            $broadcastData['editor_promosi_work_types'] = array_unique(array_map(function($file) {
                                return $file['work_type'] ?? null;
                            }, $work->editor_promosi_file_locations));
                        }
                        
                        $broadcastMessage .= " Silakan proses upload ke YouTube dan website.";
                        
                        Notification::create([
                            'user_id' => $broadcastingUser->id,
                            'type' => 'broadcasting_work_assigned',
                            'title' => 'Tugas Broadcasting Baru',
                            'message' => $broadcastMessage,
                            'data' => $broadcastData
                        ]);
                    }

                    // Notify Promosi untuk sharing (setelah Broadcasting upload)
                    // Notifikasi ini akan dikirim saat Broadcasting complete work
                    // Tidak perlu di sini karena Broadcasting complete sudah auto-create PromotionWork untuk sharing
                }

                // Notify Promosi jika ada Editor Promosi files (untuk info saja, bukan untuk sharing langsung)
                if ($hasEditorPromosiFiles && ($broadcastingUsers->isNotEmpty() || $hasEditorFiles || $hasDesignGrafisFiles)) {
                    $promosiUsers = \App\Models\User::where('role', 'Promotion')->get();
                    foreach ($promosiUsers as $promosiUser) {
                        Notification::create([
                            'user_id' => $promosiUser->id,
                            'type' => 'qc_approved_editor_promosi_ready',
                            'title' => 'QC Approved - Editor Promosi Files Ready',
                            'message' => "QC telah menyetujui file dari Editor Promosi untuk Episode {$episode->episode_number}. Broadcasting akan upload ke YouTube dan website. Setelah Broadcasting selesai, file ini siap untuk sharing.",
                            'data' => [
                                'episode_id' => $work->episode_id,
                                'qc_work_id' => $work->id,
                                'broadcasting_work_id' => $broadcastingWork->id ?? null,
                                'editor_promosi_work_types' => array_unique(array_filter(array_map(function($file) {
                                    return $file['work_type'] ?? null;
                                }, $work->editor_promosi_file_locations)))
                            ]
                        ]);
                    }
                }

                // Notify Produksi - Baca Hasil QC
                $produksiUsers = \App\Models\User::where('role', 'Production')->get();
                foreach ($produksiUsers as $produksiUser) {
                    Notification::create([
                        'user_id' => $produksiUser->id,
                        'type' => 'qc_approved_produksi_notification',
                        'title' => 'QC Disetujui - Hasil QC Tersedia',
                        'message' => "QC telah menyetujui materi untuk Episode {$work->episode->episode_number}. Silakan baca hasil QC.",
                        'data' => [
                            'episode_id' => $work->episode_id,
                            'qc_work_id' => $work->id,
                            'quality_score' => $work->quality_score ?? null,
                            'qc_notes' => $work->qc_notes ?? null
                        ]
                    ]);
                }

            } else {
                // Reject - kembali ke role yang sesuai berdasarkan source file
                $work->markAsFailed();
                $work->update([
                    'review_notes' => $request->notes ?? 'QC Rejected',
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
                                'qc_feedback' => $request->notes,
                                'reviewed_by' => $user->id,
                                'reviewed_at' => now()
                            ]);
                        }
                    }

                    $editorUsers = \App\Models\User::where('role', 'Editor')->get();
                    foreach ($editorUsers as $editorUser) {
                        Notification::create([
                            'user_id' => $editorUser->id,
                            'type' => 'qc_rejected_revision_needed',
                            'title' => 'QC Ditolak - Perlu Revisi',
                            'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number}. Alasan: {$request->notes}",
                            'data' => [
                                'episode_id' => $work->episode_id,
                                'qc_work_id' => $work->id,
                                'editor_work_id' => $editorFile['editor_work_id'] ?? null,
                                'revision_notes' => $request->notes,
                                'qc_notes' => $work->qc_notes ?? null,
                                'source' => 'editor'
                            ]
                        ]);
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
                                    'qc_feedback' => $request->notes,
                                    'reviewed_by' => $user->id,
                                    'reviewed_at' => now()
                                ]);
                            }
                        }
                    }

                    $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
                    foreach ($designGrafisUsers as $designUser) {
                        Notification::create([
                            'user_id' => $designUser->id,
                            'type' => 'qc_rejected_revision_needed',
                            'title' => 'QC Ditolak - Perlu Revisi',
                            'message' => "QC telah menolak thumbnail untuk Episode {$work->episode->episode_number}. Alasan: {$request->notes}. Silakan perbaiki dan ajukan kembali ke QC.",
                            'data' => [
                                'episode_id' => $work->episode_id,
                                'qc_work_id' => $work->id,
                                'revision_notes' => $request->notes,
                                'source' => 'design_grafis',
                                'design_grafis_work_ids' => array_map(function($file) {
                                    return $file['design_grafis_work_id'] ?? null;
                                }, $work->design_grafis_file_locations)
                            ]
                        ]);
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
                                    'review_notes' => $request->notes,
                                    'reviewed_by' => $user->id,
                                    'reviewed_at' => now()
                                ]);
                            }
                        }
                    }

                    $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
                    foreach ($editorPromosiUsers as $editorUser) {
                        Notification::create([
                            'user_id' => $editorUser->id,
                            'type' => 'qc_rejected_revision_needed',
                            'title' => 'QC Ditolak - Perlu Revisi',
                            'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number}. Alasan: {$request->notes}. Silakan perbaiki dan ajukan kembali ke QC.",
                            'data' => [
                                'episode_id' => $work->episode_id,
                                'qc_work_id' => $work->id,
                                'revision_notes' => $request->notes,
                                'source' => 'editor_promosi',
                                'promotion_work_ids' => array_map(function($file) {
                                    return $file['promotion_work_id'] ?? null;
                                }, $work->editor_promosi_file_locations)
                            ]
                        ]);
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
                        'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number}. Alasan: {$request->notes}",
                        'data' => [
                            'episode_id' => $work->episode_id,
                            'qc_work_id' => $work->id,
                            'revision_notes' => $request->notes,
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
                        'message' => "QC telah menolak materi untuk Episode {$work->episode->episode_number} dari program '{$episode->program->name}'. Alasan: {$request->notes}. Anda dapat mengedit deadline jika diperlukan.",
                        'data' => [
                            'episode_id' => $work->episode_id,
                            'program_id' => $episode->program->id,
                            'qc_work_id' => $work->id,
                            'revision_notes' => $request->notes,
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
        foreach ($producers as $producer) {
            Notification::create([
                'title' => 'Quality Control ' . ucfirst($action),
                'message' => $messages[$action] ?? "QC {$action}",
                'type' => 'quality_control_' . $action,
                'user_id' => $producer->id,
                'episode_id' => $control->episode_id
            ]);
        }

        // Notify Broadcasting
        $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
        foreach ($broadcastingUsers as $user) {
            Notification::create([
                'title' => 'Quality Control ' . ucfirst($action),
                'message' => $messages[$action] ?? "QC {$action}",
                'type' => 'quality_control_' . $action,
                'user_id' => $user->id,
                'episode_id' => $control->episode_id
            ]);
        }
    }
}













