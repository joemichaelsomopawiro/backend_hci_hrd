<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QualityControl;
use App\Models\QualityControlWork;
use App\Models\Episode;
use App\Models\Notification;
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

            $query = QualityControl::with(['episode', 'createdBy', 'qcBy']);

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

            $control->startQC($user->id);

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

            $control->completeQC(
                $request->quality_score,
                $request->improvement_areas ?? [],
                $request->notes
            );

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

            $control->update([
                'status' => 'approved',
                'qc_result_notes' => $request->get('notes', 'QC Approved')
            ]);

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

            $control->update([
                'status' => 'rejected',
                'qc_result_notes' => $request->reason
            ]);

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













