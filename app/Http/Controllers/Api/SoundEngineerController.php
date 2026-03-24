<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SoundEngineerRecording;
use App\Models\MusicArrangement;
use App\Models\Episode;
use App\Models\Notification;
use App\Models\ProductionEquipment;
use App\Models\InventoryItem;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SoundEngineerController extends Controller
{
    /**
     * Check if user is Sound Engineer (supports both 'Sound Engineer' and 'sound_engineer')
     */
    private function isSoundEngineer($user): bool
    {
        if (!$user) {
            return false;
        }

        return MusicProgramAuthorization::canUserPerformTask($user, null, 'Sound Engineer');
    }

    /**
     * Check if user has access to a specific recording recording
     */
    private function hasRecordingAccess($user, $recording): bool
    {
        if (!$user || !$recording) {
            return false;
        }

        // Use flexible authorization helper
        if (MusicProgramAuthorization::canUserPerformTask($user, $recording, 'Sound Engineer')) {
            return true;
        }

        // Must be a Sound Engineer Role
        if (!$this->isSoundEngineer($user)) {
            return false;
        }

        // If user is a global Sound Engineer, allow access to all recordings
        // (prevents empty dashboard when created_by is assigned to a different SE user).
        $isGlobalSoundEngineer = in_array(strtolower($user->role), ['sound_engineer', 'sound engineer']);
        if ($isGlobalSoundEngineer) {
            return true;
        }

        // Creator has access
        if ($recording->created_by === $user->id) {
            return true;
        }

        // Broaden access: Check if user is a sound engineer in the program's production team
        // Support both direct load and relationship
        $episode = $recording->relationLoaded('episode') ? $recording->episode : $recording->episode()->withTrashed()->first();
        if (!$episode) {
            return false;
        }

        $program = $episode->relationLoaded('program') ? $episode->program : $episode->program()->withTrashed()->first();
        if (!$program) {
            return false;
        }

        $productionTeam = $program->relationLoaded('productionTeam') ? $program->productionTeam : $program->productionTeam;
        
        if (!$productionTeam) {
            return false;
        }

        // Broaden access: Check if user is a member of the recording team for the episode
        $isMember = \App\Models\ProductionTeamMember::isMemberForEpisode($user->id, $episode->id, ['recording', 'sound_eng']);
        if ($isMember) {
            return true;
        }

        // Access members in program team
        return $productionTeam->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['sound_eng', 'sound_engineer', 'sound engineer', 'recording', 'vocal_recording'])
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get sound engineer recordings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            // Optimize query with eager loading (include trashed for data integrity)
            $query = SoundEngineerRecording::with([
                'episode' => function($q) { $q->withTrashed(); },
                'episode.program' => function($q) { $q->withTrashed(); },
                'episode.program.managerProgram',
                'episode.program.productionTeam.members.user',
                'musicArrangement.song',
                'musicArrangement.singer',
                'createdBy',
                'reviewedBy',
                'equipmentRequests' => function($q) use ($user) {
                    $q->where('requested_by', $user->id)
                      ->with([
                          'episode' => function($eq) { $eq->withTrashed(); }, 
                          'episode.program' => function($pq) { $pq->withTrashed(); }
                      ]);
                }
            ]);
            
            // Restrict dashboard to programs/episodes whose Creative Work
            // has been finally approved by Producer.
            $query->whereHas('episode', function ($eq) {
                $eq->whereHas('creativeWorks', function ($cq) {
                    $cq->where('status', 'approved');
                });
            });

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by creator - default to current user if not specified
            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            } else {
                $isGlobalSoundEngineer = in_array(strtolower($user->role), ['sound_engineer', 'sound engineer']);
                $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);

                // For global Sound Engineer, don't restrict by created_by / membership
                // so dashboard always shows tasks created after Producer approval.
                if (!$isProgramManager && !$isGlobalSoundEngineer) {
                    // Show recordings created by user OR recordings for episodes where user is in the program's production team
                    $query->where(function($q) use ($user) {
                        $q->where('created_by', $user->id)
                          // Broaden filter: check if user is a sound engineer in the program's production team
                          ->orWhereHas('episode.program.productionTeam.members', function($mq) use ($user) {
                              $mq->where('user_id', $user->id)
                                 ->whereIn('role', ['sound_eng', 'sound_engineer', 'sound engineer', 'recording', 'vocal_recording', 'recording_team'])
                                 ->where('is_active', true);
                          })
                          // Keep existing episode-specific team check just in case it's used
                          ->orWhereHas('episode.teamAssignments', function($aq) use ($user) {
                              $aq->where('team_type', 'recording')
                                 ->where('status', '!=', 'cancelled')
                                 ->whereHas('members', function($mq) use ($user) {
                                     $mq->where('user_id', $user->id)
                                        ->where('is_active', true);
                                 });
                          });
                    });
                }
            }
            
            $recordings = $query->orderBy('created_at', 'desc')->paginate(15);

            // Add convenience fields and only include equipment requests made by current user
            $recordings->getCollection()->transform(function ($recording) use ($user) {
                $episode = $recording->episode;
                $program = $episode?->program;

                // Set basic properties
                $recording->episode_number = $episode->episode_number ?? null;
                $recording->program_name = $program->name ?? null;
                $recording->program_id = $program->id ?? null;

                // Fallback for orphaned/missing data
                if (!$episode) {
                    $recording->episode_number = "#" . ($recording->episode_id ?? 'N/A');
                    $recording->program_name = "Unknown Program (Ep ID: {$recording->episode_id})";
                } elseif (!$program) {
                    $recording->program_name = "Unknown Program (Prog ID: " . ($episode->program_id ?? 'N/A') . ")";
                }

                // Append status for trashed records
                if ($episode && $episode->trashed()) {
                    $recording->episode_number .= " (Deleted)";
                }
                if ($program && $program->trashed()) {
                    $recording->program_name .= " (Deleted)";
                }

                $recording->recording_link = $recording->file_link; // alias for frontend
                
                // Equipment requests already filtered in 'with' query for efficiency
                // but we still ensure the relation is set correctly
                $recording->setRelation('equipmentRequests', $recording->equipmentRequests);
                
                return $recording;
            });
            
            return response()->json([
                'success' => true,
                'data' => $recordings,
                'message' => 'Sound engineer recordings retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recordings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sound engineer recording by ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }
-
            $recording = SoundEngineerRecording::with([
                'episode' => function($q) { $q->withTrashed(); },
                'episode.program' => function($q) { $q->withTrashed(); },
                'episode.program.productionTeam.members',
                'musicArrangement',
                'createdBy',
                'reviewedBy',
                'equipmentRequests' => function($q) use ($user) {
                    $q->where('requested_by', $user->id)
                      ->with([
                          'episode' => function($eq) { $eq->withTrashed(); },
                          'episode.program' => function($pq) { $pq->withTrashed(); }
                      ]);
                }
            ])->findOrFail($id);
        
            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }

            // Add convenience fields
            $episode = $recording->episode;
            $program = $episode?->program;

            $recording->episode_number = $episode->episode_number ?? null;
            $recording->program_name = $program->name ?? null;
            $recording->program_id = $program->id ?? null;

             // Fallback for orphaned/missing data
             if (!$episode) {
                $recording->episode_number = "#" . ($recording->episode_id ?? 'N/A');
                $recording->program_name = "Unknown Program (Ep ID: {$recording->episode_id})";
            } elseif (!$program) {
                $recording->program_name = "Unknown Program (Prog ID: " . ($episode->program_id ?? 'N/A') . ")";
            }

            // Append status for trashed records
            if ($episode && $episode->trashed()) {
                $recording->episode_number .= " (Deleted)";
            }
            if ($program && $program->trashed()) {
                $recording->program_name .= " (Deleted)";
            }

            $recording->recording_link = $recording->file_link; // alias for frontend
            
            // Equipment requests already filtered in 'with' query
            $recording->setRelation('equipmentRequests', $recording->equipmentRequests);
            
            return response()->json([
                'success' => true,
                'data' => $recording,
                'message' => 'Sound engineer recording retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recording',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create sound engineer recording
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'recording_notes' => 'nullable|string',
                'equipment_used' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'music_arrangement_id' => 'nullable|exists:music_arrangements,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user has access to this episode's production team
        $episode = Episode::with(['program.productionTeam.members', 'productionTeam.members'])->findOrFail($request->episode_id);
        
        // Prioritize Episode's production team, then fallback to Program's production team
        $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;

        if (!$productionTeam) {
            return response()->json([
                'success' => false,
                'message' => 'Episode does not have a production team assigned (neither directly nor via program).'
            ], 400);
        }
        
        $hasAccess = $productionTeam->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['sound_eng', 'sound_engineer', 'recording', 'vocal_recording'])
            ->where('is_active', true)
            ->exists();

            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned as Sound Engineer for this episode\'s production team.'
                ], 403);
            }
            
            $recording = SoundEngineerRecording::create([
                'episode_id' => $request->episode_id,
                'music_arrangement_id' => $request->music_arrangement_id,
                'recording_notes' => $request->recording_notes,
                'equipment_used' => $request->equipment_used,
                'recording_schedule' => $request->recording_schedule,
                'status' => 'draft',
                'created_by' => $user->id
            ]);
            
            // Audit logging
            ControllerSecurityHelper::logCreate($recording, [
                'episode_id' => $recording->episode_id,
                'music_arrangement_id' => $recording->music_arrangement_id,
                'status' => 'draft'
            ], $request);
            
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
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail($id);
            
            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to update this recording.'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'recording_notes' => 'nullable|string',
                'equipment_used' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'recording_link' => 'nullable|string|max:2048',
                'file_link' => 'nullable|string|max:2048'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $oldData = $recording->toArray();
            $updateData = $request->only([
                'recording_notes',
                'equipment_used',
                'recording_schedule',
                'file_link'
            ]);
            
            // Map recording_link → file_link (frontend sends recording_link, DB column is file_link)
            if ($request->has('recording_link') && !$request->has('file_link')) {
                $updateData['file_link'] = $request->recording_link;
            }
            
            $recording->update($updateData);
            
            // Audit logging
            ControllerSecurityHelper::logUpdate($recording, $oldData, $updateData, $request);
            
            return response()->json([
                'success' => true,
                'data' => $recording->fresh(),
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
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail($id);
            
            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }
            
            // Allow starting if not already started or completed
            $allowedStatuses = ['draft', 'pending', 'scheduled', 'in_progress', 'ready'];
            if (!in_array($recording->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording cannot be started in its current status: ' . $recording->status . '. Current allowed statuses: ' . implode(', ', $allowedStatuses)
                ], 400);
            }
            
            $recording->startRecording();
            
            return response()->json([
                'success' => true,
                'data' => $recording->fresh(),
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
    public function completeRecording(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail($id);
            
            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }

            $isCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');
            
            if ($recording->status !== 'recording') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active recordings can be completed'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'recording_link' => 'nullable|string|max:2048',
                'vocal_file_link' => 'nullable|string|max:2048',
                'recording_notes' => 'nullable|string',
                'completion_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Resolve the recording link: accept multiple field names, fallback to existing
            $recordingLink = $request->recording_link 
                ?? $request->vocal_file_link 
                ?? $recording->file_link;
            
            if (!$recordingLink) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording link is required. Please upload a recording link first.'
                ], 422);
            }
            
            // Update recording with link and status
            $recording->update([
                'status' => 'completed',
                'recording_completed_at' => now(),
                'file_link' => $recordingLink,
                'recording_notes' => $request->recording_notes ?? $request->completion_notes ?? $recording->recording_notes
            ]);

            // Log Workflow State for Complete Recording
            $workflowService = app(\App\Services\WorkflowStateService::class);
            $workflowService->updateWorkflowState(
                $recording->episode,
                'sound_engineering',
                'sound_engineer',
                $user->id,
                "Vocal recording completed and uploaded by {$user->name}",
                $user->id,
                [
                    'action' => 'vocal_recording_completed',
                    'file_link' => $recordingLink,
                    'notes' => $request->recording_notes ?? $request->completion_notes
                ]
            );
            
            // Auto-create Sound Engineer Editing task
            $existingEditing = \App\Models\SoundEngineerEditing::where('episode_id', $recording->episode_id)
                ->where('sound_engineer_recording_id', $recording->id)
                ->first();

            if (!$existingEditing) {
                $episode = $recording->episode;
                $program = $episode ? $episode->program : null;
                $productionTeam = ($program && $program->productionTeam) ? $program->productionTeam : null;
                
                // Find the actual Sound Engineer for this episode to assign editing
                $soundEngineer = null;
                if ($productionTeam) {
                    $soundEngineerMember = $productionTeam->members()
                        ->whereIn('role', ['sound_eng', 'sound_engineer'])
                        ->where('is_active', true)
                        ->first();
                    if ($soundEngineerMember) {
                        $soundEngineer = $soundEngineerMember->user;
                    }
                }
                
                // Fallback to current user if they are a sound engineer, otherwise null (assign manually later)
                $assignedSoundEngId = $soundEngineer ? $soundEngineer->id : (in_array(strtolower($user->role), ['sound_eng', 'sound_engineer']) ? $user->id : null);

                $editing = \App\Models\SoundEngineerEditing::create([
                    'episode_id' => $recording->episode_id,
                    'sound_engineer_recording_id' => $recording->id,
                    'sound_engineer_id' => $assignedSoundEngId,
                    'vocal_file_path' => $recording->file_path ?? null,
                    'vocal_file_link' => $recordingLink,
                    'editing_notes' => "Recording by: {$user->name}. " . ($isCoordinator ? "(Tim Rekam Vokal). " : "") . "Recording notes: " . ($request->recording_notes ?? 'N/A'),
                    'status' => 'in_progress',
                    'created_by' => $user->id
                ]);

                // Determine identity for messages
                $identity = $isCoordinator ? "Tim Rekam Vokal" : "Sound Engineer";

                // Notify Producer for recording QC (only notify Producer from same production team)
                if ($productionTeam && $productionTeam->producer_id) {
                    \App\Models\Notification::create([
                        'user_id' => $productionTeam->producer_id,
                        'type' => 'sound_engineer_recording_completed',
                        'title' => 'Vocal Recording Completed',
                        'message' => "{$identity} ({$user->name}) telah menyelesaikan rekaman vokal untuk Episode {$episode->episode_number}. Harap tinjau QC.",
                        'data' => [
                            'recording_id' => $recording->id,
                            'editing_id' => $editing->id,
                            'episode_id' => $recording->episode_id
                        ]
                    ]);
                }

                // Notify the assigned Sound Engineer to start editing
                if ($assignedSoundEngId && $assignedSoundEngId !== $user->id) {
                    \App\Models\Notification::create([
                        'user_id' => $assignedSoundEngId,
                        'type' => 'vocal_editing_task_created',
                        'title' => 'Tugas Edit Vokal Baru',
                        'message' => "Rekaman vokal untuk Episode {$episode->episode_number} telah selesai oleh {$identity}. Silakan mulai proses editing.",
                        'data' => [
                            'recording_id' => $recording->id,
                            'editing_id' => $editing->id,
                            'episode_id' => $recording->episode_id
                        ]
                    ]);
                }
                
                // Update workflow state to sound_engineering if needed
                try {
                    if ($episode && ($episode->current_workflow_state === 'production' || $episode->current_workflow_state === 'production_planning' || $episode->current_workflow_state === 'shooting_recording')) {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $episode,
                            'sound_engineering',
                            'sound_eng',
                            null,
                            'Sound engineer recording completed, proceeding to editing'
                        );
                    }
                } catch (\Throwable $e) {
                    // Do not fail completion just because workflow state update can't be written (e.g. enum mismatch).
                    \Log::warning('SoundEngineer completeRecording: workflow state update failed', [
                        'recording_id' => $recording->id,
                        'episode_id' => $recording->episode_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $recording->load(['episode', 'musicArrangement', 'createdBy']),
                'message' => 'Recording completed successfully. Vocal file link saved and editing task created automatically.'
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
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail($id);
            
            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }
            
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
            
            $recording->review($user->id, $request->review_notes);
            
            return response()->json([
                'success' => true,
                'data' => $recording->fresh(),
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
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail($id);
            
            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }
            
            $url = $recording->file_url;
            
            return response()->json([
                'success' => true,
                'data' => ['url' => $url],
                'message' => 'Recording file URL retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get file URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recordings by episode
     */
    public function getByEpisode(int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            // Check if user has access to this episode
            $episode = Episode::with(['program.productionTeam.members'])->findOrFail($episodeId);
            $hasAccess = $episode->program->productionTeam
                ->members()
                ->where('user_id', $user->id)
                ->where('role', 'sound_eng')
                ->where('is_active', true)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned as Sound Engineer for this episode\'s production team.'
                ], 403);
            }

            $recordings = SoundEngineerRecording::where('episode_id', $episodeId)
                ->with([
                    'episode' => function($q) { $q->withTrashed(); },
                    'episode.program' => function($q) { $q->withTrashed(); },
                    'createdBy', 
                    'reviewedBy'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Add convenience fields
            $recordings->transform(function ($recording) {
                $episode = $recording->episode;
                $program = $episode?->program;

                $recording->episode_number = $episode->episode_number ?? null;
                $recording->program_name = $program->name ?? null;
                $recording->program_id = $program->id ?? null;

                if (!$episode) {
                    $recording->episode_number = "#" . ($recording->episode_id ?? 'N/A');
                    $recording->program_name = "Unknown Program (Ep ID: {$recording->episode_id})";
                } elseif (!$program) {
                    $recording->program_name = "Unknown Program (Prog ID: " . ($episode->program_id ?? 'N/A') . ")";
                }

                if ($episode && $episode->trashed()) {
                    $recording->episode_number .= " (Deleted)";
                }
                if ($program && $program->trashed()) {
                    $recording->program_name .= " (Deleted)";
                }

                $recording->recording_link = $recording->file_link;
                return $recording;
            });
            
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
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recordings = SoundEngineerRecording::where('status', $status)
                ->where('created_by', $user->id) // Only show current user's recordings
                ->with([
                    'episode' => function($q) { $q->withTrashed(); },
                    'episode.program' => function($q) { $q->withTrashed(); },
                    'createdBy', 
                    'reviewedBy'
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            // Add convenience fields
            $recordings->getCollection()->transform(function ($recording) {
                $episode = $recording->episode;
                $program = $episode?->program;

                $recording->episode_number = $episode->episode_number ?? null;
                $recording->program_name = $program->name ?? null;
                $recording->program_id = $program->id ?? null;

                if (!$episode) {
                    $recording->episode_number = "#" . ($recording->episode_id ?? 'N/A');
                    $recording->program_name = "Unknown Program (Ep ID: {$recording->episode_id})";
                } elseif (!$program) {
                    $recording->program_name = "Unknown Program (Prog ID: " . ($episode->program_id ?? 'N/A') . ")";
                }

                if ($episode && $episode->trashed()) {
                    $recording->episode_number .= " (Deleted)";
                }
                if ($program && $program->trashed()) {
                    $recording->program_name .= " (Deleted)";
                }

                $recording->recording_link = $recording->file_link;
                return $recording;
            });
            
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
     * Get approved music arrangements for Sound Engineer
     * Sound Engineer dapat melihat arrangement yang sudah approved untuk episode dari ProductionTeam mereka
     */
    public function getApprovedArrangements(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            // Get ProductionTeams where user is Sound Engineer
            $productionTeamIds = \App\Models\ProductionTeamMember::where('user_id', $user->id)
                ->where('role', 'sound_eng')
                ->where('is_active', true)
                ->pluck('production_team_id');

            if ($productionTeamIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No production teams assigned'
                ]);
            }

            // Get approved arrangements from episodes of programs in user's production teams
        // Include both 'approved' and 'arrangement_approved' status
        $query = MusicArrangement::whereIn('status', ['approved', 'arrangement_approved'])
            ->with(['episode.program.productionTeam', 'createdBy', 'soundEngineerHelper'])
            ->whereHas('episode.program', function ($q) use ($productionTeamIds) {
                $q->whereIn('production_team_id', $productionTeamIds);
            });

            // Filter by episode_id if provided
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Exclude arrangements that already have recording
            $query->whereDoesntHave('soundEngineerRecording', function ($q) {
                $q->where('status', '!=', 'cancelled');
            });

            $arrangements = $query->orderBy('reviewed_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Approved music arrangements retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get approved arrangements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved arrangement by episode ID
     */
    public function getArrangementByEpisode(int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            // Get episode and check if user has access
            $episode = Episode::with(['program.productionTeam.members'])->findOrFail($episodeId);
            
            // Check if user is Sound Engineer in episode's production team
            $hasAccess = $episode->program->productionTeam
                ->members()
                ->where('user_id', $user->id)
                ->where('role', 'sound_eng')
                ->where('is_active', true)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned as Sound Engineer for this episode\'s production team.'
                ], 403);
            }

            // Get approved arrangement for this episode
            $arrangement = MusicArrangement::where('episode_id', $episodeId)
                ->where('status', 'approved')
                ->with(['createdBy', 'reviewedBy', 'episode'])
                ->first();

            if (!$arrangement) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approved arrangement found for this episode'
                ], 404);
            }

            // Check if recording already exists
            $existingRecording = SoundEngineerRecording::where('episode_id', $episodeId)
                ->where('music_arrangement_id', $arrangement->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'arrangement' => $arrangement,
                    'has_recording' => $existingRecording !== null,
                    'recording' => $existingRecording
                ],
                'message' => 'Approved arrangement retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get arrangement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create recording from approved arrangement
     */
    public function createRecordingFromArrangement(Request $request, int $arrangementId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $arrangement = MusicArrangement::with(['episode.program.productionTeam.members'])->findOrFail($arrangementId);

            // Validate arrangement is approved
            if ($arrangement->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved arrangements can be used for recording'
                ], 400);
            }

            // Check if user has access
            $hasAccess = $arrangement->episode->program->productionTeam
                ->members()
                ->where('user_id', $user->id)
                ->where('role', 'sound_eng')
                ->where('is_active', true)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned as Sound Engineer for this episode\'s production team.'
                ], 403);
            }

            // Check if recording already exists
            $existingRecording = SoundEngineerRecording::where('episode_id', $arrangement->episode_id)
                ->where('music_arrangement_id', $arrangementId)
                ->first();

            if ($existingRecording) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording already exists for this arrangement',
                    'data' => $existingRecording
                ], 400);
            }

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

            $recording = SoundEngineerRecording::create([
                'episode_id' => $arrangement->episode_id,
                'music_arrangement_id' => $arrangementId,
                'recording_notes' => $request->recording_notes ?? "Recording for arrangement: {$arrangement->song_title}",
                'equipment_used' => $request->equipment_used,
                'recording_schedule' => $request->recording_schedule,
                'status' => 'draft',
                'created_by' => $user->id
            ]);

            // Notify Producer
            $producers = \App\Models\User::where('role', 'Producer')->get();
            foreach ($producers as $producer) {
                \App\Models\Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'sound_engineer_recording_created',
                    'title' => 'Sound Engineer Started Recording',
                    'message' => "Sound Engineer {$user->name} has started recording for arrangement '{$arrangement->song_title}'.",
                    'data' => [
                        'recording_id' => $recording->id,
                        'arrangement_id' => $arrangementId,
                        'episode_id' => $arrangement->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $recording->load(['episode', 'musicArrangement', 'createdBy']),
                'message' => 'Recording created successfully from approved arrangement'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create recording',
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
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $userId = $request->get('user_id', $user->id); // Default to current user
            $episodeId = $request->get('episode_id');
            
            $query = SoundEngineerRecording::query();
            
            // Only show statistics for current user's recordings
            $query->where('created_by', $userId);
            
            if ($episodeId) {
                $query->where('episode_id', $episodeId);
            }
            
            $statistics = [
                'total' => $query->count(),
                'draft' => (clone $query)->where('status', 'draft')->count(),
                'recording' => (clone $query)->where('status', 'recording')->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
                'reviewed' => (clone $query)->where('status', 'reviewed')->count()
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

    /**
     * Get rejected arrangements that need Sound Engineer help
     * User: "kalau di tolak atau no masuk kembali ke music arrangaer dan sound engginer"
     */
    public function getRejectedArrangementsNeedingHelp(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            // Debug: Cek semua arrangement dengan status rejected
            $allRejected = MusicArrangement::whereIn('status', ['arrangement_rejected', 'rejected'])->get();
            Log::info('SoundEngineer getRejectedArrangements - All rejected arrangements', [
                'user_id' => $user->id,
                'total_rejected' => $allRejected->count(),
                'arrangements' => $allRejected->map(function ($arr) {
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'episode_id' => $arr->episode_id,
                        'needs_sound_engineer_help' => $arr->needs_sound_engineer_help,
                        'sound_engineer_helper_id' => $arr->sound_engineer_helper_id
                    ];
                })->toArray()
            ]);
            
            // Tampilkan: butuh bantuan SE, ATAU sudah dibantu SE tapi masih rejected (agar SE bisa kirim link)
            $query = MusicArrangement::with([
                'episode' => function($q) { $q->withTrashed(); },
                'episode.program' => function($q) { $q->withTrashed(); },
                'episode.program.productionTeam',
                'song',
                'singer',
                'createdBy'
            ])
                ->where(function ($q) use ($user) {
                    $q->where(function ($sq) use ($user) {
                        $sq->whereIn('status', ['arrangement_rejected', 'rejected', 'song_rejected']) // Included song_rejected
                        ->where(function ($helpQ) use ($user) {
                            // Item marked as needing help OR specifically assigned to this user OR no helper assigned yet
                            $helpQ->where('needs_sound_engineer_help', true)
                                ->orWhereNull('sound_engineer_helper_id')
                                ->orWhere('sound_engineer_helper_id', $user->id);
                        });
                    })
                    ->orWhere(function ($sq) use ($user) {
                        // Pending approve after Sound Engineer submitted fix (auto-submitted to Producer)
                        $sq->where('status', 'arrangement_submitted')
                           ->where('sound_engineer_helper_id', $user->id);
                    });
                });

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Get all production team IDs where this user is a Sound Engineer member
            $productionTeamIds = \App\Models\ProductionTeamMember::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer'])
                ->pluck('production_team_id');

            // --- DEBUG LOGS ---
            $totalCountInDb = MusicArrangement::whereIn('status', ['arrangement_rejected', 'rejected', 'song_rejected'])->count();
            $needsHelpCount = (clone $query)->count();
            
            Log::info('SoundEngineer Rejected Arrangements Debug', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'total_rejected_in_db' => $totalCountInDb,
                'count_after_needs_help_filter' => $needsHelpCount,
                'production_team_ids' => $productionTeamIds->toArray()
            ]);

            // LOOSENED FILTER: 
            // 1. If user role is 'sound_engineer' or 'Sound Engineer' in users table, they see ALL rejections that need help
            // 2. Otherwise, check team memberships
            $isGlobalSoundEngineer = in_array(strtolower($user->role), ['sound_engineer', 'sound engineer']);
            
            $query->where(function ($q) use ($user, $productionTeamIds, $isGlobalSoundEngineer) {
                if ($isGlobalSoundEngineer) {
                    $q->where('id', '>', 0); // effectively no filter if global role
                } else {
                    $q->where(function($sq) use ($productionTeamIds) {
                        if ($productionTeamIds->isNotEmpty()) {
                            $sq->whereHas('episode', function ($eq) use ($productionTeamIds) {
                                $eq->withTrashed()->whereIn('production_team_id', $productionTeamIds);
                            })
                            ->orWhereHas('episode.program', function ($pq) use ($productionTeamIds) {
                                $pq->withTrashed()->whereIn('production_team_id', $productionTeamIds);
                            });
                        } else {
                            $sq->whereRaw('0=1'); 
                        }
                    })
                    // Match via Episode-specific Team Assignment
                    ->orWhereHas('episode.teamAssignments', function($aq) use ($user) {
                        $aq->where('status', '!=', 'cancelled')
                           ->whereHas('members', function($mq) use ($user) {
                               $mq->where('user_id', $user->id)
                                  ->where('is_active', true)
                                  ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer']);
                           });
                    })
                    // Fallback: Show arrangements where the episode/program has NO production team
                    ->orWhereHas('episode', function ($eq) {
                        $eq->withTrashed()->where(function($ep) {
                            $ep->whereNull('production_team_id')
                               ->orWhereNotExists(function($query) {
                                   $query->select(\DB::raw(1))
                                         ->from('production_teams')
                                         ->whereColumn('production_teams.id', 'episodes.production_team_id');
                               });
                        });
                    });
                }
            });

            $finalCount = (clone $query)->count();
            Log::info('SoundEngineer Rejected Arrangements Final Count', ['count' => $finalCount]);

            // Prefer sorting by review date when rejected/approved; fallback to submission date.
            $arrangements = $query->orderByRaw('COALESCE(reviewed_at, submitted_at, updated_at) DESC')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Rejected arrangements needing help retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rejected arrangements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rejected arrangements history for Sound Engineer
     * (arrangements that were previously rejected and later approved after Sound Engineer helped).
     */
    public function getRejectedArrangementsHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            // Only show arrangements where this Sound Engineer actually helped.
            // After help + Producer approval, the status becomes approved/arrangement_approved,
            // but rejection_reason/sound_engineer_help_at may still remain.
            $query = MusicArrangement::with([
                'episode' => function ($q) { $q->withTrashed(); },
                'episode.program' => function ($q) { $q->withTrashed(); },
                'episode.program.productionTeam',
                'song',
                'singer',
                'createdBy',
                'reviewedBy',
            ])
                ->where('sound_engineer_helper_id', $user->id)
                ->whereIn('status', ['approved', 'arrangement_approved', 'song_approved'])
                ->whereNotNull('sound_engineer_help_at')
                ->where(function ($q) {
                    $q->whereNotNull('rejection_reason')
                        ->orWhereNotNull('sound_engineer_help_notes');
                });

            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            $arrangements = $query
                ->orderByRaw('COALESCE(reviewed_at, submitted_at, updated_at) DESC')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Rejected arrangements history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rejected arrangements history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rejected song proposals that need help
     * User: "Terima Notifikasi" - Sound Engineer melihat song proposals yang rejected
     */
    public function getRejectedSongProposals(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $query = MusicArrangement::where('status', 'song_rejected')
                ->with(['episode.program.productionTeam', 'createdBy']);

            // Filter by episode_id if provided
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Only show arrangements from production teams where Sound Engineer is member
            $query->whereHas('episode.program.productionTeam.members', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('role', 'sound_eng')
                  ->where('is_active', true);
            });

            $arrangements = $query->orderBy('reviewed_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Rejected song proposals retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rejected song proposals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sound Engineer membantu perbaikan song proposal yang ditolak
     * User: "Bantu Perbaikan" - setelah song proposal rejected
     */
    public function helpFixSongProposal(Request $request, int $arrangementId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'help_notes' => 'required|string|max:1000',
                'suggested_song_title' => 'nullable|string|max:255',
                'suggested_singer_name' => 'nullable|string|max:255',
                'song_id' => 'nullable|exists:songs,id',
                'singer_id' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::with([
                'episode' => fn($q) => $q->withTrashed(),
                'episode.program' => fn($q) => $q->withTrashed(),
                'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                'createdBy'
            ])->findOrFail($arrangementId);

            // Only allow help if status is song_rejected
            if ($arrangement->status !== 'song_rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only help fix rejected song proposals'
                ], 400);
            }

            // Check if Sound Engineer has access (Match dashboard logic: Global SE or Team member)
            $isGlobalSoundEngineer = in_array(strtolower($user->role), ['sound_engineer', 'sound engineer']);
            $hasAccess = $isGlobalSoundEngineer;
            
            if (!$hasAccess && $arrangement->episode && $arrangement->episode->program && $arrangement->episode->program->productionTeam) {
                $hasAccess = $arrangement->episode->program->productionTeam
                    ->members()
                    ->where('user_id', $user->id)
                    ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer'])
                    ->where('is_active', true)
                    ->exists();
            }

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned as Sound Engineer for this episode\'s production team and do not have a global Sound Engineer role.'
                ], 403);
            }

            // Get suggested values
            $suggestedSongTitle = $request->suggested_song_title;
            $suggestedSingerName = $request->suggested_singer_name;
            
            if ($request->song_id && !$suggestedSongTitle) {
                $song = \App\Models\Song::find($request->song_id);
                if ($song) {
                    $suggestedSongTitle = $song->title;
                }
            }

            // Update arrangement with Sound Engineer help
            $arrangement->update([
                'sound_engineer_helper_id' => $user->id,
                'sound_engineer_help_notes' => $request->help_notes,
                'sound_engineer_help_at' => now(),
                'needs_sound_engineer_help' => false, // Help provided
                // Update song/singer if suggested
                'song_title' => $suggestedSongTitle ?? $arrangement->song_title,
                'singer_name' => $suggestedSingerName ?? $arrangement->singer_name,
                'song_id' => $request->song_id ?? $arrangement->song_id,
                'singer_id' => $request->singer_id ?? $arrangement->singer_id,
                'status' => 'song_proposal' // Reset to song_proposal for resubmission
            ]);

            // Log Workflow State for Help Song Proposal
            $workflowService = app(\App\Services\WorkflowStateService::class);
            $workflowService->updateWorkflowState(
                $arrangement->episode,
                'song_proposal',
                'sound_engineer',
                $user->id,
                "Sound Engineer helped fix rejected song proposal: {$arrangement->song_title}",
                $user->id,
                [
                    'action' => 'help_fix_song_proposal',
                    'help_notes' => $request->help_notes,
                    'suggested_song' => $suggestedSongTitle,
                    'suggested_singer' => $suggestedSingerName
                ]
            );

            // Notify Music Arranger
            Notification::create([
                'user_id' => $arrangement->created_by,
                'type' => 'sound_engineer_helping_song_proposal',
                'title' => 'Sound Engineer Membantu Perbaikan Usulan Lagu',
                'message' => "Sound Engineer {$user->name} telah membantu perbaikan usulan lagu '{$arrangement->song_title}'. Catatan: {$request->help_notes}",
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id,
                    'help_notes' => $request->help_notes,
                    'suggested_song_title' => $suggestedSongTitle,
                    'suggested_singer_name' => $suggestedSingerName
                ]
            ]);

            // Notify Producer
            $producer = $arrangement->episode->program->productionTeam->producer;
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'sound_engineer_helping_song_proposal_producer',
                    'title' => 'Sound Engineer Membantu Perbaikan Usulan Lagu',
                    'message' => "Sound Engineer {$user->name} telah membantu perbaikan usulan lagu '{$arrangement->song_title}'.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'help_notes' => $request->help_notes
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['episode', 'createdBy', 'soundEngineerHelper']),
                'message' => 'Help provided successfully. Music Arranger has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error helping fix song proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sound Engineer membantu perbaikan arrangement yang ditolak
     * User: "sound engginer terima notifikasi, bantu perbaikan arr lagunya"
     */
    public function helpFixArrangement(Request $request, int $arrangementId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'help_notes' => 'required|string|max:2000',
                'suggested_fixes' => 'nullable|string|max:2000',
                'file' => 'nullable|file|mimes:mp3,wav,midi|max:102400',
                'help_file_link' => 'nullable|string|max:2048',
                'link' => 'nullable|string|max:2048', // Frontend kirim "link" → wajib ada agar status masuk ke Producer
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::with([
                'episode' => function($q) { $q->withTrashed(); },
                'episode.productionTeam' => function($q) { $q->withTrashed(); },
                'episode.program' => function($q) { $q->withTrashed(); },
                'episode.program.productionTeam' => function($q) { $q->withTrashed(); },
                'createdBy'
            ])->findOrFail($arrangementId);

            // Validate arrangement is rejected
            if (!in_array($arrangement->status, ['arrangement_rejected', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only help fix rejected arrangements'
                ], 400);
            }

            // Validate Sound Engineer is in the same production team or has global role
            $isGlobalSoundEngineer = in_array(strtolower($user->role), ['sound_engineer', 'sound engineer']);
            $episode = $arrangement->episode;
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode not found for this arrangement'
                ], 404);
            }
            
            $isInTeam = $isGlobalSoundEngineer;
            if (!$isInTeam) {
                $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
                if ($productionTeam) {
                    $isInTeam = $productionTeam->members()
                        ->where('user_id', $user->id)
                        ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer'])
                        ->where('is_active', true)
                        ->exists();
                } else {
                    // If no team assigned, allow any Sound Engineer
                    $isInTeam = true;
                }
            }


            if (!$isInTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not part of the production team for this arrangement and do not have a global Sound Engineer role'
                ], 403);
            }

            // Handle Help File Link (frontend kirim "link"). Jika SE sudah pernah isi link tapi status belum pindah, pakai link yang tersimpan.
            $rawLink = $request->filled('help_file_link') ? $request->input('help_file_link') : $request->input('link');
            $helpFileLink = is_string($rawLink) ? trim($rawLink) : '';
            if ($helpFileLink === '' && $arrangement->sound_engineer_helper_id === (int) $user->id && !empty($arrangement->sound_engineer_help_file_link)) {
                $helpFileLink = trim($arrangement->sound_engineer_help_file_link);
            }
            $fileUploaded = false;
            $hasHelpFile = $helpFileLink !== '' || $fileUploaded;

            // Mark help as provided. Hanya set needs_sound_engineer_help = false kalau ada link,
            // agar kalau SE submit tanpa link, item tetap muncul di list SE dan bisa submit lagi dengan link.
            $updateData = [
                'needs_sound_engineer_help' => !$hasHelpFile,
                'sound_engineer_helper_id' => $user->id,
                'sound_engineer_help_at' => now(),
                'sound_engineer_help_notes' => $request->help_notes
            ];
            if ($request->has('suggested_fixes')) {
                $updateData['arrangement_notes'] = ($arrangement->arrangement_notes ?? '') . "\n\n[Sound Engineer Help] " . $request->suggested_fixes;
            }
            if ($helpFileLink !== '') {
                $updateData['sound_engineer_help_file_link'] = $helpFileLink;
            }

            $arrangement->update($updateData);

            // Log Workflow State for Help Arrangement
            $workflowService = app(\App\Services\WorkflowStateService::class);
            $workflowService->updateWorkflowState(
                $arrangement->episode,
                'music_arrangement',
                'sound_engineer',
                $user->id,
                "Sound Engineer helped fix rejected arrangement: {$arrangement->song_title}",
                $user->id,
                [
                    'action' => 'help_fix_arrangement',
                    'help_notes' => $request->help_notes,
                    'has_file' => $hasHelpFile,
                    'file_link' => $helpFileLink
                ]
            );
            
            if ($hasHelpFile) {
                // Simpan link perbaikan ke file_link arrangement agar Producer bisa dengar
                if ($helpFileLink) {
                    $arrangement->update([
                        'file_link' => $helpFileLink
                    ]);
                }
                
                // Auto-submit ke Producer agar muncul di "Menunggu Approval" / Music Arrangements
                $arrangement->update([
                    'status' => 'arrangement_submitted',
                    'submitted_at' => now()
                ]);
                Log::info('SoundEngineer helpFixArrangement: arrangement resubmitted to Producer', [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id,
                    'status' => $arrangement->fresh()->status
                ]);
                
                // Notify Producer - Arrangement ready for approval
                $episode = $arrangement->episode;
                $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
                $producer = $productionTeam ? $productionTeam->producer : null;
                
                if ($producer) {
                    Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'arrangement_fixed_by_sound_engineer',
                        'title' => 'Arrangement Diperbaiki - Siap Review',
                        'message' => "Sound Engineer {$user->name} telah memperbaiki arrangement '{$arrangement->song_title}' dan siap untuk direview.",
                        'data' => [
                            'arrangement_id' => $arrangement->id,
                            'episode_id' => $arrangement->episode_id,
                            'sound_engineer_id' => $user->id,
                            'fixed_by' => 'sound_engineer',
                            'help_notes' => $request->help_notes
                        ]
                    ]);
                }
                
                // Notify Music Arranger - Info only (pekerjaan sudah di-submit)
                Notification::create([
                    'user_id' => $arrangement->created_by,
                    'type' => 'sound_engineer_fixed_and_submitted',
                    'title' => 'Arrangement Sudah Diperbaiki & Disubmit',
                    'message' => "Sound Engineer {$user->name} telah memperbaiki dan mengirim ulang arrangement '{$arrangement->song_title}' ke Producer untuk direview.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'sound_engineer_id' => $user->id,
                        'help_notes' => $request->help_notes
                    ]
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $arrangement->fresh(['soundEngineerHelper', 'createdBy']),
                    'message' => 'Arrangement fixed and auto-submitted to Producer for approval. Both Music Arranger and Producer have been notified.'
                ]);
            }

            // Prepare notification message (jika tanpa file, tetap notify Music Arranger)
            $helpType = "";
            if ($request->has('help_file_link')) {
                $helpType = "link file";
            } elseif ($fileUploaded) {
                $helpType = "file upload";
            }
            
            $fileMessage = $helpType ? " dan menyertakan {$helpType}" : "";
            
            // Notify Music Arranger
            Notification::create([
                'user_id' => $arrangement->created_by,
                'type' => 'sound_engineer_helping_arrangement',
                'title' => 'Sound Engineer Membantu Perbaikan Arrangement',
                'message' => "Sound Engineer {$user->name} telah memberikan bantuan perbaikan untuk arrangement '{$arrangement->song_title}'{$fileMessage}. Silakan review dan submit ulang ke Producer. Catatan: {$request->help_notes}",
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id,
                    'sound_engineer_id' => $user->id,
                    'help_notes' => $request->help_notes,
                    'suggested_fixes' => $request->suggested_fixes,
                    'file_uploaded' => $fileUploaded,
                    'help_file_link' => $request->help_file_link
                ]
            ]);

            // Notify Producer (Info Only - Not Submission)
            // Support episode.productionTeam langsung atau episode.program.productionTeam
            $episode = $arrangement->episode;
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                 Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'sound_engineer_helping_arrangement',
                    'title' => 'Sound Engineer Membantu Perbaikan Arrangement',
                    'message' => "Sound Engineer {$user->name} telah memberikan bantuan perbaikan ke Arranger untuk '{$arrangement->song_title}'.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'sound_engineer_id' => $user->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['soundEngineerHelper', 'createdBy']),
                'message' => 'Help notes provided. Music Arranger has been notified to review and submit.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error helping fix arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Jadwal Rekaman Vokal - Sound Engineer terima jadwal dari Creative Work
     * POST /api/live-tv/roles/sound-engineer/recordings/{id}/accept-schedule
     */
    public function acceptRecordingSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode.creativeWork'])->findOrFail($id);

            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }

            // Jadwal bisa dari Creative Work (Creative input) atau sudah di-set saat Producer approve Creative Work
            $scheduleToUse = $recording->recording_schedule;
            $creativeWork = $recording->episode->creativeWork;
            if ($creativeWork && $creativeWork->recording_schedule) {
                $scheduleToUse = $creativeWork->recording_schedule;
            }

            if (!$scheduleToUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal rekaman vokal belum tersedia. Creative harus mengisi jadwal rekaman di Creative Work lalu submit ke Producer. Setelah Producer menyetujui Creative Work, jadwal akan tersedia di sini.'
                ], 400);
            }

            $recording->update([
                'recording_schedule' => $scheduleToUse,
                // UI expects "pending" so Accept Work action is available.
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement']),
                'message' => 'Jadwal rekaman vokal diterima. Tanggal rekaman: ' . \Carbon\Carbon::parse($scheduleToUse)->format('d M Y')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting recording schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Sound Engineer terima pekerjaan rekaman vokal
     * POST /api/live-tv/sound-engineer/recordings/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $recording = SoundEngineerRecording::findOrFail($id);

            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }

            if (!in_array($recording->status, ['draft', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is draft or pending'
                ], 400);
            }

            $recording->update([
                'status' => 'in_progress'
            ]);

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement', 'createdBy']),
                'message' => 'Work accepted successfully. You can now input equipment list and proceed with recording.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request Equipment - Sound Engineer request equipment ke Art & Set Properti
     * POST /api/live-tv/roles/sound-engineer/recordings/{id}/request-equipment
     */
    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.equipment_name' => 'required|string|max:255',
                'equipment_list.*.equipment_id' => 'nullable|integer|exists:equipment_inventory,id',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.return_date' => 'required|date|after_or_equal:today',
                'equipment_list.*.notes' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000',
                'request_group_id' => 'nullable|string|max:64',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }

            // Specific check for equipment: Sound Engineer (role) or Recording Team Coordinator
            $isCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');
            $isSoundEngineerRole = in_array(strtolower($user->role), ['sound engineer', 'sound_engineer']);
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);

            if (!$isSoundEngineerRole && !$isCoordinator && !$isProgramManager && !MusicProgramAuthorization::hasProducerAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Koordinator Tim Rekam Vokal atau Sound Engineer yang dapat meminjam alat.'
                ], 403);
            }

            $equipmentRequestIds = [];
            $unavailableEquipment = [];
            $scheduleDt = $recording->recording_schedule ? \Carbon\Carbon::parse($recording->recording_schedule) : null;

            // Normalize and aggregate requested quantities by equipment name
            $normalizedItems = [];
            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];

                if (!empty($equipment['equipment_id'])) {
                    $inventoryItem = InventoryItem::find($equipment['equipment_id']);
                    if ($inventoryItem) {
                        $equipmentName = $inventoryItem->name;
                    }
                }

                $qty = (int) ($equipment['quantity'] ?? 0);
                if ($qty < 1) continue;
                $normalizedItems[] = [
                    'name' => $equipmentName,
                    'quantity' => $qty,
                    'notes' => $equipment['notes'] ?? null,
                ];
            }

            $qtyByName = [];
            foreach ($normalizedItems as $it) {
                $qtyByName[$it['name']] = ($qtyByName[$it['name']] ?? 0) + (int) $it['quantity'];
            }

            // Check availability per name (total qty) in master inventory
            $inventoryCounts = InventoryItem::whereIn('name', array_keys($qtyByName))
                ->get()
                ->pluck('available_quantity', 'name');

            foreach ($qtyByName as $name => $qty) {
                $availableCount = $inventoryCounts->get($name, 0);

                if ($availableCount < $qty) {
                    $unavailableEquipment[] = [
                        'equipment_name' => $name,
                        'requested_quantity' => $qty,
                        'available_count' => $availableCount,
                        'reason' => 'Equipment tidak tersedia dalam jumlah yang cukup di stok pusat'
                    ];
                }
            }

            if (!empty($unavailableEquipment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some equipment is not available or currently in use',
                    'unavailable_equipment' => $unavailableEquipment
                ], 400);
            }

            // Create ONE pending request per (episode, requester).
            // If a pending request already exists, merge equipment into it (avoid duplicate cards in Art & Set Properti).
            $equipmentList = [];
            foreach ($qtyByName as $name => $qty) {
                for ($i = 0; $i < $qty; $i++) $equipmentList[] = $name;
            }

            $notesLines = [];
            foreach ($normalizedItems as $it) {
                if (!empty($it['notes'])) {
                    $notesLines[] = "{$it['name']}: {$it['notes']}";
                }
            }
            if (!empty($request->request_notes)) {
                $notesLines[] = (string) $request->request_notes;
            }

            $existingPending = ProductionEquipment::where('episode_id', $recording->episode_id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingPending) {
                $existingList = is_array($existingPending->equipment_list)
                    ? $existingPending->equipment_list
                    : (json_decode($existingPending->equipment_list, true) ?? []);

                $mergedList = array_values(array_merge($existingList, $equipmentList));

                $appendNotes = !empty($notesLines) ? implode("\n", $notesLines) : null;
                $mergedNotes = $existingPending->request_notes;
                if (!empty($appendNotes)) {
                    $mergedNotes = trim((string) $mergedNotes);
                    $mergedNotes = $mergedNotes !== ''
                        ? ($mergedNotes . "\n" . $appendNotes)
                        : $appendNotes;
                }

                $existingQtyMap = is_array($existingPending->equipment_quantities)
                    ? $existingPending->equipment_quantities
                    : (json_decode($existingPending->equipment_quantities, true) ?? []);
                if (!is_array($existingQtyMap)) {
                    $existingQtyMap = [];
                }
                foreach ($qtyByName as $k => $v) {
                    $existingQtyMap[$k] = (int) ($existingQtyMap[$k] ?? 0) + (int) ($v ?? 0);
                }

                $existingPending->update([
                    'program_id' => $existingPending->program_id ?: ($recording->episode ? $recording->episode->program_id : null),
                    'request_group_id' => $existingPending->request_group_id ?: ($request->request_group_id ?: null),
                    'equipment_list' => $mergedList,
                    'equipment_quantities' => $existingQtyMap,
                    'request_notes' => $mergedNotes ?: null,
                    'scheduled_date' => $existingPending->scheduled_date ?: ($scheduleDt ? $scheduleDt->toDateString() : null),
                    'scheduled_time' => $existingPending->scheduled_time ?: ($scheduleDt ? $scheduleDt->format('H:i:s') : null),
                ]);

                $equipmentRequest = $existingPending->fresh();
            } else {
                $equipmentRequest = ProductionEquipment::create([
                    'episode_id' => $recording->episode_id,
                    'program_id' => $recording->episode ? $recording->episode->program_id : null,
                    'request_group_id' => $request->request_group_id ?: null,
                    'equipment_list' => $equipmentList,
                    'equipment_quantities' => $qtyByName,
                    'request_notes' => !empty($notesLines) ? implode("\n", $notesLines) : null,
                    'scheduled_date' => $scheduleDt ? $scheduleDt->toDateString() : null,
                    'scheduled_time' => $scheduleDt ? $scheduleDt->format('H:i:s') : null,
                    'status' => 'pending',
                    'requested_by' => $user->id,
                    'requested_at' => now()
                ]);
            }

            $equipmentRequestIds[] = $equipmentRequest->id;

            // Update recording with equipment list
            $recording->update([
                'equipment_used' => $request->equipment_list
            ]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => $existingPending ? 'equipment_request_updated' : 'equipment_request_created',
                    'title' => $existingPending ? 'Update Permintaan Alat' : 'Permintaan Alat Baru',
                    'message' => $existingPending
                        ? "Tim Produksi (Musik/Rekam) menambahkan item pada permintaan equipment Episode {$recording->episode->episode_number}."
                        : "Tim Rekam Vokal meminta equipment untuk rekaman vokal Episode {$recording->episode->episode_number}.",
                    'data' => [
                        'equipment_request_ids' => $equipmentRequestIds,
                        'episode_id' => $recording->episode_id,
                        'recording_id' => $recording->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recording' => $recording->fresh(['episode', 'musicArrangement']),
                    'equipment_requests' => ProductionEquipment::whereIn('id', $equipmentRequestIds)->get()
                ],
                'message' => 'Equipment requests created successfully. Art & Set Properti has been notified.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batalkan permintaan alat (hanya yang status pending dan yang diajukan oleh user ini).
     * DELETE /api/live-tv/sound-engineer/equipment-requests/{id}
     */
    public function cancelEquipmentRequest(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->isSoundEngineer($user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $equipment = ProductionEquipment::where('id', $id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$equipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan tidak ditemukan atau sudah tidak dapat dibatalkan (hanya permintaan dengan status menunggu persetujuan yang bisa dibatalkan).'
                ], 404);
            }

            $equipment->delete();
            return response()->json([
                'success' => true,
                'message' => 'Permintaan alat berhasil dibatalkan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available equipment for Sound Engineer role to view before requesting
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user is Sound Engineer
        if (!$this->isSoundEngineer($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        // Return unique names with effective availability (available - reserved pending)
        $availableEquipment = InventoryItem::where('status', 'active') // InventoryItem uses 'active' status for available
            ->select(['id', 'equipment_id', 'name', 'category', 'available_quantity', 'total_quantity'])
            ->orderBy('name')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $availableEquipment,
            'message' => 'Available equipment retrieved successfully'
        ]);
    }

    /**
     * Notify Art & Set Properti that equipment has been returned physically
     * POST /sound-engineer/equipment/{id}/notify-return
     */
    public function notifyReturn(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Check if user is Sound Engineer
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $equipment = ProductionEquipment::find($id);
            
            if (!$equipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request not found.'
                ], 404);
            }

            // Sound Engineer sudah diverifikasi via isSoundEngineer()
            // Cukup pastikan equipment terkait episode yang valid
            // (tidak perlu strict ownership check karena dalam tim bisa saling handle)

            // Verify status
            if ($equipment->status !== 'in_use' && $equipment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment must be in "approved" or "in_use" status to notify return.'
                ], 400);
            }

            // Update return notes
            $currentNotes = $equipment->return_notes ?? '';
            $timestamp = now()->format('Y-m-d H:i');
            $newNote = "[User Return Notification] Sound Engineer {$user->name} reported equipment returned at {$timestamp}.";
            
            $equipment->update([
                'return_notes' => $currentNotes ? $currentNotes . "\n" . $newNote : $newNote
            ]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_return_notification',
                    'title' => 'Pengembalian Alat (Sound Eng Reported)',
                    'message' => "Sound Engineer {$user->name} melaporkan telah mengembalikan alat: {$equipment->equipment_name} (ID: {$equipment->id}). Harap cek fisik & konfirmasi return.",
                    'data' => [
                        'equipment_id' => $equipment->id,
                        'equipment_name' => $equipment->equipment_name,
                        'reported_by' => $user->name,
                        'role' => $user->role
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi pengembalian berhasil dikirim ke Art & Set Properti. Harap tunggu konfirmasi final mereka.',
                'data' => $equipment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending return notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan - Sound Engineer selesaikan setelah input list alat
     * POST /api/live-tv/sound-engineer/recordings/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $recording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            // Check if user has access to this recording
            if (!$this->hasRecordingAccess($user, $recording)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this recording.'
                ], 403);
            }

            if ($recording->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is in_progress'
                ], 400);
            }

            // Validate equipment list has been input
            if (!$recording->equipment_used || empty($recording->equipment_used)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please input equipment list before completing work'
                ], 400);
            }

            // Update status to ready (siap untuk recording, bukan completed karena belum upload file)
            $recording->update([
                'status' => 'ready' // Status ready berarti sudah input equipment, siap untuk recording
            ]);

            // Log Workflow State for Complete Work (Equipment input)
            $workflowService = app(\App\Services\WorkflowStateService::class);
            $workflowService->updateWorkflowState(
                $recording->episode,
                'sound_engineering',
                'sound_engineer',
                $user->id,
                "Sound Engineer completed equipment input for Episode {$recording->episode->episode_number}",
                $user->id,
                [
                    'action' => 'sound_engineer_work_completed',
                    'equipment_count' => count($recording->equipment_used ?? [])
                ]
            );

            // Notify Producer
            $episode = $recording->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'sound_engineer_work_completed',
                    'title' => 'Sound Engineer Work Selesai',
                    'message' => "Sound Engineer telah menyelesaikan input list alat untuk rekaman vokal Episode {$episode->episode_number}. Siap untuk recording.",
                    'data' => [
                        'recording_id' => $recording->id,
                        'episode_id' => $recording->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement', 'createdBy']),
                'message' => 'Work completed successfully. Equipment list has been submitted. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return Equipment to Art & Set Properti
     * POST /api/live-tv/sound-engineer/recordings/{id}/return-equipment
     */
    public function returnEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_request_ids' => 'required|array|min:1',
                'equipment_request_ids.*' => 'required|integer|exists:production_equipment,id',
                'return_condition' => 'required|array|min:1',
                'return_condition.*.equipment_request_id' => 'required|integer',
                'return_condition.*.condition' => 'required|in:good,damaged,lost',
                'return_condition.*.notes' => 'nullable|string|max:1000',
                'return_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            $equipmentRequestIds = $request->equipment_request_ids;
            $returnConditions = collect($request->return_condition)->keyBy('equipment_request_id');

            // Authorization:
            // Allow return if user is the borrower for ALL equipment requests being returned,
            // OR user is a Coordinator of the recording team for this episode.
            $isCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');
            $borrowerCount = ProductionEquipment::whereIn('id', $equipmentRequestIds)
                ->where('requested_by', $user->id)
                ->count();
            $isBorrowerForAll = $borrowerCount === count($equipmentRequestIds);

            if (!$isBorrowerForAll && !$isCoordinator) {
                // Legacy access check (recording assignment)
                if ($recording->created_by !== $user->id) {
                    $episode = $recording->episode;
                    if ($episode && $episode->program && $episode->program->productionTeam) {
                        $hasAccess = $episode->program->productionTeam->members()
                            ->where('user_id', $user->id)
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->exists();
                        
                        if (!$hasAccess) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Unauthorized: You can only return equipment you borrowed (requested_by) or recordings assigned to you.'
                            ], 403);
                        }
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You can only return equipment you borrowed (requested_by) or recordings assigned to you.'
                        ], 403);
                    }
                }
            }
            
            $returnedEquipment = [];
            $failedEquipment = [];

            foreach ($equipmentRequestIds as $equipmentRequestId) {
                $equipment = ProductionEquipment::find($equipmentRequestId);
                
                if (!$equipment) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Equipment request not found'
                    ];
                    continue;
                }

                // Verify equipment belongs to this recording's episode
                if ($equipment->episode_id !== $recording->episode_id) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Equipment request does not belong to this episode'
                    ];
                    continue;
                }

                // Sound Engineer sudah diverifikasi via isSoundEngineer()
                // Tidak perlu strict ownership check

                // Verify equipment is approved (can only return approved equipment)
                if ($equipment->status !== 'approved' && $equipment->status !== 'in_use') {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => "Equipment is not in approved or in_use status (current: {$equipment->status})"
                    ];
                    continue;
                }

                // Get return condition for this equipment
                $conditionData = $returnConditions->get($equipmentRequestId);
                if (!$conditionData) {
                    $failedEquipment[] = [
                        'equipment_request_id' => $equipmentRequestId,
                        'reason' => 'Return condition not provided'
                    ];
                    continue;
                }

                // Update equipment status to returned (returned_by = user yang mengembalikan, untuk Riwayat)
                $equipment->update([
                    'status' => 'returned',
                    'return_condition' => $conditionData['condition'],
                    'return_notes' => ($conditionData['notes'] ?? '') . ($request->return_notes ? "\n" . $request->return_notes : ''),
                    'returned_at' => now(),
                    'returned_by' => $user->id
                ]);
                // NOTE: We no longer increment available_quantity here.
                // It will be handled by Art & Set Properti when they confirm the return.
                
                $returnedEquipment[] = $equipment->fresh();
            }

            // Notify Art & Set Properti
            if (!empty($returnedEquipment)) {
                $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
                $equipmentNames = collect($returnedEquipment)->map(function($eq) {
                    return is_array($eq->equipment_list) ? implode(', ', $eq->equipment_list) : ($eq->equipment_list ?? 'N/A');
                })->implode('; ');

                foreach ($artSetUsers as $artSetUser) {
                    Notification::create([
                        'user_id' => $artSetUser->id,
                        'type' => 'equipment_returned',
                        'title' => 'Alat Dikembalikan oleh Sound Engineer',
                        'message' => "Sound Engineer {$user->name} telah mengembalikan alat untuk Episode {$recording->episode->episode_number}. Alat: {$equipmentNames}",
                        'data' => [
                            'recording_id' => $recording->id,
                            'episode_id' => $recording->episode_id,
                            'equipment_request_ids' => collect($returnedEquipment)->pluck('id')->toArray(),
                            'equipment_list' => $equipmentNames,
                            'returned_by' => $user->id,
                            'returned_by_name' => $user->name
                        ]
                    ]);
                }
            }

            // Audit logging
            if (!empty($returnedEquipment)) {
                \App\Helpers\ControllerSecurityHelper::logCrud('sound_engineer_equipment_returned', $recording, [
                    'equipment_count' => count($returnedEquipment),
                    'equipment_request_ids' => collect($returnedEquipment)->pluck('id')->toArray(),
                    'failed_count' => count($failedEquipment)
                ], $request);
            }

            // Clear cache
            \App\Helpers\QueryOptimizer::clearAllIndexCaches();

            if (!empty($failedEquipment)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'recording' => $recording->fresh(['episode']),
                        'returned_equipment' => $returnedEquipment,
                        'failed_equipment' => $failedEquipment
                    ],
                    'message' => count($returnedEquipment) . ' equipment returned successfully. ' . count($failedEquipment) . ' equipment failed to return.',
                    'warnings' => $failedEquipment
                ], 207); // 207 Multi-Status
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recording' => $recording->fresh(['episode']),
                    'returned_equipment' => $returnedEquipment
                ],
                'message' => 'Equipment returned successfully. Art & Set Properti has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error returning equipment: ' . $e->getMessage()
            ], 500);
        }
    }
}














