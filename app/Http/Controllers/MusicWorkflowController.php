<?php

namespace App\Http\Controllers;

use App\Models\MusicSubmission;
use App\Models\MusicWorkflowNotification;
use App\Services\MusicWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @property MusicWorkflowService $workflowService
 * @method MusicWorkflowService getWorkflowService()
 */
class MusicWorkflowController extends Controller
{
    /** @var MusicWorkflowService */
    protected $workflowService;

    public function __construct(\App\Services\MusicWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get current active submission for user
     */
    public function getCurrentSubmission(): JsonResponse
    {
        try {
            $user = Auth::user();
            $submission = $this->workflowService->getCurrentSubmission($user->id);

            if (!$submission) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active submission found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $submission->id,
                    'song' => [
                        'id' => $submission->song->id,
                        'title' => $submission->song->title,
                        'artist' => $submission->song->artist
                    ],
                    'music_arranger' => [
                        'id' => $submission->musicArranger->id,
                        'name' => $submission->musicArranger->name
                    ],
                    'current_state' => $submission->current_state,
                    'status_label' => $submission->status_label,
                    'status_color' => $submission->status_color,
                    'priority' => $submission->priority,
                    'requested_date' => $submission->requested_date,
                    'created_at' => $submission->created_at
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving current submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow list with filters
     */
    public function getWorkflowList(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'role', 'search', 'urgent', 'per_page']);
            $result = $this->workflowService->getWorkflowList($filters);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total_pages' => $result['total_pages'],
                    'current_page' => $result['current_page'],
                    'total' => $result['total']
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new submission
     */
    public function createSubmission(Request $request): JsonResponse
    {
        try {
            Log::info('MusicWorkflowController::createSubmission called', [
                'user_id' => Auth::id(),
                'user_role' => Auth::user()?->role,
                'request_data' => $request->all()
            ]);

            $user = Auth::user();
            
            if (!$user) {
                Log::warning('No authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }
            
            if ($user->role !== 'Music Arranger') {
                Log::warning('Unauthorized access attempt', [
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Music Arranger can create submissions.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'song_id' => 'required|exists:songs,id',
                'proposed_singer_id' => 'nullable|exists:users,id',
                'arrangement_notes' => 'nullable|string|max:1000',
                'requested_date' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only([
                'song_id', 'proposed_singer_id', 'arrangement_notes', 'requested_date'
            ]);
            $data['music_arranger_id'] = $user->id;
            $data['current_state'] = 'submitted';
            $data['submission_status'] = 'pending';
            $data['submitted_at'] = now();

            Log::info('Creating submission with data:', $data);
            
            // Create submission directly without service for now
            $submission = MusicSubmission::create($data);
            Log::info('Submission created successfully', ['submission_id' => $submission->id]);

            return response()->json([
                'success' => true,
                'message' => 'Submission created successfully.',
                'data' => $submission->load(['song', 'proposedSinger', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            Log::error('Error in createSubmission', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error creating submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update submission (Music Arranger only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::where('id', $id)
                ->where('music_arranger_id', $user->id)
                ->first();

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission not found or you do not have permission to edit it.'
                ], 404);
            }

            // Allow editing if submission is in submitted or rejected state
            if (!in_array($submission->current_state, ['submitted', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission cannot be edited in its current state. Only submitted or rejected submissions can be edited.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'song_id' => 'sometimes|required|exists:songs,id',
                'proposed_singer_id' => 'nullable|exists:users,id',
                'arrangement_notes' => 'nullable|string|max:1000',
                'requested_date' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update($request->only([
                'song_id', 'proposed_singer_id', 'arrangement_notes', 'requested_date'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Submission updated successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            Log::error('MusicWorkflowController::update error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete submission (Music Arranger only)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::where('id', $id)
                ->where('music_arranger_id', $user->id)
                ->first();

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission not found or you do not have permission to delete it.'
                ], 404);
            }

            // Only allow deletion if submission is in submitted state
            if ($submission->current_state !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission cannot be deleted in its current state: ' . $submission->current_state
                ], 400);
            }

            Log::info('Deleting submission', [
                'id' => $submission->id,
                'music_arranger_id' => $submission->music_arranger_id,
                'current_state' => $submission->current_state
            ]);

            $submission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Submission deleted successfully.'
            ]);

        } catch (Exception $e) {
            Log::error('MusicWorkflowController::destroy error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transition workflow state
     */
    public function transitionState(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'new_state' => 'required|string',
                'notes' => 'nullable|string|max:1000',
                'assigned_user_id' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $this->workflowService->transitionState(
                $id,
                $request->new_state,
                $user->id,
                $request->notes,
                $request->assigned_user_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Workflow state transitioned successfully.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error transitioning workflow state: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow history
     */
    public function getWorkflowHistory($id): JsonResponse
    {
        try {
            $history = $this->workflowService->getWorkflowHistory($id);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications for user
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->only(['type', 'unread_only', 'per_page']);
            $result = $this->workflowService->getNotifications($user->id, $filters);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total_pages' => $result['total_pages'],
                    'current_page' => $result['current_page'],
                    'total' => $result['total']
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->workflowService->markNotificationAsRead($id, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking notification as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->workflowService->markAllNotificationsAsRead($user->id);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking all notifications as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStats(): JsonResponse
    {
        try {
            $stats = $this->workflowService->getWorkflowStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed analytics
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            $analytics = $this->workflowService->getAnalytics();

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== UNIFIED DATA ENDPOINTS =====

    /**
     * Get unified songs from all sources
     */
    public function getUnifiedSongs(Request $request): JsonResponse
    {
        try {
            // Debug: Log start
            Log::info('getUnifiedSongs called', ['request' => $request->all()]);
            
            $query = \App\Models\Song::available();
            Log::info('Song query created');

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
                Log::info('Search applied', ['search' => $request->search]);
            }

            // Filter by genre
            if ($request->has('genre') && !empty($request->genre)) {
                $query->where('genre', $request->genre);
                Log::info('Genre filter applied', ['genre' => $request->genre]);
            }

            // Get per_page parameter (default 15, max 1000)
            $perPage = min((int) $request->get('per_page', 15), 1000);
            Log::info('About to paginate', ['perPage' => $perPage]);
            
            $songs = $query->orderBy('title')->paginate($perPage);
            Log::info('Songs paginated', ['count' => $songs->count()]);

            // Add audio URL to each song
            $songs->getCollection()->transform(function ($song) {
                $song->audio_file_url = $song->audio_file_path ? asset('storage/' . $song->audio_file_path) : null;
                return $song;
            });
            Log::info('Songs transformed');

            return response()->json([
                'success' => true,
                'message' => 'Unified songs retrieved successfully.',
                'data' => [
                    'songs' => $songs->items(),
                    'total' => $songs->total(),
                    'current_page' => $songs->currentPage(),
                    'last_page' => $songs->lastPage(),
                    'per_page' => $songs->perPage()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error in getUnifiedSongs: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get unified singers from all sources
     */
    public function getUnifiedSingers(Request $request): JsonResponse
    {
        try {
            // Get singers from Singer model (active status)
            $singerQuery = \App\Models\Singer::active();
            
            // Get users with Singer role
            $userQuery = \App\Models\User::where('role', 'Singer');

            // Search functionality for singers
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $singerQuery->search($search);
                $userQuery->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $singers = $singerQuery->orderBy('name')->get();
            $userSingers = $userQuery->orderBy('name')->get();

            // Merge and deduplicate by email
            $allSingers = collect();
            $emailsSeen = [];

            // Add singers from Singer model
            foreach ($singers as $singer) {
                if (!in_array($singer->email, $emailsSeen)) {
                    $allSingers->push([
                        'id' => $singer->id,
                        'name' => $singer->name,
                        'email' => $singer->email,
                        'phone' => $singer->phone,
                        'bio' => $singer->bio,
                        'specialties' => $singer->specialties,
                        'status' => $singer->status,
                        'source' => 'singer',
                        'created_at' => $singer->created_at,
                        'updated_at' => $singer->updated_at
                    ]);
                    $emailsSeen[] = $singer->email;
                }
            }

            // Add users with Singer role
            foreach ($userSingers as $user) {
                if (!in_array($user->email, $emailsSeen)) {
                    $allSingers->push([
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'bio' => null,
                        'specialties' => [],
                        'status' => 'active',
                        'source' => 'user',
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at
                    ]);
                    $emailsSeen[] = $user->email;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Unified singers retrieved successfully.',
                'data' => [
                    'singers' => $allSingers->values()->toArray(),
                    'total' => $allSingers->count(),
                    'singer_count' => $singers->count(),
                    'user_singer_count' => $userSingers->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error in getUnifiedSingers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    // ===== ROLE-SPECIFIC WORKFLOW METHODS =====

    /**
     * Music Arranger: Submit arrangement
     */
    public function submitArrangement(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->music_arranger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only arrange your own submissions.'
                ], 403);
            }

            if ($submission->current_state !== 'arranging') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in arranging state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                // Allow audio/video and documents
                'arrangement_file' => 'required|file|mimes:mp3,wav,ogg,m4a,flac,aac,mp4,webm,pdf,doc,docx,mid,midi|max:51200',
                'arrangement_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle file upload
            $file = $request->file('arrangement_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('arrangements', $filename, 'public');

            $submission->update([
                'current_state' => 'arrangement_review',
                'arrangement_file_path' => $path,
                'arrangement_file_url' => asset('storage/' . $path),
                'arrangement_notes' => $request->arrangement_notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement submitted successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Music Arranger: Start arranging (new or after rejection)
     */
    public function startArranging(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);

            if ($submission->music_arranger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only start arranging your own submissions.'
                ], 403);
            }

            // Allow starting from submitted, producer_review, arranging (if not started yet), or rejected (for re-arranging)
            if (!in_array($submission->current_state, ['submitted', 'producer_review', 'arranging', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not ready to start arranging.'
                ], 400);
            }

            // If already in arranging state and already started, no need to start again
            if ($submission->current_state === 'arranging' && $submission->arrangement_started) {
                return response()->json([
                    'success' => true,
                    'message' => 'Arranging already started.',
                    'data' => $submission->fresh(['song', 'musicArranger'])
                ]);
            }

            $submission->update([
                'current_state' => 'arranging',
                'arrangement_started' => true,
                'arrangement_started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arranging started successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting arranging: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Producer: Proses arrangement (step terpisah sebelum QC Music)
     */
    public function processArrangement(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'arrangement_review') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in arrangement review state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'producer_notes' => 'nullable|string|max:1000',
                'processing_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update using raw SQL to avoid any timestamp issues
            try {
                DB::table('music_submissions')
                    ->where('id', $submission->id)
                    ->update([
                        'current_state' => 'quality_control', // ✅ Use valid enum value
                        'producer_notes' => $request->producer_notes,
                        'processing_notes' => $request->processing_notes,
                        'processed_at' => now(),
                        'updated_at' => now()
                    ]);
            } catch (\Exception $e) {
                Log::error('Error updating music submission in processArrangement', [
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing arrangement: ' . $e->getMessage()
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Arrangement sedang diproses. Siap untuk QC Music.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer: QC Music (setelah proses)
     */
    public function qcMusic(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'producer_processing') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission must be processed first.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'qc_decision' => 'required|in:approved,needs_improvement',
                'qc_notes' => 'nullable|string|max:1000',
                'quality_score' => 'required|integer|min:1|max:10',
                'improvement_areas' => 'nullable|array',
                'improvement_areas.*' => 'string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $qcDecision = $request->qc_decision;
            
            // Sesuai flowchart: QC Music menentukan routing
            if ($qcDecision === 'approved') {
                // YES → Creative Work
                $nextState = 'creative_work';
                $message = 'QC Music completed. Arrangement approved for Creative work.';
            } else {
                // NO → Sound Engineering
                $nextState = 'sound_engineering';
                $message = 'QC Music completed. Arrangement sent to Sound Engineer for improvement.';
            }
            
            $submission->update([
                'current_state' => $nextState,
                'qc_decision' => $qcDecision,
                'quality_score' => $request->quality_score,
                'improvement_areas' => $request->improvement_areas,
                'qc_completed_at' => now(),
                'approved_at' => $qcDecision === 'approved' ? now() : null
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'submission' => $submission->fresh(['song', 'musicArranger']),
                    'qc_result' => [
                        'decision' => $qcDecision,
                        'next_state' => $nextState,
                        'quality_score' => $request->quality_score,
                        'improvement_areas' => $request->improvement_areas
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error in QC Music: ' . $e->getMessage()
            ], 500);
        }
    }

    // Note: approveArrangement dan rejectArrangement sudah tidak diperlukan
    // karena QC Music langsung menentukan routing sesuai flowchart

    /**
     * Sound Engineer: Accept work assignment
     */
    public function acceptSoundEngineeringWork($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'sound_engineering') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in sound engineering state.'
                ], 400);
            }

            // Assign to current Sound Engineer
            $submission->update([
                'assigned_sound_engineer_id' => $user->id,
                'sound_engineering_started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sound engineering work accepted successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting sound engineering work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sound Engineer: Complete sound engineering
     */
    public function completeSoundEngineering(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'sound_engineering') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in sound engineering state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'processed_audio_file' => 'required|file|mimes:mp3,wav,ogg|max:10240',
                'sound_engineering_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle file upload
            $file = $request->file('processed_audio_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('processed_audio', $filename, 'public');

            $submission->update([
                'current_state' => 'quality_control',
                'processed_audio_path' => $path,
                'processed_audio_url' => asset('storage/' . $path),
                'sound_engineering_notes' => $request->sound_engineering_notes,
                'sound_engineering_completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sound engineering completed successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing sound engineering: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer: Approve arrangement
     */
    public function approveArrangement(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'arrangement_review') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in arrangement review state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'producer_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'current_state' => 'producer_processing',
                'producer_notes' => $request->producer_notes,
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement approved. Ready for processing.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            Log::error('Error in approveArrangement', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'submission_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error approving arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer: Reject arrangement
     */
    public function rejectArrangement(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'arrangement_review') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in arrangement review state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'producer_feedback' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'current_state' => 'rejected',
                'producer_feedback' => $request->producer_feedback,
                'rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement rejected successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            Log::error('Error in rejectArrangement', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'submission_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Music Arranger: Resubmit arrangement after rejection
     * This endpoint sets status to 'pending' for frontend compatibility
     */
    public function resubmitArrangement(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->music_arranger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only resubmit your own arrangements.'
                ], 403);
            }

            // Only allow resubmission if current state is rejected
            if ($submission->current_state !== 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in rejected state. Cannot resubmit.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'arrangement_file' => 'required|file|mimes:mp3,wav,ogg,m4a,flac,aac,mp4,webm,pdf,doc,docx,mid,midi|max:51200',
                'arrangement_notes' => 'nullable|string|max:1000',
                'song_id' => 'nullable|exists:songs,id',
                'proposed_singer_id' => 'nullable|exists:users,id',
                'requested_date' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle file upload
            $file = $request->file('arrangement_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('arrangements', $filename, 'public');

            // Prepare update data
            $updateData = [
                'current_state' => 'submitted', // ✅ Kembali ke submitted
                'submission_status' => 'pending', // ✅ Status pending untuk review
                'arrangement_file_path' => $path,
                'arrangement_file_url' => asset('storage/' . $path),
                'arrangement_file_name' => $filename,
                'arrangement_notes' => $request->arrangement_notes,
                'arrangement_completed_at' => now(),
                'producer_feedback' => null, // Clear previous feedback
                'rejected_at' => null, // Clear rejection timestamp
                'submitted_at' => now(), // Update submission timestamp
                'updated_at' => now()
            ];

            // Update song if provided
            if ($request->has('song_id') && $request->song_id) {
                $updateData['song_id'] = $request->song_id;
            }

            // Update proposed singer if provided
            if ($request->has('proposed_singer_id') && $request->proposed_singer_id) {
                $updateData['proposed_singer_id'] = $request->proposed_singer_id;
            }

            // Update requested date if provided
            if ($request->has('requested_date') && $request->requested_date) {
                $updateData['requested_date'] = $request->requested_date;
            }

            // Update using raw SQL to avoid any timestamp issues
            try {
                DB::table('music_submissions')
                    ->where('id', $submission->id)
                    ->update($updateData);
            } catch (\Exception $e) {
                Log::error('Error updating music submission in resubmitArrangement', [
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error resubmitting arrangement: ' . $e->getMessage()
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Arrangement resubmitted successfully. Waiting for Producer review.',
                'data' => $submission->fresh(['song', 'musicArranger', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            Log::error('Error in resubmitArrangement', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'submission_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error resubmitting arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sound Engineer: Reject arrangement back to Music Arranger
     */
    public function rejectArrangementBackToArranger(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'sound_engineering') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in sound engineering state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'sound_engineer_feedback' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'current_state' => 'arranging',
                'sound_engineer_feedback' => $request->sound_engineer_feedback,
                'sound_engineering_rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement sent back to Music Arranger for revision.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer: Approve quality control
     */
    public function approveQuality(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'quality_control') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in quality control state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'quality_control_notes' => 'nullable|string|max:1000',
                'next_state' => 'required|in:creative_work,arranging'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'current_state' => $request->next_state,
                'quality_control_notes' => $request->quality_control_notes,
                'quality_control_approved' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quality control approved successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving quality control: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Creative: Accept work assignment
     */
    public function acceptCreativeWork($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'creative_work') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in creative work state.'
                ], 400);
            }

            // Assign to current Creative
            $submission->update([
                'assigned_creative_id' => $user->id,
                'creative_work_started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Creative work accepted successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Creative: Submit creative work
     */
    public function submitCreativeWork(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'creative_work') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in creative work state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'script_content' => 'required|string',
                'storyboard_data' => 'nullable|array',
                'recording_schedule' => 'nullable|date|after:today',
                'shooting_schedule' => 'nullable|date|after:today',
                'shooting_location' => 'nullable|string|max:255',
                'budget_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'current_state' => 'final_approval',
                'script_content' => $request->script_content,
                'storyboard_data' => $request->storyboard_data,
                'recording_schedule' => $request->recording_schedule,
                'shooting_schedule' => $request->shooting_schedule,
                'shooting_location' => $request->shooting_location,
                'budget_data' => $request->budget_data,
                'creative_work_completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Creative work submitted successfully.',
                'data' => $submission->fresh(['song', 'musicArranger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer: Final approval
     */
    public function finalApprove(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            if ($submission->current_state !== 'final_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission is not in final approval state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'final_approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'current_state' => 'completed',
                'submission_status' => 'completed',
                'final_approval_notes' => $request->final_approval_notes,
                'completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Submission approved and completed successfully.',
                'data' => $submission->fresh(['song', 'musicArranger', 'proposedSinger', 'approvedSinger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error final approving submission: ' . $e->getMessage()
            ], 500);
        }
    }
}