<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Program;
use App\Models\ProductionTeam;
use App\Models\Deadline;
use App\Services\ProgramPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ManagerProgramController extends Controller
{
    /**
     * Assign/Change production team untuk episode tertentu (with program context)
     */
    public function assignTeam(Request $request, int $programId, int $episodeId): JsonResponse
    {
        return $this->assignTeamToEpisode($request, $episodeId);
    }
    
    /**
     * Assign/Change production team untuk episode tertentu
     */
    public function assignTeamToEpisode(Request $request, int $episodeId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can assign teams'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'production_team_id' => 'required|exists:production_teams,id',
            'notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode = Episode::findOrFail($episodeId);
            $team = ProductionTeam::findOrFail($request->production_team_id);
            
            // Save old team for notification
            $oldTeamId = $episode->production_team_id;
            
            // Update episode team
            $episode->update([
                'production_team_id' => $request->production_team_id,
                'team_assignment_notes' => $request->notes,
                'team_assigned_by' => $user->id,
                'team_assigned_at' => now()
            ]);
            
            // Create notification for new team members
            $teamMembers = $team->members()->where('is_active', true)->get();
            foreach ($teamMembers as $member) {
                \App\Models\Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'team_assigned',
                    'title' => 'Ditugaskan ke Episode Baru',
                    'message' => "Anda ditugaskan untuk Episode {$episode->episode_number} - {$episode->title}",
                    'data' => [
                        'episode_id' => $episode->id,
                        'program_id' => $episode->program_id,
                        'notes' => $request->notes
                    ]
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'production_team_id' => $episode->production_team_id,
                    'team_assigned_at' => $episode->team_assigned_at,
                    'team_assigned_by' => $episode->team_assigned_by,
                    'team_assignment_notes' => $episode->team_assignment_notes
                ],
                'message' => 'Team assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign team',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Edit deadline untuk episode (with program & episode context)
     */
    public function editDeadline(Request $request, int $programId, int $episodeId, int $deadlineId): JsonResponse
    {
        return $this->editDeadlineById($request, $deadlineId);
    }
    
    /**
     * Edit deadline untuk episode
     */
    public function editDeadlineById(Request $request, int $deadlineId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can edit deadlines'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'deadline_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'description' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $deadline = Deadline::findOrFail($deadlineId);
            
            // Save old deadline for history
            $oldDeadline = $deadline->deadline_date;
            
            // Update deadline
            $deadline->update([
                'deadline_date' => $request->deadline_date,
                'description' => $request->description ?? $deadline->description,
                'change_reason' => $request->reason,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'auto_generated' => false // Mark as manually edited
            ]);
            
            // Notify role yang terkait
            $episode = $deadline->episode;
            if ($episode && $episode->productionTeam) {
                $teamMembers = $episode->productionTeam->members()
                    ->where('role', $deadline->role)
                    ->where('is_active', true)
                    ->get();
                    
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'deadline_changed',
                        'title' => 'Deadline Diubah',
                        'message' => "Deadline untuk Episode {$episode->episode_number} telah diubah",
                        'data' => [
                            'episode_id' => $episode->id,
                            'old_deadline' => $oldDeadline,
                            'new_deadline' => $request->deadline_date,
                            'reason' => $request->reason
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $deadline->id,
                    'episode_id' => $deadline->episode_id,
                    'role' => $deadline->role,
                    'deadline_date' => $deadline->deadline_date,
                    'changed_deadline_date' => $deadline->deadline_date,
                    'change_reason' => $deadline->change_reason,
                    'changed_by' => $deadline->changed_by,
                    'changed_at' => $deadline->changed_at
                ],
                'message' => 'Deadline updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate episodes untuk program (manual trigger)
     */
    public function generateEpisodes(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can generate episodes'
            ], 403);
        }
        
        $validated = $request->validate([
            'number_of_episodes' => 'required|integer|min:1|max:100',
            'start_date' => 'required|date|after_or_equal:today',
            'interval_days' => 'required|integer|min:1|max:30'
        ]);
        
        try {
            $program = Program::findOrFail($programId);
            
            // Check if episodes already generated
            if ($program->episodes()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episodes already generated for this program. Delete existing episodes first.'
                ], 400);
            }
            
            // Generate episodes with custom parameters
            $numberOfEpisodes = $validated['number_of_episodes'];
            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $intervalDays = $validated['interval_days'];
            
            $episodes = [];
            
            for ($i = 1; $i <= $numberOfEpisodes; $i++) {
                $airDate = $startDate->copy()->addDays(($i - 1) * $intervalDays);
                
                $episode = Episode::create([
                    'program_id' => $program->id,
                    'episode_number' => $i,
                    'title' => "Episode $i",
                    'air_date' => $airDate->format('Y-m-d'),
                    'status' => 'planning'
                ]);
                
                // Generate deadlines automatically
                $episode->generateDeadlines();
                
                $episodes[] = $episode->load('deadlines');
            }
            
            // Reload dengan episodes
            $program->load('episodes.deadlines');
            
            // Notify production team if exists
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
                
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'episodes_generated',
                        'title' => 'Episode Program Dibuat',
                        'message' => "$numberOfEpisodes episode untuk program '{$program->name}' telah dibuat dengan deadline otomatis",
                        'data' => [
                            'program_id' => $program->id,
                            'total_episodes' => $numberOfEpisodes
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "$numberOfEpisodes episodes generated successfully with deadlines",
                'data' => [
                    'episodes' => $episodes,
                    'program_id' => $program->id,
                    'total_episodes' => count($episodes)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate episodes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Manager Program Dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can access this dashboard'
            ], 403);
        }
        
        try {
            // Programs managed by this user
            $programs = Program::where('manager_program_id', $user->id)
                ->with(['productionTeam', 'episodes'])
                ->get();
            
            // Statistics
            $stats = [
                'total_programs' => $programs->count(),
                'active_programs' => $programs->where('status', 'active')->count(),
                'draft_programs' => $programs->where('status', 'draft')->count(),
                'total_episodes' => Episode::whereIn('program_id', $programs->pluck('id'))->count(),
                'pending_approvals' => \App\Models\ProgramProposal::where('status', 'submitted')->count(),
                'budget_requests' => Program::where('budget_approved', false)
                    ->whereNotNull('budget_amount')
                    ->count()
            ];
            
            // Upcoming deadlines
            $upcomingDeadlines = Deadline::whereHas('episode', function($q) use ($programs) {
                $q->whereIn('program_id', $programs->pluck('id'));
            })
            ->where('deadline_date', '>=', now())
            ->where('deadline_date', '<=', now()->addDays(7))
            ->where('is_completed', false)
            ->orderBy('deadline_date')
            ->with('episode.program')
            ->get();
            
            // Recent activities
            $recentPrograms = $programs->sortByDesc('updated_at')->take(5)->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'programs' => $programs,
                    'upcoming_deadlines' => $upcomingDeadlines,
                    'recent_activities' => $recentPrograms
                ],
                'message' => 'Dashboard data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update episode views manually (with program context)
     */
    public function updateEpisodeViews(Request $request, int $programId, int $episodeId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can update views'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'actual_views' => 'required|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode = Episode::with('program')->findOrFail($episodeId);
            
            // Calculate previous views for growth rate
            $previousViews = $episode->actual_views ?? 0;
            
            // Update views
            $episode->update([
                'actual_views' => $request->actual_views,
                'views_last_updated' => now()
            ]);
            
            // Calculate growth rate
            if ($previousViews > 0) {
                $growthRate = (($request->actual_views - $previousViews) / $previousViews) * 100;
                $episode->update(['views_growth_rate' => $growthRate]);
            }
            
            // Check performance
            $targetViews = $episode->program->target_views_per_episode ?? 0;
            $performance = 'below_target';
            if ($request->actual_views >= $targetViews) {
                $performance = 'above_target';
            }
            
            $episode->refresh();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'actual_views' => $episode->actual_views,
                    'target_views' => $targetViews,
                    'performance' => $performance,
                    'growth_rate' => $episode->views_growth_rate ?? 0,
                    'views_last_updated' => $episode->views_last_updated
                ],
                'message' => 'Views updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update views',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get program performance
     */
    public function getProgramPerformance(int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view performance'
            ], 403);
        }
        
        try {
            $program = Program::with(['episodes'])->findOrFail($programId);
            
            $totalEpisodes = $program->episodes()->count();
            $episodesWithViews = $program->episodes()->whereNotNull('actual_views')->where('actual_views', '>', 0)->count();
            $averageViews = $program->episodes()->whereNotNull('actual_views')->where('actual_views', '>', 0)->avg('actual_views') ?? 0;
            $targetViews = $program->target_views_per_episode ?? 0;
            
            // Calculate performance
            $performancePercentage = $targetViews > 0 ? ($averageViews / $targetViews) * 100 : 0;
            $performanceStatus = 'pending';
            
            if ($performancePercentage >= 150) {
                $performanceStatus = 'excellent';
            } elseif ($performancePercentage >= 100) {
                $performanceStatus = 'good';
            } elseif ($performancePercentage >= 75) {
                $performanceStatus = 'fair';
            } elseif ($episodesWithViews > 0) {
                $performanceStatus = 'poor';
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'total_episodes' => $totalEpisodes,
                    'episodes_with_views' => $episodesWithViews,
                    'average_views' => round($averageViews, 2),
                    'target_views' => $targetViews,
                    'performance_status' => $performanceStatus,
                    'performance_percentage' => round($performancePercentage, 2),
                    'recommendation' => $this->getRecommendation($performanceStatus)
                ],
                'message' => 'Program performance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get recommendation based on performance
     */
    private function getRecommendation(string $status): string
    {
        return match($status) {
            'excellent' => 'Program performing excellently, consider increasing production',
            'good' => 'Program performing well, continue production',
            'fair' => 'Program needs improvement, review content strategy',
            'poor' => 'Program underperforming, consider major changes or closure',
            default => 'Need more data to evaluate'
        };
    }
    
    /**
     * Get weekly performance report
     */
    public function getWeeklyPerformance(int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view performance'
            ], 403);
        }
        
        try {
            $performanceService = app(ProgramPerformanceService::class);
            $report = $performanceService->getWeeklyPerformanceReport($programId);
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Weekly performance report retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Evaluate all active programs performance
     */
    public function evaluateAllPrograms(): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can evaluate programs'
            ], 403);
        }
        
        try {
            $performanceService = app(ProgramPerformanceService::class);
            $results = $performanceService->evaluateAllActivePrograms();
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Programs evaluated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to evaluate programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Manually close program
     */
    public function closeProgram(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can close programs'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = Program::findOrFail($programId);
            
            $program->update([
                'status' => 'cancelled',
                'rejection_notes' => $request->reason,
                'rejected_by' => $user->id,
                'rejected_at' => now()
            ]);
            
            // Notifikasi ke production team
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'program_closed',
                        'title' => 'Program Ditutup',
                        'message' => "Program '{$program->name}' telah ditutup: {$request->reason}",
                        'data' => [
                            'program_id' => $program->id,
                            'reason' => $request->reason
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program closed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit opsi jadwal tayang ke Manager Broadcasting
     * User: "Manager Program dapat mengirim opsi jadwal tayang ke Manager Broadcasting"
     */
    public function submitScheduleOptions(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can submit schedule options'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'schedule_options' => 'required|array|min:1',
            'schedule_options.*.date' => 'required|date|after:now',
            'schedule_options.*.time' => 'required|date_format:H:i',
            'schedule_options.*.notes' => 'nullable|string|max:500',
            'episode_id' => 'nullable|exists:episodes,id',
            'platform' => 'nullable|in:tv,youtube,website,all',
            'submission_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $program = Program::findOrFail($programId);

            // Build schedule options array
            $scheduleOptions = [];
            foreach ($request->schedule_options as $index => $option) {
                $scheduleDate = \Carbon\Carbon::parse($option['date'])->setTimeFromTimeString($option['time']);
                
                $scheduleOptions[] = [
                    'index' => $index,
                    'date' => $scheduleDate->format('Y-m-d'),
                    'time' => $option['time'],
                    'datetime' => $scheduleDate->format('Y-m-d H:i:s'),
                    'notes' => $option['notes'] ?? null,
                    'formatted' => $scheduleDate->format('d M Y, H:i')
                ];
            }

            // Create schedule option
            $scheduleOption = \App\Models\ProgramScheduleOption::create([
                'program_id' => $programId,
                'episode_id' => $request->episode_id,
                'submitted_by' => $user->id,
                'schedule_options' => $scheduleOptions,
                'platform' => $request->platform ?? 'all',
                'status' => 'pending',
                'submission_notes' => $request->submission_notes
            ]);

            // Notify Manager Broadcasting
            $this->notifyManagerBroadcasting($scheduleOption, $program);

            return response()->json([
                'success' => true,
                'data' => $scheduleOption->load(['program', 'episode', 'submittedBy']),
                'message' => 'Schedule options submitted successfully. Manager Broadcasting has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit schedule options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get submitted schedule options
     */
    public function getScheduleOptions(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view schedule options'
            ], 403);
        }

        try {
            $query = \App\Models\ProgramScheduleOption::with(['program', 'episode', 'submittedBy', 'reviewedBy'])
                ->where('program_id', $programId);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            $options = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $options,
                'message' => 'Schedule options retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedule options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Manager Broadcasting about schedule options
     */
    private function notifyManagerBroadcasting($scheduleOption, $program): void
    {
        $managerBroadcastingUsers = \App\Models\User::where('role', 'Distribution Manager')->get();
        
        $episodeInfo = $scheduleOption->episode 
            ? "Episode {$scheduleOption->episode->episode_number}" 
            : "Program";

        $optionsText = collect($scheduleOption->schedule_options)
            ->map(function($option, $index) {
                return ($index + 1) . ". " . $option['formatted'] . ($option['notes'] ? " ({$option['notes']})" : '');
            })
            ->implode("\n");

        $optionsCount = is_array($scheduleOption->schedule_options) ? count($scheduleOption->schedule_options) : 0;
        
        foreach ($managerBroadcastingUsers as $managerUser) {
            \App\Models\Notification::create([
                'title' => 'Opsi Jadwal Tayang Baru',
                'message' => "Manager Program mengirim {$optionsCount} opsi jadwal tayang untuk program '{$program->name}' - {$episodeInfo}.\n\nOpsi:\n{$optionsText}",
                'type' => 'schedule_options_submitted',
                'user_id' => $managerUser->id,
                'data' => [
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $program->id,
                    'episode_id' => $scheduleOption->episode_id,
                    'platform' => $scheduleOption->platform,
                    'options_count' => $optionsCount
                ]
            ]);
        }
    }
}

