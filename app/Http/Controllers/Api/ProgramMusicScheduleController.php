<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Deadline;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ProgramMusicScheduleController extends Controller
{
    /**
     * Get shooting schedules (from production deadlines)
     * Accessible by ALL authenticated users
     */
    public function getShootingSchedules(Request $request): JsonResponse
    {
        try {
            // Get production deadlines as shooting schedule reference
            $query = Deadline::with(['episode.program'])
                ->where('role', 'production')
                ->whereHas('episode.program');

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('deadline_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('deadline_date', '<=', $request->end_date);
            }

            // Filter by month
            if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('deadline_date', $request->month)
                      ->whereYear('deadline_date', $request->year);
            }

            // Default: get future schedules
            if (!$request->has('start_date') && !$request->has('month')) {
                $query->where('deadline_date', '>=', now());
            }

            $schedules = $query->orderBy('deadline_date', 'asc')->get();

            // Format for calendar
            $calendarEvents = $schedules->map(function ($deadline) {
                $episode = $deadline->episode;
                $program = $episode->program;
                
                return [
                    'id' => $deadline->id,
                    'title' => $program->name . ' - Episode ' . $episode->episode_number,
                    'start' => $deadline->deadline_date,
                    'type' => 'shooting',
                    'color' => '#3b82f6', // Blue
                    'status' => $deadline->status,
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'episode_id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'episode_title' => $episode->title,
                    'deadline_role' => $deadline->role,
                    'team_id' => $episode->production_team_id
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $calendarEvents,
                'total' => $calendarEvents->count(),
                'message' => 'Shooting schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving shooting schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get air schedules (from episodes air_date)
     * Accessible by ALL authenticated users
     */
    public function getAirSchedules(Request $request): JsonResponse
    {
        try {
            $query = Episode::with('program')
                ->whereNotNull('air_date')
                ->whereHas('program');

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('air_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('air_date', '<=', $request->end_date);
            }

            // Filter by month
            if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('air_date', $request->month)
                      ->whereYear('air_date', $request->year);
            }

            // Default: get future schedules
            if (!$request->has('start_date') && !$request->has('month')) {
                $query->where('air_date', '>=', now());
            }

            $schedules = $query->orderBy('air_date', 'asc')->get();

            // Format for calendar
            $calendarEvents = $schedules->map(function ($episode) {
                $program = $episode->program;
                
                return [
                    'id' => $episode->id,
                    'title' => $program->name . ' - Episode ' . $episode->episode_number,
                    'start' => $episode->air_date,
                    'type' => 'airing',
                    'color' => '#ef4444', // Red
                    'status' => $episode->status,
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'episode_id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'episode_title' => $episode->title,
                    'air_time' => $program->air_time,
                    'broadcast_channel' => $program->broadcast_channel,
                    'duration_minutes' => $program->duration_minutes,
                    'views' => $episode->actual_views ?? 0
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $calendarEvents,
                'total' => $calendarEvents->count(),
                'message' => 'Air schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving air schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get combined calendar (shooting + airing schedules)
     * Accessible by ALL authenticated users
     */
    public function getCalendar(Request $request): JsonResponse
    {
        try {
            $events = [];

            // Get shooting schedules (production deadlines)
            $shootingQuery = Deadline::with(['episode.program'])
                ->where('role', 'production')
                ->whereHas('episode.program');

            if ($request->has('start_date') && $request->has('end_date')) {
                $shootingQuery->whereBetween('deadline_date', [
                    $request->start_date, 
                    $request->end_date
                ]);
            } else {
                $shootingQuery->where('deadline_date', '>=', now());
            }

            $shootingSchedules = $shootingQuery->orderBy('deadline_date')->get();

            foreach ($shootingSchedules as $deadline) {
                $episode = $deadline->episode;
                $program = $episode->program;
                
                $events[] = [
                    'id' => 'shooting_' . $deadline->id,
                    'title' => $program->name . ' - Syuting Episode ' . $episode->episode_number,
                    'start' => Carbon::parse($deadline->deadline_date)->format('Y-m-d H:i:s'),
                    'type' => 'shooting',
                    'color' => '#3b82f6',
                    'program_id' => $program->id,
                    'episode_id' => $episode->id,
                    'status' => $deadline->status
                ];
            }

            // Get air schedules
            $airQuery = Episode::with('program')
                ->whereNotNull('air_date')
                ->whereHas('program');

            if ($request->has('start_date') && $request->has('end_date')) {
                $airQuery->whereBetween('air_date', [
                    $request->start_date, 
                    $request->end_date
                ]);
            } else {
                $airQuery->where('air_date', '>=', now());
            }

            $airSchedules = $airQuery->orderBy('air_date')->get();

            foreach ($airSchedules as $episode) {
                $program = $episode->program;
                
                $events[] = [
                    'id' => 'airing_' . $episode->id,
                    'title' => $program->name . ' - Tayang Episode ' . $episode->episode_number,
                    'start' => Carbon::parse($episode->air_date)->format('Y-m-d H:i:s'),
                    'type' => 'airing',
                    'color' => '#ef4444',
                    'program_id' => $program->id,
                    'episode_id' => $episode->id,
                    'status' => $episode->status,
                    'views' => $episode->actual_views ?? 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $events,
                'total' => count($events),
                'shooting_count' => $shootingSchedules->count(),
                'airing_count' => $airSchedules->count(),
                'message' => 'Calendar events retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving calendar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's schedules
     */
    public function getTodaySchedules(): JsonResponse
    {
        try {
            $today = now()->format('Y-m-d');

            // Shooting schedules today
            $shootingToday = Deadline::with(['episode.program'])
                ->where('role', 'production')
                ->whereDate('deadline_date', $today)
                ->whereHas('episode.program')
                ->get()
                ->map(function ($deadline) {
                    $episode = $deadline->episode;
                    $program = $episode->program;
                    
                    return [
                        'id' => $deadline->id,
                        'title' => $program->name . ' - Episode ' . $episode->episode_number,
                        'time' => Carbon::parse($deadline->deadline_date)->format('H:i'),
                        'type' => 'shooting',
                        'status' => $deadline->status,
                        'program_name' => $program->name,
                        'episode_number' => $episode->episode_number
                    ];
                });

            // Air schedules today
            $airToday = Episode::with('program')
                ->whereDate('air_date', $today)
                ->whereHas('program')
                ->get()
                ->map(function ($episode) {
                    $program = $episode->program;
                    
                    return [
                        'id' => $episode->id,
                        'title' => $program->name . ' - Episode ' . $episode->episode_number,
                        'time' => $program->air_time,
                        'type' => 'airing',
                        'status' => $episode->status,
                        'program_name' => $program->name,
                        'episode_number' => $episode->episode_number,
                        'broadcast_channel' => $program->broadcast_channel
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $today,
                    'shooting' => $shootingToday,
                    'airing' => $airToday,
                    'shooting_count' => $shootingToday->count(),
                    'airing_count' => $airToday->count()
                ],
                'message' => "Today's schedules retrieved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving today schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get this week's schedules
     */
    public function getWeekSchedules(): JsonResponse
    {
        try {
            $startOfWeek = now()->startOfWeek()->format('Y-m-d');
            $endOfWeek = now()->endOfWeek()->format('Y-m-d');

            // Shooting schedules this week
            $shootingWeek = Deadline::with(['episode.program'])
                ->where('role', 'production')
                ->whereBetween('deadline_date', [$startOfWeek, $endOfWeek])
                ->whereHas('episode.program')
                ->orderBy('deadline_date')
                ->get();

            // Air schedules this week
            $airWeek = Episode::with('program')
                ->whereBetween('air_date', [$startOfWeek, $endOfWeek])
                ->whereHas('program')
                ->orderBy('air_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'week_start' => $startOfWeek,
                    'week_end' => $endOfWeek,
                    'shooting' => $shootingWeek,
                    'airing' => $airWeek,
                    'shooting_count' => $shootingWeek->count(),
                    'airing_count' => $airWeek->count()
                ],
                'message' => "This week's schedules retrieved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving week schedules: ' . $e->getMessage()
            ], 500);
        }
    }
}







