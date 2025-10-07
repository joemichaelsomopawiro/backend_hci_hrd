<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProgramRegulerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            
            $query = \DB::table('programs_reguler')
                ->select('*')
                ->orderBy('created_at', 'desc');
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            $programs = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $programs,
                'message' => 'Programs retrieved successfully'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve programs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|in:regular,special,seasonal',
                'status' => 'required|in:draft,planning,production,completed,archived',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
                'air_time' => 'required|string',
                'duration_minutes' => 'required|integer|min:1',
                'broadcast_channel' => 'required|string|max:100',
                'description' => 'nullable|string',
                'target_audience' => 'nullable|string',
                'budget' => 'nullable|numeric|min:0',
                'manager_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $programData = $request->only([
                'name', 'type', 'status', 'start_date', 'end_date',
                'air_time', 'duration_minutes', 'broadcast_channel',
                'description', 'target_audience', 'budget', 'manager_id'
            ]);
            
            $programData['created_at'] = now();
            $programData['updated_at'] = now();

            $programId = \DB::table('programs_reguler')->insertGetId($programData);

            $program = \DB::table('programs_reguler')->find($programId);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create program: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $program = \DB::table('programs_reguler')->find($id);

            if (!$program) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $program->teams = \DB::table('teams')
                ->where('program_id', $id)
                ->where('is_active', true)
                ->get();

            $program->episodes = \DB::table('episodes')
                ->where('program_id', $id)
                ->orderBy('episode_number', 'desc')
                ->limit(10)
                ->get();

            $program->schedules = \DB::table('schedules')
                ->where('program_id', $id)
                ->where('scheduled_date', '>=', now()->format('Y-m-d'))
                ->orderBy('scheduled_date', 'asc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve program: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $program = \DB::table('programs_reguler')->find($id);

            if (!$program) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:regular,special,seasonal',
                'status' => 'sometimes|required|in:draft,planning,production,completed,archived',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'nullable|date|after:start_date',
                'air_time' => 'sometimes|required|string',
                'duration_minutes' => 'sometimes|required|integer|min:1',
                'broadcast_channel' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string',
                'target_audience' => 'nullable|string',
                'budget' => 'nullable|numeric|min:0',
                'manager_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'name', 'type', 'status', 'start_date', 'end_date',
                'air_time', 'duration_minutes', 'broadcast_channel',
                'description', 'target_audience', 'budget', 'manager_id'
            ]);
            
            $updateData['updated_at'] = now();

            \DB::table('programs_reguler')
                ->where('id', $id)
                ->update($updateData);

            $updatedProgram = \DB::table('programs_reguler')->find($id);

            return response()->json([
                'success' => true,
                'data' => $updatedProgram,
                'message' => 'Program updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update program: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $program = \DB::table('programs_reguler')->find($id);

            if (!$program) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            \DB::table('programs_reguler')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Program deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete program: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDashboard($id)
    {
        try {
            $program = \DB::table('programs_reguler')->find($id);

            if (!$program) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $totalEpisodes = \DB::table('episodes')
                ->where('program_id', $id)
                ->count();

            $completedEpisodes = \DB::table('episodes')
                ->where('program_id', $id)
                ->where('status', 'completed')
                ->count();

            $upcomingSchedules = \DB::table('schedules')
                ->where('program_id', $id)
                ->where('scheduled_date', '>=', now()->format('Y-m-d'))
                ->count();

            $activeTeams = \DB::table('teams')
                ->where('program_id', $id)
                ->where('is_active', true)
                ->count();

            $recentActivity = \DB::table('workflow_history')
                ->where('entity_type', 'program')
                ->where('entity_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $dashboard = [
                'program' => $program,
                'statistics' => [
                    'total_episodes' => $totalEpisodes,
                    'completed_episodes' => $completedEpisodes,
                    'upcoming_schedules' => $upcomingSchedules,
                    'active_teams' => $activeTeams,
                    'completion_rate' => $totalEpisodes > 0 
                        ? round(($completedEpisodes / $totalEpisodes) * 100, 2) 
                        : 0
                ],
                'recent_activity' => $recentActivity
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Program dashboard retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve program dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}

