<?php

namespace App\Http\Controllers;

use App\Models\ProductionTeam;
use App\Models\ProductionTeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductionTeamController extends Controller
{
    /**
     * Display a listing of production teams
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductionTeam::with(['producer', 'members.user', 'programs']);

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by producer
            if ($request->has('producer_id')) {
                $query->where('producer_id', $request->producer_id);
            }

            // Filter teams that are ready for production
            if ($request->boolean('ready_for_production')) {
                $query->whereHas('members', function ($q) {
                    $q->where('is_active', true);
                });
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $teams = $query->paginate($request->get('per_page', 15));

            // Add additional info for each team
            $teams->getCollection()->transform(function ($team) {
                $team->roles_summary = $team->getRolesSummary();
                $team->missing_roles = $team->getMissingRoles();
                $team->ready_for_production = $team->isReadyForProduction();
                
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
                
                return $team;
            });

            return response()->json([
                'success' => true,
                'data' => $teams,
                'message' => 'Production teams retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created production team
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:production_teams,name',
                'description' => 'nullable|string',
                'producer_id' => 'required|exists:users,id',
                'created_by' => 'required|exists:users,id',
                'members' => 'required|array|min:6', // Minimal 6 members (1 untuk setiap role)
                'members.*.user_id' => 'required|exists:users,id',
                'members.*.role' => 'required|in:kreatif,musik_arr,sound_eng,produksi,editor,art_set_design',
                'members.*.notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate: Setiap role wajib harus ada minimal 1 orang
            $members = $request->members;
            $roles = array_column($members, 'role');
            $missingRoles = array_diff(ProductionTeam::REQUIRED_ROLES, $roles);

            if (!empty($missingRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim harus memiliki minimal 1 orang untuk setiap role',
                    'missing_roles' => array_values($missingRoles),
                    'missing_roles_labels' => array_map(function ($role) {
                        return ProductionTeam::ROLE_LABELS[$role];
                    }, $missingRoles)
                ], 422);
            }

            DB::beginTransaction();

            // Create production team
            $team = ProductionTeam::create([
                'name' => $request->name,
                'description' => $request->description,
                'producer_id' => $request->producer_id,
                'created_by' => $request->created_by,
                'is_active' => true
            ]);

            // Add members
            foreach ($members as $member) {
                $team->addMember(
                    $member['user_id'],
                    $member['role'],
                    $member['notes'] ?? null
                );
            }

            DB::commit();

            // Load relationships
            $team->load(['producer', 'members.user', 'programs']);
            $team->roles_summary = $team->getRolesSummary();
            $team->ready_for_production = $team->isReadyForProduction();

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Production team created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating production team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified production team
     */
    public function show(string $id): JsonResponse
    {
        try {
            $team = ProductionTeam::with([
                'producer',
                'members.user',
                'programs.episodes',
                'createdBy'
            ])->findOrFail($id);

            $team->roles_summary = $team->getRolesSummary();
            $team->missing_roles = $team->getMissingRoles();
            $team->ready_for_production = $team->isReadyForProduction();

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
                'data' => $team,
                'message' => 'Production team retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production team: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified production team
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $team = ProductionTeam::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:production_teams,name,' . $id,
                'description' => 'nullable|string',
                'producer_id' => 'sometimes|required|exists:users,id',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team->update($request->all());
            $team->load(['producer', 'members.user', 'programs']);

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Production team updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating production team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified production team
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $team = ProductionTeam::findOrFail($id);

            // Check if team has active programs
            $activePrograms = $team->programs()->whereIn('status', ['approved', 'in_production'])->count();
            
            if ($activePrograms > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete team with active programs. Please complete or cancel programs first.',
                    'active_programs_count' => $activePrograms
                ], 422);
            }

            $team->delete();

            return response()->json([
                'success' => true,
                'message' => 'Production team deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting production team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add members to production team
     */
    public function addMembers(Request $request, string $id): JsonResponse
    {
        try {
            $team = ProductionTeam::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'members' => 'required|array|min:1',
                'members.*.user_id' => 'required|exists:users,id',
                'members.*.role' => 'required|in:kreatif,musik_arr,sound_eng,produksi,editor,art_set_design',
                'members.*.notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $addedMembers = [];
            foreach ($request->members as $member) {
                // Check if already exists
                $exists = ProductionTeamMember::where('production_team_id', $team->id)
                    ->where('user_id', $member['user_id'])
                    ->where('role', $member['role'])
                    ->where('is_active', true)
                    ->exists();

                if (!$exists) {
                    $addedMember = $team->addMember(
                        $member['user_id'],
                        $member['role'],
                        $member['notes'] ?? null
                    );
                    $addedMember->load('user');
                    $addedMembers[] = $addedMember;
                }
            }

            DB::commit();

            $team->load(['producer', 'members.user']);
            $team->roles_summary = $team->getRolesSummary();
            $team->ready_for_production = $team->isReadyForProduction();

            return response()->json([
                'success' => true,
                'data' => $team,
                'added_members' => $addedMembers,
                'message' => count($addedMembers) . ' member(s) added successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error adding members: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove members from production team
     */
    public function removeMembers(Request $request, string $id): JsonResponse
    {
        try {
            $team = ProductionTeam::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'members' => 'required|array|min:1',
                'members.*.user_id' => 'required|exists:users,id',
                'members.*.role' => 'required|in:kreatif,musik_arr,sound_eng,produksi,editor,art_set_design'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            foreach ($request->members as $member) {
                // Check if this is the last person in this role
                $roleCount = $team->activeMembers()
                    ->where('role', $member['role'])
                    ->count();

                if ($roleCount <= 1) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove the last member for role: ' . ProductionTeam::ROLE_LABELS[$member['role']],
                        'role' => $member['role']
                    ], 422);
                }

                $team->removeMember($member['user_id'], $member['role']);
            }

            DB::commit();

            $team->load(['producer', 'members.user']);
            $team->roles_summary = $team->getRolesSummary();

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Member(s) removed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error removing members: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available users for team (users who are not already in the team)
     */
    public function getAvailableUsers(string $id): JsonResponse
    {
        try {
            $team = ProductionTeam::findOrFail($id);

            // Get users who are already in the team
            $existingUserIds = $team->activeMembers()->pluck('user_id')->unique()->toArray();

            // Get available users (excluding producer and existing members)
            $availableUsers = User::whereNotIn('id', array_merge($existingUserIds, [$team->producer_id]))
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $availableUsers,
                'message' => 'Available users retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get producers list (users who can be producers)
     */
    public function getProducers(): JsonResponse
    {
        try {
            // TODO: Add role filter when role system is implemented
            // For now, get all users
            $producers = User::select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $producers,
                'message' => 'Producers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving producers: ' . $e->getMessage()
            ], 500);
        }
    }
}

