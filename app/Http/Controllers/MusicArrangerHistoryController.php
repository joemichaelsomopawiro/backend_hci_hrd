<?php

namespace App\Http\Controllers;

use App\Models\MusicSubmission;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class MusicArrangerHistoryController extends BaseController
{
    /**
     * Get all submissions for Music Arranger with filtering and pagination
     */
    public function getSubmissions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Music Arranger can access this endpoint.'
                ], 403);
            }

            $query = MusicSubmission::with([
                'song',
                'proposedSinger',
                'approvedSinger'
            ])->where('music_arranger_id', $user->id);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                if ($request->status === 'arranging') {
                    // Include tasks that are ready for arranging or being arranged
                    $query->whereIn('submission_status', [
                        'arranging', 
                        'arrangement_review', 
                        'awaiting_arrangement'
                    ]);
                } elseif ($request->status === 'approved') {
                    // Include only approved tasks
                    $query->whereIn('submission_status', ['approved', 'ready_for_arranging']);
                } else {
                    $query->where('submission_status', $request->status);
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('song', function ($sq) use ($search) {
                        $sq->where('title', 'like', "%{$search}%")
                           ->orWhere('artist', 'like', "%{$search}%");
                    })
                    ->orWhereHas('proposedSinger', function ($psq) use ($search) {
                        $psq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('arrangement_notes', 'like', "%{$search}%");
                });
            }

            // Date filtering
            if ($request->has('date_filter')) {
                switch ($request->date_filter) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereMonth('created_at', now()->month)
                              ->whereYear('created_at', now()->year);
                        break;
                }
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $submissions = $query->paginate($perPage);

            // Transform data for frontend
            $transformedSubmissions = collect($submissions->items())->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'song' => [
                        'id' => $submission->song->id,
                        'title' => $submission->song->title,
                        'artist' => $submission->song->artist,
                        'duration' => $submission->song->duration,
                        'audio_url' => $submission->song->audio_url
                    ],
                    'proposed_singer' => $submission->proposedSinger ? [
                        'id' => $submission->proposedSinger->id,
                        'name' => $submission->proposedSinger->name
                    ] : null,
                    'approved_singer' => $submission->approvedSinger ? [
                        'id' => $submission->approvedSinger->id,
                        'name' => $submission->approvedSinger->name
                    ] : null,
                    'submission_status' => $submission->submission_status ?? 'draft',
                    'current_state' => $submission->current_state,
                    'arrangement_notes' => $submission->arrangement_notes,
                    'producer_notes' => $submission->producer_notes,
                    'producer_feedback' => $submission->producer_feedback,
                    'requested_date' => $submission->requested_date,
                    'submitted_at' => $submission->submitted_at,
                    'approved_at' => $submission->approved_at,
                    'rejected_at' => $submission->rejected_at,
                    'completed_at' => $submission->completed_at,
                    'version' => $submission->version ?? 1,
                    'available_actions' => $this->getAvailableActions($submission),
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedSubmissions,
                'pagination' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'per_page' => $submissions->perPage(),
                    'total' => $submissions->total(),
                    'from' => $submissions->firstItem(),
                    'to' => $submissions->lastItem()
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
     * Get single submission details
     */
    public function getSubmission($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::with([
                'song',
                'proposedSinger',
                'approvedSinger'
            ])->where('id', $id)
              ->where('music_arranger_id', $user->id)
              ->first();

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission not found.'
                ], 404);
            }

            $transformedSubmission = [
                'id' => $submission->id,
                'song' => [
                    'id' => $submission->song->id,
                    'title' => $submission->song->title,
                    'artist' => $submission->song->artist,
                    'duration' => $submission->song->duration,
                    'audio_url' => $submission->song->audio_url
                ],
                'proposed_singer' => $submission->proposedSinger ? [
                    'id' => $submission->proposedSinger->id,
                    'name' => $submission->proposedSinger->name
                ] : null,
                'approved_singer' => $submission->approvedSinger ? [
                    'id' => $submission->approvedSinger->id,
                    'name' => $submission->approvedSinger->name
                ] : null,
                'submission_status' => $submission->submission_status ?? 'draft',
                'current_state' => $submission->current_state,
                'arrangement_notes' => $submission->arrangement_notes,
                'producer_notes' => $submission->producer_notes,
                'producer_feedback' => $submission->producer_feedback,
                'requested_date' => $submission->requested_date,
                'submitted_at' => $submission->submitted_at,
                'approved_at' => $submission->approved_at,
                'rejected_at' => $submission->rejected_at,
                'completed_at' => $submission->completed_at,
                'version' => $submission->version ?? 1,
                'available_actions' => $this->getAvailableActions($submission),
                'created_at' => $submission->created_at,
                'updated_at' => $submission->updated_at
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedSubmission
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update submission (only for draft and rejected status)
     */
    public function updateSubmission(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
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
                    'message' => 'Submission not found.'
                ], 404);
            }

            // Check if submission can be edited
            $currentStatus = $submission->submission_status ?? 'draft';
            if (!in_array($currentStatus, ['draft', 'pending', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission cannot be edited in its current status.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'song_id' => 'required|exists:songs,id',
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

            DB::beginTransaction();

            $submission->update([
                'song_id' => $request->song_id,
                'proposed_singer_id' => $request->proposed_singer_id,
                'arrangement_notes' => $request->arrangement_notes,
                'requested_date' => $request->requested_date,
                'submission_status' => 'draft' // Reset to draft after editing
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Submission updated successfully.',
                'data' => $submission->fresh(['song', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete submission (only for draft status)
     */
    public function deleteSubmission($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
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
                    'message' => 'Submission not found.'
                ], 404);
            }

            // Check if submission can be deleted
            $currentStatus = $submission->submission_status ?? 'draft';
            if (!in_array($currentStatus, ['draft', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft and pending submissions can be deleted.'
                ], 400);
            }

            DB::beginTransaction();

            // Delete related files if any
            if ($submission->arrangement_file_path) {
                Storage::disk('public')->delete($submission->arrangement_file_path);
            }

            $submission->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Submission deleted successfully.'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit submission to producer
     */
    public function submitSubmission($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
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
                    'message' => 'Submission not found.'
                ], 404);
            }

            // Check if submission can be submitted
            $currentStatus = $submission->submission_status ?? 'draft';
            if (!in_array($currentStatus, ['draft', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This submission cannot be submitted in its current status.'
                ], 400);
            }

            DB::beginTransaction();

            $submission->update([
                'submission_status' => 'pending',
                'submitted_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Submission sent to producer successfully.',
                'data' => $submission->fresh(['song', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error submitting submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel submission (only for pending status)
     */
    public function cancelSubmission($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
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
                    'message' => 'Submission not found.'
                ], 404);
            }

            // Check if submission can be cancelled
            $currentStatus = $submission->submission_status ?? 'draft';
            if ($currentStatus !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending submissions can be cancelled.'
                ], 400);
            }

            DB::beginTransaction();

            $submission->update([
                'submission_status' => 'draft'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Submission cancelled successfully.',
                'data' => $submission->fresh(['song', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resubmit rejected submission
     */
    public function resubmitSubmission(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
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
                    'message' => 'Submission not found.'
                ], 404);
            }

            // Check if submission can be resubmitted
            $currentStatus = $submission->submission_status ?? 'draft';
            if ($currentStatus !== 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only rejected submissions can be resubmitted.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'song_id' => 'required|exists:songs,id',
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

            DB::beginTransaction();

            // Create new version
            $newSubmission = MusicSubmission::create([
                'music_arranger_id' => $user->id,
                'song_id' => $request->song_id,
                'proposed_singer_id' => $request->proposed_singer_id,
                'arrangement_notes' => $request->arrangement_notes,
                'requested_date' => $request->requested_date,
                'submission_status' => 'pending',
                'submitted_at' => now(),
                'version' => ($submission->version ?? 1) + 1,
                'parent_submission_id' => $submission->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Submission resubmitted successfully.',
                'data' => $newSubmission->fresh(['song', 'proposedSinger'])
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error resubmitting submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download submission files
     */
    public function downloadFiles($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
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
                    'message' => 'Submission not found.'
                ], 404);
            }

            $files = [];

            if ($submission->arrangement_file_path) {
                $files['arrangement_file'] = [
                    'name' => 'Arrangement File',
                    'url' => Storage::url($submission->arrangement_file_path),
                    'path' => $submission->arrangement_file_path
                ];
            }

            if ($submission->processed_audio_path) {
                $files['processed_audio'] = [
                    'name' => 'Processed Audio',
                    'url' => Storage::url($submission->processed_audio_path),
                    'path' => $submission->processed_audio_path
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $files
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available actions based on submission status
     */
    private function getAvailableActions($submission): array
    {
        $status = $submission->submission_status ?? 'draft';
        
        switch ($status) {
            case 'draft':
                return ['edit', 'delete', 'submit'];
            case 'pending':
                return ['edit', 'delete', 'cancel', 'view'];
            case 'under_review':
                return ['view', 'add_comment'];
            case 'approved':
                return ['view', 'track_progress'];
            case 'rejected':
                return ['edit', 'resubmit', 'view_feedback'];
            case 'completed':
                return ['view', 'download'];
            default:
                return ['view'];
        }
    }
}