<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Services\ProgramWorkflowService;
use App\Services\WorkflowStateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EpisodeController extends Controller
{
    protected $programWorkflowService;
    protected $workflowStateService;

    public function __construct(ProgramWorkflowService $programWorkflowService, WorkflowStateService $workflowStateService)
    {
        $this->programWorkflowService = $programWorkflowService;
        $this->workflowStateService = $workflowStateService;
    }

    /**
     * Get all episodes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Episode::with(['program', 'deadlines', 'workflowStates']);
        
        // Filter by program
        if ($request->has('program_id')) {
            $query->where('program_id', $request->program_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by workflow state
        if ($request->has('workflow_state')) {
            $query->where('current_workflow_state', $request->workflow_state);
        }
        
        // Filter by assigned user
        if ($request->has('assigned_to_user')) {
            $query->where('assigned_to_user', $request->assigned_to_user);
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $episodes = $query->orderBy('episode_number')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $episodes,
            'message' => 'Episodes retrieved successfully'
        ]);
    }

    /**
     * Create single episode manually
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'program_id' => 'required|exists:programs,id',
            'episode_number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'air_date' => 'required|date|after_or_equal:today',
            'status' => 'sometimes|in:planning,in_progress,completed,cancelled'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Check if episode number already exists for this program
            $exists = Episode::where('program_id', $request->program_id)
                ->where('episode_number', $request->episode_number)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode number already exists for this program'
                ], 422);
            }
            
            // Create episode
            $episode = Episode::create([
                'program_id' => $request->program_id,
                'episode_number' => $request->episode_number,
                'title' => $request->title,
                'description' => $request->description,
                'air_date' => $request->air_date,
                'status' => $request->status ?? 'planning'
            ]);
            
            // Generate deadlines automatically
            $episode->generateDeadlines();
            
            // Load relationships
            $episode->load(['program', 'deadlines']);
            
            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode created successfully with auto-generated deadlines'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get episode by ID
     */
    public function show(int $id): JsonResponse
    {
        $episode = Episode::with([
            'program',
            'deadlines',
            'workflowStates.assignedToUser',
            'mediaFiles',
            'musicArrangements',
            'creativeWorks',
            'productionEquipment',
            'soundEngineerRecordings',
            'editorWorks',
            'designGrafisWorks',
            'promotionMaterials',
            'broadcastingSchedules',
            'qualityControls',
            'budgets'
        ])->findOrFail($id);
        
        $progress = $this->programWorkflowService->getEpisodeProgress($episode);
        
        return response()->json([
            'success' => true,
            'data' => [
                'episode' => $episode,
                'progress' => $progress
            ],
            'message' => 'Episode retrieved successfully'
        ]);
    }

    /**
     * Update episode
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'rundown' => 'nullable|string',
            'script' => 'nullable|string',
            'talent_data' => 'nullable|array',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'production_notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update episode workflow state
     */
    public function updateWorkflowState(Request $request, int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'new_state' => 'required|string',
            'assigned_role' => 'required|string',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if transition is valid
        if (!$this->workflowStateService->isValidTransition($episode->current_workflow_state, $request->new_state)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid workflow state transition',
                'current_state' => $episode->current_workflow_state,
                'requested_state' => $request->new_state,
                'allowed_transitions' => $this->workflowStateService->getNextPossibleStates($episode->current_workflow_state)
            ], 400);
        }
        
        try {
            $workflowState = $this->workflowStateService->updateWorkflowState(
                $episode,
                $request->new_state,
                $request->assigned_role,
                $request->assigned_user_id,
                $request->notes
            );
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode->fresh(),
                    'workflow_state' => $workflowState
                ],
                'message' => 'Episode workflow state updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update episode workflow state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete episode
     */
    public function complete(int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        
        if ($episode->status === 'aired') {
            return response()->json([
                'success' => false,
                'message' => 'Episode is already completed'
            ], 400);
        }
        
        try {
            $episode = $this->programWorkflowService->completeEpisode($episode);
            
            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episode workflow history
     */
    public function workflowHistory(int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        $history = $this->workflowStateService->getWorkflowHistory($episode);
        
        return response()->json([
            'success' => true,
            'data' => $history,
            'message' => 'Episode workflow history retrieved successfully'
        ]);
    }

    /**
     * Get episode current workflow state
     */
    public function currentWorkflowState(int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        $currentState = $this->workflowStateService->getCurrentWorkflowState($episode);
        
        return response()->json([
            'success' => true,
            'data' => $currentState,
            'message' => 'Episode current workflow state retrieved successfully'
        ]);
    }

    /**
     * Get episode progress
     */
    public function progress(int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        $progress = $this->programWorkflowService->getEpisodeProgress($episode);
        
        return response()->json([
            'success' => true,
            'data' => $progress,
            'message' => 'Episode progress retrieved successfully'
        ]);
    }

    /**
     * Get episode deadlines
     */
    public function deadlines(int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        $deadlines = $episode->deadlines()->with('completedBy')->get();
        
        return response()->json([
            'success' => true,
            'data' => $deadlines,
            'message' => 'Episode deadlines retrieved successfully'
        ]);
    }

    /**
     * Get episode media files
     */
    public function mediaFiles(int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        $mediaFiles = $episode->mediaFiles()->with('uploadedBy')->get();
        
        return response()->json([
            'success' => true,
            'data' => $mediaFiles,
            'message' => 'Episode media files retrieved successfully'
        ]);
    }

    /**
     * Get episode by workflow state
     */
    public function byWorkflowState(string $state): JsonResponse
    {
        $episodes = $this->workflowStateService->getEpisodesByWorkflowState($state);
        
        return response()->json([
            'success' => true,
            'data' => $episodes,
            'message' => 'Episodes by workflow state retrieved successfully'
        ]);
    }

    /**
     * Get user workflow tasks
     */
    public function userTasks(int $userId): JsonResponse
    {
        $tasks = $this->workflowStateService->getUserWorkflowTasks($userId);
        
        return response()->json([
            'success' => true,
            'data' => $tasks,
            'message' => 'User workflow tasks retrieved successfully'
        ]);
    }
}








