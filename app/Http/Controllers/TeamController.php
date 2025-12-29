<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Team::with(['users', 'program', 'teamLead']);

            // Filter berdasarkan role
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Filter berdasarkan status aktif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Search berdasarkan nama
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $teams = $query->paginate($request->get('per_page', 15));

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'role' => 'required|in:creative,promotion,graphic_design,production,editor,art_set_properti',
                'program_id' => 'required|exists:programs,id',
                'team_lead_id' => 'nullable|exists:users,id',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team = Team::create($request->all());
            $team->load(['users', 'program', 'teamLead']);

            return response()->json([
                'success' => true,
                'data' => $team,
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
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $team = Team::with(['users', 'program', 'teamLead'])->findOrFail($id);

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'role' => 'sometimes|required|in:creative,promotion,graphic_design,production,editor,art_set_properti',
                'team_lead_id' => 'nullable|exists:users,id',
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
            $team->load(['users', 'program', 'teamLead']);

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
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);

            // Check if team has members
            if ($team->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete team with existing members'
                ], 400);
            }

            $team->delete();

            return response()->json([
                'success' => true,
                'message' => 'Team deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add members to team
     */
    public function addMembers(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'roles' => 'nullable|array',
                'roles.*' => 'in:member,lead,assistant'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userIds = $request->user_ids;
            $roles = $request->roles ?? array_fill(0, count($userIds), 'member');

            // Use the users() relationship which is BelongsToMany
            foreach ($userIds as $index => $userId) {
                $team->users()->syncWithoutDetaching([
                    $userId => [
                        'role' => $roles[$index] ?? 'member',
                        'is_active' => true,
                        'joined_at' => now()
                    ]
                ]);
            }

            $team->load(['users', 'members']);

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Members added successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding members: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove members from team
     */
    public function removeMembers(Request $request, string $id): JsonResponse
    {
        try {
            $team = Team::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team->users()->detach($request->user_ids);
            $team->load(['users', 'members']);

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Members removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing members: ' . $e->getMessage()
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
                'role' => 'required|in:member,lead,assistant'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team->users()->updateExistingPivot($request->user_id, [
                'role' => $request->role
            ]);

            $team->load(['users', 'members']);

            return response()->json([
                'success' => true,
                'data' => $team,
                'message' => 'Member role updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating member role: ' . $e->getMessage()
            ], 500);
        }
    }
}
