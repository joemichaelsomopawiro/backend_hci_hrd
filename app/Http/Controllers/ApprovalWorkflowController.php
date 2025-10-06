<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\ProgramNotification;
use App\Services\ProgramWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ApprovalWorkflowController extends Controller
{
    protected $workflowService;

    public function __construct(ProgramWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Submit program for approval
     */
    public function submitProgramForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            if ($program->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft programs can be submitted for approval'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'submission_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->update([
                'status' => 'pending_approval',
                'submission_notes' => $request->submission_notes,
                'submitted_at' => now(),
                'submitted_by' => Auth::id()
            ]);

            // Notify manager for approval
            $this->notifyForApproval($program, 'program');

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting program for approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit rundown for approval
     */
    public function submitRundownForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            if ($episode->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft episodes can be submitted for rundown approval'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rundown_content' => 'required|string',
                'submission_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update([
                'status' => 'rundown_pending_approval',
                'script' => $request->rundown_content,
                'submission_notes' => $request->submission_notes,
                'submitted_at' => now(),
                'submitted_by' => Auth::id()
            ]);

            // Notify producer for approval
            $this->notifyForApproval($episode, 'rundown');

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Rundown submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting rundown for approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit schedule for approval
     */
    public function submitScheduleForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
            if ($schedule->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft schedules can be submitted for approval'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'submission_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update([
                'status' => 'pending_approval',
                'submission_notes' => $request->submission_notes,
                'submitted_at' => now(),
                'submitted_by' => Auth::id()
            ]);

            // Notify manager for approval
            $this->notifyForApproval($schedule, 'schedule');

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting schedule for approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program
     */
    public function approveProgram(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            if ($program->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only programs pending approval can be approved'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = $this->workflowService->processApprovalWorkflow(
                'program',
                $id,
                'approve',
                Auth::id(),
                $request->approval_notes
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $program->fresh(),
                    'message' => 'Program approved successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve program'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject program
     */
    public function rejectProgram(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            if ($program->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only programs pending approval can be rejected'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = $this->workflowService->processApprovalWorkflow(
                'program',
                $id,
                'reject',
                Auth::id(),
                $request->rejection_reason
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $program->fresh(),
                    'message' => 'Program rejected successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject program'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve rundown
     */
    public function approveRundown(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            if ($episode->status !== 'rundown_pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only episodes with pending rundown approval can be approved'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = $this->workflowService->processApprovalWorkflow(
                'rundown',
                $id,
                'approve',
                Auth::id(),
                $request->approval_notes
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $episode->fresh(),
                    'message' => 'Rundown approved successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve rundown'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving rundown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject rundown
     */
    public function rejectRundown(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            if ($episode->status !== 'rundown_pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only episodes with pending rundown approval can be rejected'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = $this->workflowService->processApprovalWorkflow(
                'rundown',
                $id,
                'reject',
                Auth::id(),
                $request->rejection_reason
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $episode->fresh(),
                    'message' => 'Rundown rejected successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject rundown'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting rundown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve schedule
     */
    public function approveSchedule(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
            if ($schedule->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only schedules pending approval can be approved'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = $this->workflowService->processApprovalWorkflow(
                'schedule',
                $id,
                'approve',
                Auth::id(),
                $request->approval_notes
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $schedule->fresh(),
                    'message' => 'Schedule approved successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve schedule'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject schedule
     */
    public function rejectSchedule(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
            if ($schedule->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only schedules pending approval can be rejected'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = $this->workflowService->processApprovalWorkflow(
                'schedule',
                $id,
                'reject',
                Auth::id(),
                $request->rejection_reason
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $schedule->fresh(),
                    'message' => 'Schedule rejected successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject schedule'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $pendingApprovals = [];

            // Get pending programs (for managers)
            if (in_array($user->role, ['Manager', 'Program Manager'])) {
                $pendingPrograms = Program::where('status', 'pending_approval')
                    ->with(['manager', 'producer'])
                    ->get();
                    
                foreach ($pendingPrograms as $program) {
                    $pendingApprovals[] = [
                        'type' => 'program',
                        'id' => $program->id,
                        'title' => $program->name,
                        'description' => $program->description,
                        'submitted_at' => $program->submitted_at,
                        'submitted_by' => $program->submittedBy->name ?? 'Unknown',
                        'entity' => $program
                    ];
                }
            }

            // Get pending rundowns (for producers)
            if (in_array($user->role, ['Producer', 'Manager', 'Program Manager'])) {
                $pendingRundowns = Episode::where('status', 'rundown_pending_approval')
                    ->with(['program', 'submittedBy'])
                    ->get();
                    
                foreach ($pendingRundowns as $episode) {
                    $pendingApprovals[] = [
                        'type' => 'rundown',
                        'id' => $episode->id,
                        'title' => $episode->title,
                        'description' => $episode->description,
                        'submitted_at' => $episode->submitted_at,
                        'submitted_by' => $episode->submittedBy->name ?? 'Unknown',
                        'entity' => $episode
                    ];
                }
            }

            // Get pending schedules (for managers)
            if (in_array($user->role, ['Manager', 'Program Manager'])) {
                $pendingSchedules = Schedule::where('status', 'pending_approval')
                    ->with(['program', 'episode', 'submittedBy'])
                    ->get();
                    
                foreach ($pendingSchedules as $schedule) {
                    $pendingApprovals[] = [
                        'type' => 'schedule',
                        'id' => $schedule->id,
                        'title' => $schedule->title,
                        'description' => $schedule->description,
                        'submitted_at' => $schedule->submitted_at,
                        'submitted_by' => $schedule->submittedBy->name ?? 'Unknown',
                        'entity' => $schedule
                    ];
                }
            }

            // Sort by submission date
            usort($pendingApprovals, function($a, $b) {
                return strtotime($b['submitted_at']) - strtotime($a['submitted_at']);
            });

            return response()->json([
                'success' => true,
                'data' => $pendingApprovals,
                'message' => 'Pending approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pending approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approval history
     */
    public function getApprovalHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $history = [];

            // Get programs approved/rejected by user
            $programs = Program::where('approved_by', $user->id)
                ->orWhere('rejected_by', $user->id)
                ->with(['manager', 'producer'])
                ->get();
                
            foreach ($programs as $program) {
                $history[] = [
                    'type' => 'program',
                    'id' => $program->id,
                    'title' => $program->name,
                    'action' => $program->approved_by ? 'approved' : 'rejected',
                    'action_date' => $program->approved_at ?? $program->rejected_at,
                    'notes' => $program->approval_notes ?? $program->rejection_notes,
                    'entity' => $program
                ];
            }

            // Get episodes approved/rejected by user
            $episodes = Episode::where('approved_by', $user->id)
                ->orWhere('rejected_by', $user->id)
                ->with(['program', 'submittedBy'])
                ->get();
                
            foreach ($episodes as $episode) {
                $history[] = [
                    'type' => 'rundown',
                    'id' => $episode->id,
                    'title' => $episode->title,
                    'action' => $episode->approved_by ? 'approved' : 'rejected',
                    'action_date' => $episode->approved_at ?? $episode->rejected_at,
                    'notes' => $episode->approval_notes ?? $episode->rejection_notes,
                    'entity' => $episode
                ];
            }

            // Get schedules approved/rejected by user
            $schedules = Schedule::where('approved_by', $user->id)
                ->orWhere('rejected_by', $user->id)
                ->with(['program', 'episode', 'submittedBy'])
                ->get();
                
            foreach ($schedules as $schedule) {
                $history[] = [
                    'type' => 'schedule',
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'action' => $schedule->approved_by ? 'approved' : 'rejected',
                    'action_date' => $schedule->approved_at ?? $schedule->rejected_at,
                    'notes' => $schedule->approval_notes ?? $schedule->rejection_notes,
                    'entity' => $schedule
                ];
            }

            // Sort by action date
            usort($history, function($a, $b) {
                return strtotime($b['action_date']) - strtotime($a['action_date']);
            });

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Approval history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approval history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify for approval
     */
    private function notifyForApproval($entity, string $type): void
    {
        $notifyUsers = [];
        
        switch ($type) {
            case 'program':
                $notifyUsers = ['Manager', 'Program Manager'];
                break;
            case 'rundown':
                $notifyUsers = ['Producer', 'Manager', 'Program Manager'];
                break;
            case 'schedule':
                $notifyUsers = ['Manager', 'Program Manager'];
                break;
        }
        
        foreach ($notifyUsers as $role) {
            $users = \App\Models\User::where('role', $role)->get();
            
            foreach ($users as $user) {
                ProgramNotification::create([
                    'title' => ucfirst($type) . ' Pending Approval',
                    'message' => "A {$type} is pending your approval: " . $entity->title ?? $entity->name,
                    'type' => 'approval_request',
                    'user_id' => $user->id,
                    'program_id' => $entity->program_id ?? $entity->id,
                    'episode_id' => $entity->episode_id ?? ($type === 'rundown' ? $entity->id : null),
                    'schedule_id' => $type === 'schedule' ? $entity->id : null
                ]);
            }
        }
    }
}
