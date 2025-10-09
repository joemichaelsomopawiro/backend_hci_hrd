<?php

namespace App\Http\Controllers;

use App\Models\ProgramRegular;
use App\Models\ProductionTeam;
use App\Models\ProgramEpisode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProgramRegularController extends Controller
{
    /**
     * Display a listing of program regular
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProgramRegular::with([
                'productionTeam.producer',
                'managerProgram',
                'episodes',
                'proposal'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by production team
            if ($request->has('production_team_id')) {
                $query->where('production_team_id', $request->production_team_id);
            }

            // Filter by manager
            if ($request->has('manager_program_id')) {
                $query->where('manager_program_id', $request->manager_program_id);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $programs = $query->paginate($request->get('per_page', 15));

            // Add additional info
            $programs->getCollection()->transform(function ($program) {
                $program->progress_percentage = $program->progress_percentage;
                $program->next_episode = $program->next_episode;
                $program->is_completed = $program->isCompleted();
                return $program;
            });

            return response()->json([
                'success' => true,
                'data' => $programs,
                'message' => 'Programs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving programs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created program regular
     * Auto-generate 53 episodes with deadlines
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'production_team_id' => 'required|exists:production_teams,id',
                'manager_program_id' => 'required|exists:users,id',
                'start_date' => 'required|date|after_or_equal:today',
                'air_time' => 'required|date_format:H:i',
                'duration_minutes' => 'required|integer|min:1|max:180',
                'broadcast_channel' => 'nullable|string|max:255',
                'target_views_per_episode' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate production team is ready (has all required roles)
            $team = ProductionTeam::findOrFail($request->production_team_id);
            if (!$team->isReadyForProduction()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim produksi belum lengkap. Pastikan tim memiliki semua role yang diperlukan.',
                    'missing_roles' => $team->getMissingRoles(),
                    'roles_summary' => $team->getRolesSummary()
                ], 422);
            }

            DB::beginTransaction();

            // Create program
            $program = ProgramRegular::create([
                'name' => $request->name,
                'description' => $request->description,
                'production_team_id' => $request->production_team_id,
                'manager_program_id' => $request->manager_program_id,
                'start_date' => $request->start_date,
                'air_time' => $request->air_time,
                'duration_minutes' => $request->duration_minutes,
                'broadcast_channel' => $request->broadcast_channel,
                'target_views_per_episode' => $request->target_views_per_episode,
                'status' => 'draft'
            ]);

            // Auto-generate 53 episodes with deadlines
            $program->generateEpisodes();

            DB::commit();

            // Load relationships
            $program->load([
                'productionTeam.producer',
                'managerProgram',
                'episodes.deadlines'
            ]);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program created successfully with 53 episodes and deadlines',
                'total_episodes' => $program->episodes()->count()
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified program regular
     */
    public function show(string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::with([
                'productionTeam.producer',
                'productionTeam.members.user',
                'managerProgram',
                'episodes.deadlines',
                'proposal',
                'approvals.requestedBy',
                'submittedBy',
                'approvedBy',
                'rejectedBy'
            ])->findOrFail($id);

            // Additional info
            $program->progress_percentage = $program->progress_percentage;
            $program->next_episode = $program->next_episode;
            $program->is_completed = $program->isCompleted();

            // Get statistics
            $totalEpisodes = $program->episodes()->count();
            $airedEpisodes = $program->episodes()->where('status', 'aired')->count();
            $upcomingEpisodes = $program->upcomingEpisodes()->count();
            $overdueEpisodes = $program->episodes()->overdue()->count();

            return response()->json([
                'success' => true,
                'data' => $program,
                'statistics' => [
                    'total_episodes' => $totalEpisodes,
                    'aired_episodes' => $airedEpisodes,
                    'upcoming_episodes' => $upcomingEpisodes,
                    'overdue_episodes' => $overdueEpisodes,
                    'progress_percentage' => $program->progress_percentage
                ],
                'message' => 'Program retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified program regular
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::findOrFail($id);

            // Cannot update if status is completed or cancelled
            if (in_array($program->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update program with status: ' . $program->status
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'production_team_id' => 'sometimes|required|exists:production_teams,id',
                'start_date' => 'sometimes|required|date',
                'air_time' => 'sometimes|required|date_format:H:i',
                'duration_minutes' => 'sometimes|required|integer|min:1|max:180',
                'broadcast_channel' => 'nullable|string|max:255',
                'target_views_per_episode' => 'nullable|integer|min:0',
                'status' => 'sometimes|in:draft,pending_approval,approved,in_production,completed,cancelled,rejected'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If changing production team, validate the new team
            if ($request->has('production_team_id') && $request->production_team_id != $program->production_team_id) {
                $team = ProductionTeam::findOrFail($request->production_team_id);
                if (!$team->isReadyForProduction()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tim produksi baru belum lengkap',
                        'missing_roles' => $team->getMissingRoles()
                    ], 422);
                }
            }

            $program->update($request->all());
            $program->load(['productionTeam.producer', 'managerProgram', 'episodes']);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified program regular
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::findOrFail($id);

            // Cannot delete if status is approved or in_production
            if (in_array($program->status, ['approved', 'in_production'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete program with status: ' . $program->status . '. Please cancel the program first.'
                ], 422);
            }

            $program->delete();

            return response()->json([
                'success' => true,
                'message' => 'Program deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program for approval
     */
    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($program->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft programs can be submitted for approval'
                ], 422);
            }

            $program->submitForApproval($request->user_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($program->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending programs can be approved'
                ], 422);
            }

            $program->approve($request->user_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program approved successfully'
            ]);
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
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($program->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending programs can be rejected'
                ], 422);
            }

            $program->reject($request->user_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program rejected'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program dashboard (statistics and overview)
     */
    public function dashboard(string $id): JsonResponse
    {
        try {
            $program = ProgramRegular::with([
                'productionTeam.producer',
                'episodes.deadlines'
            ])->findOrFail($id);

            // Episodes statistics
            $totalEpisodes = $program->episodes()->count();
            $airedEpisodes = $program->episodes()->where('status', 'aired')->count();
            $upcomingEpisodes = $program->upcomingEpisodes()->count();
            $overdueEpisodes = $program->episodes()->overdue()->count();

            // Deadlines statistics
            $totalDeadlines = ProgramEpisode::where('program_regular_id', $id)
                ->join('episode_deadlines', 'program_episodes.id', '=', 'episode_deadlines.program_episode_id')
                ->count();
            
            $completedDeadlines = ProgramEpisode::where('program_regular_id', $id)
                ->join('episode_deadlines', 'program_episodes.id', '=', 'episode_deadlines.program_episode_id')
                ->where('episode_deadlines.is_completed', true)
                ->count();
            
            $overdueDeadlines = ProgramEpisode::where('program_regular_id', $id)
                ->join('episode_deadlines', 'program_episodes.id', '=', 'episode_deadlines.program_episode_id')
                ->where('episode_deadlines.deadline_date', '<', now())
                ->where('episode_deadlines.is_completed', false)
                ->where('episode_deadlines.status', '!=', 'cancelled')
                ->count();

            // Next 5 episodes
            $nextEpisodes = $program->upcomingEpisodes()
                ->with('deadlines')
                ->take(5)
                ->get();

            // Recent aired episodes
            $recentAired = $program->episodes()
                ->where('status', 'aired')
                ->orderBy('air_date', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'program' => $program,
                    'episodes_stats' => [
                        'total' => $totalEpisodes,
                        'aired' => $airedEpisodes,
                        'upcoming' => $upcomingEpisodes,
                        'overdue' => $overdueEpisodes,
                        'progress_percentage' => $program->progress_percentage
                    ],
                    'deadlines_stats' => [
                        'total' => $totalDeadlines,
                        'completed' => $completedDeadlines,
                        'overdue' => $overdueDeadlines,
                        'completion_percentage' => $totalDeadlines > 0 ? round(($completedDeadlines / $totalDeadlines) * 100, 2) : 0
                    ],
                    'next_episodes' => $nextEpisodes,
                    'recent_aired' => $recentAired
                ],
                'message' => 'Program dashboard retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available production teams (ready for production)
     */
    public function getAvailableTeams(): JsonResponse
    {
        try {
            $teams = ProductionTeam::with(['producer', 'members.user'])
                ->active()
                ->get()
                ->filter(function ($team) {
                    return $team->isReadyForProduction();
                })
                ->map(function ($team) {
                    $team->roles_summary = $team->getRolesSummary();
                    return $team;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $teams,
                'message' => 'Available teams retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving teams: ' . $e->getMessage()
            ], 500);
        }
    }
}

