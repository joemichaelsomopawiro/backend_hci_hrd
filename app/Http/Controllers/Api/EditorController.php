<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditorWork;
use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class EditorController extends Controller
{
    /**
     * Get editor works
     */
    public function index(Request $request): JsonResponse
    {
        $query = EditorWork::with(['episode', 'createdBy', 'reviewedBy']);
        
        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }
        
        // Filter by work type
        if ($request->has('work_type')) {
            $query->where('work_type', $request->work_type);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }
        
        $works = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $works,
            'message' => 'Editor works retrieved successfully'
        ]);
    }

    /**
     * Get editor work by ID
     */
    public function show(int $id): JsonResponse
    {
        $work = EditorWork::with(['episode', 'createdBy', 'reviewedBy'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $work,
            'message' => 'Editor work retrieved successfully'
        ]);
    }

    /**
     * Create editor work
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'work_type' => 'required|in:main_episode,bts,highlight_ig,highlight_tv,highlight_facebook,advertisement',
            'editing_notes' => 'nullable|string',
            'source_files' => 'nullable|array',
            'file_notes' => 'nullable|string',
            'created_by' => 'required|exists:users,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work = EditorWork::create([
                'episode_id' => $request->episode_id,
                'work_type' => $request->work_type,
                'editing_notes' => $request->editing_notes,
                'source_files' => $request->source_files,
                'file_notes' => $request->file_notes,
                'file_complete' => false,
                'status' => 'draft',
                'created_by' => $request->created_by
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Editor work created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create editor work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update editor work
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $work = EditorWork::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'editing_notes' => 'nullable|string',
            'source_files' => 'nullable|array',
            'file_notes' => 'nullable|string',
            'file_complete' => 'nullable|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Editor work updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update editor work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit editor work for review
     */
    public function submit(int $id): JsonResponse
    {
        $work = EditorWork::findOrFail($id);
        
        if ($work->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft works can be submitted'
            ], 400);
        }
        
        try {
            $work->submitForReview();
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Editor work submitted for review successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit editor work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve editor work
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $work = EditorWork::findOrFail($id);
        
        if ($work->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted works can be approved'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'review_notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->approve(auth()->id(), $request->review_notes);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Editor work approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve editor work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject editor work
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $work = EditorWork::findOrFail($id);
        
        if ($work->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted works can be rejected'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->reject(auth()->id(), $request->rejection_reason);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Editor work rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject editor work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get editor work file URL
     */
    public function getFileUrl(int $id): JsonResponse
    {
        $work = EditorWork::findOrFail($id);
        $url = $work->file_url;
        
        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
            'message' => 'Editor work file URL retrieved successfully'
        ]);
    }

    /**
     * Get editor works by episode
     */
    public function getByEpisode(int $episodeId): JsonResponse
    {
        try {
            $works = EditorWork::where('episode_id', $episodeId)
                ->with(['createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Editor works by episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get editor works by episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get editor works by work type
     */
    public function getByWorkType(string $workType): JsonResponse
    {
        try {
            $works = EditorWork::where('work_type', $workType)
                ->with(['episode', 'createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Editor works by work type retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get editor works by work type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get editor works by status
     */
    public function getByStatus(string $status): JsonResponse
    {
        try {
            $works = EditorWork::where('status', $status)
                ->with(['episode', 'createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Editor works by status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get editor works by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report missing files to Producer
     * User: "dapat ajukan file tidak lengkap kepada Producer"
     */
    public function reportMissingFiles(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'missing_files' => 'required|array|min:1',
                'missing_files.*' => 'required|string',
                'notes' => 'nullable|string|max:1000',
                'urgency' => 'nullable|in:low,medium,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            // Update work with missing files info
            $work->update([
                'file_complete' => false,
                'file_notes' => ($work->file_notes ? $work->file_notes . "\n\n" : '') . 
                               "MISSING FILES REPORTED: " . implode(', ', $request->missing_files) .
                               ($request->notes ? "\nNotes: {$request->notes}" : ''),
                'status' => 'file_incomplete'
            ]);

            // Notify Producer
            $this->notifyProducerMissingFiles($work, $request->missing_files, $request->notes, $request->urgency ?? 'medium');

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->load(['episode', 'createdBy']),
                    'missing_files' => $request->missing_files,
                    'notes' => $request->notes
                ],
                'message' => 'Missing files reported successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting missing files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Producer about missing files
     */
    private function notifyProducerMissingFiles($work, array $missingFiles, ?string $notes, string $urgency): void
    {
        $producerUsers = \App\Models\User::where('role', 'Producer')->get();
        
        foreach ($producerUsers as $producer) {
            \App\Models\Notification::create([
                'title' => 'File Tidak Lengkap - Editor',
                'message' => "Editor melaporkan file tidak lengkap untuk Episode {$work->episode->episode_number}. File yang kurang: " . implode(', ', $missingFiles),
                'type' => 'editor_missing_files',
                'user_id' => $producer->id,
                'episode_id' => $work->episode_id,
                'data' => [
                    'work_id' => $work->id,
                    'work_type' => $work->work_type,
                    'missing_files' => $missingFiles,
                    'notes' => $notes,
                    'urgency' => $urgency
                ]
            ]);
        }
    }

    /**
     * Get editor work statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $episodeId = $request->get('episode_id');
            
            $query = EditorWork::query();
            
            if ($userId) {
                $query->where('created_by', $userId);
            }
            
            if ($episodeId) {
                $query->where('episode_id', $episodeId);
            }
            
            $statistics = [
                'total' => $query->count(),
                'draft' => $query->where('status', 'draft')->count(),
                'editing' => $query->where('status', 'editing')->count(),
                'completed' => $query->where('status', 'completed')->count(),
                'reviewed' => $query->where('status', 'reviewed')->count(),
                'approved' => $query->where('status', 'approved')->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Editor work statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get editor work statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}














