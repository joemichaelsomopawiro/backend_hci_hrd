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
            // 1. Get shooting schedules from CreativeWork
            $creativeShooting = \App\Models\CreativeWork::whereNotNull('shooting_schedule')
                ->where('status', '!=', 'cancelled')
                ->with(['episode.program'])
                ->get()
                ->map(function ($work) {
                    return [
                        'id' => 'creative_shooting_' . $work->id,
                        'title' => 'Syuting: ' . ($work->episode->program->name ?? 'Unknown') . ' Ep ' . ($work->episode->episode_number ?? ''),
                        'start' => $work->shooting_schedule,
                        'type' => 'shooting',
                        'location' => $work->shooting_location ?? 'Studio',
                        'status' => $work->status,
                        'color' => '#3b82f6' // Blue
                    ];
                });

            // 2. Get shooting schedules from MusicSchedule
            $musicShooting = \App\Models\MusicSchedule::where('schedule_type', 'shooting')
                ->where('status', '!=', 'cancelled')
                ->with(['musicSubmission.episode.program'])
                ->get()
                ->map(function ($schedule) {
                    $episode = $schedule->musicSubmission->episode ?? null;
                    return [
                        'id' => 'music_shooting_' . $schedule->id,
                        'title' => 'Syuting Musik: ' . ($episode ? $episode->program->name . ' - ' . $episode->title : 'Music Shooting'),
                        'start' => $schedule->scheduled_datetime,
                        'type' => 'shooting',
                        'location' => $schedule->location,
                        'status' => $schedule->status,
                        'color' => '#2563eb' // Darker Blue
                    ];
                });

            $schedules = collect($creativeShooting)->merge($musicShooting)->sortBy('start')->values();

            return response()->json(['success' => true, 'data' => $schedules]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getRecordingSchedules(Request $request): JsonResponse
    {
        try {
            // 1. Get recording schedules from CreativeWork
            $creativeRecording = \App\Models\CreativeWork::whereNotNull('recording_schedule')
                ->where('status', '!=', 'cancelled')
                ->with(['episode.program'])
                ->get()
                ->map(function ($work) {
                    return [
                        'id' => 'creative_recording_' . $work->id,
                        'title' => 'Rekaman: ' . ($work->episode->program->name ?? 'Unknown') . ' Ep ' . ($work->episode->episode_number ?? ''),
                        'start' => $work->recording_schedule,
                        'type' => 'recording',
                        'location' => 'Studio Rekaman',
                        'status' => $work->status,
                        'color' => '#10b981' // Green
                    ];
                });

            // 2. Get recording schedules from MusicSchedule
            $musicRecording = \App\Models\MusicSchedule::where('schedule_type', 'recording')
                ->where('status', '!=', 'cancelled')
                ->with(['musicSubmission.episode.program'])
                ->get()
                ->map(function ($schedule) {
                    $episode = $schedule->musicSubmission->episode ?? null;
                    return [
                        'id' => 'music_recording_' . $schedule->id,
                        'title' => 'Rekaman Vokal: ' . ($episode ? $episode->program->name . ' - ' . $episode->title : 'Vocal Recording'),
                        'start' => $schedule->scheduled_datetime,
                        'type' => 'recording',
                        'location' => $schedule->location,
                        'status' => $schedule->status,
                        'color' => '#059669' // Darker Green
                    ];
                });

            $schedules = collect($creativeRecording)->merge($musicRecording)->sortBy('start')->values();

            return response()->json(['success' => true, 'data' => $schedules]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getAirSchedules(Request $request): JsonResponse
    {
        try {
            // 1. Get from BroadcastingSchedule (Primary source for verified airing)
            $broadcastingAiring = \App\Models\BroadcastingSchedule::whereIn('status', ['scheduled', 'uploaded', 'published'])
                ->with(['episode.program'])
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => 'broadcasting_airing_' . $schedule->id,
                        'title' => 'Tayang: ' . ($schedule->episode->program->name ?? 'Unknown') . ' Ep ' . ($schedule->episode->episode_number ?? ''),
                        'start' => $schedule->schedule_date,
                        'type' => 'airing',
                        'platform' => $schedule->platform,
                        'status' => $schedule->status,
                        'color' => '#ef4444' // Red
                    ];
                });

            // 2. Get from Episode air_date (Fallback/Plan source)
            $episodeAiring = \App\Models\Episode::whereNotNull('air_date')
                ->whereNotIn('id', \App\Models\BroadcastingSchedule::pluck('episode_id')) // Avoid duplicates
                ->with(['program'])
                ->get()
                ->map(function ($episode) {
                    return [
                        'id' => 'episode_airing_' . $episode->id,
                        'title' => 'Rencana Tayang: ' . ($episode->program->name ?? 'Unknown') . ' Ep ' . $episode->episode_number,
                        'start' => $episode->air_date . ' ' . ($episode->air_time ?? '00:00:00'),
                        'type' => 'airing',
                        'status' => $episode->status,
                        'color' => '#f87171' // Lighter Red
                    ];
                });

            $schedules = collect($broadcastingAiring)->merge($episodeAiring)->sortBy('start')->values();
            
            return response()->json(['success' => true, 'data' => $schedules]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCalendar(Request $request): JsonResponse
    {
        try {
            $shooting = $this->getShootingSchedules($request)->getData()->data;
            $recording = $this->getRecordingSchedules($request)->getData()->data;
            $airing = $this->getAirSchedules($request)->getData()->data;
            
            $allEvents = array_merge($shooting, $recording, $airing);
            
            // Re-sort by start date
            usort($allEvents, function($a, $b) {
                return strcmp($a->start, $b->start);
            });

            return response()->json([
                'success' => true, 
                'data' => $allEvents, 
                'message' => 'Unified calendar retrieved'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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
