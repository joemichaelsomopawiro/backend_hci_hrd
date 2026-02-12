<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SoundEngineerRecording;
use App\Models\MusicArrangement;
use App\Models\Episode;
use App\Models\Notification;
use App\Models\ProductionEquipment;
use App\Models\EquipmentInventory;
use App\Helpers\ControllerSecurityHelper;
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
        
        $role = $user->role;
        return in_array(strtolower($role), ['sound engineer', 'sound_engineer']);
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

            // Optimize query with eager loading
            $query = SoundEngineerRecording::with([
                'episode.program.managerProgram',
                'episode.program.productionTeam.members.user',
                'musicArrangement.song',
                'musicArrangement.singer',
                'createdBy',
                'reviewedBy'
            ]);
            
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
                // Only show recordings created by current user
                $query->where('created_by', $user->id);
            }
            
            $recordings = $query->orderBy('created_at', 'desc')->paginate(15);
            
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

            $recording = SoundEngineerRecording::with([
            'episode.program.productionTeam.members',
            'musicArrangement',
            'createdBy',
            'reviewedBy'
        ])->findOrFail($id);
        
        // Check if user has access to this recording (must be creator or in same production team)
        if ($recording->created_by !== $user->id) {
            // Check if user is in the same production team
            $productionTeam = $recording->episode?->program?->productionTeam;
            if ($productionTeam) {
                // Access members from eager-loaded collection
                $hasAccess = $productionTeam->members
                    ->where('user_id', $user->id)
                    ->where('role', 'sound_eng')
                    ->where('is_active', true)
                    ->count() > 0;
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You do not have access to this recording.'
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You do not have access to this recording.'
                    ], 403);
                }
            }
            
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
            ->where('role', 'sound_eng')
            ->where('is_active', true)
            ->exists();

            if (!$hasAccess) {
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
            
            // Check if user has access (must be creator or in same production team)
            if ($recording->created_by !== $user->id) {
                $productionTeam = $recording->episode?->program?->productionTeam;
                if ($productionTeam) {
                    $hasAccess = $productionTeam->members
                        ->where('user_id', $user->id)
                        ->where('role', 'sound_eng')
                        ->where('is_active', true)
                        ->count() > 0;
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You do not have access to update this recording.'
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You do not have access to update this recording.'
                    ], 403);
                }
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
            
            $oldData = $recording->toArray();
            $updateData = $request->only([
                'recording_notes',
                'equipment_used',
                'recording_schedule',
                'file_link' // New: External storage link
            ]);
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
            
            // Check if user has access
            if ($recording->created_by !== $user->id) {
                $productionTeam = $recording->episode?->program?->productionTeam;
                if ($productionTeam) {
                    $hasAccess = $productionTeam->members
                        ->where('user_id', $user->id)
                        ->where('role', 'sound_eng')
                        ->where('is_active', true)
                        ->count() > 0;
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You do not have access to this recording.'
                        ], 403);
                    }
                }
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
            
            // Check if user has access
            if ($recording->created_by !== $user->id) {
                $productionTeam = $recording->episode?->program?->productionTeam;
                if ($productionTeam) {
                    $hasAccess = $productionTeam->members
                        ->where('user_id', $user->id)
                        ->where('role', 'sound_eng')
                        ->where('is_active', true)
                        ->count() > 0;
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You do not have access to this recording.'
                        ], 403);
                    }
                }
            }
            
            if ($recording->status !== 'recording') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active recordings can be completed'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'vocal_file_link' => 'required|url|max:2048',
                'recording_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update recording with link and status
            $recording->update([
                'status' => 'completed',
                'recording_completed_at' => now(),
                'file_link' => $request->vocal_file_link,
                'recording_notes' => $request->recording_notes ?? $recording->recording_notes
            ]);
            
            // Auto-create Sound Engineer Editing task
            $existingEditing = \App\Models\SoundEngineerEditing::where('episode_id', $recording->episode_id)
                ->where('sound_engineer_recording_id', $recording->id)
                ->first();

            if (!$existingEditing) {
                $editing = \App\Models\SoundEngineerEditing::create([
                    'episode_id' => $recording->episode_id,
                    'sound_engineer_recording_id' => $recording->id,
                    'sound_engineer_id' => $user->id,
                    'vocal_file_path' => $recording->file_path ?? null, // Copy recording file path (backward compatibility)
                    'vocal_file_link' => $request->vocal_file_link, // Use the new link
                    'editing_notes' => "Editing task created automatically from completed recording. Recording notes: " . ($request->recording_notes ?? 'N/A'),
                    'status' => 'in_progress',
                    'created_by' => $user->id
                ]);

                // Notify Producer for recording QC (only notify Producer from same production team)
                $episode = $recording->episode;
                $productionTeam = $episode->program->productionTeam;
                
                if ($productionTeam && $productionTeam->producer) {
                    \App\Models\Notification::create([
                        'user_id' => $productionTeam->producer_id,
                        'type' => 'sound_engineer_recording_completed',
                        'title' => 'Sound Engineer Recording Completed',
                        'message' => "Sound Engineer {$user->name} has completed recording for Episode {$episode->episode_number}. Please review for QC.",
                        'data' => [
                            'recording_id' => $recording->id,
                            'editing_id' => $editing->id,
                            'episode_id' => $recording->episode_id
                        ]
                    ]);
                }
                
                // Update workflow state to sound_engineering if needed
                if ($episode->current_workflow_state === 'production' || $episode->current_workflow_state === 'production_planning' || $episode->current_workflow_state === 'shooting_recording') {
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'sound_engineering',
                        'sound_eng',
                        null,
                        'Sound engineer recording completed, proceeding to editing'
                    );
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
            
            // Check if user has access
            if ($recording->created_by !== $user->id) {
                $productionTeam = $recording->episode?->program?->productionTeam;
                if ($productionTeam) {
                    $hasAccess = $productionTeam->members
                        ->where('user_id', $user->id)
                        ->where('role', 'sound_eng')
                        ->where('is_active', true)
                        ->count() > 0;
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You do not have access to this recording.'
                        ], 403);
                    }
                }
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
            
            // Check if user has access
            if ($recording->created_by !== $user->id) {
                $productionTeam = $recording->episode?->program?->productionTeam;
                if ($productionTeam) {
                    $hasAccess = $productionTeam->members
                        ->where('user_id', $user->id)
                        ->where('role', 'sound_eng')
                        ->where('is_active', true)
                        ->count() > 0;
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You do not have access to this recording.'
                        ], 403);
                    }
                }
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
            $user = Auth::user();
            
            if (!$this->isSoundEngineer($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . ($user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            $recordings = SoundEngineerRecording::where('status', $status)
                ->where('created_by', $user->id) // Only show current user's recordings
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
            
            $query = MusicArrangement::with(['episode.productionTeam', 'episode.program.productionTeam', 'createdBy', 'reviewedBy'])
                ->whereIn('status', ['arrangement_rejected', 'rejected']) // Support both status values
                ->where(function($q) {
                    $q->where('needs_sound_engineer_help', true)
                      ->orWhereNull('sound_engineer_helper_id');
                });

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Debug: Cek sebelum filter productionTeam
            $beforeFilter = $query->get();
            Log::info('SoundEngineer getRejectedArrangements - Before productionTeam filter', [
                'user_id' => $user->id,
                'total_before_filter' => $beforeFilter->count(),
                'arrangements' => $beforeFilter->map(function ($arr) {
                    $episode = $arr->episode;
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'episode_id' => $arr->episode_id,
                        'episode_production_team_id' => $episode->production_team_id ?? null,
                        'program_production_team_id' => ($episode->program && $episode->program->productionTeam) ? $episode->program->production_team_id : null
                    ];
                })->toArray()
            ]);

            // Only show arrangements from user's production teams
            // Support episode.productionTeam langsung atau episode.program.productionTeam
            $query->where(function ($q) use ($user) {
                // Episode punya productionTeam langsung
                $q->whereHas('episode.productionTeam.members', function ($subQ) use ($user) {
                    $subQ->where('user_id', $user->id)
                         ->where('role', 'sound_eng')
                         ->where('is_active', true);
                })
                // Atau episode tidak punya productionTeam, ambil dari Program
                ->orWhereHas('episode.program.productionTeam.members', function ($subQ) use ($user) {
                    $subQ->where('user_id', $user->id)
                         ->where('role', 'sound_eng')
                         ->where('is_active', true);
                });
            });
            
            // Debug: Cek setelah filter productionTeam
            $afterFilter = $query->get();
            Log::info('SoundEngineer getRejectedArrangements - After productionTeam filter', [
                'user_id' => $user->id,
                'total_after_filter' => $afterFilter->count(),
                'arrangements' => $afterFilter->map(function ($arr) {
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'episode_id' => $arr->episode_id
                    ];
                })->toArray()
            ]);

            $arrangements = $query->orderBy('reviewed_at', 'desc')->paginate(15);

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

            $arrangement = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($arrangementId);

            // Only allow help if status is song_rejected
            if ($arrangement->status !== 'song_rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only help fix rejected song proposals'
                ], 400);
            }

            // Check if Sound Engineer has access
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
                'file' => 'nullable|file|mimes:mp3,wav,midi|max:102400', // Optional: upload fixed arrangement file (100MB max)
                'help_file_link' => 'nullable|url|max:2048', // Optional: link to fixed arrangement
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($arrangementId);

            // Validate arrangement is rejected
            if (!in_array($arrangement->status, ['arrangement_rejected', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only help fix rejected arrangements'
                ], 400);
            }

            // Validate Sound Engineer is in the same production team
            // Support episode.productionTeam langsung atau episode.program.productionTeam
            $episode = $arrangement->episode;
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            
            if (!$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode does not have a production team assigned'
                ], 400);
            }
            
            $isInTeam = $productionTeam->members()
                ->where('user_id', $user->id)
                ->where('role', 'sound_eng')
                ->where('is_active', true)
                ->exists();

            if (!$isInTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not part of the production team for this arrangement'
                ], 403);
            }

            // Prepare update data
            // Mark help as provided
            $updateData = [
                'needs_sound_engineer_help' => false,
                'sound_engineer_helper_id' => $user->id,
                'sound_engineer_help_at' => now(),
                'sound_engineer_help_notes' => $request->help_notes
            ];

            // Update arrangement with suggested fixes if provided
            if ($request->has('suggested_fixes')) {
                $updateData['arrangement_notes'] = ($arrangement->arrangement_notes ?? '') . "\n\n[Sound Engineer Help] " . $request->suggested_fixes;
            }

            // Handle Help File Link
            if ($request->has('help_file_link')) {
                $updateData['sound_engineer_help_file_link'] = $request->help_file_link;
            }

            // Handle file upload if provided
            $fileUploaded = false;
            if ($request->hasFile('file')) {
                try {
                    // Start saving file logic
                    $file = $request->file('file');
                    $filePath = $file->store('music-arrangements', 'public');
                    
                    // We save the file info but we DO NOT submit it as the "main" file yet.
                    // Or do we? The requirement is "Submit Link to Arranger".
                    // If we overwrite file_path, the Arranger sees it. 
                    // Let's overwrite file_path so Arranger can download it, BUT do NOT change status to submitted.
                    
                    $updateData['file_path'] = $filePath;
                    $updateData['file_name'] = $file->getClientOriginalName();
                    $updateData['file_size'] = $file->getSize();
                    $updateData['mime_type'] = $file->getMimeType();
                    $fileUploaded = true;

                    // Log file upload
                    \App\Helpers\AuditLogger::logFileUpload('audio', $file->getClientOriginalName(), $file->getSize(), null, $request);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload failed: ' . $e->getMessage()
                    ], 422);
                }
            }

            // Update arrangement
            $arrangement->update($updateData);
            
            // Jika Sound Engineer menyertakan file (link atau upload), langsung submit ke Producer
            // Ini agar tidak perlu bolak-balik ke Music Arranger lagi
            $hasHelpFile = $request->has('help_file_link') || $fileUploaded;
            
            if ($hasHelpFile) {
                // Copy help file to main file_link jika perlu
                if ($request->has('help_file_link') && !$arrangement->file_link) {
                    $arrangement->update([
                        'file_link' => $request->help_file_link
                    ]);
                }
                
                // Auto-submit ke Producer
                $arrangement->update([
                    'status' => 'arrangement_submitted',
                    'submitted_at' => now()
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

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This recording is not assigned to you.'
                ], 403);
            }

            // Get recording schedule from Creative Work
            $creativeWork = $recording->episode->creativeWork;
            if (!$creativeWork || !$creativeWork->recording_schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording schedule not found in Creative Work'
                ], 400);
            }

            $recording->update([
                'recording_schedule' => $creativeWork->recording_schedule,
                'status' => 'scheduled'
            ]);

            return response()->json([
                'success' => true,
                'data' => $recording->fresh(['episode', 'musicArrangement']),
                'message' => 'Recording schedule accepted successfully. Recording date: ' . \Carbon\Carbon::parse($creativeWork->recording_schedule)->format('d M Y')
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

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This recording is not assigned to you.'
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
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.return_date' => 'required|date|after_or_equal:today',
                'equipment_list.*.notes' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recording = SoundEngineerRecording::with(['episode'])->findOrFail($id);

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This recording is not assigned to you.'
                ], 403);
            }

            $equipmentRequests = [];
            $unavailableEquipment = [];

            // Check each equipment availability
            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];
                $quantity = $equipment['quantity'];

                // Check if equipment is available (not in_use or assigned)
            $availableCount = EquipmentInventory::where('name', $equipmentName)
                ->whereIn('status', ['available'])
                ->count();



                if ($availableCount < $quantity) {
                    $unavailableEquipment[] = [
                        'equipment_name' => $equipmentName,
                        'requested_quantity' => $quantity,
                        'available_count' => $availableCount,
                        'reason' => 'Equipment tidak tersedia dalam jumlah yang diminta'
                    ];
                    continue;
                }

                // Create equipment request
                // We fill the array with the equipment name repeated 'quantity' times 
                // to match the Art & Set Properti approval logic
                $equipmentRequest = ProductionEquipment::create([
                    'episode_id' => $recording->episode_id,
                    'equipment_list' => array_fill(0, $quantity, $equipmentName),
                    'request_notes' => ($equipment['notes'] ?? '') . ($request->request_notes ? "\n" . $request->request_notes : ''),
                    'status' => 'pending',
                    'requested_by' => $user->id,
                    'requested_at' => now()
                ]);

                $equipmentRequests[] = $equipmentRequest->id;
            }

            if (!empty($unavailableEquipment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some equipment is not available or currently in use',
                    'unavailable_equipment' => $unavailableEquipment,
                    'available_requests' => $equipmentRequests
                ], 400);
            }

            // Update recording with equipment list
            $recording->update([
                'equipment_used' => $request->equipment_list
            ]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_request_created',
                    'title' => 'Permintaan Alat Baru',
                    'message' => "Sound Engineer meminta equipment untuk rekaman vokal Episode {$recording->episode->episode_number}.",
                    'data' => [
                        'equipment_request_ids' => $equipmentRequests,
                        'episode_id' => $recording->episode_id,
                        'recording_id' => $recording->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recording' => $recording->fresh(['episode', 'musicArrangement']),
                    'equipment_requests' => ProductionEquipment::whereIn('id', $equipmentRequests)->get()
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
        
        // Group by name to show total available quantity for each type/model
        $availableEquipment = EquipmentInventory::where('status', 'available')
            ->select('name', 'category', \DB::raw('count(*) as available_quantity'))
            ->groupBy('name', 'category')
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

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This recording is not assigned to you.'
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

            // Check if user has access
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
                            'message' => 'Unauthorized: This recording is not assigned to you.'
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: This recording is not assigned to you.'
                    ], 403);
                }
            }

            $equipmentRequestIds = $request->equipment_request_ids;
            $returnConditions = collect($request->return_condition)->keyBy('equipment_request_id');
            
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

                // Update equipment status to returned
                $equipment->update([
                    'status' => 'returned',
                    'return_condition' => $conditionData['condition'],
                    'return_notes' => ($conditionData['notes'] ?? '') . ($request->return_notes ? "\n" . $request->return_notes : ''),
                    'returned_at' => now()
                ]);

                // Update EquipmentInventory if exists
                $equipmentInventory = \App\Models\EquipmentInventory::where('episode_id', $equipment->episode_id)
                    ->where('equipment_name', is_array($equipment->equipment_list) ? $equipment->equipment_list[0] : $equipment->equipment_list)
                    ->where('status', 'assigned')
                    ->where('assigned_to', $user->id)
                    ->first();

                if ($equipmentInventory) {
                    $equipmentInventory->update([
                        'status' => 'returned',
                        'return_condition' => $conditionData['condition'],
                        'return_notes' => $conditionData['notes'] ?? null,
                        'returned_at' => now()
                    ]);
                }

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














