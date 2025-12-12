<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Episode;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\ProductionEquipment;
use App\Models\SoundEngineerRecording;
use App\Models\SoundEngineerEditing;
use App\Models\EditorWork;
use App\Models\DesignGrafisWork;
use App\Models\PromotionMaterial;
use App\Models\BroadcastingSchedule;
use App\Models\QualityControl;
use App\Models\Budget;
use App\Models\Notification;
use App\Models\ProgramApproval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProducerController extends Controller
{
    /**
     * Get approvals pending
     */
    public function getApprovals(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $approvals = [];
            
            // Song proposals pending approval (status song_proposal)
            $songProposals = MusicArrangement::where('status', 'song_proposal')
                ->with(['episode.program.productionTeam', 'createdBy'])
                ->whereHas('episode.program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->get();
            
            // Music arrangements pending approval (arrangement file submitted)
            // Producer hanya bisa melihat arrangement dari ProductionTeam mereka
            $musicArrangements = MusicArrangement::whereIn('status', ['submitted', 'arrangement_submitted'])
                ->with(['episode.program.productionTeam', 'createdBy'])
                ->whereHas('episode.program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->get();
            
            // Creative works pending approval
            $creativeWorks = CreativeWork::where('status', 'submitted')
                ->with(['episode', 'createdBy'])
                ->get();
            
            // Equipment requests pending approval
            $equipmentRequests = ProductionEquipment::where('status', 'pending')
                ->with(['episode', 'requestedBy'])
                ->get();
            
            // Budget requests pending approval
            $budgetRequests = Budget::where('status', 'submitted')
                ->with(['episode', 'requestedBy'])
                ->get();

            // Sound Engineer Recordings pending QC (completed recordings yang belum direview)
            $soundEngineerRecordings = SoundEngineerRecording::where('status', 'completed')
                ->whereNull('reviewed_by')
                ->with(['episode.program.productionTeam', 'musicArrangement', 'createdBy'])
                ->whereHas('episode.program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->get();

            // Sound Engineer Editing pending approval
            $soundEngineerEditing = SoundEngineerEditing::where('status', 'submitted')
                ->with(['episode.program.productionTeam', 'recording', 'soundEngineer'])
                ->whereHas('episode.program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->get();
            
            // Editor Work pending approval
            $editorWorks = EditorWork::where('status', 'submitted')
                ->with(['episode.program.productionTeam', 'createdBy'])
                ->whereHas('episode.program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->get();
            
            $approvals = [
                'song_proposals' => $songProposals, // New: Song proposals (lagu & penyanyi)
                'music_arrangements' => $musicArrangements, // Arrangement files
                'creative_works' => $creativeWorks,
                'equipment_requests' => $equipmentRequests,
                'budget_requests' => $budgetRequests,
                'sound_engineer_recordings' => $soundEngineerRecordings,
                'sound_engineer_editing' => $soundEngineerEditing,
                'editor_works' => $editorWorks
            ];
            
            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Pending approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending approvals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve item
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:song_proposal,music_arrangement,creative_work,equipment_request,budget_request,sound_engineer_recording,sound_engineer_editing,editor_work',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $item = null;
            
            switch ($request->type) {
                case 'song_proposal':
                    // Approve song proposal (lagu & penyanyi)
                    $item = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This song proposal is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'song_proposal') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only song proposals can be approved'
                        ], 400);
                    }
                    
                    // Approve song proposal
                    $item->update([
                        'status' => 'song_approved',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_notes' => $request->notes
                    ]);
                    
                    // Notify Music Arranger
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'song_proposal_approved',
                        'title' => 'Usulan Lagu & Penyanyi Diterima',
                        'message' => "Usulan lagu '{$item->song_title}'" . ($item->singer_name ? " dengan penyanyi '{$item->singer_name}'" : '') . " telah diterima. Silakan arrange lagu.",
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $item->fresh(['episode', 'createdBy']),
                        'message' => 'Song proposal approved successfully. Music Arranger has been notified.'
                    ]);
                    
                case 'music_arrangement':
                    // Approve arrangement file (setelah song approved dan arrangement file submitted)
                    $item = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access to this arrangement
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This arrangement is not from your production team.'
                        ], 403);
                    }
                    
                    // Only approve if status is arrangement_submitted
                    if (!in_array($item->status, ['arrangement_submitted', 'submitted'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted arrangement files can be approved'
                        ], 400);
                    }
                    
                    // Update status to arrangement_approved
                    $item->update([
                        'status' => 'arrangement_approved',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_notes' => $request->notes
                    ]);
                    
                    // Reload to get updated values after approve
                    $item->refresh();
                    
                    // Notify Music Arranger
                    $approvalMessage = "Arrangement lagu '{$item->song_title}' telah disetujui oleh Producer.";
                    if ($item->producer_modified) {
                        $approvalMessage .= " Producer telah memodifikasi: Song dari '{$item->original_song_title}' menjadi '{$item->song_title}', Singer dari '{$item->original_singer_name}' menjadi '{$item->singer_name}'.";
                    }
                    
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'music_arrangement_approved',
                        'title' => 'Arrangement Lagu Disetujui',
                        'message' => $approvalMessage,
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes,
                            'producer_modified' => $item->producer_modified
                        ]
                    ]);

                    // Auto-create recording task for Sound Engineer
                    // Get Sound Engineer from ProductionTeam
                    $episode = $item->episode;
                    $productionTeam = $episode->program->productionTeam;
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->get();

                        foreach ($soundEngineers as $soundEngineerMember) {
                            // Check if recording already exists
                            $existingRecording = SoundEngineerRecording::where('episode_id', $episode->id)
                                ->where('music_arrangement_id', $item->id)
                                ->first();

                            if (!$existingRecording) {
                                // Create draft recording task
                                $recording = SoundEngineerRecording::create([
                                    'episode_id' => $episode->id,
                                    'music_arrangement_id' => $item->id,
                                    'recording_notes' => "Recording task created automatically for approved arrangement: {$item->song_title}",
                                    'status' => 'draft',
                                    'created_by' => $soundEngineerMember->user_id
                                ]);

                                // Notify Sound Engineer
                                Notification::create([
                                    'user_id' => $soundEngineerMember->user_id,
                                    'type' => 'recording_task_created',
                                    'title' => 'New Recording Task',
                                    'message' => "A recording task has been created for approved arrangement '{$item->song_title}' (Episode {$episode->episode_number}).",
                                    'data' => [
                                        'recording_id' => $recording->id,
                                        'arrangement_id' => $item->id,
                                        'episode_id' => $episode->id
                                    ]
                                ]);
                            }
                        }
                    }
                    
                    // Auto-create Creative Work task after Music Arrangement approved
                    $episode = $item->episode;
                    $existingCreativeWork = CreativeWork::where('episode_id', $episode->id)->first();
                    
                    if (!$existingCreativeWork) {
                        // Get Creative from production team
                        $productionTeam = $episode->program->productionTeam;
                        $creativeUser = null;
                        
                        if ($productionTeam) {
                            $creativeMember = $productionTeam->members()
                                ->where('role', 'creative')
                                ->where('is_active', true)
                                ->first();
                            
                            if ($creativeMember) {
                                $creativeUser = $creativeMember->user_id;
                            }
                        }
                        
                        // If no Creative in team, find any Creative user
                        if (!$creativeUser) {
                            $creative = \App\Models\User::where('role', 'Creative')->first();
                            $creativeUser = $creative ? $creative->id : null;
                        }
                        
                        // Create Creative Work task
                        $creativeWork = CreativeWork::create([
                            'episode_id' => $episode->id,
                            'script_content' => "Creative work task for episode {$episode->episode_number}. Music arrangement '{$item->song_title}' has been approved.",
                            'status' => 'draft',
                            'created_by' => $creativeUser ?? $user->id
                        ]);
                        
                        // Notify Creative
                        if ($creativeUser) {
                            Notification::create([
                                'user_id' => $creativeUser,
                                'type' => 'creative_work_created',
                                'title' => 'New Creative Work Task',
                                'message' => "A new creative work task has been created for Episode {$episode->episode_number}. Music arrangement '{$item->song_title}' has been approved.",
                                'data' => [
                                    'creative_work_id' => $creativeWork->id,
                                    'episode_id' => $episode->id,
                                    'arrangement_id' => $item->id
                                ]
                            ]);
                        }
                    }
                    
                    // Auto-update episode workflow state if needed
                    if ($episode->current_workflow_state === 'music_arrangement') {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $episode,
                            'creative_work',
                            'creative',
                            null,
                            'Music arrangement approved, proceeding to creative work'
                        );
                    }
                    break;
                    
                case 'creative_work':
                    $item = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This creative work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted creative works can be approved'
                        ], 400);
                    }
                    
                    $item->approve(auth()->id(), $request->notes);
                    
                    // Notify Creative
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'creative_work_approved',
                        'title' => 'Creative Work Approved',
                        'message' => "Your creative work for Episode {$item->episode->episode_number} has been approved by Producer.",
                        'data' => [
                            'creative_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    
                    // Update workflow state to production_planning
                    if ($item->episode->current_workflow_state === 'creative_work') {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $item->episode,
                            'production_planning',
                            'produksi',
                            null,
                            'Creative work approved, proceeding to production planning'
                        );
                    }
                    break;
                    
                case 'equipment_request':
                    $item = ProductionEquipment::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;
                    
                case 'budget_request':
                    $item = Budget::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;

                case 'sound_engineer_recording':
                    $item = SoundEngineerRecording::with(['episode.program.productionTeam'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This recording is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only completed recordings can be reviewed'
                        ], 400);
                    }

                    // Review recording (mark as reviewed)
                    $item->review(auth()->id(), $request->notes);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'sound_engineer_recording_approved',
                        'title' => 'Recording Approved',
                        'message' => "Your recording has been approved by Producer. Notes: " . ($request->notes ?? 'No notes'),
                        'data' => [
                            'recording_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    break;

                case 'sound_engineer_editing':
                    $item = SoundEngineerEditing::with(['episode.program.productionTeam'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editing work is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editing works can be approved'
                        ], 400);
                    }

                    // Approve editing
                    $item->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now()
                    ]);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->sound_engineer_id,
                        'type' => 'sound_engineer_editing_approved',
                        'title' => 'Editing Work Approved',
                        'message' => "Your editing work has been approved by Producer. Notes: " . ($request->notes ?? 'No notes'),
                        'data' => [
                            'editing_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);

                    // Notify Editor that audio is ready
                    $episode = $item->episode;
                    $productionTeam = $episode->program->productionTeam;
                    
                    // Get Editors from production team
                    $editors = [];
                    if ($productionTeam) {
                        $editorMembers = $productionTeam->members()
                            ->where('role', 'editor')
                            ->where('is_active', true)
                            ->get();
                        
                        foreach ($editorMembers as $editorMember) {
                            $editors[] = $editorMember->user_id;
                        }
                    }
                    
                    // If no editors in team, find any Editor user
                    if (empty($editors)) {
                        $editorUsers = \App\Models\User::where('role', 'Editor')->pluck('id')->toArray();
                        $editors = $editorUsers;
                    }
                    
                    foreach ($editors as $editorId) {
                        Notification::create([
                            'user_id' => $editorId,
                            'type' => 'audio_ready_for_editing',
                            'title' => 'Audio Ready for Video Editing',
                            'message' => "Final audio file is ready for episode {$episode->episode_number}. You can now start video editing.",
                            'data' => [
                                'editing_id' => $item->id,
                                'episode_id' => $episode->id,
                                'audio_file_path' => $item->final_file_path
                            ]
                        ]);
                    }
                    
                    // Update workflow state to editing
                    if ($episode->current_workflow_state === 'sound_engineering' || $episode->current_workflow_state === 'production') {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $episode,
                            'editing',
                            'editor',
                            null,
                            'Sound engineer editing approved, audio ready for video editing'
                        );
                    }
                    break;
                    
                case 'editor_work':
                    $item = EditorWork::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editor work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editor works can be approved'
                        ], 400);
                    }
                    
                    $item->approve(auth()->id(), $request->notes);
                    
                    // Notify Editor
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'editor_work_approved',
                        'title' => 'Editor Work Approved',
                        'message' => "Your editor work for Episode {$item->episode->episode_number} has been approved by Producer.",
                        'data' => [
                            'editor_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    
                    // Notify Quality Control that editing is ready for QC
                    $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
                    foreach ($qcUsers as $qcUser) {
                        Notification::create([
                            'user_id' => $qcUser->id,
                            'type' => 'editor_work_ready_for_qc',
                            'title' => 'Editor Work Ready for QC',
                            'message' => "Editor work for Episode {$item->episode->episode_number} has been approved and is ready for quality control.",
                            'data' => [
                                'editor_work_id' => $item->id,
                                'episode_id' => $item->episode_id
                            ]
                        ]);
                    }
                    
                    // Update workflow state to quality_control
                    if ($item->episode->current_workflow_state === 'editing') {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $item->episode,
                            'quality_control',
                            'quality_control',
                            null,
                            'Editor work approved, proceeding to quality control'
                        );
                    }
                    break;
            }
            
            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Item approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject item
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:song_proposal,music_arrangement,creative_work,equipment_request,budget_request,sound_engineer_recording,sound_engineer_editing,editor_work',
            'reason' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $item = null;
            
            switch ($request->type) {
                case 'song_proposal':
                    // Reject song proposal (lagu & penyanyi)
                    $item = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This song proposal is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'song_proposal') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only song proposals can be rejected'
                        ], 400);
                    }
                    
                    // Reject song proposal
                    $item->update([
                        'status' => 'song_rejected',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'rejection_reason' => $request->reason
                    ]);
                    
                    // Notify Music Arranger
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'song_proposal_rejected',
                        'title' => 'Usulan Lagu & Penyanyi Ditolak',
                        'message' => "Usulan lagu '{$item->song_title}'" . ($item->singer_name ? " dengan penyanyi '{$item->singer_name}'" : '') . " telah ditolak. Alasan: {$request->reason}. Sound Engineer dapat membantu perbaikan.",
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason,
                            'needs_sound_engineer_help' => true
                        ]
                    ]);
                    
                    // Notify Sound Engineers that they can help
                    $episode = $item->episode;
                    $productionTeam = $episode->program->productionTeam;
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->get();
                        
                        foreach ($soundEngineers as $soundEngineerMember) {
                            Notification::create([
                                'user_id' => $soundEngineerMember->user_id,
                                'type' => 'song_proposal_rejected_help_needed',
                                'title' => 'Bantu Perbaikan Usulan Lagu',
                                'message' => "Usulan lagu '{$item->song_title}' untuk Episode {$episode->episode_number} telah ditolak. Anda dapat membantu Music Arranger untuk perbaikan.",
                                'data' => [
                                    'arrangement_id' => $item->id,
                                    'episode_id' => $episode->id,
                                    'rejection_reason' => $request->reason
                                ]
                            ]);
                        }
                    }
                    
                    return response()->json([
                        'success' => true,
                        'data' => $item->fresh(['episode', 'createdBy']),
                        'message' => 'Song proposal rejected successfully. Music Arranger and Sound Engineers have been notified.'
                    ]);
                    
                case 'music_arrangement':
                    // Reject arrangement file
                    $item = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access to this arrangement
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This arrangement is not from your production team.'
                        ], 403);
                    }
                    
                    // Only reject if status is arrangement_submitted
                    if (!in_array($item->status, ['arrangement_submitted', 'submitted'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted arrangement files can be rejected'
                        ], 400);
                    }
                    
                    $item->update([
                        'status' => 'arrangement_rejected',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'rejection_reason' => $request->reason
                    ]);
                    
                    // Notify Music Arranger
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'music_arrangement_rejected',
                        'title' => 'Arrangement Lagu Ditolak',
                        'message' => "Arrangement lagu '{$item->song_title}' telah ditolak. Alasan: {$request->reason}. Sound Engineer dapat membantu perbaikan arrangement ini.",
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason,
                            'needs_sound_engineer_help' => true
                        ]
                    ]);

                    // Notify Sound Engineers in the production team that they can help
                    $episode = $item->episode;
                    $productionTeam = $episode->program->productionTeam;
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->get();

                        foreach ($soundEngineers as $soundEngineerMember) {
                            Notification::create([
                                'user_id' => $soundEngineerMember->user_id,
                                'type' => 'arrangement_needs_help',
                                'title' => 'Arrangement Ditolak - Butuh Bantuan',
                                'message' => "Arrangement '{$item->song_title}' untuk Episode {$episode->episode_number} telah ditolak. Anda dapat membantu perbaikan arrangement ini.",
                                'data' => [
                                    'arrangement_id' => $item->id,
                                    'episode_id' => $episode->id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                        }
                    }
                    break;
                    
                case 'creative_work':
                    $item = CreativeWork::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This creative work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted creative works can be rejected'
                        ], 400);
                    }
                    
                    $item->reject(auth()->id(), $request->reason);
                    
                    // Notify Creative
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'creative_work_rejected',
                        'title' => 'Creative Work Rejected',
                        'message' => "Your creative work for Episode {$item->episode->episode_number} has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'creative_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                    
                    // Workflow state tetap di creative_work untuk revisi
                    break;
                    
                case 'equipment_request':
                    $item = ProductionEquipment::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;
                    
                case 'budget_request':
                    $item = Budget::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;

                case 'sound_engineer_recording':
                    $item = SoundEngineerRecording::with(['episode.program.productionTeam'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This recording is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only completed recordings can be rejected'
                        ], 400);
                    }

                    // Reject recording - change status back to recording for revision
                    $item->update([
                        'status' => 'recording',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_notes' => $request->reason
                    ]);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'sound_engineer_recording_rejected',
                        'title' => 'Recording Needs Revision',
                        'message' => "Your recording has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'recording_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                    break;

                case 'sound_engineer_editing':
                    $item = SoundEngineerEditing::with(['episode.program.productionTeam'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editing work is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editing works can be rejected'
                        ], 400);
                    }

                    // Reject editing
                    $item->update([
                        'status' => 'revision_needed',
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => $request->reason
                    ]);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->sound_engineer_id,
                        'type' => 'sound_engineer_editing_rejected',
                        'title' => 'Editing Work Needs Revision',
                        'message' => "Your editing work has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'editing_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                    break;
                    
                case 'editor_work':
                    $item = EditorWork::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editor work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editor works can be rejected'
                        ], 400);
                    }
                    
                    $item->reject(auth()->id(), $request->reason);
                    
                    // Notify Editor
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'editor_work_rejected',
                        'title' => 'Editor Work Rejected',
                        'message' => "Your editor work for Episode {$item->episode->episode_number} has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'editor_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                    break;
            }
            
            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Item rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get programs
     * Producer hanya bisa melihat program dari ProductionTeam mereka
     */
    public function getPrograms(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $query = Program::with(['managerProgram', 'productionTeam']);
            
            // Producer hanya bisa melihat program dari ProductionTeam mereka
            $query->whereHas('productionTeam', function ($q) use ($user) {
                $q->where('producer_id', $user->id);
            });
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by production team
            if ($request->has('production_team_id')) {
                $query->where('production_team_id', $request->production_team_id);
            }
            
            $programs = $query->orderBy('created_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $programs,
                'message' => 'Programs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes
     * Producer hanya bisa melihat episode dari program ProductionTeam mereka
     */
    public function getEpisodes(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $query = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                });
            
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
            
            $episodes = $query->orderBy('episode_number')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get episodes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get production overview
     */
    public function getProductionOverview(Request $request): JsonResponse
    {
        try {
            $programId = $request->get('program_id');
            
            $overview = [
                'programs' => Program::count(),
                'episodes' => Episode::count(),
                'deadlines' => \App\Models\Deadline::count(),
                'overdue_deadlines' => \App\Models\Deadline::where('status', 'overdue')->count(),
                'pending_approvals' => $this->getPendingApprovalsCount(),
                'in_production_episodes' => Episode::where('status', 'in_production')->count(),
                'completed_episodes' => Episode::where('status', 'aired')->count()
            ];
            
            if ($programId) {
                $overview['program_specific'] = [
                    'episodes' => Episode::where('program_id', $programId)->count(),
                    'deadlines' => \App\Models\Deadline::whereHas('episode', function ($q) use ($programId) {
                        $q->where('program_id', $programId);
                    })->count(),
                    'overdue_deadlines' => \App\Models\Deadline::whereHas('episode', function ($q) use ($programId) {
                        $q->where('program_id', $programId);
                    })->where('status', 'overdue')->count()
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $overview,
                'message' => 'Production overview retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get production overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending approvals count
     */
    private function getPendingApprovalsCount(): int
    {
        return MusicArrangement::where('status', 'submitted')->count() +
               CreativeWork::where('status', 'submitted')->count() +
               ProductionEquipment::where('status', 'pending')->count() +
               Budget::where('status', 'submitted')->count();
    }

    /**
     * Get team performance
     */
    public function getTeamPerformance(Request $request): JsonResponse
    {
        try {
            $programId = $request->get('program_id');
            $teamId = $request->get('team_id');
            
            $query = \App\Models\ProductionTeam::with(['members.user']);
            
            if ($teamId) {
                $query->where('id', $teamId);
            }
            
            $teams = $query->get();
            
            $performance = [];
            
            foreach ($teams as $team) {
                $teamPerformance = [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'members' => [],
                    'total_deadlines' => 0,
                    'completed_deadlines' => 0,
                    'overdue_deadlines' => 0
                ];
                
                foreach ($team->members as $member) {
                    $memberPerformance = [
                        'user_id' => $member->user_id,
                        'user_name' => $member->user->name,
                        'role' => $member->role,
                        'deadlines' => $this->getMemberDeadlines($member->user_id, $programId),
                        'workflow_tasks' => $this->getMemberWorkflowTasks($member->user_id, $programId)
                    ];
                    
                    $teamPerformance['members'][] = $memberPerformance;
                    $teamPerformance['total_deadlines'] += $memberPerformance['deadlines']['total'];
                    $teamPerformance['completed_deadlines'] += $memberPerformance['deadlines']['completed'];
                    $teamPerformance['overdue_deadlines'] += $memberPerformance['deadlines']['overdue'];
                }
                
                $performance[] = $teamPerformance;
            }
            
            return response()->json([
                'success' => true,
                'data' => $performance,
                'message' => 'Team performance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get team performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member deadlines
     */
    private function getMemberDeadlines(int $userId, ?int $programId = null): array
    {
        $query = \App\Models\Deadline::whereHas('episode', function ($q) use ($programId) {
            if ($programId) {
                $q->where('program_id', $programId);
            }
        });
        
        return [
            'total' => $query->count(),
            'completed' => $query->where('is_completed', true)->count(),
            'overdue' => $query->where('status', 'overdue')->count()
        ];
    }

    /**
     * Get member workflow tasks
     */
    private function getMemberWorkflowTasks(int $userId, ?int $programId = null): array
    {
        $query = \App\Models\WorkflowState::where('assigned_to_user_id', $userId);
        
        if ($programId) {
            $query->whereHas('episode', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        
        return [
            'total' => $query->count(),
            'by_state' => $query->groupBy('current_state')->selectRaw('current_state, COUNT(*) as count')->get()
        ];
    }

    /**
     * Cancel jadwal syuting/rekaman
     * User: "dapat cancel jadwal syuting(jika terjadi kendala)"
     */
    public function cancelSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = \App\Models\MusicSchedule::findOrFail($id);

            // Update schedule status
            $schedule->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason,
                'cancelled_by' => $user->id,
                'cancelled_at' => now()
            ]);

            // Notify team members
            $this->notifyScheduleCancelled($schedule, $request->reason);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['musicSubmission', 'creator', 'canceller']),
                'message' => 'Schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign production teams to creative work
     * Called by Producer after approving creative work
     */
    public function assignProductionTeams(Request $request, int $creativeWorkId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'shooting_team_ids' => 'nullable|array',
                'shooting_team_ids.*' => 'exists:users,id',
                'shooting_schedule_id' => 'nullable|exists:music_schedules,id',
                'setting_team_ids' => 'nullable|array',
                'setting_team_ids.*' => 'exists:users,id',
                'recording_team_ids' => 'nullable|array',
                'recording_team_ids.*' => 'exists:users,id',
                'recording_schedule_id' => 'nullable|exists:music_schedules,id',
                'shooting_team_notes' => 'nullable|string|max:1000',
                'setting_team_notes' => 'nullable|string|max:1000',
                'recording_team_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::findOrFail($creativeWorkId);
            $episode = $creativeWork->episode;

            $episodeId = $episode->id;
            $assignments = [];

            // Assign shooting team
            if ($request->has('shooting_team_ids') && count($request->shooting_team_ids) > 0) {
                $shootingAssignment = \App\Models\ProductionTeamAssignment::create([
                    'music_submission_id' => null, // Optional for episode-based workflow
                    'episode_id' => $episodeId,
                    'schedule_id' => $request->shooting_schedule_id,
                    'assigned_by' => $user->id,
                    'team_type' => 'shooting',
                    'team_name' => 'Shooting Team',
                    'team_notes' => $request->shooting_team_notes,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($request->shooting_team_ids as $index => $userId) {
                    \App\Models\ProductionTeamMember::create([
                        'assignment_id' => $shootingAssignment->id,
                        'user_id' => $userId,
                        'role' => $index === 0 ? 'leader' : 'crew',
                        'status' => 'assigned',
                    ]);
                }
                $assignments['shooting_team'] = $shootingAssignment;
            }

            // Assign setting team
            if ($request->has('setting_team_ids') && count($request->setting_team_ids) > 0) {
                $settingAssignment = \App\Models\ProductionTeamAssignment::create([
                    'music_submission_id' => null, // Optional for episode-based workflow
                    'episode_id' => $episodeId,
                    'schedule_id' => $request->shooting_schedule_id,
                    'assigned_by' => $user->id,
                    'team_type' => 'setting',
                    'team_name' => 'Setting Team',
                    'team_notes' => $request->setting_team_notes,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($request->setting_team_ids as $index => $userId) {
                    \App\Models\ProductionTeamMember::create([
                        'assignment_id' => $settingAssignment->id,
                        'user_id' => $userId,
                        'role' => $index === 0 ? 'leader' : 'crew',
                        'status' => 'assigned',
                    ]);
                }
                $assignments['setting_team'] = $settingAssignment;
            }

            // Assign recording team
            if ($request->has('recording_team_ids') && count($request->recording_team_ids) > 0) {
                $recordingAssignment = \App\Models\ProductionTeamAssignment::create([
                    'music_submission_id' => null, // Optional for episode-based workflow
                    'episode_id' => $episodeId,
                    'schedule_id' => $request->recording_schedule_id,
                    'assigned_by' => $user->id,
                    'team_type' => 'recording',
                    'team_name' => 'Recording Team',
                    'team_notes' => $request->recording_team_notes,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($request->recording_team_ids as $index => $userId) {
                    \App\Models\ProductionTeamMember::create([
                        'assignment_id' => $recordingAssignment->id,
                        'user_id' => $userId,
                        'role' => $index === 0 ? 'leader' : 'crew',
                        'status' => 'assigned',
                    ]);
                }
                $assignments['recording_team'] = $recordingAssignment;
            }

            // Notify team members
            foreach ($assignments as $assignment) {
                $loadedAssignment = \App\Models\ProductionTeamAssignment::with('members.user')->find($assignment->id);
                foreach ($loadedAssignment->members as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'team_assigned',
                        'title' => 'Ditugaskan ke Tim Produksi',
                        'message' => "Anda ditugaskan ke {$loadedAssignment->team_name} untuk Episode {$episode->episode_number}",
                        'data' => [
                            'assignment_id' => $loadedAssignment->id,
                            'episode_id' => $episode->id,
                            'team_type' => $loadedAssignment->team_type
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $assignments,
                'message' => 'Production teams assigned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Emergency reassign team untuk jadwal syuting
     * User: "dapat mengganti tim syuting secara dadakan"
     */
    public function emergencyReassignTeam(Request $request, $scheduleId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'team_type' => 'required|in:shooting,setting,recording',
                'new_team_member_ids' => 'required|array|min:1',
                'new_team_member_ids.*' => 'exists:users,id',
                'reason' => 'required|string|max:1000',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = \App\Models\MusicSchedule::findOrFail($scheduleId);

            // Find existing team assignment
            $existingAssignment = \App\Models\ProductionTeamAssignment::where('schedule_id', $scheduleId)
                ->where('team_type', $request->team_type)
                ->whereIn('status', ['assigned', 'confirmed', 'in_progress'])
                ->first();

            // Cancel old assignment if exists
            if ($existingAssignment) {
                $oldMemberIds = $existingAssignment->members()->pluck('user_id')->toArray();
                
                $existingAssignment->update([
                    'status' => 'cancelled',
                    'completed_at' => now()
                ]);

                // Notify old team members
                $this->notifyTeamReassigned($oldMemberIds, $schedule, 'removed', $request->reason);
            }

            // Create new team assignment
            $newAssignment = \App\Models\ProductionTeamAssignment::create([
                'music_submission_id' => $schedule->music_submission_id,
                'schedule_id' => $scheduleId,
                'assigned_by' => $user->id,
                'team_type' => $request->team_type,
                'team_name' => ucfirst($request->team_type) . ' Team (Emergency)',
                'team_notes' => $request->notes . ' | REASSIGNMENT REASON: ' . $request->reason,
                'status' => 'assigned',
                'assigned_at' => now()
            ]);

            // Add new team members
            foreach ($request->new_team_member_ids as $index => $userId) {
                \App\Models\ProductionTeamMember::create([
                    'assignment_id' => $newAssignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'status' => 'assigned'
                ]);
            }

            // Notify new team members
            $this->notifyTeamReassigned($request->new_team_member_ids, $schedule, 'assigned', $request->reason);

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => $schedule->load(['musicSubmission']),
                    'old_assignment' => $existingAssignment,
                    'new_assignment' => $newAssignment->load('members.user')
                ],
                'message' => 'Team emergency reassigned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reassigning team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify schedule cancelled
     */
    private function notifyScheduleCancelled($schedule, $reason): void
    {
        // Notify team members assigned to this schedule
        $teamAssignments = \App\Models\ProductionTeamAssignment::where('schedule_id', $schedule->id)
            ->whereIn('status', ['assigned', 'confirmed'])
            ->get();

        foreach ($teamAssignments as $assignment) {
            foreach ($assignment->members as $member) {
                \App\Models\Notification::create([
                    'title' => 'Jadwal Dibatalkan',
                    'message' => "Jadwal {$schedule->getScheduleTypeLabel()} untuk Episode telah dibatalkan. Alasan: {$reason}",
                    'type' => 'schedule_cancelled',
                    'user_id' => $member->user_id,
                    'data' => [
                        'schedule_id' => $schedule->id,
                        'schedule_type' => $schedule->schedule_type,
                        'reason' => $reason
                    ]
                ]);
            }
        }
    }

    /**
     * Notify team reassigned
     */
    private function notifyTeamReassigned(array $memberIds, $schedule, string $action, string $reason): void
    {
        $message = $action === 'assigned' 
            ? "Anda ditugaskan secara darurat untuk jadwal {$schedule->getScheduleTypeLabel()}. Alasan: {$reason}"
            : "Anda telah digantikan dari jadwal {$schedule->getScheduleTypeLabel()}. Alasan: {$reason}";

        foreach ($memberIds as $userId) {
            \App\Models\Notification::create([
                'title' => $action === 'assigned' ? 'Ditugaskan Darurat' : 'Tim Diganti',
                'message' => $message,
                'type' => 'team_emergency_reassigned',
                'user_id' => $userId,
                'data' => [
                    'schedule_id' => $schedule->id,
                    'schedule_type' => $schedule->schedule_type,
                    'action' => $action,
                    'reason' => $reason
                ]
            ]);
        }
    }

    /**
     * Producer edit rundown dengan approval flow ke Manager Program
     * User: "Producer dapat mengedit rundown jika dibutuhkan dan ajukan ke program manager"
     */
    public function editRundown(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'new_rundown' => 'required|string',
                'edit_reason' => 'required|string|max:1000',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::with(['program'])->findOrFail($episodeId);

            // Check if Producer has access to this episode's program
            if (!$episode->program || $episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to edit rundown for this episode.'
                ], 403);
            }

            // Get Manager Program
            $managerProgram = $episode->program->managerProgram;
            if (!$managerProgram) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager Program not found for this program.'
                ], 404);
            }

            // Create ProgramApproval request
            $approval = ProgramApproval::create([
                'approvable_id' => $episode->id,
                'approvable_type' => Episode::class,
                'approval_type' => 'episode_rundown',
                'requested_by' => $user->id,
                'requested_at' => now(),
                'request_notes' => $request->notes,
                'request_data' => [
                    'new_rundown' => $request->new_rundown,
                    'current_rundown' => $episode->rundown,
                    'edit_reason' => $request->edit_reason,
                    'episode_id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'episode_title' => $episode->title
                ],
                'current_data' => [
                    'current_rundown' => $episode->rundown
                ],
                'status' => 'pending',
                'priority' => 'normal'
            ]);

            // Notify Manager Program
            Notification::create([
                'user_id' => $managerProgram->id,
                'type' => 'rundown_edit_request',
                'title' => 'Permintaan Edit Rundown',
                'message' => "Producer {$user->name} meminta edit rundown untuk Episode {$episode->episode_number}: {$episode->title}. Alasan: {$request->edit_reason}",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'edit_reason' => $request->edit_reason
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->load(['requestedBy']),
                    'episode' => $episode->load(['program'])
                ],
                'message' => 'Rundown edit request submitted successfully. Waiting for Manager Program approval.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting rundown edit request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer edit song/singer sebelum approve arrangement
     * User: "Producer dapat mengganti usulan lagu dan penyanyi dari music arranger"
     */
    public function editArrangementSongSinger(Request $request, int $arrangementId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'song_title' => 'nullable|string|max:255',
                'singer_name' => 'nullable|string|max:255',
                'song_id' => 'nullable|exists:songs,id',
                'singer_id' => 'nullable|exists:users,id',
                'modification_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::with(['episode.program.productionTeam', 'createdBy'])->findOrFail($arrangementId);

            // Validate Producer has access
            if ($arrangement->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This arrangement is not from your production team.'
                ], 403);
            }

            // Only allow modification if status is submitted
            if ($arrangement->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only modify arrangement with status "submitted"'
                ], 400);
            }

            // Get new values (use provided or keep existing)
            $newSongTitle = $request->song_title ?? $arrangement->song_title;
            $newSingerName = $request->singer_name ?? $arrangement->singer_name;
            $songId = $request->song_id;
            $singerId = $request->singer_id;

            // If song_id provided, get song title from database
            if ($songId && !$request->song_title) {
                $song = \App\Models\Song::find($songId);
                if ($song) {
                    $newSongTitle = $song->title;
                }
            }

            // Apply modification
            $arrangement->producerModify($newSongTitle, $newSingerName, $songId, $singerId);

            // Notify Music Arranger about modification
            Notification::create([
                'user_id' => $arrangement->created_by,
                'type' => 'arrangement_modified_by_producer',
                'title' => 'Arrangement Dimodifikasi oleh Producer',
                'message' => "Producer telah memodifikasi song/singer untuk arrangement '{$arrangement->song_title}'. " . ($request->modification_notes ? "Catatan: {$request->modification_notes}" : ''),
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id,
                    'original_song_title' => $arrangement->original_song_title,
                    'original_singer_name' => $arrangement->original_singer_name,
                    'modified_song_title' => $newSongTitle,
                    'modified_singer_name' => $newSingerName,
                    'modification_notes' => $request->modification_notes
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['song', 'singer']),
                'message' => 'Arrangement song/singer modified successfully. Music Arranger has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error modifying arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer kirim reminder manual ke crew
     * User: "Dapat mengingatkan melalui sistem setiap crew yang menjadi timnya"
     */
    public function sendReminderToCrew(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'crew_member_ids' => 'nullable|array',
                'crew_member_ids.*' => 'exists:users,id',
                'role' => 'nullable|string|in:kreatif,musik_arr,sound_eng,produksi,editor,art_set_design,design_grafis,promotion,broadcasting,quality_control',
                'message' => 'required|string|max:1000',
                'priority' => 'nullable|in:low,normal,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::with(['program.productionTeam'])->findOrFail($request->episode_id);

            // Validate Producer has access
            if ($episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This episode is not from your production team.'
                ], 403);
            }

            $crewMembers = [];
            
            // If specific crew member IDs provided
            if ($request->has('crew_member_ids') && count($request->crew_member_ids) > 0) {
                $crewMembers = \App\Models\User::whereIn('id', $request->crew_member_ids)
                    ->whereHas('productionTeamMembers', function($q) use ($episode) {
                        $q->where('production_team_id', $episode->program->productionTeam->id)
                          ->where('is_active', true);
                    })
                    ->get();
            }
            // If role provided, get all crew with that role
            elseif ($request->has('role')) {
                $crewMembers = $episode->program->productionTeam->members()
                    ->where('role', $request->role)
                    ->where('is_active', true)
                    ->with('user')
                    ->get()
                    ->pluck('user')
                    ->filter();
            }
            // If neither provided, send to all active crew members
            else {
                $crewMembers = $episode->program->productionTeam->members()
                    ->where('is_active', true)
                    ->with('user')
                    ->get()
                    ->pluck('user')
                    ->filter();
            }

            if ($crewMembers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No crew members found to send reminder to.'
                ], 404);
            }

            $sentCount = 0;
            foreach ($crewMembers as $crewMember) {
                Notification::create([
                    'user_id' => $crewMember->id,
                    'type' => 'producer_reminder',
                    'title' => 'Reminder dari Producer',
                    'message' => $request->message,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'priority' => $request->priority ?? 'normal',
                    'data' => [
                        'episode_number' => $episode->episode_number,
                        'episode_title' => $episode->title,
                        'reminder_from' => $user->name,
                        'reminder_from_role' => 'Producer'
                    ]
                ]);
                $sentCount++;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'reminder_sent_to' => $sentCount,
                    'crew_members' => $crewMembers->pluck('id', 'name')
                ],
                'message' => "Reminder sent successfully to {$sentCount} crew member(s)."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reminder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weekly airing control dashboard
     * Producer dapat melihat dan mengontrol episode yang akan tayang minggu ini
     */
    public function getWeeklyAiringControl(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();

            // Get episodes yang akan tayang minggu ini dari program Producer
            $episodesThisWeek = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->whereBetween('air_date', [$weekStart, $weekEnd])
                ->orderBy('air_date', 'asc')
                ->get();

            // Categorize episodes
            $readyEpisodes = [];
            $notReadyEpisodes = [];
            $airedEpisodes = [];

            foreach ($episodesThisWeek as $episode) {
                $readiness = $this->checkEpisodeReadiness($episode);
                
                $episodeData = [
                    'id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'title' => $episode->title,
                    'program_name' => $episode->program->name,
                    'air_date' => $episode->air_date,
                    'status' => $episode->status,
                    'current_workflow_state' => $episode->current_workflow_state,
                    'readiness' => $readiness,
                    'days_until_air' => now()->diffInDays($episode->air_date, false)
                ];

                if ($episode->status === 'aired') {
                    $airedEpisodes[] = $episodeData;
                } elseif ($readiness['is_ready']) {
                    $readyEpisodes[] = $episodeData;
                } else {
                    $notReadyEpisodes[] = $episodeData;
                }
            }

            // Statistics
            $totalEpisodes = $episodesThisWeek->count();
            $readyCount = count($readyEpisodes);
            $notReadyCount = count($notReadyEpisodes);
            $airedCount = count($airedEpisodes);
            $readinessRate = $totalEpisodes > 0 ? round(($readyCount / ($totalEpisodes - $airedCount)) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d'),
                        'current_date' => now()->format('Y-m-d')
                    ],
                    'statistics' => [
                        'total_episodes_this_week' => $totalEpisodes,
                        'ready_episodes' => $readyCount,
                        'not_ready_episodes' => $notReadyCount,
                        'aired_episodes' => $airedCount,
                        'readiness_rate' => $readinessRate
                    ],
                    'episodes' => [
                        'ready' => $readyEpisodes,
                        'not_ready' => $notReadyEpisodes,
                        'aired' => $airedEpisodes
                    ]
                ],
                'message' => 'Weekly airing control data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving weekly airing control: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes upcoming this week
     */
    public function getUpcomingEpisodesThisWeek(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();

            $query = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->whereBetween('air_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'aired')
                ->orderBy('air_date', 'asc');

            // Filter by readiness
            if ($request->has('ready_only') && $request->boolean('ready_only')) {
                $episodes = $query->get()->filter(function ($episode) {
                    return $this->checkEpisodeReadiness($episode)['is_ready'];
                })->values();
            } else {
                $episodes = $query->get();
            }

            $episodesData = $episodes->map(function ($episode) {
                $readiness = $this->checkEpisodeReadiness($episode);
                return [
                    'id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'title' => $episode->title,
                    'program_name' => $episode->program->name,
                    'program_id' => $episode->program_id,
                    'air_date' => $episode->air_date,
                    'status' => $episode->status,
                    'current_workflow_state' => $episode->current_workflow_state,
                    'readiness' => $readiness,
                    'days_until_air' => now()->diffInDays($episode->air_date, false),
                    'deadlines' => [
                        'total' => $episode->deadlines->count(),
                        'completed' => $episode->deadlines->where('is_completed', true)->count(),
                        'overdue' => $episode->deadlines->where('status', 'overdue')->count()
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d')
                    ],
                    'episodes' => $episodesData,
                    'count' => $episodesData->count()
                ],
                'message' => 'Upcoming episodes this week retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving upcoming episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes ready for airing this week
     */
    public function getReadyEpisodesThisWeek(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();

            $episodes = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->whereBetween('air_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'aired')
                ->orderBy('air_date', 'asc')
                ->get();

            // Filter only ready episodes
            $readyEpisodes = $episodes->filter(function ($episode) {
                return $this->checkEpisodeReadiness($episode)['is_ready'];
            })->map(function ($episode) {
                $readiness = $this->checkEpisodeReadiness($episode);
                return [
                    'id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'title' => $episode->title,
                    'program_name' => $episode->program->name,
                    'program_id' => $episode->program_id,
                    'air_date' => $episode->air_date,
                    'status' => $episode->status,
                    'current_workflow_state' => $episode->current_workflow_state,
                    'readiness' => $readiness,
                    'days_until_air' => now()->diffInDays($episode->air_date, false)
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d')
                    ],
                    'episodes' => $readyEpisodes,
                    'count' => $readyEpisodes->count()
                ],
                'message' => 'Ready episodes this week retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving ready episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check episode readiness for airing
     * Returns checklist of what's needed for episode to be ready
     */
    private function checkEpisodeReadiness(Episode $episode): array
    {
        $readiness = [
            'is_ready' => false,
            'checklist' => [],
            'missing_items' => [],
            'warnings' => []
        ];

        // Check 1: Episode status should be ready_to_air or at least post_production
        $statusReady = in_array($episode->status, ['ready_to_air', 'post_production']);
        $readiness['checklist']['status'] = [
            'label' => 'Episode Status',
            'status' => $statusReady ? 'ready' : 'not_ready',
            'value' => $episode->status,
            'required' => 'ready_to_air'
        ];
        if (!$statusReady) {
            $readiness['missing_items'][] = 'Episode status harus ready_to_air';
        }

        // Check 2: Rundown should exist
        $hasRundown = !empty($episode->rundown);
        $readiness['checklist']['rundown'] = [
            'label' => 'Rundown',
            'status' => $hasRundown ? 'ready' : 'not_ready',
            'value' => $hasRundown ? 'Available' : 'Missing'
        ];
        if (!$hasRundown) {
            $readiness['missing_items'][] = 'Rundown belum tersedia';
        }

        // Check 3: All deadlines should be completed
        $deadlines = $episode->deadlines;
        $totalDeadlines = $deadlines->count();
        $completedDeadlines = $deadlines->where('is_completed', true)->count();
        $overdueDeadlines = $deadlines->where('status', 'overdue')->count();
        
        $allDeadlinesCompleted = $totalDeadlines > 0 && $totalDeadlines === $completedDeadlines;
        $readiness['checklist']['deadlines'] = [
            'label' => 'Deadlines',
            'status' => $allDeadlinesCompleted ? 'ready' : ($overdueDeadlines > 0 ? 'overdue' : 'in_progress'),
            'value' => "{$completedDeadlines}/{$totalDeadlines} completed",
            'overdue' => $overdueDeadlines
        ];
        if (!$allDeadlinesCompleted) {
            $remainingDeadlines = $totalDeadlines - $completedDeadlines;
            $readiness['missing_items'][] = "Masih ada {$remainingDeadlines} deadline yang belum selesai";
        }
        if ($overdueDeadlines > 0) {
            $readiness['warnings'][] = "Ada {$overdueDeadlines} deadline yang overdue";
        }

        // Check 4: Music Arrangement should be approved (for music programs)
        $musicArrangement = MusicArrangement::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['music_arrangement'] = [
            'label' => 'Music Arrangement',
            'status' => $musicArrangement ? 'ready' : 'not_ready',
            'value' => $musicArrangement ? 'Approved' : 'Not approved or missing'
        ];
        if (!$musicArrangement) {
            $readiness['missing_items'][] = 'Music arrangement belum approved';
        }

        // Check 5: Creative Work should be approved
        $creativeWork = CreativeWork::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['creative_work'] = [
            'label' => 'Creative Work',
            'status' => $creativeWork ? 'ready' : 'not_ready',
            'value' => $creativeWork ? 'Approved' : 'Not approved or missing'
        ];
        if (!$creativeWork) {
            $readiness['missing_items'][] = 'Creative work belum approved';
        }

        // Check 6: Sound Engineering should be completed
        $soundEngineering = SoundEngineerEditing::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['sound_engineering'] = [
            'label' => 'Sound Engineering',
            'status' => $soundEngineering ? 'ready' : 'not_ready',
            'value' => $soundEngineering ? 'Approved' : 'Not approved or missing'
        ];
        if (!$soundEngineering) {
            $readiness['missing_items'][] = 'Sound engineering belum completed';
        }

        // Check 7: Editor Work should be approved
        $editorWork = EditorWork::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['editor_work'] = [
            'label' => 'Editor Work',
            'status' => $editorWork ? 'ready' : 'not_ready',
            'value' => $editorWork ? 'Approved' : 'Not approved or missing'
        ];
        if (!$editorWork) {
            $readiness['missing_items'][] = 'Editor work belum approved';
        }

        // Check 8: QC should be approved
        $qcApproved = QualityControl::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['quality_control'] = [
            'label' => 'Quality Control',
            'status' => $qcApproved ? 'ready' : 'not_ready',
            'value' => $qcApproved ? 'Approved' : 'Not approved or missing'
        ];
        if (!$qcApproved) {
            $readiness['missing_items'][] = 'QC belum approved';
        }

        // Check 9: Days until air - warning if less than 3 days
        $daysUntilAir = now()->diffInDays($episode->air_date, false);
        if ($daysUntilAir < 3 && $daysUntilAir >= 0) {
            $readiness['warnings'][] = "Episode akan tayang dalam {$daysUntilAir} hari";
        }
        if ($daysUntilAir < 0) {
            $readiness['warnings'][] = "Episode sudah melewati jadwal tayang!";
        }

        // Overall readiness: Episode is ready if status is ready_to_air and QC is approved
        $readiness['is_ready'] = $episode->status === 'ready_to_air' && $qcApproved;

        return $readiness;
    }

    /**
     * Review Creative Work - Producer cek script, storyboard, budget
     * POST /api/live-tv/producer/creative-works/{id}/review
     */
    public function reviewCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'script_approved' => 'nullable|boolean',
                'storyboard_approved' => 'nullable|boolean',
                'budget_approved' => 'nullable|boolean',
                'script_review_notes' => 'nullable|string|max:2000',
                'storyboard_review_notes' => 'nullable|string|max:2000',
                'budget_review_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Validate Producer has access
            if ($creativeWork->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            if (!in_array($creativeWork->status, ['submitted', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or revised creative works can be reviewed'
                ], 400);
            }

            // Update review fields
            $creativeWork->update([
                'script_approved' => $request->script_approved,
                'storyboard_approved' => $request->storyboard_approved,
                'budget_approved' => $request->budget_approved,
                'script_review_notes' => $request->script_review_notes,
                'storyboard_review_notes' => $request->storyboard_review_notes,
                'budget_review_notes' => $request->budget_review_notes,
                'reviewed_by' => $user->id,
                'reviewed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $creativeWork->fresh(['episode', 'createdBy']),
                'message' => 'Creative work reviewed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tambah Tim Syuting/Setting/Rekam Vokal
     * POST /api/live-tv/producer/creative-works/{id}/assign-team
     */
    public function assignTeamToCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'team_type' => 'required|in:shooting,setting,recording',
                'team_member_ids' => 'required|array|min:1',
                'team_member_ids.*' => 'exists:users,id',
                'team_name' => 'nullable|string|max:255',
                'team_notes' => 'nullable|string|max:1000',
                'schedule_id' => 'nullable|exists:music_schedules,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Validate Producer has access
            if ($creativeWork->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Get production team members (semua crew program selain manager)
            $productionTeam = $creativeWork->episode->program->productionTeam;
            $availableMembers = $productionTeam->members()
                ->where('is_active', true)
                ->where('role', '!=', 'manager_program')
                ->pluck('user_id')
                ->toArray();

            // Validate all team members are from production team
            $invalidMembers = array_diff($request->team_member_ids, $availableMembers);
            if (!empty($invalidMembers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some team members are not part of the production team',
                    'invalid_members' => $invalidMembers
                ], 400);
            }

            // Create team assignment
            $assignment = \App\Models\ProductionTeamAssignment::create([
                'music_submission_id' => null,
                'episode_id' => $creativeWork->episode_id,
                'schedule_id' => $request->schedule_id,
                'assigned_by' => $user->id,
                'team_type' => $request->team_type,
                'team_name' => $request->team_name ?? ucfirst($request->team_type) . ' Team',
                'team_notes' => $request->team_notes,
                'status' => 'assigned',
                'assigned_at' => now()
            ]);

            // Add team members
            foreach ($request->team_member_ids as $index => $userId) {
                \App\Models\ProductionTeamMember::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'status' => 'assigned'
                ]);

                // Notify team member
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'team_assigned',
                    'title' => 'Ditugaskan ke Tim ' . ucfirst($request->team_type),
                    'message' => "Anda telah ditugaskan ke tim {$assignment->team_name} untuk Episode {$creativeWork->episode->episode_number}.",
                    'data' => [
                        'assignment_id' => $assignment->id,
                        'team_type' => $request->team_type,
                        'episode_id' => $creativeWork->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $assignment->load(['members', 'episode']),
                'message' => 'Team assigned successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ganti Tim Syuting (secara dadakan)
     * PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team
     */
    public function replaceTeamMembers(Request $request, int $assignmentId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'new_team_member_ids' => 'required|array|min:1',
                'new_team_member_ids.*' => 'exists:users,id',
                'replacement_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = \App\Models\ProductionTeamAssignment::with(['episode.program.productionTeam'])->findOrFail($assignmentId);

            // Validate Producer has access
            if ($assignment->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this assignment.'
                ], 403);
            }

            // Get old members for notification
            $oldMembers = $assignment->members()->pluck('user_id')->toArray();

            // Remove old members
            $assignment->members()->delete();

            // Add new members
            foreach ($request->new_team_member_ids as $index => $userId) {
                \App\Models\ProductionTeamMember::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'status' => 'assigned'
                ]);

                // Notify new team member
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'team_replaced',
                    'title' => 'Ditugaskan ke Tim ' . ucfirst($assignment->team_type),
                    'message' => "Anda telah menggantikan anggota tim {$assignment->team_name} untuk Episode {$assignment->episode->episode_number}. Alasan: {$request->replacement_reason}",
                    'data' => [
                        'assignment_id' => $assignment->id,
                        'team_type' => $assignment->team_type,
                        'episode_id' => $assignment->episode_id,
                        'replacement_reason' => $request->replacement_reason
                    ]
                ]);
            }

            // Notify old members
            foreach ($oldMembers as $oldMemberId) {
                if (!in_array($oldMemberId, $request->new_team_member_ids)) {
                    Notification::create([
                        'user_id' => $oldMemberId,
                        'type' => 'team_replaced_out',
                        'title' => 'Digantikan dari Tim',
                        'message' => "Anda telah digantikan dari tim {$assignment->team_name} untuk Episode {$assignment->episode->episode_number}. Alasan: {$request->replacement_reason}",
                        'data' => [
                            'assignment_id' => $assignment->id,
                            'team_type' => $assignment->team_type,
                            'episode_id' => $assignment->episode_id
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $assignment->fresh(['members', 'episode']),
                'message' => 'Team members replaced successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to replace team members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel Jadwal Syuting
     * POST /api/live-tv/producer/creative-works/{id}/cancel-shooting
     */
    public function cancelShootingSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'cancellation_reason' => 'required|string|max:1000',
                'new_shooting_schedule' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Validate Producer has access
            if ($creativeWork->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Cancel shooting schedule
            $creativeWork->update([
                'shooting_schedule_cancelled' => true,
                'shooting_cancellation_reason' => $request->cancellation_reason,
                'shooting_schedule_new' => $request->new_shooting_schedule
            ]);

            // Cancel shooting team assignments
            $shootingAssignments = \App\Models\ProductionTeamAssignment::where('episode_id', $creativeWork->episode_id)
                ->where('team_type', 'shooting')
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($shootingAssignments as $assignment) {
                $assignment->update(['status' => 'cancelled']);

                // Notify team members
                foreach ($assignment->members as $member) {
                    Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'shooting_cancelled',
                        'title' => 'Jadwal Syuting Dibatalkan',
                        'message' => "Jadwal syuting untuk Episode {$creativeWork->episode->episode_number} telah dibatalkan. Alasan: {$request->cancellation_reason}",
                        'data' => [
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'cancellation_reason' => $request->cancellation_reason,
                            'new_schedule' => $request->new_shooting_schedule
                        ]
                    ]);
                }
            }

            // Notify Creative
            Notification::create([
                'user_id' => $creativeWork->created_by,
                'type' => 'shooting_cancelled',
                'title' => 'Jadwal Syuting Dibatalkan',
                'message' => "Jadwal syuting untuk Episode {$creativeWork->episode->episode_number} telah dibatalkan oleh Producer. Alasan: {$request->cancellation_reason}",
                'data' => [
                    'creative_work_id' => $creativeWork->id,
                    'episode_id' => $creativeWork->episode_id
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $creativeWork->fresh(['episode']),
                'message' => 'Shooting schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel shooting schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit Creative Work Langsung (Producer dapat edit langsung)
     * PUT /api/live-tv/producer/creative-works/{id}/edit
     */
    public function editCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'script_content' => 'nullable|string',
                'storyboard_data' => 'nullable|array',
                'budget_data' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'shooting_schedule' => 'nullable|date',
                'shooting_location' => 'nullable|string|max:255',
                'edit_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Validate Producer has access
            if ($creativeWork->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Allow edit if status is submitted, rejected, or revised (untuk perbaikan setelah budget ditolak)
            if (!in_array($creativeWork->status, ['submitted', 'rejected', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work can only be edited when status is submitted, rejected, or revised'
                ], 400);
            }

            // Update fields
            $updateData = [];
            if ($request->has('script_content')) $updateData['script_content'] = $request->script_content;
            if ($request->has('storyboard_data')) $updateData['storyboard_data'] = $request->storyboard_data;
            if ($request->has('budget_data')) $updateData['budget_data'] = $request->budget_data;
            if ($request->has('recording_schedule')) $updateData['recording_schedule'] = $request->recording_schedule;
            if ($request->has('shooting_schedule')) $updateData['shooting_schedule'] = $request->shooting_schedule;
            if ($request->has('shooting_location')) $updateData['shooting_location'] = $request->shooting_location;

            // Jika status rejected, ubah ke revised setelah edit
            if ($creativeWork->status === 'rejected') {
                $updateData['status'] = 'revised';
                // Reset review fields
                $updateData['script_approved'] = null;
                $updateData['storyboard_approved'] = null;
                $updateData['budget_approved'] = null;
                $updateData['script_review_notes'] = null;
                $updateData['storyboard_review_notes'] = null;
                $updateData['budget_review_notes'] = null;
            }

            if ($request->edit_notes) {
                $updateData['review_notes'] = ($creativeWork->review_notes ? $creativeWork->review_notes . "\n\n" : '') . 
                    "[Producer Edit] " . $request->edit_notes;
            }

            $creativeWork->update($updateData);

            // Notify Creative
            Notification::create([
                'user_id' => $creativeWork->created_by,
                'type' => 'creative_work_edited_by_producer',
                'title' => 'Creative Work Diedit oleh Producer',
                'message' => "Producer telah mengedit creative work untuk Episode {$creativeWork->episode->episode_number}. " . ($request->edit_notes ? "Catatan: {$request->edit_notes}" : ''),
                'data' => [
                    'creative_work_id' => $creativeWork->id,
                    'episode_id' => $creativeWork->episode_id,
                    'edited_fields' => array_keys($updateData),
                    'edit_notes' => $request->edit_notes
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $creativeWork->fresh(['episode', 'createdBy']),
                'message' => 'Creative work edited successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to edit creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajukan Budget Khusus ke Manager Program
     * POST /api/live-tv/producer/creative-works/{id}/request-special-budget
     */
    public function requestSpecialBudget(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'special_budget_amount' => 'required|numeric|min:0',
                'special_budget_reason' => 'required|string|max:2000',
                'priority' => 'nullable|in:low,normal,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam', 'episode.program'])->findOrFail($id);

            // Validate Producer has access
            if ($creativeWork->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Create ProgramApproval untuk special budget
            $approval = ProgramApproval::create([
                'approvable_id' => $creativeWork->id,
                'approvable_type' => CreativeWork::class,
                'approval_type' => 'special_budget',
                'requested_by' => $user->id,
                'requested_at' => now(),
                'request_notes' => $request->special_budget_reason,
                'request_data' => [
                    'special_budget_amount' => $request->special_budget_amount,
                    'current_budget' => $creativeWork->total_budget,
                    'episode_id' => $creativeWork->episode_id
                ],
                'status' => 'pending',
                'priority' => $request->priority ?? 'normal'
            ]);

            // Update Creative Work
            $creativeWork->update([
                'requires_special_budget_approval' => true,
                'special_budget_reason' => $request->special_budget_reason,
                'special_budget_approval_id' => $approval->id
            ]);

            // Notify Manager Program
            $managerProgram = $creativeWork->episode->program->managerProgram;
            if ($managerProgram) {
                Notification::create([
                    'user_id' => $managerProgram->id,
                    'type' => 'special_budget_request',
                    'title' => 'Permintaan Budget Khusus',
                    'message' => "Producer meminta budget khusus sebesar Rp " . number_format($request->special_budget_amount, 0, ',', '.') . " untuk Episode {$creativeWork->episode->episode_number}. Alasan: {$request->special_budget_reason}",
                    'data' => [
                        'approval_id' => $approval->id,
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'budget_amount' => $request->special_budget_amount
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval,
                    'creative_work' => $creativeWork->fresh(['episode'])
                ],
                'message' => 'Special budget request submitted successfully. Manager Program has been notified.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request special budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve/Reject Creative Work dengan Review Detail
     * POST /api/live-tv/producer/creative-works/{id}/final-approval
     */
    public function finalApproveCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Validate Producer has access
            if ($creativeWork->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            if (!in_array($creativeWork->status, ['submitted', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or revised creative works can be approved/rejected'
                ], 400);
            }

            // Check if special budget approval is pending
            if ($creativeWork->requires_special_budget_approval && $creativeWork->specialBudgetApproval && 
                $creativeWork->specialBudgetApproval->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve/reject: Special budget approval is still pending from Manager Program.'
                ], 400);
            }

            if ($request->action === 'approve') {
                // Check if all reviews are done
                if ($creativeWork->script_approved === null || 
                    $creativeWork->storyboard_approved === null || 
                    $creativeWork->budget_approved === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please review script, storyboard, and budget first before final approval.'
                    ], 400);
                }

                // Check if all are approved
                if (!$creativeWork->script_approved || !$creativeWork->storyboard_approved || !$creativeWork->budget_approved) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot approve: Script, storyboard, or budget has been rejected. Please review first.'
                    ], 400);
                }

                $creativeWork->approve($user->id, $request->notes);

                // Auto-create BudgetRequest ke General Affairs
                $totalBudget = $creativeWork->total_budget;
                if ($totalBudget > 0) {
                    $budgetRequest = \App\Models\BudgetRequest::create([
                        'program_id' => $creativeWork->episode->program_id,
                        'requested_by' => $user->id,
                        'request_type' => 'creative_work',
                        'title' => "Permohonan Dana untuk Episode {$creativeWork->episode->episode_number}",
                        'description' => "Permohonan dana untuk creative work Episode {$creativeWork->episode->episode_number}. Budget: Rp " . number_format($totalBudget, 0, ',', '.'),
                        'requested_amount' => $totalBudget,
                        'status' => 'pending'
                    ]);

                    // Notify General Affairs
                    $generalAffairsUsers = \App\Models\User::where('role', 'General Affairs')->get();
                    foreach ($generalAffairsUsers as $gaUser) {
                        Notification::create([
                            'user_id' => $gaUser->id,
                            'type' => 'budget_request_created',
                            'title' => 'Permohonan Dana Baru',
                            'message' => "Producer memohon dana sebesar Rp " . number_format($totalBudget, 0, ',', '.') . " untuk Episode {$creativeWork->episode->episode_number}.",
                            'data' => [
                                'budget_request_id' => $budgetRequest->id,
                                'creative_work_id' => $creativeWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'program_id' => $creativeWork->episode->program_id,
                                'requested_amount' => $totalBudget
                            ]
                        ]);
                    }
                }

                // Notify Creative
                Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'creative_work_approved',
                    'title' => 'Creative Work Disetujui',
                    'message' => "Creative work untuk Episode {$creativeWork->episode->episode_number} telah disetujui oleh Producer. Permohonan dana telah dikirim ke General Affairs.",
                    'data' => [
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'review_notes' => $request->notes
                    ]
                ]);

                // Auto-create PromosiWork task
                $promosiUsers = \App\Models\User::where('role', 'Promosi')->get();
                if ($promosiUsers->isNotEmpty()) {
                    $promosiWork = \App\Models\PromotionWork::create([
                        'episode_id' => $creativeWork->episode_id,
                        'work_type' => 'bts_video',
                        'title' => "BTS Video & Talent Photos - Episode {$creativeWork->episode->episode_number}",
                        'description' => "Buat video BTS dan foto talent untuk Episode {$creativeWork->episode->episode_number}",
                        'shooting_date' => $creativeWork->shooting_schedule,
                        'status' => 'planning'
                    ]);

                    // Notify Promosi users
                    foreach ($promosiUsers as $promosiUser) {
                        Notification::create([
                            'user_id' => $promosiUser->id,
                            'type' => 'promosi_work_assigned',
                            'title' => 'Tugas Promosi Baru',
                            'message' => "Anda mendapat tugas untuk membuat video BTS dan foto talent untuk Episode {$creativeWork->episode->episode_number}. Jadwal syuting: " . ($creativeWork->shooting_schedule ? \Carbon\Carbon::parse($creativeWork->shooting_schedule)->format('d M Y') : 'TBD'),
                            'data' => [
                                'promotion_work_id' => $promosiWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'shooting_date' => $creativeWork->shooting_schedule
                            ]
                        ]);
                    }
                }

                // Auto-create ProduksiWork task
                $produksiUsers = \App\Models\User::where('role', 'Produksi')->get();
                if ($produksiUsers->isNotEmpty()) {
                    $produksiWork = \App\Models\ProduksiWork::create([
                        'episode_id' => $creativeWork->episode_id,
                        'creative_work_id' => $creativeWork->id,
                        'status' => 'pending'
                    ]);

                    // Notify Produksi users
                    foreach ($produksiUsers as $produksiUser) {
                        Notification::create([
                            'user_id' => $produksiUser->id,
                            'type' => 'produksi_work_assigned',
                            'title' => 'Tugas Produksi Baru',
                            'message' => "Anda mendapat tugas produksi untuk Episode {$creativeWork->episode->episode_number}. Silakan input list alat dan kebutuhan.",
                            'data' => [
                                'produksi_work_id' => $produksiWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'creative_work_id' => $creativeWork->id
                            ]
                        ]);
                    }
                }

                // Auto-create SoundEngineerRecording task untuk rekaman vokal (jika ada recording_schedule)
                if ($creativeWork->recording_schedule) {
                    $episode = $creativeWork->episode;
                    $productionTeam = $episode->program->productionTeam;
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->get();

                        foreach ($soundEngineers as $soundEngineerMember) {
                            // Check if recording already exists for this episode (vocal recording)
                            $existingRecording = SoundEngineerRecording::where('episode_id', $episode->id)
                                ->whereNull('music_arrangement_id') // Vocal recording tidak punya music_arrangement_id
                                ->first();

                            if (!$existingRecording) {
                                // Create draft recording task untuk rekaman vokal
                                $recording = SoundEngineerRecording::create([
                                    'episode_id' => $episode->id,
                                    'music_arrangement_id' => null, // Vocal recording
                                    'recording_notes' => "Recording task untuk rekaman vokal Episode {$episode->episode_number}",
                                    'recording_schedule' => $creativeWork->recording_schedule,
                                    'status' => 'draft',
                                    'created_by' => $soundEngineerMember->user_id
                                ]);

                                // Notify Sound Engineer
                                Notification::create([
                                    'user_id' => $soundEngineerMember->user_id,
                                    'type' => 'vocal_recording_task_created',
                                    'title' => 'Tugas Rekaman Vokal Baru',
                                    'message' => "Anda mendapat tugas untuk rekaman vokal Episode {$episode->episode_number}. Jadwal rekaman: " . \Carbon\Carbon::parse($creativeWork->recording_schedule)->format('d M Y'),
                                    'data' => [
                                        'recording_id' => $recording->id,
                                        'episode_id' => $episode->id,
                                        'recording_schedule' => $creativeWork->recording_schedule
                                    ]
                                ]);
                            }
                        }
                    }
                }

                // Update workflow state
                if ($creativeWork->episode->current_workflow_state === 'creative_work') {
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $creativeWork->episode,
                        'production_planning',
                        'produksi',
                        null,
                        'Creative work approved, proceeding to production planning'
                    );
                }

                return response()->json([
                    'success' => true,
                    'data' => $creativeWork->fresh(['episode', 'createdBy']),
                    'message' => 'Creative work approved successfully'
                ]);

            } else {
                // Reject
                $creativeWork->reject($user->id, $request->notes ?? 'Creative work rejected by Producer');

                // Notify Creative
                Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'creative_work_rejected',
                    'title' => 'Creative Work Ditolak',
                    'message' => "Creative work untuk Episode {$creativeWork->episode->episode_number} telah ditolak oleh Producer. Alasan: {$request->notes}",
                    'data' => [
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'rejection_reason' => $request->notes
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $creativeWork->fresh(['episode', 'createdBy']),
                    'message' => 'Creative work rejected successfully'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process creative work approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
