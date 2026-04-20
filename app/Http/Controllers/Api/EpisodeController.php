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
            'program',
            'musicArrangements.createdBy',
            'creativeWorks.createdBy',
            'produksiWorks.createdBy',
            'editorWorks.createdBy',
            'designGrafisWorks.createdBy',
            'editorPromosiWorks.createdBy',
            'soundEngineerRecordings.createdBy',
            'qualityControlWorks.createdBy',
            'broadcastingWorks.createdBy',
            'promotionWorks.createdBy',
            'deadlines.assignee'
        ])->findOrFail($id);

        $tasks = [];
        $completedStatuses = ['approved', 'completed', 'verified', 'arrangement_approved'];
        $isMusicProgram = in_array(strtolower($episode->program->category ?? ''), ['musik', 'music']);

        // 1. Collect Existing Work Records
        
        // Music Arrangement
        foreach ($episode->musicArrangements as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'music_arrangement',
                    'task_id' => $work->id,
                    'label' => 'Music Arrangement (Active)',
                    'role_key' => 'musik_arr',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? 'Unassigned',
                ];
            }
        }
        
        // Creative Work
        foreach ($episode->creativeWorks as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'creative_work',
                    'task_id' => $work->id,
                    'label' => 'Creative (Active)',
                    'role_key' => 'kreatif',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? 'Unassigned',
                ];
            }
        }
        // Produksi (Setting & Syuting)
        foreach ($episode->produksiWorks as $work) {
            if ($work->status !== 'completed' && $work->status !== 'approved') {
                $tasks[] = [
                    'task_type' => 'produksi_work',
                    'task_id' => $work->id,
                    'label' => 'Tim Produksi (Active)',
                    'role_key' => 'tim_syuting_coord',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? 'Unassigned',
                ];
            }
        }

        // Editor
        foreach ($episode->editorWorks as $work) {
            if (!in_array($work->status ?? '', $completedStatuses)) {
                $tasks[] = [
                    'task_type' => 'editor_work',
                    'task_id' => $work->id,
                    'label' => 'Editor (Active)',
                    'role_key' => 'editor',
                    'current_assignee_id' => $work->created_by,
                    'current_assignee_name' => $work->createdBy->name ?? 'Unassigned',
                ];
            }
        }

        // Add other active works if present
        $activeWorkChecks = [
            ['rel' => 'soundEngineerRecordings', 'type' => 'sound_engineer_recording', 'label' => 'Recording (Active)', 'key' => 'sound_eng'],
            ['rel' => 'qualityControlWorks', 'type' => 'quality_control_work', 'label' => 'QC (Active)', 'key' => 'quality_control'],
            ['rel' => 'designGrafisWorks', 'type' => 'design_grafis_work', 'label' => 'Graphic Design (Active)', 'key' => 'design_grafis'],
            ['rel' => 'broadcastingWorks', 'type' => 'broadcasting_work', 'label' => 'Broadcasting (Active)', 'key' => 'broadcasting'],
            ['rel' => 'promotionWorks', 'type' => 'promotion_work', 'label' => 'Promotion (Active)', 'key' => 'promotion'],
            ['rel' => 'editorPromosiWorks', 'type' => 'editor_promosi_work', 'label' => 'Editor Promosi (Active)', 'key' => 'editor_promosi'],
        ];

        foreach ($activeWorkChecks as $check) {
            if (isset($episode->{$check['rel']})) {
                foreach ($episode->{$check['rel']} as $work) {
                    if (!in_array($work->status ?? '', $completedStatuses)) {
                        $tasks[] = [
                            'task_type' => $check['type'],
                            'task_id' => $work->id,
                            'label' => $check['label'],
                            'role_key' => $check['key'],
                            'current_assignee_id' => $work->created_by ?? $work->assigned_user_id,
                            'current_assignee_name' => ($work->createdBy->name ?? $work->assignee->name ?? 'Unassigned'),
                        ];
                    }
                }
            }
        }

        // 3. Add Pending Tasks from Deadlines table
        $existingRoleKeys = collect($tasks)->pluck('role_key')->toArray();
        
        $user = auth()->user();
        $isProgramManager = $user && in_array($user->role, ['Program Manager', 'Manager Program']);

        foreach ($episode->deadlines as $deadline) {
            // Check authorization for Producer role (Only PM can reassign)
            if ($deadline->role === 'producer' && !$isProgramManager) {
                continue;
            }

            // For Music Programs, we are extremely permissive
            // We only skip if an ACTIVE version of this role already exists
            $isProduksiSpecial = in_array($deadline->role, ['tim_setting_coord', 'tim_syuting_coord', 'tim_vocal_coord']);
            
            if ($isMusicProgram || $isProduksiSpecial) {
                // Avoid adding the exact same role twice if it's already active
                if (!in_array($deadline->role, $existingRoleKeys) || $isProduksiSpecial) {
                    $tasks[] = [
                        'task_type' => 'deadline',
                        'task_id' => $deadline->id,
                        'role_key' => $deadline->role,
                        'label' => $deadline->role_label . ' (Pending)',
                        'current_assignee_id' => $deadline->assigned_user_id,
                        'current_assignee_name' => $deadline->assignee->name ?? 'Unassigned',
                    ];
                }
            }
        }

        // 4. Final Sorting by Priority
        $priority = [
            'program_manager' => 1,
            'manager_distribusi' => 2,
            'producer' => 3,
            'musik_arr' => 4,
            'kreatif' => 5,
            'tim_setting_coord' => 6,
            'tim_syuting_coord' => 7,
            'tim_vocal_coord' => 8,
            'art_set_design' => 9,
            'general_affairs' => 10,
            'editor' => 11,
            'design_grafis' => 12,
            'editor_promosi' => 13,
            'quality_control' => 14,
            'broadcasting' => 15,
            'promotion' => 16,
        ];

        usort($tasks, function($a, $b) use ($priority) {
            $pA = $priority[$a['role_key'] ?? ''] ?? 99;
            $pB = $priority[$b['role_key'] ?? ''] ?? 99;
            return $pA <=> $pB;
        });

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'message' => 'Daftar tahap alih pekerjaan berhasil dimuat.',
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

            // 0. Program Active
            $workflowSteps['program_active'] = [
                'step_key' => 'program_active',
                'step_name' => 'Program Aktif',
                'status' => 'completed', // Program must be active to see this dashboard
                'data' => [
                    'active_at' => $episode->program->approved_at ?? $episode->program->created_at
                ]
            ];
            
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
                'deadline' => $episode->deadlines()->where('role', 'musik_arr_song')->first()
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
                'deadline' => $episode->deadlines()->where('role', 'producer_acc_song')->first()
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
                'deadline' => $episode->deadlines()->where('role', 'musik_arr_lagu')->first()
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
                'deadline' => $episode->deadlines()->where('role', 'producer_acc_lagu')->first()
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
            $recordingStatus = 'pending';
            if ($soundRecording) {
                if (in_array($soundRecording->status, ['completed', 'approved', 'verified'])) {
                    $recordingStatus = 'completed';
                } else {
                    $recordingStatus = $soundRecording->status;
                }
            }
            $workflowSteps['vocal_recording'] = [
                'step_key' => 'vocal_recording',
                'step_name' => 'Sound Recording (Vocal & Instrument)',
                'status' => $recordingStatus,
                'rejection_reason' => $soundRecording ? $soundRecording->rejection_reason : null,
                'review_notes' => $soundRecording ? $soundRecording->review_notes : null,
                'data' => $soundRecording ? [
                    'id' => $soundRecording->id,
                    'status' => $soundRecording->status,
                    'created_at' => $soundRecording->created_at,
                    'updated_at' => $soundRecording->updated_at,
                    'completed_at' => $soundRecording->completed_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'tim_vocal_coord')->first()
            ];
            
            // 8. Vocal Editing
            $soundEditing = $episode->soundEngineerEditings()->latest()->first();
            $editingStatus = 'pending';
            if ($soundEditing) {
                if (in_array($soundEditing->status, ['approved', 'completed', 'verified'])) {
                    $editingStatus = 'completed';
                } elseif ($soundEditing->status === 'revision_needed' || $soundEditing->status === 'rejected') {
                    $editingStatus = 'rejected';
                } else {
                    $editingStatus = 'in_progress';
                }
            }
            $workflowSteps['vocal_editing'] = [
                'step_key' => 'vocal_editing',
                'step_name' => 'Vocal Editing (Mixing & Mastering)',
                'status' => $editingStatus,
                'rejection_reason' => $soundEditing ? $soundEditing->rejection_reason : null,
                'review_notes' => $soundEditing ? $soundEditing->review_notes : null,
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
                'step_name' => 'Production (Syuting Episode)',
                'status' => $produksiWork ? (in_array($produksiWork->status, ['completed', 'approved']) ? 'completed' : $produksiWork->status) : 'pending',
                'rejection_reason' => $produksiWork ? $produksiWork->rejection_reason : null,
                'review_notes' => $produksiWork ? $produksiWork->review_notes : null,
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
                'step_name' => 'Editor (Main Episode)',
                'status' => $editorWork ? (in_array($editorWork->status, ['completed', 'approved']) ? 'completed' : $editorWork->status) : 'pending',
                'rejection_reason' => $editorWork ? $editorWork->rejection_reason : null,
                'review_notes' => $editorWork ? $editorWork->review_notes : null,
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
                'step_name' => 'Editor QC Approval (Producer)',
                'status' => $qcWork ? (in_array($qcWork->status, ['approved', 'completed']) ? 'completed' : ($qcWork->status === 'rejected' ? 'rejected' : $qcWork->status)) : 'pending',
                'rejection_reason' => ($qcWork && $qcWork->status === 'rejected') ? $qcWork->qc_notes : null,
                'review_notes' => ($qcWork && $qcWork->status === 'approved') ? $qcWork->qc_notes : null,
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

            // 10b. Art Set & Property
            // 10b. Art Set & Property
            $produksiWorkGeneral = \App\Models\ProduksiWork::where('episode_id', $episode->id)->latest()->first();
            $artSetStatus = 'pending';
            if ($produksiWorkGeneral) {
                if ($produksiWorkGeneral->setting_completed_at !== null) {
                    $artSetStatus = 'completed';
                } elseif ($produksiWorkGeneral->status !== 'pending') {
                    $artSetStatus = 'in_progress';
                }
            }
            
            $workflowSteps['art_set_property'] = [
                'step_key' => 'art_set_property',
                'step_name' => 'Art Set & Property (Setting)',
                'status' => $artSetStatus,
                'rejection_reason' => $produksiWorkGeneral ? $produksiWorkGeneral->rejection_reason : null,
                'review_notes' => $produksiWorkGeneral ? $produksiWorkGeneral->review_notes : null,
                'data' => $produksiWorkGeneral ? [
                    'id' => $produksiWorkGeneral->id,
                    'status' => $produksiWorkGeneral->status,
                    'setting_completed_at' => $produksiWorkGeneral->setting_completed_at,
                    'updated_at' => $produksiWorkGeneral->updated_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'tim_setting_coord')->first()
            ];

            // 11. Promotion (ENHANCED)
            $promotionMaterials = $episode->promotionMaterials()->get();
            $designWorks = $episode->designGrafisWorks()->get();
            $promotionWorks = $episode->promotionWorks()->get();
            $editorPromosiWorks = $episode->editorPromosiWorks()->get();
            
            $promoStatus = 'pending';
            if ($promotionWorks->where('status', 'published')->count() > 0 || $promotionMaterials->whereIn('status', ['completed', 'published', 'approved'])->count() > 0) {
                 $promoStatus = 'in_progress_sharing';
                 $isAllDone = $promotionWorks->count() > 0 && $promotionWorks->every(fn($w) => in_array($w->status, ['published', 'completed', 'approved']));
                 if ($isAllDone) {
                     $promoStatus = 'completed';
                 }
            } elseif ($designWorks->whereIn('status', ['completed', 'approved'])->isNotEmpty() || $promotionWorks->count() > 0 || $editorPromosiWorks->whereIn('status', ['completed', 'approved'])->isNotEmpty()) {
                 $promoStatus = 'in_progress';
            }
            
            $workflowSteps['promotion_sharing'] = [
                'step_key' => 'promotion_sharing',
                'step_name' => 'Promotion Sharing',
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

            // 11b. Design & Promo Editing (Music Specific)
            $designPromoStatus = 'pending';
            $hasDesign = $designWorks->whereIn('status', ['completed', 'approved'])->isNotEmpty();
            $hasPromoEd = $editorPromosiWorks->whereIn('status', ['completed', 'approved'])->isNotEmpty();
            
            if ($hasDesign && $hasPromoEd) {
                $designPromoStatus = 'completed';
            } elseif ($designWorks->count() > 0 || $editorPromosiWorks->count() > 0) {
                $designPromoStatus = 'in_progress';
            }
            
            $workflowSteps['design_promo_editing'] = [
                'step_key' => 'design_promo_editing',
                'step_name' => 'Design Grafis & Editor Promotion',
                'status' => $designPromoStatus,
                'data' => [
                    'has_design' => $hasDesign,
                    'has_promo_editing' => $hasPromoEd,
                    'design_count' => $designWorks->count(),
                    'promo_editing_count' => $editorPromosiWorks->count()
                ],
                'deadline' => $episode->deadlines()->where('role', 'design_grafis')->first()
            ];

            // 11c. Distribution Manager QC (Missing step)
            $broadcastingWork = \App\Models\BroadcastingWork::where('episode_id', $episode->id)->latest()->first();
            $dmStatus = 'pending';
            if ($broadcastingWork) {
                if (in_array($broadcastingWork->status, ['pending', 'published', 'completed'])) {
                    // In BroadcastingWork, 'pending' means approved for broadcasting but waiting for airing
                    $dmStatus = 'completed';
                } elseif ($broadcastingWork->status === 'rejected') {
                    $dmStatus = 'rejected';
                } else {
                    $dmStatus = 'in_progress';
                }
            }
            
            $workflowSteps['dm_schedule'] = [
                'step_key' => 'dm_schedule',
                'step_name' => 'Distribution Manager QC & Schedule',
                'status' => $dmStatus,
                'data' => $broadcastingWork ? [
                    'id' => $broadcastingWork->id,
                    'status' => $broadcastingWork->status,
                    'approved_at' => $broadcastingWork->approved_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'manager_distribusi')->first()
            ];

            // 11d. Promotion Content Start (BTS / Promo Material)
            $promoWorkBts = $promotionWorks->where('work_type', 'bts_photo')->first();
            $workflowSteps['promotion_content_start'] = [
                'step_key' => 'promotion_content_start',
                'step_name' => 'Promotion Content (BTS & Materials)',
                'status' => $promoWorkBts ? (in_array($promoWorkBts->status, ['published', 'completed', 'approved']) ? 'completed' : $promoWorkBts->status) : 'pending',
                'data' => $promoWorkBts ? [
                    'id' => $promoWorkBts->id,
                    'status' => $promoWorkBts->status,
                    'updated_at' => $promoWorkBts->updated_at
                ] : null,
                'deadline' => $episode->deadlines()->where('role', 'promotion')->first()
            ];

            // 12. Broadcasting
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

            // 13. Smart Detection for Program Type
            $hasMusicData = $episode->musicArrangements()->exists();
            $programCategory = strtolower($episode->program->category ?? '');
            $isMusic = $programCategory === 'musik' || $programCategory === 'music' || $hasMusicData;

            if ($isMusic) {
                $workflowOrder = [
                    'program_active',
                    'song_proposal',
                    'song_proposal_approval',
                    'music_arrangement_link',
                    'arrangement_approval',
                    'creative_concept',
                    'producer_creative_approval',
                    'vocal_recording',
                    'vocal_editing',
                    'art_set_property',
                    'shooting_production',
                    'video_editing',
                    'design_promo_editing',
                    'quality_control',
                    'dm_schedule',
                    'broadcasting_publishing',
                    'promotion_content_start',
                    'promotion_sharing'
                ];
                
                // Ensure category is updated in memory for the rest of the function
                $episode->program->category = 'musik';
            } else {
                $workflowOrder = [
                    'program_active',
                    'music_arrangement',
                    'creative_work',
                    'production_planning',
                    'equipment_request',
                    'shooting_recording',
                    'editing',
                    'quality_control',
                    'broadcasting',
                    'promotion',
                    'completed'
                ];
            }
            
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

            // Attach deadlines to steps based on role mapping (Eager attachment)
            $musicDeadlineMap = [
                'program_active' => 'program_manager',
                'song_proposal' => 'musik_arr_song',
                'song_proposal_approval' => 'producer_acc_song',
                'music_arrangement_link' => 'musik_arr_lagu',
                'arrangement_approval' => 'producer_acc_lagu',
                'vocal_recording' => 'tim_vocal_coord',
                'vocal_editing' => 'sound_eng',
                'creative_concept' => 'kreatif',
                'producer_creative_approval' => 'producer_creative',
                'video_editing' => 'editor',
                'art_set_property' => 'tim_setting_coord',
                'shooting_production' => 'tim_syuting_coord',
                'quality_control' => 'quality_control',
                'dm_schedule' => 'manager_distribusi',
                'broadcasting_publishing' => 'broadcasting',
                'design_promo_editing' => 'design_grafis',
                'promotion_content_start' => 'promotion',
                'promotion_sharing' => 'promotion'
            ];

            // Attach history and deadlines to steps
            foreach ($workflowSteps as $key => &$step) {
                $step['step_key'] = $key;
                $step['history'] = $stepHistory[$key] ?? [];
                
                // 1. If deadline already attached as object/array, format it correctly
                if (isset($step['deadline']) && $step['deadline'] instanceof \App\Models\Deadline) {
                    $dl = $step['deadline'];
                    $step['deadline'] = [
                        'id' => $dl->id,
                        'deadline_date' => $dl->deadline_date,
                        'days_left' => $dl->getDaysLeftAttribute(),
                        'days_left_label' => $dl->getDaysLeftLabelAttribute(),
                        'is_completed' => $dl->is_completed,
                        'is_overdue' => $dl->isOverdue()
                    ];
                    continue;
                }

                // 2. Otherwise identify Target Role for Deadline from Map
                $deadlineRole = $musicDeadlineMap[$key] ?? $key;
                
                // 3. Try Specific Role First (from pre-loaded collection)
                $deadline = $episode->deadlines->where('role', $deadlineRole)->first();
                
                // 4. Ultra-Resilient Search Fallback (With Language Bridge)
                if (!$deadline) {
                    $synonyms = [
                        'song' => ['lagu', 'pengajuan', 'usulan'],
                        'arrangement' => ['aransemen', 'lagu', 'link'],
                        'approval' => ['acc', 'setuju', 'approval', 'review'],
                        'creative' => ['kreatif', 'concept', 'script'],
                        'shooting' => ['syuting', 'produksi', 'shooting'],
                        'vocal' => ['vokal', 'vocal', 'rekam', 'recording']
                    ];
                    
                    $searchWords = explode('_', $key);
                    $relatedTerms = [];
                    foreach ($searchWords as $word) {
                        $relatedTerms[] = $word;
                        if (isset($synonyms[$word])) {
                            $relatedTerms = array_merge($relatedTerms, $synonyms[$word]);
                        }
                    }
                    
                    $deadline = $episode->deadlines->filter(function($d) use ($key, $relatedTerms) {
                        $role = strtolower($d->role . ' ' . $d->role_label);
                        foreach ($relatedTerms as $term) {
                            if (str_contains($role, $term)) return true;
                        }
                        return false;
                    })->first();
                }

                if ($deadline) {
                    $step['deadline'] = [
                        'id' => $deadline->id,
                        'deadline_date' => $deadline->deadline_date,
                        'days_left' => $deadline->getDaysLeftAttribute(),
                        'days_left_label' => $deadline->getDaysLeftLabelAttribute(),
                        'is_completed' => $deadline->is_completed,
                        'is_overdue' => $deadline->isOverdue()
                    ];
                } elseif (!isset($step['deadline'])) {
                    $step['deadline'] = null;
                }
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








