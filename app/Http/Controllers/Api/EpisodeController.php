<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Services\ProgramWorkflowService;
use App\Services\WorkflowStateService;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\ProductionTeam;
use App\Models\Deadline;
use App\Models\MusicSchedule;
use App\Models\BroadcastingSchedule;
use App\Models\ProgramApproval;
use App\Models\QualityControl;
use App\Models\QualityControlWork;
use App\Models\ProductionTeamAssignment;
use App\Models\Notification;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\SoundEngineerRecording;
use App\Models\ProduksiWork;
use App\Models\EditorWork;
use App\Models\DesignGrafisWork;
use App\Models\PromotionWork;
use App\Models\BroadcastingWork;

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
        try {
            // Get per_page parameter (default: 15 untuk performa yang lebih baik)
            // Jika per_page = 0 atau 'all', return semua episodes tanpa pagination
            $perPage = $request->get('per_page', 15);
            $usePagination = !($perPage == 0 || $perPage === 'all');
            
            // Jangan pakai cache saat minta semua episode (per_page=0) agar EP 1 & episode selesai selalu tampil
            $skipCache = !$usePagination;
            
            $runQuery = function () use ($request, $perPage, $usePagination) {
                // Optimize eager loading dengan nested relations - Tambahkan Program
                $query = Episode::with([
                    'program', // Pastikan Program ter-load
                    'program.managerProgram',
                    'program.productionTeam.members.user',
                    'deadlines'
                ]);
                
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
                
                // Filter by assigned user or role (Flexible & Case-insensitive)
                if ($request->has('assigned_to_user') && $request->has('assigned_to_role')) {
                    $query->where(function($q) use ($request) {
                        $q->where('assigned_to_user', $request->assigned_to_user)
                          ->orWhereRaw('LOWER(assigned_to_role) = ?', [strtolower($request->assigned_to_role)]);
                    });
                } elseif ($request->has('assigned_to_user')) {
                    $query->where('assigned_to_user', $request->assigned_to_user);
                } elseif ($request->has('assigned_to_role')) {
                    $query->whereRaw('LOWER(assigned_to_role) = ?', [strtolower($request->assigned_to_role)]);
                }
                
                // Search
                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                    });
                }
                
                $query->orderBy('episode_number');
                
                if ($usePagination) {
                    return $query->paginate((int)$perPage);
                } else {
                    // Return all episodes (termasuk completed/aired supaya EP 1 dll tampil di Active Productions)
                    return $query->get();
                }
            };
            
            if ($skipCache) {
                $episodes = $runQuery();
            } else {
                $cacheKey = 'episodes_index_' . md5(json_encode([
                    'program_id' => $request->get('program_id'),
                    'status' => $request->get('status'),
                    'workflow_state' => $request->get('workflow_state'),
                    'assigned_to_user' => $request->get('assigned_to_user'),
                    'search' => $request->get('search'),
                    'page' => $request->get('page', 1),
                    'per_page' => $perPage
                ]));
                $episodes = \App\Helpers\QueryOptimizer::remember($cacheKey, 300, $runQuery);
            }
            
            // Handle response structure
            if ($usePagination) {
                // Pagination response (Laravel paginator)
                return response()->json([
                    'success' => true,
                    'data' => $episodes,
                    'message' => 'Episodes retrieved successfully'
                ]);
            } else {
                // Non-pagination response (collection)
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $episodes,
                        'total' => $episodes->count(),
                        'per_page' => $episodes->count(),
                        'current_page' => 1,
                        'last_page' => 1,
                        'from' => 1,
                        'to' => $episodes->count()
                    ],
                    'message' => 'Episodes retrieved successfully'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error retrieving episodes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
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
            
            // Clear cache setelah create
            QueryOptimizer::clearIndexCache('episodes');
            QueryOptimizer::clearIndexCache('programs'); // Programs juga perlu di-clear karena episodes terkait
            
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
            
            // Clear cache setelah update episode (episodes dan programs cache perlu di-clear)
            QueryOptimizer::clearAllIndexCaches();
            
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
     * Get reassignable tasks for an episode (untuk modal Reassign Task - Producer / Manager Program)
     */
    public function reassignableTasks(int $id): JsonResponse
    {
        $episode = Episode::with([
            'musicArrangements.createdBy',
            'creativeWorks.createdBy',
            'produksiWorks.createdBy',
            'editorWorks.createdBy',
            'designGrafisWorks.createdBy'
        ])->findOrFail($id);

        $tasks = [];
        $completedStatuses = ['completed', 'approved', 'arrangement_approved'];

        // Music Arrangement
        foreach ($episode->musicArrangements as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'music_arrangement',
                    'task_id' => $work->id,
                    'label' => 'Music Arrangement',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? null,
                ];
            }
        }
        // Creative Work
        foreach ($episode->creativeWorks as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'creative_work',
                    'task_id' => $work->id,
                    'label' => 'Creative',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? null,
                ];
            }
        }
        // Produksi Work
        foreach ($episode->produksiWorks as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'production_work',
                    'task_id' => $work->id,
                    'label' => 'Shooting / Production',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? null,
                ];
            }
        }
        // Editor Work
        foreach ($episode->editorWorks as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'editor_work',
                    'task_id' => $work->id,
                    'label' => 'Editing',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? null,
                ];
            }
        }
        // Design Grafis Work
        foreach ($episode->designGrafisWorks as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'design_grafis_work',
                    'task_id' => $work->id,
                    'label' => 'Design Grafis',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? null,
                ];
            }
        }
        // Quality Control Work (by episode_id)
        $qcWorks = QualityControlWork::where('episode_id', $episode->id)->whereNotIn('status', $completedStatuses)->with('createdBy')->get();
        foreach ($qcWorks as $work) {
            $tasks[] = [
                'task_type' => 'quality_control_work',
                'task_id' => $work->id,
                'label' => 'QC',
                'current_assignee_id' => $work->created_by,
                'current_assignee_name' => $work->createdBy->name ?? null,
            ];
        }
        // Broadcasting Work
        $bcWorks = BroadcastingWork::where('episode_id', $episode->id)->whereNotIn('status', $completedStatuses)->with('createdBy')->get();
        foreach ($bcWorks as $work) {
            $tasks[] = [
                'task_type' => 'broadcasting_work',
                'task_id' => $work->id,
                'label' => 'Broadcasting',
                'current_assignee_id' => $work->created_by,
                'current_assignee_name' => $work->createdBy->name ?? null,
            ];
        }
        // Promotion Work
        $promoWorks = PromotionWork::where('episode_id', $episode->id)->whereNotIn('status', $completedStatuses)->with('createdBy')->get();
        foreach ($promoWorks as $work) {
            $tasks[] = [
                'task_type' => 'promotion_work',
                'task_id' => $work->id,
                'label' => 'Promo',
                'current_assignee_id' => $work->created_by,
                'current_assignee_name' => $work->createdBy->name ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'message' => 'Reassignable tasks retrieved',
        ]);
    }

    /**
     * Handle revision request
     */
    public function handleRevision(Request $request, int $id): JsonResponse
    {
        $episode = Episode::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'revision_notes' => 'required|string|max:2000',
            'target_state' => 'required|string',
            'assigned_role' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Revert or update state to target revision state
            $workflowState = $this->workflowStateService->updateWorkflowState(
                $episode,
                $request->target_state,
                $request->assigned_role,
                null, // No specific user assigned by default on revision
                $request->revision_notes
            );
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode->fresh(),
                    'workflow_state' => $workflowState
                ],
                'message' => 'Revision handled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to handle revision',
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

    /**
     * Monitoring workflow episode
     * Menampilkan status semua tahap workflow dari awal sampai tayang
     * Accessible by ALL authenticated users (transparency)
     */
    public function monitorWorkflow(int $id): JsonResponse
    {
        $user = auth()->user();
        
        // Basic auth check only - transparency for all system users
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }
        
        try {
            $episode = Episode::with([
                'program',
                'deadlines',
                'workflowStates.assignedToUser',
                'musicArrangements',
                'creativeWorks',
                'soundEngineerRecordings',
                'editorWorks',
                'qualityControls',
                'broadcastingSchedules',
                'productionTeam.members.user',
                'designGrafisWorks',
                'promotionMaterials'
            ])->findOrFail($id);
            
            // Data Episode
            $episodeData = [
                'id' => $episode->id,
                'episode_number' => $episode->episode_number,
                'title' => $episode->title,
                'air_date' => $episode->air_date,
                'status' => $episode->status,
                'current_workflow_state' => $episode->current_workflow_state,
                'days_until_air' => now()->diffInDays($episode->air_date, false),
                'is_overdue' => now() > $episode->air_date && $episode->status !== 'aired'
            ];
            
            // Workflow Timeline - Tahap demi tahap
            $workflowSteps = [];
            
            // 1. Song Proposal
            $musicArrangement = $episode->musicArrangements()->latest()->first();
            $songProposalStatus = 'pending';
            if ($musicArrangement) {
                if ($musicArrangement->status === 'song_rejected') {
                    $songProposalStatus = 'rejected';
                } else {
                    $songProposalStatus = 'completed'; // Once created, proposal itself is done
                }
            }
            $workflowSteps['song_proposal'] = [
                'step_key' => 'song_proposal',
                'step_name' => 'Song Proposal (Music Arranger)',
                'status' => $songProposalStatus,
                'rejection_reason' => $musicArrangement->rejection_reason ?? null,
                'review_notes' => $musicArrangement->review_notes ?? null,
                'data' => $musicArrangement ? [
                    'id' => $musicArrangement->id,
                    'song_title' => $musicArrangement->song_title,
                    'created_at' => $musicArrangement->created_at,
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'musik_arr')->first()
            ];

            // 2. Song Proposal Approval
            $songApprovalStatus = 'pending';
            if ($musicArrangement) {
                if (!in_array($musicArrangement->status, ['song_proposal', 'song_rejected', 'draft'])) {
                    $songApprovalStatus = 'completed';
                } elseif ($musicArrangement->status === 'song_rejected') {
                    $songApprovalStatus = 'rejected';
                } elseif ($musicArrangement->status === 'song_proposal') {
                    $songApprovalStatus = 'in_progress';
                }
            }
            $workflowSteps['song_proposal_approval'] = [
                'step_key' => 'song_proposal_approval',
                'step_name' => 'Producer (Approve Song Proposal)',
                'status' => $songApprovalStatus,
                'rejection_reason' => $musicArrangement->rejection_reason ?? null,
                'review_notes' => $musicArrangement->review_notes ?? null,
                'data' => $musicArrangement ? [
                    'status' => $musicArrangement->status,
                    'updated_at' => $musicArrangement->updated_at,
                ] : null,
            ];

            // 3. Music Arrangement Link
            $linkStatus = 'pending';
            if ($musicArrangement) {
                if (in_array($musicArrangement->status, ['arrangement_submitted', 'arrangement_approved', 'approved'])) {
                    $linkStatus = 'completed';
                } elseif ($musicArrangement->status === 'arrangement_rejected') {
                    $linkStatus = 'rejected';
                } elseif ($musicArrangement->status === 'song_approved' || $musicArrangement->status === 'arrangement_in_progress') {
                    $linkStatus = 'in_progress';
                }
            }
            $workflowSteps['music_arrangement_link'] = [
                'step_key' => 'music_arrangement_link',
                'step_name' => 'Music Arrangement Link',
                'status' => $linkStatus,
                'rejection_reason' => $musicArrangement->rejection_reason ?? null,
                'review_notes' => $musicArrangement->review_notes ?? null,
                'data' => $musicArrangement ? [
                    'file_link' => $musicArrangement->file_link,
                    'submitted_at' => $musicArrangement->submitted_at,
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'musik_arr')->first()
            ];

            // 4. Arrangement Approval
            $arrApprovalStatus = 'pending';
            if ($musicArrangement) {
                if (in_array($musicArrangement->status, ['arrangement_approved', 'approved'])) {
                    $arrApprovalStatus = 'completed';
                } elseif ($musicArrangement->status === 'arrangement_rejected') {
                    $arrApprovalStatus = 'rejected';
                } elseif ($musicArrangement->status === 'arrangement_submitted') {
                    $arrApprovalStatus = 'in_progress';
                }
            }
            $workflowSteps['arrangement_approval'] = [
                'step_key' => 'arrangement_approval',
                'step_name' => 'Producer (Approve Arrangement)',
                'status' => $arrApprovalStatus,
                'rejection_reason' => $musicArrangement->rejection_reason ?? null,
                'review_notes' => $musicArrangement->review_notes ?? null,
                'data' => $musicArrangement ? [
                    'status' => $musicArrangement->status,
                    'approved_at' => $musicArrangement->approved_at,
                ] : null,
            ];
            
            // 5. Creative Concept
            $creativeWork = $episode->creativeWorks()->latest()->first();
            $conceptStatus = 'pending';
            if ($creativeWork) {
                $conceptStatus = 'completed'; // Created is completed for concept itself
            }
            $workflowSteps['creative_concept'] = [
                'step_key' => 'creative_concept',
                'step_name' => 'Creative Concept',
                'status' => $conceptStatus,
                'data' => $creativeWork ? [
                    'id' => $creativeWork->id,
                    'created_at' => $creativeWork->created_at,
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'kreatif')->first()
            ];

            // 6. Producer Creative Approval
            $creativeApprStatus = 'pending';
            if ($creativeWork) {
                if ($creativeWork->status === 'approved' || ($creativeWork->script_approved && $creativeWork->storyboard_approved)) {
                    $creativeApprStatus = 'completed';
                } elseif ($creativeWork->status === 'rejected') {
                    $creativeApprStatus = 'rejected';
                } elseif ($creativeWork->status === 'submitted') {
                    $creativeApprStatus = 'in_progress';
                } else {
                    $creativeApprStatus = 'draft';
                }
            }
            $workflowSteps['producer_creative_approval'] = [
                'step_key' => 'producer_creative_approval',
                'step_name' => 'Producer (Approve Creative Concept)',
                'status' => $creativeApprStatus,
                'rejection_reason' => $creativeWork->rejection_reason ?? null,
                'review_notes' => $creativeWork->review_notes ?? null,
                'data' => $creativeWork ? [
                    'status' => $creativeWork->status,
                    'updated_at' => $creativeWork->updated_at,
                ] : null,
            ];
            
            // 7. Sound Recording
            $soundRecording = $episode->soundEngineerRecordings()->latest()->first();
            $workflowSteps['vocal_recording'] = [
                'step_key' => 'vocal_recording',
                'step_name' => 'Sound Recording',
                'status' => $soundRecording ? ($soundRecording->status === 'completed' ? 'completed' : $soundRecording->status) : 'pending',
                'rejection_reason' => $soundRecording->rejection_reason ?? null,
                'review_notes' => $soundRecording->review_notes ?? null,
                'data' => $soundRecording ? [
                    'id' => $soundRecording->id,
                    'status' => $soundRecording->status,
                    'created_at' => $soundRecording->created_at,
                    'updated_at' => $soundRecording->updated_at,
                    'completed_at' => $soundRecording->completed_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'sound_eng')->first()
            ];
            
            // 8. Vocal Editing
            $soundEditing = $episode->soundEngineerEditings()->latest()->first();
            $workflowSteps['vocal_editing'] = [
                'step_key' => 'vocal_editing',
                'step_name' => 'Vocal Editing',
                'status' => $soundEditing ? ($soundEditing->status === 'approved' ? 'completed' : ($soundEditing->status === 'revision_needed' ? 'rejected' : 'in_progress')) : 'pending',
                'rejection_reason' => $soundEditing->rejection_reason ?? null,
                'review_notes' => $soundEditing->review_notes ?? null,
                'data' => $soundEditing ? [
                    'id' => $soundEditing->id,
                    'status' => $soundEditing->status,
                    'created_at' => $soundEditing->created_at,
                    'updated_at' => $soundEditing->updated_at,
                    'submitted_at' => $soundEditing->submitted_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'sound_eng')->first()
            ];
            
            // 9. Production
            $produksiWork = \App\Models\ProduksiWork::where('episode_id', $episode->id)->latest()->first();
            $workflowSteps['shooting_production'] = [
                'step_key' => 'shooting_production',
                'step_name' => 'Production',
                'status' => $produksiWork ? ($produksiWork->status === 'completed' ? 'completed' : $produksiWork->status) : 'pending',
                'rejection_reason' => $produksiWork->rejection_reason ?? null,
                'review_notes' => $produksiWork->review_notes ?? null,
                'data' => $produksiWork ? [
                    'id' => $produksiWork->id,
                    'status' => $produksiWork->status,
                    'created_at' => $produksiWork->created_at,
                    'updated_at' => $produksiWork->updated_at,
                    'completed_at' => $produksiWork->completed_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'produksi')->first()
            ];
            
            // 9. Editor
            $editorWork = $episode->editorWorks()->latest()->first();
            $workflowSteps['video_editing'] = [
                'step_key' => 'video_editing',
                'step_name' => 'Editing',
                'status' => $editorWork ? ($editorWork->status === 'completed' ? 'completed' : $editorWork->status) : 'pending',
                'rejection_reason' => $editorWork->rejection_reason ?? null,
                'review_notes' => $editorWork->review_notes ?? null,
                'data' => $editorWork ? [
                    'id' => $editorWork->id,
                    'status' => $editorWork->status,
                    'created_at' => $editorWork->created_at,
                    'updated_at' => $editorWork->updated_at,
                    'completed_at' => $editorWork->completed_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'editor')->first()
            ];
            
            // 10. Quality Control
            $qcWork = $episode->qualityControls()->latest()->first();
            $workflowSteps['quality_control'] = [
                'step_key' => 'quality_control',
                'step_name' => 'Quality Control',
                'status' => $qcWork ? ($qcWork->status === 'approved' ? 'completed' : ($qcWork->status === 'rejected' ? 'rejected' : $qcWork->status)) : 'pending',
                'rejection_reason' => $qcWork->status === 'rejected' ? $qcWork->qc_notes : null,
                'review_notes' => $qcWork->status === 'approved' ? $qcWork->qc_notes : null,
                'data' => $qcWork ? [
                    'id' => $qcWork->id,
                    'status' => $qcWork->status,
                    'quality_score' => $qcWork->quality_score,
                    'qc_notes' => $qcWork->qc_notes,
                    'created_at' => $qcWork->created_at,
                    'updated_at' => $qcWork->updated_at,
                    'qc_completed_at' => $qcWork->qc_completed_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'quality_control')->first()
            ];

            // 11. Promotion (ENHANCED)
            $promotionMaterials = $episode->promotionMaterials()->get();
            $designWorks = $episode->designGrafisWorks()->get();
            $promotionWorks = $episode->promotionWorks()->get();
            
            $promoStatus = 'pending';
            if ($promotionWorks->where('status', 'published')->count() > 0 || $promotionMaterials->whereIn('status', ['completed', 'published', 'approved'])->count() > 0) {
                 $promoStatus = 'in_progress_sharing';
                 $isAllDone = $promotionWorks->count() > 0 && $promotionWorks->every(fn($w) => in_array($w->status, ['published', 'completed']));
                 if ($isAllDone) {
                     $promoStatus = 'completed';
                 }
            } elseif ($designWorks->where('status', 'completed')->exists() || $promotionWorks->count() > 0) {
                 $promoStatus = 'in_progress';
            }
            
            $workflowSteps['promotion_sharing'] = [
                'step_key' => 'promotion_sharing',
                'step_name' => 'Promotion',
                'status' => $promoStatus,
                'data' => [
                    'design_works_count' => $designWorks->count(),
                    'promotion_materials_count' => $promotionMaterials->count(),
                    'promotion_works' => $promotionWorks->map(function($w) {
                        return [
                            'id' => $w->id,
                            'work_type' => $w->work_type,
                            'status' => $w->status,
                            'updated_at' => $w->updated_at
                        ];
                    })
                ],
                'deadline' => $episode->deadlines()->where('role', 'promotion')->first()
            ];

            // 12. Broadcasting
            $broadcastingWork = \App\Models\BroadcastingWork::where('episode_id', $episode->id)->latest()->first();
            $broadcastingStatus = 'pending';
            
            if ($episode->status === 'aired') {
                $broadcastingStatus = 'completed';
            } elseif ($broadcastingWork) {
                if ($broadcastingWork->status === 'published' || $broadcastingWork->status === 'completed') {
                     $broadcastingStatus = 'completed';
                } else {
                     $broadcastingStatus = $broadcastingWork->status;
                }
            }
            
            $workflowSteps['broadcasting_publishing'] = [
                'step_key' => 'broadcasting_publishing',
                'step_name' => 'Broadcasting',
                'status' => $broadcastingStatus,
                'data' => $broadcastingWork ? [
                    'id' => $broadcastingWork->id,
                    'status' => $broadcastingWork->status,
                    'youtube_url' => $broadcastingWork->youtube_url,
                    'website_url' => $broadcastingWork->website_url,
                    'published_time' => $broadcastingWork->published_time,
                    'created_at' => $broadcastingWork->created_at,
                    'updated_at' => $broadcastingWork->updated_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'broadcasting')->first()
            ];

            // 13. Define Workflow Order for Music Programs
            $workflowOrder = [
                'program_active',
                'song_proposal',
                'song_proposal_approval',
                'music_arrangement_link',
                'arrangement_approval',
                'creative_content',
                'producer_creative_approval',
                'vocal_recording',
                'vocal_editing',
                'video_editing',
                'quality_control',
                'distribution_manager_accept',
                'broadcasting_schedule'
            ];
            
            // Workflow Timeline (History)
            $workflowStates = $episode->workflowStates()
                ->with(['assignedToUser', 'performingUser'])
                ->orderBy('created_at')
                ->get();

            // Group action history by step
            $stepHistory = [];
            foreach ($workflowStates as $state) {
                $action = $state->metadata['action'] ?? null;
                if (!$action) continue;

                $targetStep = null;
                $isRejection = strpos($action, 'rejected') !== false;
                $isApproval = strpos($action, 'approved') !== false;

                if ($action === 'song_proposal_rejected' || $action === 'song_proposal_approved') {
                    $targetStep = 'song_proposal_approval';
                    // Duplicate to song_proposal for visibility
                    $stepHistory['song_proposal'][] = [
                        'id' => $state->id . '_dup',
                        'type' => $isRejection ? 'rejection' : ($isApproval ? 'approval' : 'action'),
                        'performed_by' => $state->performingUser ? $state->performingUser->name : 'Producer',
                        'performed_by_role' => $state->performingUser ? $state->performingUser->role : 'Producer',
                        'notes' => $state->metadata['rejection_reason'] ?? $state->metadata['review_notes'] ?? $state->metadata['notes'] ?? $state->notes,
                        'timestamp' => $state->created_at,
                        'timestamp_formatted' => $state->created_at->format('d M Y, H:i')
                    ];
                }
                elseif ($action === 'music_arrangement_rejected' || $action === 'music_arrangement_approved') {
                    $targetStep = 'arrangement_approval';
                    // Duplicate to music_arrangement_link for visibility
                    $stepHistory['music_arrangement_link'][] = [
                        'id' => $state->id . '_dup',
                        'type' => $isRejection ? 'rejection' : ($isApproval ? 'approval' : 'action'),
                        'performed_by' => $state->performingUser ? $state->performingUser->name : 'Producer',
                        'performed_by_role' => $state->performingUser ? $state->performingUser->role : 'Producer',
                        'notes' => $state->metadata['rejection_reason'] ?? $state->metadata['review_notes'] ?? $state->metadata['notes'] ?? $state->notes,
                        'timestamp' => $state->created_at,
                        'timestamp_formatted' => $state->created_at->format('d M Y, H:i')
                    ];
                }
                elseif ($action === 'creative_work_rejected' || $action === 'creative_work_approved' || $action === 'creative_approved') $targetStep = 'producer_creative_approval';
                elseif ($action === 'vocal_recording_rejected' || $action === 'vocal_recording_approved' || $action === 'recording_completed') $targetStep = 'vocal_recording';
                elseif ($action === 'vocal_editing_rejected' || $action === 'vocal_editing_approved' || $action === 'editing_approved') $targetStep = 'vocal_editing';
                elseif ($action === 'video_editing_rejected' || $action === 'video_editing_approved' || $action === 'editor_submitted') $targetStep = 'video_editing';
                elseif ($action === 'qc_rejected' || $action === 'qc_approved' || $action === 'qc_revision' || $action === 'qc_finish') $targetStep = 'quality_control';
                elseif ($action === 'distribution_manager_rejected' || $action === 'distribution_manager_approved') $targetStep = 'distribution_manager_accept';

                if ($targetStep) {
                    $stepHistory[$targetStep][] = [
                        'id' => $state->id,
                        'type' => $isRejection ? 'rejection' : ($isApproval ? 'approval' : 'action'),
                        'performed_by' => $state->performingUser ? $state->performingUser->name : 'Producer',
                        'performed_by_role' => $state->performingUser ? $state->performingUser->role : 'Producer',
                        'notes' => $state->metadata['rejection_reason'] ?? $state->metadata['review_notes'] ?? $state->metadata['notes'] ?? $state->notes,
                        'timestamp' => $state->created_at,
                        'timestamp_formatted' => $state->created_at->format('d M Y, H:i')
                    ];
                }
            }

            // Attach history to steps
            foreach ($workflowSteps as $key => &$step) {
                $step['history'] = $stepHistory[$key] ?? [];
                // Backward compatibility for UI if needed
                $step['rejection_history'] = collect($step['history'])->where('type', 'rejection')->values()->all();
            }
            unset($step);

            // Calculate Progress
            $completedSteps = collect($workflowSteps)->filter(function($step) {
                return $step['status'] === 'completed';
            })->count();
            
            $totalSteps = count($workflowSteps);
            $progressPercentage = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100, 2) : 0;
            
            // Timeline for display
            $timeline = $workflowStates->map(function($state) {
                    return [
                        'id' => $state->id,
                        'state' => $state->current_state,
                        'state_label' => $state->state_label,
                        'assigned_to_role' => $state->assigned_to_role,
                        'assigned_to_user' => $state->assignedToUser ? $state->assignedToUser->name : null,
                        'performing_user' => $state->performingUser ? $state->performingUser->name : ($state->notes ? 'System/User' : null),
                        'performing_user_role' => $state->performingUser ? $state->performingUser->role : null,
                        'notes' => $state->notes,
                        'metadata' => $state->metadata,
                        'created_at' => $state->created_at,
                        'updated_at' => $state->updated_at,
                        'timestamp_formatted' => $state->created_at->format('d M Y, H:i')
                    ];
                });
            
            // All Deadlines Summary
            $deadlinesSummary = $episode->deadlines->map(function($deadline) {
                return [
                    'id' => $deadline->id,
                    'role' => $deadline->role,
                    'role_label' => $deadline->role_label,
                    'deadline_date' => $deadline->deadline_date,
                    'status' => $deadline->status,
                    'is_completed' => $deadline->is_completed,
                    'is_overdue' => $deadline->isOverdue(),
                    'completed_at' => $deadline->completed_at
                ];
            });
            
            // Check if user is part of the team (Optional: might want to flag if they are just a viewer)
            // But for now, we return data to all auth users.
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episodeData,
                    'program' => [
                        'id' => $episode->program->id,
                        'name' => $episode->program->name
                    ],
                    'workflow_steps' => $workflowSteps,
                    'workflow_order' => $workflowOrder,
                    'progress' => [
                        'percentage' => $progressPercentage,
                        'completed_steps' => $completedSteps,
                        'total_steps' => $totalSteps
                    ],
                    'timeline' => $timeline,
                    'deadlines' => $deadlinesSummary,
                    'production_team' => $episode->productionTeam ? [
                        'id' => $episode->productionTeam->id,
                        'name' => $episode->productionTeam->name,
                        'members' => $episode->productionTeam->members->map(function($member) {
                            return [
                                'id' => $member->id,
                                'user_id' => $member->user_id,
                                'user_name' => $member->user->name ?? null,
                                'role' => $member->role
                            ];
                        })
                    ] : null
                ],
                'message' => 'Episode workflow monitoring data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow monitoring',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}








