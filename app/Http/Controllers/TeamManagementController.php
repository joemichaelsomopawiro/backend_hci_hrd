<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Models\Program;
use App\Models\ProgramNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TeamManagementController extends Controller
{
    /**
     * Display a listing of teams
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Team::with(['program', 'teamLead', 'members']);

            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            // Filter by role
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $teams = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $teams,
                'message' => 'Teams retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created team
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'program_id' => 'nullable|exists:programs,id',
                'team_lead_id' => 'nullable|exists:users,id',
                'role' => 'required|in:creative,promotion,design,production,art_set,editor',
                'members' => 'nullable|array',
                'members.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team = Team::create([
                'name' => $request->name,
                'description' => $request->description,
                'program_id' => $request->program_id,
                'team_lead_id' => $request->team_lead_id,
                'role' => $request->role,
                'is_active' => true
            ]);

            // Add members to team
            if ($request->has('members')) {
                foreach ($request->members as $memberId) {
                    $team->addMember(User::find($memberId), 'member', true);
                }
            }

            // Notify team members
            $this->notifyTeamMembers($team, 'team_created');

            return response()->json([
                'success' => true,
                'data' => $team->load(['program', 'teamLead', 'members']),
                'message' => 'Team created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified team
     */
    public function show(string $id): JsonResponse
    {
        try {
            $team = Team::with(['program', 'teamLead', 'members', 'schedules'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Team retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified team
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'team_lead_id' => 'nullable|exists:users,id',
                'department' => 'sometimes|in:creative,promotion,design,production,art_set,editor',
                'status' => 'sometimes|in:active,inactive,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team->update($request->all());
            $team->load(['program', 'teamLead', 'members']);

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Team updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add member to team
     */
    public function addMember(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'role' => 'nullable|string|max:255',
                'status' => 'nullable|in:active,inactive,pending'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($request->user_id);
            
            if ($team->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already a member of this team'
                ], 400);
            }

            $team->addMember($user, $request->role ?? 'member', $request->status ?? 'active');

            // Notify user
            $this->notifyUser($user, 'added_to_team', $team);

            return response()->json([
                'success' => true,
                'data' => $team->load(['members']),
                'message' => 'Member added to team successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding member to team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove member from team
     */
    public function removeMember(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($request->user_id);
            
            if (!$team->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a member of this team'
                ], 400);
            }

            $team->removeMember($user);

            // Notify user
            $this->notifyUser($user, 'removed_from_team', $team);

            return response()->json([
                'success' => true,
                'data' => $team->load(['members']),
                'message' => 'Member removed from team successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing member from team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update member role
     */
    public function updateMemberRole(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'role' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($request->user_id);
            
            if (!$team->hasMember($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a member of this team'
                ], 400);
            }

            $team->updateMemberRole($user, $request->role);

            // Notify user
            $this->notifyUser($user, 'role_updated', $team, ['new_role' => $request->role]);

            return response()->json([
                'success' => true,
                'data' => $team->load(['members']),
                'message' => 'Member role updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating member role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teams by department
     */
    public function getTeamsByDepartment(Request $request): JsonResponse
    {
        try {
            $department = $request->get('department');
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department is required'
                ], 400);
            }

            $teams = Team::where('role', $department)
                ->where('is_active', true)
                ->with(['program', 'teamLead', 'members'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $teams,
                'message' => 'Teams retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's teams
     */
    public function getUserTeams(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $teams = $user->teams
                ->load(['program', 'teamLead', 'members']);

            return response()->json([
                'success' => true,
                'data' => $teams,
                'message' => 'User teams retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify team members
     */
    private function notifyTeamMembers(Team $team, string $action): void
    {
        $messages = [
            'team_created' => "You have been added to team '{$team->name}'",
            'team_updated' => "Team '{$team->name}' has been updated"
        ];

        foreach ($team->members as $member) {
            ProgramNotification::create([
                'title' => 'Team Update',
                'message' => $messages[$action] ?? "Team '{$team->name}' {$action}",
                'type' => 'team_update',
                'user_id' => $member->id,
                'program_id' => $team->program_id
            ]);
        }
    }

    /**
     * Notify user
     */
    private function notifyUser(User $user, string $action, Team $team, array $data = []): void
    {
        $messages = [
            'added_to_team' => "You have been added to team '{$team->name}'",
            'removed_from_team' => "You have been removed from team '{$team->name}'",
            'role_updated' => "Your role in team '{$team->name}' has been updated to {$data['new_role']}"
        ];

        ProgramNotification::create([
            'title' => 'Team Membership Update',
            'message' => $messages[$action] ?? "Team membership update for '{$team->name}'",
            'type' => 'team_membership_update',
            'user_id' => $user->id,
            'program_id' => $team->program_id
        ]);
    }
}
