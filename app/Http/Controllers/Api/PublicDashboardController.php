<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Episode;
use App\Models\Program;
use App\Models\BroadcastingSchedule;
use App\Models\CreativeWork;
use App\Models\MusicSchedule;
use App\Models\User;
use App\Models\Deadline;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PublicDashboardController extends Controller
{
    /**
     * Get jadwal syuting yang sudah di-approve (untuk semua pegawai HCI)
     * Accessible by ALL authenticated users
     */
    public function getApprovedShootingSchedules(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get creative works yang sudah approved (berisi jadwal syuting)
            $creativeWorksQuery = CreativeWork::with(['episode.program', 'createdBy'])
                ->where('status', 'approved')
                ->whereNotNull('shooting_schedule');
            
            // Get music schedules yang sudah confirmed/scheduled (untuk program musik)
            $musicSchedulesQuery = MusicSchedule::with(['musicSubmission.episode.program', 'creator'])
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('schedule_type', 'shooting');

            // Filter creative works by date range
            if ($request->has('start_date')) {
                $creativeWorksQuery->where('shooting_schedule', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $creativeWorksQuery->where('shooting_schedule', '<=', $request->end_date);
            }
            if ($request->has('month') && $request->has('year')) {
                $creativeWorksQuery->whereMonth('shooting_schedule', $request->month)
                              ->whereYear('shooting_schedule', $request->year);
            }
            
            // Filter music schedules by date range
            if ($request->has('start_date')) {
                $musicSchedulesQuery->where('scheduled_datetime', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $musicSchedulesQuery->where('scheduled_datetime', '<=', $request->end_date);
            }
            if ($request->has('month') && $request->has('year')) {
                $musicSchedulesQuery->whereMonth('scheduled_datetime', $request->month)
                                   ->whereYear('scheduled_datetime', $request->year);
            }

            $creativeSchedules = $creativeWorksQuery->get();
            $musicScheduleList = $musicSchedulesQuery->get();

            // Format creative works untuk calendar
            $calendarEvents = $creativeSchedules->toBase()->map(function ($work) {
                return [
                    'id' => 'creative_' . $work->id,
                    'title' => $work->episode->program->name . ' - Episode ' . $work->episode->episode_number,
                    'start' => $work->shooting_schedule,
                    'location' => $work->shooting_location,
                    'episode_title' => $work->episode->title,
                    'program_name' => $work->episode->program->name,
                    'type' => 'shooting',
                    'source' => 'creative_work',
                    'status' => 'approved',
                    'description' => $work->script_content ? 'Script ready' : null
                ];
            });
            
            // Format music schedules untuk calendar
            $musicEvents = $musicScheduleList->toBase()->map(function ($schedule) {
                $episode = $schedule->musicSubmission->episode ?? null;
                return [
                    'id' => 'music_' . $schedule->id,
                    'title' => ($episode ? $episode->program->name . ' - Episode ' . $episode->episode_number : 'Music Schedule') . ' (Syuting Video Klip)',
                    'start' => $schedule->getEffectiveDatetime(),
                    'location' => $schedule->location,
                    'episode_title' => $episode ? $episode->title : null,
                    'program_name' => $episode ? $episode->program->name : null,
                    'type' => 'shooting',
                    'source' => 'music_schedule',
                    'status' => $schedule->status,
                    'description' => $schedule->schedule_notes
                ];
            });
            
            // Merge events
            $calendarEvents = $calendarEvents->merge($musicEvents)->sortBy('start')->values();

            return response()->json([
                'success' => true,
                'data' => $calendarEvents,
                'message' => 'Approved shooting schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving shooting schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get jadwal tayang yang sudah di-approve (untuk semua pegawai HCI)
     * Accessible by ALL authenticated users
     */
    public function getApprovedAirSchedules(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get broadcasting schedules yang sudah scheduled/published
            $query = BroadcastingSchedule::with(['episode.program', 'createdBy'])
                ->whereIn('status', ['scheduled', 'uploaded', 'published']);

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('schedule_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('schedule_date', '<=', $request->end_date);
            }

            // Filter by month
            if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('schedule_date', $request->month)
                      ->whereYear('schedule_date', $request->year);
            }

            $schedules = $query->orderBy('schedule_date', 'asc')->get();

            // Format untuk calendar
            $calendarEvents = $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->episode->program->name . ' - Episode ' . $schedule->episode->episode_number,
                    'start' => $schedule->schedule_date,
                    'platform' => $schedule->platform,
                    'episode_title' => $schedule->episode->title,
                    'program_name' => $schedule->episode->program->name,
                    'type' => 'airing',
                    'status' => $schedule->status,
                    'url' => $schedule->url
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $calendarEvents,
                'message' => 'Approved air schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving air schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get combined calendar (jadwal syuting + jadwal tayang)
     * Accessible by ALL authenticated users
     */
    public function getCalendar(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $events = [];

            // Get shooting schedules from Creative Work
            $shootingQuery = CreativeWork::with(['episode.program'])
                ->where('status', 'approved')
                ->whereNotNull('shooting_schedule');

            if ($request->has('start_date') && $request->has('end_date')) {
                $shootingQuery->whereBetween('shooting_schedule', [
                    $request->start_date, 
                    $request->end_date
                ]);
            }

            $shootingSchedules = $shootingQuery->get();

            foreach ($shootingSchedules as $work) {
                $events[] = [
                    'id' => 'shooting_' . $work->id,
                    'title' => $work->episode->program->name . ' - Syuting Episode ' . $work->episode->episode_number,
                    'start' => $work->shooting_schedule->format('Y-m-d H:i:s'),
                    'location' => $work->shooting_location,
                    'type' => 'shooting',
                    'color' => '#3b82f6', // Blue
                    'program_id' => $work->episode->program_id,
                    'episode_id' => $work->episode_id
                ];
            }
            
            // Get shooting schedules from Music Schedule (program musik)
            $musicShootingQuery = MusicSchedule::with(['musicSubmission.episode.program'])
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('schedule_type', 'shooting');

            if ($request->has('start_date') && $request->has('end_date')) {
                $musicShootingQuery->whereBetween('scheduled_datetime', [
                    $request->start_date, 
                    $request->end_date
                ]);
            }

            $musicShootingSchedules = $musicShootingQuery->get();

            foreach ($musicShootingSchedules as $schedule) {
                $episode = $schedule->musicSubmission->episode ?? null;
                $events[] = [
                    'id' => 'music_shooting_' . $schedule->id,
                    'title' => ($episode ? $episode->program->name . ' - Syuting Episode ' . $episode->episode_number : 'Music Shooting') . ' (Video Klip)',
                    'start' => $schedule->getEffectiveDatetime()->format('Y-m-d H:i:s'),
                    'location' => $schedule->location,
                    'type' => 'shooting',
                    'color' => '#3b82f6', // Blue
                    'program_id' => $episode ? $episode->program_id : null,
                    'episode_id' => $episode ? $episode->id : null
                ];
            }

            // Get recording schedules
            $recordingQuery = CreativeWork::with(['episode.program'])
                ->where('status', 'approved')
                ->whereNotNull('recording_schedule');

            if ($request->has('start_date') && $request->has('end_date')) {
                $recordingQuery->whereBetween('recording_schedule', [
                    $request->start_date, 
                    $request->end_date
                ]);
            }

            $recordingSchedules = $recordingQuery->get();

            foreach ($recordingSchedules as $work) {
                $events[] = [
                    'id' => 'recording_' . $work->id,
                    'title' => $work->episode->program->name . ' - Rekaman Episode ' . $work->episode->episode_number,
                    'start' => $work->recording_schedule->format('Y-m-d H:i:s'),
                    'type' => 'recording',
                    'color' => '#10b981', // Green
                    'program_id' => $work->episode->program_id,
                    'episode_id' => $work->episode_id
                ];
            }

            // Get air schedules
            $airQuery = BroadcastingSchedule::with(['episode.program'])
                ->whereIn('status', ['scheduled', 'uploaded', 'published']);

            if ($request->has('start_date') && $request->has('end_date')) {
                $airQuery->whereBetween('schedule_date', [
                    $request->start_date, 
                    $request->end_date
                ]);
            }

            $airSchedules = $airQuery->get();

            foreach ($airSchedules as $schedule) {
                $events[] = [
                    'id' => 'airing_' . $schedule->id,
                    'title' => $schedule->episode->program->name . ' - Tayang Episode ' . $schedule->episode->episode_number,
                    'start' => $schedule->schedule_date->format('Y-m-d H:i:s'),
                    'platform' => $schedule->platform,
                    'type' => 'airing',
                    'color' => '#ef4444', // Red
                    'program_id' => $schedule->episode->program_id,
                    'episode_id' => $schedule->episode_id,
                    'url' => $schedule->url
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $events,
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
     * Get dashboard overview untuk semua pegawai
     * Shows: programs, episodes, schedules, deadlines, KPI
     */
    public function getDashboardOverview(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $overview = [
                'user' => [
                    'name' => $user->name,
                    'role' => $user->role,
                    'email' => $user->email
                ],
                
                'today' => [
                    'date' => now()->format('Y-m-d'),
                    'shooting_schedules' => CreativeWork::where('status', 'approved')
                        ->whereDate('shooting_schedule', today())
                        ->with(['episode.program'])
                        ->get()
                        ->merge(
                            MusicSchedule::where('status', 'scheduled')
                                ->where('schedule_type', 'shooting')
                                ->whereDate('scheduled_datetime', today())
                                ->with(['musicSubmission.episode.program'])
                                ->get()
                        ),
                    
                    'recording_schedules' => CreativeWork::where('status', 'approved')
                        ->whereDate('recording_schedule', today())
                        ->with(['episode.program'])
                        ->get(),
                        // DISABLED: MusicSubmission model not exist yet
                    
                    'air_schedules' => BroadcastingSchedule::whereIn('status', ['scheduled', 'uploaded', 'published'])
                        ->whereDate('schedule_date', today())
                        ->with(['episode.program'])
                        ->get()
                ],
                
                'upcoming' => [
                    'shooting_schedules' => CreativeWork::where('status', 'approved')
                        ->where('shooting_schedule', '>', now())
                        ->where('shooting_schedule', '<=', now()->addDays(7))
                        ->with(['episode.program'])
                        ->orderBy('shooting_schedule')
                        ->limit(5)
                        ->get(),
                        // DISABLED: MusicSubmission model not exist yet
                    
                    'air_schedules' => BroadcastingSchedule::whereIn('status', ['scheduled', 'uploaded', 'published'])
                        ->where('schedule_date', '>', now())
                        ->where('schedule_date', '<=', now()->addDays(7))
                        ->with(['episode.program'])
                        ->orderBy('schedule_date')
                        ->limit(5)
                        ->get()
                ],
                
                'statistics' => [
                    'active_programs' => Program::where('status', 'active')->count(),
                    'total_episodes_this_month' => Episode::whereMonth('created_at', now()->month)->count(),
                    'upcoming_air_this_week' => BroadcastingSchedule::whereIn('status', ['scheduled'])
                        ->whereBetween('schedule_date', [now()->startOfWeek(), now()->endOfWeek()])
                        ->count()
                ],
                
                'kpi' => [
                    'on_time_completion_rate' => $this->getOnTimeCompletionRate(),
                    'deadline_compliance' => $this->getDeadlineCompliance(),
                    'work_completion' => $this->getWorkCompletion()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $overview,
                'message' => 'Dashboard overview retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard overview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get work progress for current user's team
     */
    public function getTeamProgress(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get users with same role
            $teamMembers = User::where('role', $user->role)->get();

            $teamProgress = [];

            foreach ($teamMembers as $member) {
                $progress = [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'work_summary' => []
                ];

                // Count works based on role
                switch ($user->role) {
                    case 'Music Arranger':
                        $progress['work_summary'] = [
                            'total' => \App\Models\MusicArrangement::where('created_by', $member->id)->count(),
                            'approved' => \App\Models\MusicArrangement::where('created_by', $member->id)->where('status', 'approved')->count(),
                            'pending' => \App\Models\MusicArrangement::where('created_by', $member->id)->where('status', 'submitted')->count()
                        ];
                        break;
                    
                    case 'Creative':
                        $progress['work_summary'] = [
                            'total' => CreativeWork::where('created_by', $member->id)->count(),
                            'approved' => CreativeWork::where('created_by', $member->id)->where('status', 'approved')->count(),
                            'pending' => CreativeWork::where('created_by', $member->id)->where('status', 'submitted')->count()
                        ];
                        break;
                    
                    // Add more roles as needed
                }

                $teamProgress[] = $progress;
            }

            return response()->json([
                'success' => true,
                'data' => $teamProgress,
                'message' => 'Team progress retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving team progress: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get on-time completion rate (KPI)
     */
    private function getOnTimeCompletionRate()
    {
        $completedDeadlines = Deadline::where('is_completed', true)
            ->whereNotNull('completed_at')
            ->whereNotNull('deadline_date')
            ->get();

        if ($completedDeadlines->isEmpty()) {
            return 0;
        }

        $onTimeCount = $completedDeadlines->filter(function ($deadline) {
            return $deadline->completed_at <= $deadline->deadline_date;
        })->count();

        return round(($onTimeCount / $completedDeadlines->count()) * 100, 2);
    }
    
    /**
     * Get deadline compliance (KPI)
     */
    private function getDeadlineCompliance()
    {
        $totalDeadlines = Deadline::count();
        $completedDeadlines = Deadline::where('is_completed', true)->count();
        $onTimeDeadlines = Deadline::where('is_completed', true)
            ->whereColumn('completed_at', '<=', 'deadline_date')
            ->count();

        return [
            'total_deadlines' => $totalDeadlines,
            'completed_deadlines' => $completedDeadlines,
            'on_time_deadlines' => $onTimeDeadlines,
            'compliance_rate' => $totalDeadlines > 0 ? round(($completedDeadlines / $totalDeadlines) * 100, 2) : 0,
            'on_time_rate' => $completedDeadlines > 0 ? round(($onTimeDeadlines / $completedDeadlines) * 100, 2) : 0
        ];
    }
    
    /**
     * Get work completion (KPI)
     */
    private function getWorkCompletion()
    {
        $roles = ['creative', 'musik_arr', 'sound_eng', 'production', 'editor'];
        
        $completion = [];
        foreach ($roles as $role) {
            $total = Deadline::where('role', $role)->count();
            $completed = Deadline::where('role', $role)->where('is_completed', true)->count();
            $onTime = Deadline::where('role', $role)
                ->where('is_completed', true)
                ->whereColumn('completed_at', '<=', 'deadline_date')
                ->count();

            $completion[] = [
                'role' => $role,
                'total' => $total,
                'completed' => $completed,
                'on_time' => $onTime,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'on_time_rate' => $completed > 0 ? round(($onTime / $completed) * 100, 2) : 0
            ];
        }

        return $completion;
    }
}









