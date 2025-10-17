<?php

namespace App\Http\Controllers;

use App\Models\MusicSubmission;
use App\Models\Song;
use App\Models\Singer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * @property Singer $singer
 * @method Singer getSinger()
 */
class ProducerMusicController extends BaseController
{
    /**
     * Get dashboard data for Producer
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get statistics
            $totalSubmissions = MusicSubmission::count();
            $pendingSubmissions = MusicSubmission::whereIn('current_state', [
                'submitted', 'producer_review', 'arranging', 'arrangement_review'
            ])->count();
            $completedSubmissions = MusicSubmission::where('current_state', 'completed')->count();
            $rejectedSubmissions = MusicSubmission::where('current_state', 'rejected')->count();

            // Get recent submissions
            $recentSubmissions = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger'])
                ->latest()
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => [
                        'total_submissions' => $totalSubmissions,
                        'pending_submissions' => $pendingSubmissions,
                        'completed_submissions' => $completedSubmissions,
                        'rejected_submissions' => $rejectedSubmissions
                    ],
                    'recent_submissions' => $recentSubmissions
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all music requests
     */
    public function getAllRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger', 'approvedSinger']);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('current_state', $request->status);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('song', function ($sq) use ($search) {
                        $sq->where('title', 'like', "%{$search}%")
                           ->orWhere('artist', 'like', "%{$search}%");
                    })
                    ->orWhereHas('musicArranger', function ($maq) use ($search) {
                        $maq->where('name', 'like', "%{$search}%");
                    });
                });
            }

            $submissions = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $submissions
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending requests
     */
    public function getPendingRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submissions = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger'])
                ->whereIn('current_state', ['submitted', 'producer_review'])
                ->orderBy('created_at', 'desc')
                ->get();
            
                return response()->json([
                'success' => true,
                'data' => $submissions
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pending requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved requests
     */
    public function getApprovedRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submissions = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger', 'approvedSinger'])
                ->where('current_state', 'completed')
                ->orderBy('completed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $submissions
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approved requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rejected requests
     */
    public function getRejectedRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submissions = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger'])
                ->where('current_state', 'rejected')
                ->orderBy('rejected_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $submissions
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rejected requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my requests (assigned to me)
     */
    public function getMyRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submissions = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger'])
                ->whereHas('workflowStates', function ($query) use ($user) {
                    $query->where('assigned_to_user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $submissions
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving my requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single request
     */
    public function getRequest($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::with([
                'song', 'musicArranger', 'proposedSinger', 'approvedSinger',
                'workflowHistory.actionByUser', 'workflowStates'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $submission
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed request information for Producer
     */
    public function getRequestDetail($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::with([
                'song', 'musicArranger', 'proposedSinger', 'approvedSinger', 'modifiedByProducer'
            ])->findOrFail($id);

            // Format data untuk Producer dengan informasi lengkap
            $formattedData = [
                'id' => $submission->id,
                'current_state' => $submission->current_state,
                'submission_status' => $submission->submission_status,
                'is_urgent' => $submission->is_urgent,
                'version' => $submission->version,
                
                // Data Lagu
                'song' => $submission->song ? [
                    'id' => $submission->song->id,
                    'title' => $submission->song->title,
                    'artist' => $submission->song->artist,
                    'genre' => $submission->song->genre,
                    'duration' => $submission->song->duration,
                    'key_signature' => $submission->song->key_signature,
                    'bpm' => $submission->song->bpm,
                    'notes' => $submission->song->notes,
                    'audio_file_path' => $submission->song->audio_file_path,
                    'audio_file_url' => $submission->song->audio_file_url,
                    'status' => $submission->song->status
                ] : null,
                
                // Data Music Arranger
                'music_arranger' => $submission->musicArranger ? [
                    'id' => $submission->musicArranger->id,
                    'name' => $submission->musicArranger->name,
                    'email' => $submission->musicArranger->email,
                    'phone' => $submission->musicArranger->phone,
                    'profile_picture_url' => $submission->musicArranger->profile_picture_url
                ] : null,
                
                // Data Proposed Singer
                'proposed_singer' => $submission->proposedSinger ? [
                    'id' => $submission->proposedSinger->id,
                    'name' => $submission->proposedSinger->name,
                    'email' => $submission->proposedSinger->email,
                    'phone' => $submission->proposedSinger->phone,
                    'role' => $submission->proposedSinger->role,
                    'profile_picture_url' => $submission->proposedSinger->profile_picture_url
                ] : null,
                
                // Data Approved Singer
                'approved_singer' => $submission->approvedSinger ? [
                    'id' => $submission->approvedSinger->id,
                    'name' => $submission->approvedSinger->name,
                    'email' => $submission->approvedSinger->email,
                    'phone' => $submission->approvedSinger->phone,
                    'role' => $submission->approvedSinger->role,
                    'profile_picture_url' => $submission->approvedSinger->profile_picture_url
                ] : null,
                
                // Data Arrangement
                'arrangement' => [
                    'notes' => $submission->arrangement_notes,
                    'file_path' => $submission->arrangement_file_path,
                    'file_url' => $submission->arrangement_file_url,
                    'file_name' => $submission->arrangement_file_name,
                    'started' => $submission->arrangement_started,
                    'started_at' => $submission->arrangement_started_at,
                    'completed_at' => $submission->arrangement_completed_at
                ],
                
                // Data Request
                'request' => [
                    'requested_date' => $submission->requested_date,
                    'submitted_at' => $submission->submitted_at,
                    'approved_at' => $submission->approved_at,
                    'rejected_at' => $submission->rejected_at,
                    'completed_at' => $submission->completed_at
                ],
                
                // Data Producer
                'producer' => [
                    'notes' => $submission->producer_notes,
                    'feedback' => $submission->producer_feedback,
                    'processing_notes' => $submission->processing_notes,
                    'processed_at' => $submission->processed_at,
                    'modified_by' => $submission->modifiedByProducer ? [
                        'id' => $submission->modifiedByProducer->id,
                        'name' => $submission->modifiedByProducer->name,
                        'email' => $submission->modifiedByProducer->email
                    ] : null,
                    'modified_at' => $submission->modified_at
                ],
                
                // Data QC Music
                'qc_music' => [
                    'decision' => $submission->qc_decision,
                    'quality_score' => $submission->quality_score,
                    'improvement_areas' => $submission->improvement_areas,
                    'completed_at' => $submission->qc_completed_at
                ],
                
                // Timestamps
                'timestamps' => [
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedData
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving request detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modify request (Producer can change song/singer before approval)
     */
    public function modifyRequest(Request $request, $id): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info('ProducerMusicController::modifyRequest called', [
                'id' => $id,
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
                'user_role' => auth()->user()?->role
            ]);

            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                \Illuminate\Support\Facades\Log::error('Unauthorized access to modifyRequest', [
                    'user_role' => $user->role,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::findOrFail($id);
            
            \Illuminate\Support\Facades\Log::info('Found submission for modify', [
                'submission_id' => $submission->id,
                'current_state' => $submission->current_state,
                'music_arranger_id' => $submission->music_arranger_id
            ]);

            if ($submission->current_state !== 'submitted' && $submission->current_state !== 'producer_review') {
                \Illuminate\Support\Facades\Log::error('Invalid state for modify', [
                    'submission_id' => $submission->id,
                    'current_state' => $submission->current_state
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'This submission cannot be modified in its current state.'
                ], 400);
            }

            // Normalize incoming IDs and payload before validation
            $normalized = $request->all();

            // Cast numeric fields
            if (isset($normalized['song_id'])) {
                $normalized['song_id'] = (int) $normalized['song_id'];
            }
            if (isset($normalized['proposed_singer_id']) && $normalized['proposed_singer_id'] !== null && $normalized['proposed_singer_id'] !== '') {
                $normalized['proposed_singer_id'] = (int) $normalized['proposed_singer_id'];
            }

            // Frontend might send Singer.id instead of Users.id. Map if needed.
            if (!empty($normalized['proposed_singer_id'])) {
                $proposedId = (int) $normalized['proposed_singer_id'];
                $userExists = \App\Models\User::where('id', $proposedId)->exists();
                if (!$userExists) {
                    $singerModel = Singer::find($proposedId);
                    if ($singerModel) {
                        // Try mapping by email to existing User with role Singer
                        $mappedUserId = \App\Models\User::where('email', $singerModel->email)
                            ->where('role', 'Singer')
                            ->value('id');
                        if ($mappedUserId) {
                            $normalized['proposed_singer_id'] = (int) $mappedUserId;
                            \Illuminate\Support\Facades\Log::info('Mapped Singer.id to Users.id for proposed_singer_id', [
                                'incoming_id' => $proposedId,
                                'mapped_user_id' => $mappedUserId
                            ]);
                        } else {
                            // If no user mapping found, drop the field to avoid validation error
                            unset($normalized['proposed_singer_id']);
                            \Illuminate\Support\Facades\Log::warning('Dropping proposed_singer_id - no matching Users.id found for Singer.id', [
                                'incoming_id' => $proposedId
                            ]);
                        }
                    }
                }
            }

            // Do not accept approved_singer_id directly from client (server decides)
            if (array_key_exists('approved_singer_id', $normalized)) {
                unset($normalized['approved_singer_id']);
            }

            // Merge normalized data back to request for validation/update
            $request->merge($normalized);

            $validator = Validator::make($request->all(), [
                'song_id' => 'sometimes|required|exists:songs,id',
                'proposed_singer_id' => 'nullable|exists:users,id',
                'arrangement_notes' => 'nullable|string|max:1000',
                'requested_date' => 'nullable|date|after_or_equal:today',
                'producer_notes' => 'nullable|string',
                'auto_approve' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                \Illuminate\Support\Facades\Log::error('Validation failed in modifyRequest', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update submission with Producer's modifications
            $updateData = [];
            
            if ($request->has('song_id')) {
                $updateData['song_id'] = $request->song_id;
            }
            
            if ($request->has('proposed_singer_id')) {
                $updateData['proposed_singer_id'] = $request->proposed_singer_id;
                $updateData['approved_singer_id'] = $request->proposed_singer_id; // Auto-approve the modified singer
            }
            
            if ($request->has('arrangement_notes')) {
                $updateData['arrangement_notes'] = $request->arrangement_notes;
            }
            
            if ($request->has('requested_date')) {
                $updateData['requested_date'] = $request->requested_date;
            }
            
            if ($request->has('producer_notes')) {
                $updateData['producer_notes'] = $request->producer_notes;
            }

            // Set state based on auto_approve flag
            $autoApprove = $request->boolean('auto_approve', true); // Default to true
            
            if ($autoApprove) {
                $updateData['current_state'] = 'arranging';
                $updateData['approved_at'] = now();
            } else {
                $updateData['current_state'] = 'producer_review';
            }

            $updateData['modified_by_producer'] = $user->id;
            $updateData['modified_at'] = now();

            \Illuminate\Support\Facades\Log::info('Updating submission with modify data', [
                'submission_id' => $submission->id,
                'update_data' => $updateData
            ]);

            $submission->update($updateData);

            $message = $autoApprove 
                ? 'Request modified and approved successfully.' 
                : 'Request modified successfully. Awaiting final approval.';

            \Illuminate\Support\Facades\Log::info('Submission modified successfully', [
                'submission_id' => $submission->id,
                'new_state' => $submission->current_state,
                'auto_approve' => $autoApprove
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $submission->fresh(['song', 'musicArranger', 'proposedSinger', 'approvedSinger'])
            ]);
            
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in modifyRequest', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'submission_id' => $id ?? 'unknown',
                'request_data' => $request->all() ?? []
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error modifying request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve request
     */
    public function approveRequest(Request $request, $id): JsonResponse
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

            if ($submission->current_state !== 'submitted' && $submission->current_state !== 'producer_review') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission cannot be approved in its current state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'producer_notes' => 'nullable|string',
                'approved_singer_id' => 'nullable|exists:users,id'
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
                'producer_notes' => $request->producer_notes,
                'approved_singer_id' => $request->approved_singer_id,
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully.',
                'data' => $submission->fresh(['song', 'musicArranger', 'proposedSinger', 'approvedSinger'])
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject request
     */
    public function rejectRequest(Request $request, $id): JsonResponse
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

            if ($submission->current_state === 'completed' || $submission->current_state === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission cannot be rejected in its current state.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'producer_feedback' => 'required|string'
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
                'message' => 'Request rejected successfully.',
                'data' => $submission->fresh(['song', 'musicArranger', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Take request (assign to me)
     */
    public function takeRequest($id): JsonResponse
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

            if ($submission->current_state !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission cannot be taken in its current state.'
                ], 400);
            }

            $submission->update([
                'current_state' => 'producer_review'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request taken successfully.',
                'data' => $submission->fresh(['song', 'musicArranger', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error taking request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all submissions with detailed information for Producer
     */
    public function getAllSubmissions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = MusicSubmission::with(['song', 'musicArranger', 'proposedSinger', 'approvedSinger']);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('current_state', $request->status);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('song', function ($sq) use ($search) {
                        $sq->where('title', 'like', "%{$search}%")
                           ->orWhere('artist', 'like', "%{$search}%");
                    })
                    ->orWhereHas('musicArranger', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('proposedSinger', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            }

            $perPage = $request->get('per_page', 15);
            $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Format data untuk Producer
            $formattedSubmissions = $submissions->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'current_state' => $submission->current_state,
                    'submission_status' => $submission->submission_status,
                    'is_urgent' => $submission->is_urgent,
                    'version' => $submission->version,
                    
                    // Data Lagu
                    'song' => $submission->song ? [
                        'id' => $submission->song->id,
                        'title' => $submission->song->title,
                        'artist' => $submission->song->artist,
                        'genre' => $submission->song->genre,
                        'duration' => $submission->song->duration,
                        'audio_file_url' => $submission->song->audio_file_url,
                        'status' => $submission->song->status
                    ] : null,
                    
                    // Data Music Arranger
                    'music_arranger' => $submission->musicArranger ? [
                        'id' => $submission->musicArranger->id,
                        'name' => $submission->musicArranger->name,
                        'email' => $submission->musicArranger->email,
                        'phone' => $submission->musicArranger->phone
                    ] : null,
                    
                    // Data Proposed Singer
                    'proposed_singer' => $submission->proposedSinger ? [
                        'id' => $submission->proposedSinger->id,
                        'name' => $submission->proposedSinger->name,
                        'email' => $submission->proposedSinger->email,
                        'phone' => $submission->proposedSinger->phone
                    ] : null,
                    
                    // Data Arrangement
                    'arrangement' => [
                        'notes' => $submission->arrangement_notes,
                        'file_url' => $submission->arrangement_file_url,
                        'file_name' => $submission->arrangement_file_name,
                        'started' => $submission->arrangement_started,
                        'completed_at' => $submission->arrangement_completed_at
                    ],
                    
                    // Data Request
                    'request' => [
                        'requested_date' => $submission->requested_date,
                        'submitted_at' => $submission->submitted_at,
                        'approved_at' => $submission->approved_at,
                        'rejected_at' => $submission->rejected_at
                    ],
                    
                    // Data Producer
                    'producer' => [
                        'notes' => $submission->producer_notes,
                        'feedback' => $submission->producer_feedback,
                        'processing_notes' => $submission->processing_notes
                    ],
                    
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedSubmissions,
                'pagination' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'per_page' => $submissions->perPage(),
                    'total' => $submissions->total()
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving submissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all singers
     */
    public function getSingers(Request $request): JsonResponse
    {
        try {
            $query = Singer::active();

            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            $singers = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $singers
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving singers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new singer
     */
    public function addSinger(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:singers,email',
                'phone' => 'nullable|string|max:20',
                'bio' => 'nullable|string',
                'specialties' => 'nullable|array',
                'specialties.*' => 'string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $singerData = $request->only([
                'name', 'email', 'phone', 'bio', 'specialties'
            ]);
            $singerData['created_by'] = $user->id;
            $singerData['updated_by'] = $user->id;

            $singer = Singer::create($singerData);

            return response()->json([
                'success' => true,
                'message' => 'Singer added successfully.',
                'data' => $singer
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding singer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all songs
     */
    public function getSongs(Request $request): JsonResponse
    {
        try {
            $query = Song::available();

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            // Filter by genre
            if ($request->has('genre') && !empty($request->genre)) {
                $query->where('genre', $request->genre);
            }

            // Get per_page parameter (default 15, max 1000)
            $perPage = min((int) $request->get('per_page', 15), 1000);
            $songs = $query->orderBy('title')->paginate($perPage);

            // Add audio URL to each song
            $songs->getCollection()->transform(function ($song) {
                $song->audio_file_url = $song->audio_file ? asset('storage/' . $song->audio_file) : null;
                return $song;
            });

            return response()->json([
                'success' => true,
                'data' => $songs
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving songs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new song
     */
    public function addSong(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'artist' => 'required|string|max:255',
                'genre' => 'nullable|string|max:100',
                'lyrics' => 'nullable|string',
                'duration' => 'nullable|string|max:20',
                'key_signature' => 'nullable|string|max:10',
                'bpm' => 'nullable|integer|min:1|max:300',
                'notes' => 'nullable|string',
                'status' => 'nullable|string|in:available,unavailable',
                'audio_file' => 'nullable|file|mimes:mp3,wav,ogg|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $songData = $request->only([
                'title', 'artist', 'genre', 'lyrics', 'duration',
                'key_signature', 'bpm', 'notes', 'status'
            ]);
            $songData['created_by'] = $user->id;
            $songData['updated_by'] = $user->id;
            
            // Set default status if not provided
            if (!isset($songData['status']) || empty($songData['status'])) {
                $songData['status'] = 'available';
            }

            // Handle audio file upload
            if ($request->hasFile('audio_file')) {
                $file = $request->file('audio_file');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('songs', $filename, 'public');
                
                $songData['audio_file_path'] = $path;
                $songData['audio_file_name'] = $file->getClientOriginalName();
                $songData['file_size'] = $file->getSize();
                $songData['mime_type'] = $file->getMimeType();
            }

            $song = Song::create($songData);

            return response()->json([
                'success' => true,
                'message' => 'Song added successfully.',
                'data' => [
                    'id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist,
                    'genre' => $song->genre,
                    'lyrics' => $song->lyrics,
                    'duration' => $song->duration,
                    'key_signature' => $song->key_signature,
                    'bpm' => $song->bpm,
                    'notes' => $song->notes,
                    'audio_file' => $song->audio_file,
                    'audio_file_url' => $song->audio_file ? asset('storage/' . $song->audio_file) : null,
                    'status' => $song->status,
                    'created_by' => $song->created_by,
                    'updated_by' => $song->updated_by,
                    'created_at' => $song->created_at,
                    'updated_at' => $song->updated_at
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding song: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update song
     */
    public function updateSong(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $song = Song::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'artist' => 'required|string|max:255',
                'genre' => 'nullable|string|max:100',
                'lyrics' => 'nullable|string',
                'duration' => 'nullable|string|max:20',
                'key_signature' => 'nullable|string|max:10',
                'bpm' => 'nullable|integer|min:1|max:300',
                'notes' => 'nullable|string',
                'audio_file' => 'nullable|file|mimes:mp3,wav,ogg|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $songData = $request->only([
                'title', 'artist', 'genre', 'lyrics', 'duration',
                'key_signature', 'bpm', 'notes'
            ]);
            $songData['updated_by'] = $user->id;

            // Handle audio file upload
            if ($request->hasFile('audio_file')) {
                // Delete old file
                if ($song->audio_file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($song->audio_file_path);
                }

                $file = $request->file('audio_file');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('songs', $filename, 'public');
                
                $songData['audio_file_path'] = $path;
                $songData['audio_file_name'] = $file->getClientOriginalName();
                $songData['file_size'] = $file->getSize();
                $songData['mime_type'] = $file->getMimeType();
            }

            $song->update($songData);

            return response()->json([
                'success' => true,
                'message' => 'Song updated successfully.',
                'data' => [
                    'id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist,
                    'genre' => $song->genre,
                    'lyrics' => $song->lyrics,
                    'duration' => $song->duration,
                    'key_signature' => $song->key_signature,
                    'bpm' => $song->bpm,
                    'notes' => $song->notes,
                    'audio_file' => $song->audio_file,
                    'audio_file_url' => $song->audio_file ? asset('storage/' . $song->audio_file) : null,
                    'status' => $song->status,
                    'created_by' => $song->created_by,
                    'updated_by' => $song->updated_by,
                    'created_at' => $song->created_at,
                    'updated_at' => $song->updated_at
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating song: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete song
     */
    public function deleteSong($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $song = Song::findOrFail($id);
            
            // Check if song is being used in submissions
            $submissionCount = MusicSubmission::where('song_id', $id)->count();
            if ($submissionCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete song. It is being used in ' . $submissionCount . ' submission(s).'
                ], 400);
            }

            // Delete audio file
            if ($song->audio_file_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($song->audio_file_path);
            }

            $song->delete();

            return response()->json([
                'success' => true,
                'message' => 'Song deleted successfully.'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting song: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get song audio
     */
    public function getSongAudio($id): JsonResponse
    {
        try {
            $song = Song::findOrFail($id);
            
            if (!$song->audio_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audio file not found for this song.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'audio_url' => $song->audio_url,
                    'file_name' => $song->audio_file_name,
                    'file_size' => $song->file_size,
                    'mime_type' => $song->mime_type
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving song audio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update singer
     */
    public function updateSinger(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Try to find in Singer model first
            $singer = Singer::find($id);
            
            if (!$singer) {
                // If not found in Singer model, try User model
                $singer = \App\Models\User::where('id', $id)->where('role', 'Singer')->first();
                
                if (!$singer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Singer not found.'
                    ], 404);
                }
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:singers,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'bio' => 'nullable|string',
                'specialties' => 'nullable|array',
                'specialties.*' => 'string|max:100',
                'profile_picture' => 'nullable|file|image|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $singerData = $request->only([
                'name', 'email', 'phone', 'bio', 'specialties'
            ]);
            $singerData['updated_by'] = $user->id;

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // Delete old file
                if ($singer->profile_picture) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($singer->profile_picture);
                }

                $file = $request->file('profile_picture');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('singers', $filename, 'public');
                $singerData['profile_picture'] = $path;
            }

            $singer->update($singerData);

            return response()->json([
                'success' => true,
                'message' => 'Singer updated successfully.',
                'data' => $singer
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating singer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete singer
     */
    public function deleteSinger($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Try to find in Singer model first
            $singer = Singer::find($id);
            
            if (!$singer) {
                // If not found in Singer model, try User model
                $singer = \App\Models\User::where('id', $id)->where('role', 'Singer')->first();
                
                if (!$singer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Singer not found.'
                    ], 404);
                }
            }

            // Check if singer is being used in submissions
            $submissionCount = MusicSubmission::where('proposed_singer_id', $id)
                ->orWhere('approved_singer_id', $id)
                ->count();
            
            if ($submissionCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete singer. They are being used in ' . $submissionCount . ' submission(s).'
                ], 400);
            }

            // Delete profile picture
            if ($singer->profile_picture) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($singer->profile_picture);
            }

            $singer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Singer deleted successfully.'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting singer: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== PHASE 2: CREATIVE WORKFLOW ====================

    /**
     * Get creative work for review
     * GET /api/music/producer/submissions/{id}/creative-work
     */
    public function getCreativeWorkForReview($id)
    {
        try {
            $submission = MusicSubmission::with([
                'creativeWork.creator',
                'budget',
                'schedules',
                'song',
                'musicArranger'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'submission' => $submission,
                    'creative_work' => $submission->creativeWork,
                    'budget' => $submission->budget,
                    'schedules' => $submission->schedules,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get creative work: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Review creative work (script, storyboard, budget)
     * POST /api/music/producer/submissions/{id}/review-creative-work
     */
    public function reviewCreativeWork(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'script_approved' => 'required|boolean',
            'storyboard_approved' => 'required|boolean',
            'budget_approved' => 'nullable|boolean',
            'review_notes' => 'nullable|string|max:1000',
            'budget_review_notes' => 'nullable|string|max:1000',
            'budget_edits' => 'nullable|array',
            'budget_edits.talent_budget' => 'nullable|numeric|min:0',
            'budget_edits.production_budget' => 'nullable|numeric|min:0',
            'budget_edits.other_budget' => 'nullable|numeric|min:0',
            'request_special_approval' => 'nullable|boolean',
            'special_approval_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Inject CreativeWorkflowService
        $workflowService = app(\App\Services\CreativeWorkflowService::class);
        $result = $workflowService->reviewCreativeWork($id, $request->all());

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Assign production teams (shooting, setting, recording)
     * POST /api/music/producer/submissions/{id}/assign-teams
     */
    public function assignProductionTeams(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'shooting_team_ids' => 'nullable|array',
            'shooting_team_ids.*' => 'exists:users,id',
            'shooting_team_notes' => 'nullable|string|max:500',
            'shooting_schedule_id' => 'nullable|exists:music_schedules,id',
            
            'setting_team_ids' => 'nullable|array',
            'setting_team_ids.*' => 'exists:users,id',
            'setting_team_notes' => 'nullable|string|max:500',
            
            'recording_team_ids' => 'nullable|array',
            'recording_team_ids.*' => 'exists:users,id',
            'recording_team_notes' => 'nullable|string|max:500',
            'recording_schedule_id' => 'nullable|exists:music_schedules,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Inject CreativeWorkflowService
        $workflowService = app(\App\Services\CreativeWorkflowService::class);
        $result = $workflowService->assignProductionTeams($id, $request->all());

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Cancel schedule
     * POST /api/music/producer/schedules/{id}/cancel
     */
    public function cancelSchedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Inject CreativeWorkflowService
        $workflowService = app(\App\Services\CreativeWorkflowService::class);
        $result = $workflowService->manageSchedule($id, 'cancel', $request->all());

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Reschedule
     * POST /api/music/producer/schedules/{id}/reschedule
     */
    public function rescheduleSchedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_datetime' => 'required|date|after_or_equal:today',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Inject CreativeWorkflowService
        $workflowService = app(\App\Services\CreativeWorkflowService::class);
        $result = $workflowService->manageSchedule($id, 'reschedule', $request->all());

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }
}