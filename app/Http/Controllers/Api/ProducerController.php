<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Episode;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\ProductionEquipment;
use App\Models\SoundEngineerRecording;
use App\Models\EditorWork;
use App\Models\DesignGrafisWork;
use App\Models\PromotionMaterial;
use App\Models\BroadcastingSchedule;
use App\Models\QualityControl;
use App\Models\Budget;
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
            $approvals = [];
            
            // Music arrangements pending approval
            $musicArrangements = MusicArrangement::where('status', 'submitted')
                ->with(['episode', 'createdBy'])
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
            
            $approvals = [
                'music_arrangements' => $musicArrangements,
                'creative_works' => $creativeWorks,
                'equipment_requests' => $equipmentRequests,
                'budget_requests' => $budgetRequests
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
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:music_arrangement,creative_work,equipment_request,budget_request',
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
                case 'music_arrangement':
                    $item = MusicArrangement::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;
                    
                case 'creative_work':
                    $item = CreativeWork::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;
                    
                case 'equipment_request':
                    $item = ProductionEquipment::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;
                    
                case 'budget_request':
                    $item = Budget::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
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
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:music_arrangement,creative_work,equipment_request,budget_request',
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
                case 'music_arrangement':
                    $item = MusicArrangement::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;
                    
                case 'creative_work':
                    $item = CreativeWork::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;
                    
                case 'equipment_request':
                    $item = ProductionEquipment::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;
                    
                case 'budget_request':
                    $item = Budget::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
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
}
