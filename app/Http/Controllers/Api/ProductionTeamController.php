<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionTeam;
use App\Services\ProductionTeamService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductionTeamController extends Controller
{
    protected $productionTeamService;

    public function __construct(ProductionTeamService $productionTeamService)
    {
        $this->productionTeamService = $productionTeamService;
    }

    /**
     * Get all production teams
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductionTeam::with(['producer', 'members.user']);
        
        // Filter by producer
        if ($request->has('producer_id')) {
            $query->where('producer_id', $request->producer_id);
        }
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $teams = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Transform members to include user data explicitly
        $teams->getCollection()->transform(function ($team) {
            $team->members = $team->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'user' => $member->user ? [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'role' => $member->user->role
                    ] : null,
                    'role' => $member->role,
                    'role_label' => $member->role_label,
                    'is_active' => $member->is_active,
                    'joined_at' => $member->joined_at,
                    'notes' => $member->notes
                ];
            });
            return $team;
        });
        
        return response()->json([
            'success' => true,
            'data' => $teams,
            'message' => 'Production teams retrieved successfully'
        ]);
    }

    /**
     * Get production team by ID
     */
    public function show(int $id): JsonResponse
    {
        $team = ProductionTeam::with(['producer', 'members.user', 'programs'])->findOrFail($id);
        $statistics = $this->productionTeamService->getTeamStatistics($team);
        
        // Transform members to include user data explicitly
        $team->members = $team->members->map(function ($member) {
            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->user->role
                ] : null,
                'role' => $member->role,
                'role_label' => $member->role_label,
                'is_active' => $member->is_active,
                'joined_at' => $member->joined_at,
                'notes' => $member->notes
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'team' => $team,
                'statistics' => $statistics
            ],
            'message' => 'Production team retrieved successfully'
        ]);
    }

    /**
     * Create production team
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:production_teams',
            'description' => 'nullable|string',
            'producer_id' => 'required|exists:users,id',
            'created_by' => 'nullable|exists:users,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $data = $request->all();
            
            // Set created_by jika tidak ada
            if (!isset($data['created_by'])) {
                $data['created_by'] = auth()->id();
            }
            
            $team = $this->productionTeamService->createTeam($data);
            
            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Production team created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create production team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update production team
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:production_teams,name,' . $id,
            'description' => 'nullable|string',
            'producer_id' => 'sometimes|exists:users,id',
            'is_active' => 'sometimes|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $team->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Production team updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update production team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete production team
     */
    public function destroy(int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        
        if ($team->programs()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete production team that has programs'
            ], 400);
        }
        
        try {
            $team->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Production team deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete production team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add member to team
     */
    public function addMember(Request $request, int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:kreatif,musik_arr,sound_eng,produksi,editor,art_set_design',
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
            $member = $this->productionTeamService->addMember(
                $team,
                $request->user_id,
                $request->role,
                $request->notes
            );
            
            // Load user relationship and transform response
            $member->load('user');
            $memberData = [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->user->role
                ] : null,
                'role' => $member->role,
                'role_label' => $member->role_label,
                'is_active' => $member->is_active,
                'joined_at' => $member->joined_at,
                'notes' => $member->notes
            ];
            
            return response()->json([
                'success' => true,
                'data' => $memberData,
                'message' => 'Member added to team successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add member to team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove member from team
     */
    public function removeMember(Request $request, int $id, int $memberId): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'role' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $result = $this->productionTeamService->removeMember($team, $memberId, $request->role);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to remove member from team'
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Member removed from team successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member from team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update team member
     */
    public function updateMember(Request $request, int $id, int $memberId): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        $member = $team->members()->findOrFail($memberId);
        
        $validator = Validator::make($request->all(), [
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
            $member = $this->productionTeamService->updateMember($member, $request->all());
            
            return response()->json([
                'success' => true,
                'data' => $member,
                'message' => 'Team member updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update team member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team members
     */
    public function getMembers(int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        $members = $this->productionTeamService->getTeamMembers($team);
        
        return response()->json([
            'success' => true,
            'data' => $members,
            'message' => 'Team members retrieved successfully'
        ]);
    }

    /**
     * Get team statistics
     */
    public function getStatistics(int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        $statistics = $this->productionTeamService->getTeamStatistics($team);
        
        return response()->json([
            'success' => true,
            'data' => $statistics,
            'message' => 'Team statistics retrieved successfully'
        ]);
    }

    /**
     * Get team workload
     */
    public function getWorkload(int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        $workload = $this->productionTeamService->getTeamWorkload($team);
        
        return response()->json([
            'success' => true,
            'data' => $workload,
            'message' => 'Team workload retrieved successfully'
        ]);
    }

    /**
     * Get teams by producer
     */
    public function getTeamsByProducer(int $producerId): JsonResponse
    {
        $teams = $this->productionTeamService->getTeamsByProducer($producerId);
        
        return response()->json([
            'success' => true,
            'data' => $teams,
            'message' => 'Producer teams retrieved successfully'
        ]);
    }

    /**
     * Get available users for role
     */
    public function getAvailableUsersForRole(string $role): JsonResponse
    {
        $users = $this->productionTeamService->getAvailableUsersForRole($role);
        
        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Available users for role retrieved successfully'
        ]);
    }

    /**
     * Deactivate team
     */
    public function deactivate(int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        
        try {
            $result = $this->productionTeamService->deactivateTeam($team);
            
            return response()->json([
                'success' => true,
                'message' => 'Team deactivated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate team
     */
    public function reactivate(int $id): JsonResponse
    {
        $team = ProductionTeam::findOrFail($id);
        
        try {
            $result = $this->productionTeamService->reactivateTeam($team);
            
            return response()->json([
                'success' => true,
                'message' => 'Team reactivated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate team',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
