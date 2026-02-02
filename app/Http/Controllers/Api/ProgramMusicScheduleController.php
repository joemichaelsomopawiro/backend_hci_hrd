<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgramMusicScheduleController extends Controller
{
    public function getShootingSchedules(Request $request): JsonResponse
    {
        try {
            // Get creative works with shooting schedules
            $schedules = \App\Models\CreativeWork::whereNotNull('shooting_schedule')
                ->where('status', '!=', 'cancelled')
                ->with(['episode.program', 'episode.productionTeam'])
                ->orderBy('shooting_schedule', 'asc')
                ->get()
                ->map(function ($work) {
                    return [
                        'id' => $work->id,
                        'title' => 'Shooting: ' . ($work->episode->title ?? 'Episode ' . $work->episode->episode_number),
                        'start' => $work->shooting_schedule,
                        'end' => $work->shooting_schedule, // Assuming 1 day event for now
                        'type' => 'shooting',
                        'program_name' => $work->episode->program->name ?? 'Unknown Program',
                        'location' => $work->location ?? 'Studio',
                        'status' => $work->status
                    ];
                });

            return response()->json(['success' => true, 'data' => $schedules, 'message' => 'Shooting schedules retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to get shooting schedules', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAirSchedules(Request $request): JsonResponse
    {
        try {
            // Get episodes with air dates
            $schedules = \App\Models\Episode::whereNotNull('air_date')
                ->with(['program'])
                ->orderBy('air_date', 'asc')
                ->get()
                ->map(function ($episode) {
                    return [
                        'id' => $episode->id,
                        'title' => 'Airing: ' . $episode->title,
                        'start' => $episode->air_date . ' ' . ($episode->air_time ?? '00:00:00'),
                        'type' => 'airing',
                        'program_name' => $episode->program->name ?? 'Unknown Program',
                        'status' => $episode->status
                    ];
                });
            
            return response()->json(['success' => true, 'data' => $schedules, 'message' => 'Air schedules retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to get air schedules', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCalendar(Request $request): JsonResponse
    {
        // Combine shooting and airing
        $shooting = $this->getShootingSchedules($request)->getData()->data;
        $airing = $this->getAirSchedules($request)->getData()->data;
        
        return response()->json([
            'success' => true, 
            'data' => array_merge($shooting, $airing), 
            'message' => 'Calendar data retrieved'
        ]);
    }

    public function getTodaySchedules(Request $request): JsonResponse
    {
        $today = now()->format('Y-m-d');
        // Simple filter implementation
        // For production, use DB query
        $all = $this->getCalendar($request)->getData()->data;
        $todayEvents = array_filter($all, function($event) use ($today) {
            return str_starts_with($event->start, $today);
        });

        return response()->json(['success' => true, 'data' => array_values($todayEvents), 'message' => 'Today schedules retrieved']);
    }

    public function getWeekSchedules(Request $request): JsonResponse
    {
        // Placeholder for week logic
        return $this->getCalendar($request);
    }
}
