<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SoundEngineerRecording;
use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SoundEngineerController extends Controller
{
    /**
     * Get sound engineer recordings
     */
    public function index(Request $request): JsonResponse
    {
        $query = SoundEngineerRecording::with(['episode', 'createdBy', 'reviewedBy']);
        
        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }
        
        $recordings = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $recordings,
            'message' => 'Sound engineer recordings retrieved successfully'
        ]);
    }

    /**
     * Get sound engineer recording by ID
     */
    public function show(int $id): JsonResponse
    {
        $recording = SoundEngineerRecording::with(['episode', 'createdBy', 'reviewedBy'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $recording,
            'message' => 'Sound engineer recording retrieved successfully'
        ]);
    }

    /**
     * Create sound engineer recording
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'recording_notes' => 'nullable|string',
            'equipment_used' => 'nullable|array',
            'recording_schedule' => 'nullable|date',
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
            $recording = SoundEngineerRecording::create([
                'episode_id' => $request->episode_id,
                'recording_notes' => $request->recording_notes,
                'equipment_used' => $request->equipment_used,
                'recording_schedule' => $request->recording_schedule,
                'status' => 'draft',
                'created_by' => $request->created_by
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Sound engineer recording created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sound engineer recording',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sound engineer recording
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $recording = SoundEngineerRecording::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'recording_notes' => 'nullable|string',
            'equipment_used' => 'nullable|array',
            'recording_schedule' => 'nullable|date'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $recording->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Sound engineer recording updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sound engineer recording',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start recording
     */
    public function startRecording(int $id): JsonResponse
    {
        $recording = SoundEngineerRecording::findOrFail($id);
        
        if ($recording->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft recordings can be started'
            ], 400);
        }
        
        try {
            $recording->startRecording();
            
            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Recording started successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start recording',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete recording
     */
    public function completeRecording(int $id): JsonResponse
    {
        $recording = SoundEngineerRecording::findOrFail($id);
        
        if ($recording->status !== 'recording') {
            return response()->json([
                'success' => false,
                'message' => 'Only active recordings can be completed'
            ], 400);
        }
        
        try {
            $recording->completeRecording();
            
            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Recording completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete recording',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Review recording
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $recording = SoundEngineerRecording::findOrFail($id);
        
        if ($recording->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed recordings can be reviewed'
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
            $recording->review(auth()->id(), $request->review_notes);
            
            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Recording reviewed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review recording',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recording file URL
     */
    public function getFileUrl(int $id): JsonResponse
    {
        $recording = SoundEngineerRecording::findOrFail($id);
        $url = $recording->file_url;
        
        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
            'message' => 'Recording file URL retrieved successfully'
        ]);
    }

    /**
     * Get recordings by episode
     */
    public function getByEpisode(int $episodeId): JsonResponse
    {
        try {
            $recordings = SoundEngineerRecording::where('episode_id', $episodeId)
                ->with(['createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $recordings,
                'message' => 'Recordings by episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recordings by episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recordings by status
     */
    public function getByStatus(string $status): JsonResponse
    {
        try {
            $recordings = SoundEngineerRecording::where('status', $status)
                ->with(['episode', 'createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $recordings,
                'message' => 'Recordings by status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recordings by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recording statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $episodeId = $request->get('episode_id');
            
            $query = SoundEngineerRecording::query();
            
            if ($userId) {
                $query->where('created_by', $userId);
            }
            
            if ($episodeId) {
                $query->where('episode_id', $episodeId);
            }
            
            $statistics = [
                'total' => $query->count(),
                'draft' => $query->where('status', 'draft')->count(),
                'recording' => $query->where('status', 'recording')->count(),
                'completed' => $query->where('status', 'completed')->count(),
                'reviewed' => $query->where('status', 'reviewed')->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Recording statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recording statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}














