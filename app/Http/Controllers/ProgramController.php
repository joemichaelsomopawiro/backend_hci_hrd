<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Team;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Program::with(['manager', 'producer', 'teams', 'episodes']);

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan tipe
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter berdasarkan manager
            if ($request->has('manager_id')) {
                $query->where('manager_id', $request->manager_id);
            }

            // Search berdasarkan nama
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $programs = $query->paginate($request->get('per_page', 15));

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:weekly,monthly,quarterly,special',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
                'air_time' => 'nullable|date_format:H:i',
                'duration_minutes' => 'required|integer|min:1',
                'broadcast_channel' => 'nullable|string|max:255',
                'rundown' => 'nullable|string',
                'requirements' => 'nullable|array',
                'manager_id' => 'required|exists:users,id',
                'producer_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program = Program::create($request->all());

            // Load relationships
            $program->load(['manager', 'producer', 'teams']);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $program = Program::with([
                'manager',
                'producer',
                'teams.members.user',
                'episodes',
                'schedules',
                'mediaFiles',
                'productionEquipment',
                'notifications'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'sometimes|required|in:weekly,monthly,quarterly,special',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'nullable|date|after:start_date',
                'air_time' => 'nullable|date_format:H:i',
                'duration_minutes' => 'sometimes|required|integer|min:1',
                'broadcast_channel' => 'nullable|string|max:255',
                'rundown' => 'nullable|string',
                'requirements' => 'nullable|array',
                'manager_id' => 'sometimes|required|exists:users,id',
                'producer_id' => 'nullable|exists:users,id',
                'status' => 'sometimes|in:draft,active,completed,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->update($request->all());
            $program->load(['manager', 'producer', 'teams']);

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
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);

            // Check if program has episodes or schedules
            if ($program->episodes()->count() > 0 || $program->schedules()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete program with existing episodes or schedules'
                ], 400);
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
     * Assign teams to program
     */
    public function assignTeams(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'team_ids' => 'required|array',
                'team_ids.*' => 'exists:teams,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->teams()->sync($request->team_ids);

            $program->load('teams');

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Teams assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program statistics
     */
    public function statistics(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);

            $stats = [
                'total_episodes' => $program->episodes()->count(),
                'episodes_by_status' => $program->episodes()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'total_teams' => $program->teams()->count(),
                'total_schedules' => $program->schedules()->count(),
                'schedules_by_status' => $program->schedules()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'total_media_files' => $program->mediaFiles()->count(),
                'media_files_by_type' => $program->mediaFiles()
                    ->selectRaw('file_type, count(*) as count')
                    ->groupBy('file_type')
                    ->pluck('count', 'file_type'),
                'upcoming_episodes' => $program->episodes()
                    ->where('air_date', '>=', now())
                    ->where('status', '!=', 'aired')
                    ->count(),
                'overdue_schedules' => $program->schedules()
                    ->where('deadline', '<', now())
                    ->where('status', '!=', 'completed')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Program statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program dashboard data
     */
    public function dashboard(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);

            $dashboard = [
                'program' => $program->load(['manager', 'producer']),
                'recent_episodes' => $program->episodes()
                    ->orderBy('air_date', 'desc')
                    ->limit(5)
                    ->get(),
                'upcoming_schedules' => $program->schedules()
                    ->where('start_time', '>=', now())
                    ->orderBy('start_time', 'asc')
                    ->limit(10)
                    ->get(),
                'team_performance' => $program->teams()
                    ->withCount(['schedules as completed_schedules' => function($query) {
                        $query->where('status', 'completed');
                    }])
                    ->withCount(['schedules as total_schedules'])
                    ->get(),
                'recent_media' => $program->mediaFiles()
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Program dashboard data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}
