<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Program;
use App\Models\ProductionTeam;
use App\Models\Deadline;
use App\Models\MusicSchedule;
use App\Models\BroadcastingSchedule;
use App\Models\ProgramApproval;
use App\Models\QualityControl;
use App\Models\QualityControlWork;
use App\Models\ProductionTeamAssignment;
use App\Models\Notification;
use Carbon\Carbon;
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
            'interval_days' => 'required|integer|min:1|max:30',
            'regenerate' => 'nullable|boolean' // Opsi untuk regenerate episode yang sudah ada
        ]);
        
        try {
            $program = Program::findOrFail($programId);
            
            // Check if episodes already generated (exclude soft deleted)
            $existingEpisodes = $program->episodes()->whereNull('deleted_at')->count();
            
            if ($existingEpisodes > 0) {
                // Jika regenerate = true, hapus episode yang lama dulu
                if ($request->get('regenerate', false)) {
                    // Soft delete semua episode yang ada
                    $program->episodes()->whereNull('deleted_at')->each(function($episode) {
                        $episode->delete(); // Soft delete
                    });
                } else {
                    // Episode sudah ada, return info tanpa error
                    // Biarkan user tahu episode sudah ada, tapi tetap bisa generate dengan regenerate=true
                    return response()->json([
                        'success' => false,
                        'message' => 'Episodes already generated for this program. Use regenerate=true to regenerate episodes.',
                        'existing_episodes_count' => $existingEpisodes,
                        'can_regenerate' => true
                    ], 400);
                }
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
                    'description' => "Episode $i dari program {$program->name}",
                    'air_date' => $airDate->format('Y-m-d'),
                    'production_date' => $airDate->copy()->subDays(7), // 7 hari sebelum tayang
                    'status' => 'draft',
                    'current_workflow_state' => 'episode_generated'
                ]);
                
                // Generate deadlines automatically
                $episode->generateDeadlines();
                
                // Create initial workflow state
                \App\Models\WorkflowState::create([
                    'episode_id' => $episode->id,
                    'current_state' => 'episode_generated',
                    'assigned_to_role' => 'manager_program',
                    'assigned_to_user_id' => $program->manager_program_id,
                    'notes' => 'Episode generated by Manager Program'
                ]);
                
                $episodes[] = $episode->load(['deadlines', 'workflowStates']);
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
                'total_episodes' => Episode::whereIn('program_id', $programs->pluck('id'))
                    ->whereNull('deleted_at')
                    ->whereHas('program', function($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->count(),
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
     * Update episode views manually
     */
    public function updateEpisodeViews(Request $request, int $episodeId): JsonResponse
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
            
            $totalEpisodes = $program->episodes()->whereNull('deleted_at')->count();
            $episodesWithViews = $program->episodes()->whereNull('deleted_at')->whereNotNull('actual_views')->where('actual_views', '>', 0)->count();
            $averageViews = $program->episodes()->whereNull('deleted_at')->whereNotNull('actual_views')->where('actual_views', '>', 0)->avg('actual_views') ?? 0;
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

    /**
     * Cancel shooting/recording schedule (Override authority)
     * Manager Program dapat mengcancel jadwal syuting dengan alasan
     */
    public function cancelSchedule(Request $request, int $scheduleId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can cancel schedules'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'notify_team' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schedule = MusicSchedule::findOrFail($scheduleId);
            
            // Check if schedule can be cancelled
            if (in_array($schedule->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel schedule that is already completed or cancelled'
                ], 400);
            }

            // Save old status for history
            $oldStatus = $schedule->status;

            // Cancel schedule
            $schedule->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason . ' (Cancelled by Manager Program)',
                'cancelled_by' => $user->id,
                'cancelled_at' => now()
            ]);

            // Notify team members if requested
            if ($request->get('notify_team', true)) {
                $this->notifyScheduleCancellation($schedule, $request->reason);
            }

            // Create approval record for audit trail
            ProgramApproval::create([
                'approvable_type' => MusicSchedule::class,
                'approvable_id' => $schedule->id,
                'approval_type' => 'schedule_cancellation',
                'requested_by' => $user->id,
                'request_notes' => $request->reason,
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => 'Cancelled by Manager Program (Override authority)',
                'priority' => 'high'
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['musicSubmission', 'canceller']),
                'message' => 'Schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule shooting/recording schedule (Override authority)
     * Manager Program dapat merubah jadwal syuting dengan alasan
     */
    public function reschedule(Request $request, int $scheduleId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can reschedule'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_datetime' => 'required|date|after:now',
            'reason' => 'required|string|max:1000',
            'location' => 'nullable|string|max:255',
            'location_address' => 'nullable|string|max:500',
            'schedule_notes' => 'nullable|string|max:1000',
            'notify_team' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schedule = MusicSchedule::findOrFail($scheduleId);
            
            // Check if schedule can be rescheduled
            if (in_array($schedule->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reschedule schedule that is already completed or cancelled'
                ], 400);
            }

            // Save old data for history
            $oldDatetime = $schedule->scheduled_datetime;
            $oldLocation = $schedule->location;
            
            // Parse new datetime
            $newDatetime = Carbon::parse($request->new_datetime);

            // Reschedule
            $schedule->update([
                'status' => 'rescheduled',
                'rescheduled_datetime' => $newDatetime,
                'reschedule_reason' => $request->reason . ' (Rescheduled by Manager Program)',
                'rescheduled_by' => $user->id,
                'rescheduled_at' => now(),
                'location' => $request->location ?? $schedule->location,
                'location_address' => $request->location_address ?? $schedule->location_address,
                'schedule_notes' => ($schedule->schedule_notes ? $schedule->schedule_notes . "\n\n" : '') . 
                                  "RESCHEDULED: {$request->reason}" . 
                                  ($request->schedule_notes ? "\n{$request->schedule_notes}" : '')
            ]);

            // Notify team members if requested
            if ($request->get('notify_team', true)) {
                $this->notifyScheduleReschedule($schedule, $oldDatetime, $newDatetime, $request->reason);
            }

            // Create approval record for audit trail
            ProgramApproval::create([
                'approvable_type' => MusicSchedule::class,
                'approvable_id' => $schedule->id,
                'approval_type' => 'schedule_change',
                'requested_by' => $user->id,
                'request_notes' => $request->reason,
                'request_data' => [
                    'new_datetime' => $newDatetime->format('Y-m-d H:i:s'),
                    'new_location' => $request->location ?? $schedule->location,
                    'new_location_address' => $request->location_address ?? $schedule->location_address
                ],
                'current_data' => [
                    'old_datetime' => $oldDatetime,
                    'old_location' => $oldLocation
                ],
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => 'Rescheduled by Manager Program (Override authority)',
                'priority' => 'high'
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['musicSubmission', 'rescheduler']),
                'message' => 'Schedule rescheduled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Override approval (Override authority untuk semua bidang)
     * Manager Program dapat override approval di semua bidang
     */
    public function overrideApproval(Request $request, int $approvalId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can override approvals'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approval = ProgramApproval::findOrFail($approvalId);
            
            // Check if approval can be overridden
            if (in_array($approval->status, ['approved', 'rejected', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot override approval that is already processed'
                ], 400);
            }

            $action = $request->action;
            $reason = $request->reason . ' (Overridden by Manager Program)';

            if ($action === 'approve') {
                $approval->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'approval_notes' => $reason . ($request->notes ? "\n{$request->notes}" : '')
                ]);

                // Apply the approval to the related model
                $this->applyApproval($approval);
            } else {
                $approval->update([
                    'status' => 'rejected',
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'rejection_notes' => $reason . ($request->notes ? "\n{$request->notes}" : '')
                ]);
            }

            // Notify relevant parties
            $this->notifyApprovalOverride($approval, $action, $reason);

            return response()->json([
                'success' => true,
                'data' => $approval->load(['approvable', 'requestedBy']),
                'message' => "Approval {$action}d successfully (Override authority)"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to override approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending rundown edit requests
     * Manager Program dapat melihat permintaan edit rundown dari Producer
     */
    public function getRundownEditRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view rundown edit requests'
            ], 403);
        }

        try {
            $query = ProgramApproval::where('approval_type', 'episode_rundown')
                ->where('status', 'pending')
                ->with(['approvable', 'requestedBy']);

            // Filter by program
            if ($request->has('program_id')) {
                $query->whereHas('approvable', function($q) use ($request) {
                    $q->where('program_id', $request->program_id);
                });
            }

            $requests = $query->orderBy('requested_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Rundown edit requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rundown edit requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve rundown edit request dari Producer
     */
    public function approveRundownEdit(Request $request, int $approvalId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can approve rundown edits'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approval = ProgramApproval::findOrFail($approvalId);

            if ($approval->approval_type !== 'episode_rundown') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid approval type'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval request is not pending'
                ], 400);
            }

            $episode = Episode::findOrFail($approval->approvable_id);
            $requestData = $approval->request_data;

            // Update approval status
            $approval->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes
            ]);

            // Apply the rundown edit
            $episode->update([
                'rundown' => $requestData['new_rundown'],
                'updated_at' => now()
            ]);

            // Notify Producer
            Notification::create([
                'user_id' => $approval->requested_by,
                'type' => 'rundown_edit_approved',
                'title' => 'Edit Rundown Disetujui',
                'message' => "Permintaan edit rundown untuk Episode {$episode->episode_number}: {$episode->title} telah disetujui oleh Manager Program.",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->load(['approvable', 'requestedBy', 'approvedBy']),
                    'episode' => $episode
                ],
                'message' => 'Rundown edit approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving rundown edit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject rundown edit request dari Producer
     */
    public function rejectRundownEdit(Request $request, int $approvalId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can reject rundown edits'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approval = ProgramApproval::findOrFail($approvalId);

            if ($approval->approval_type !== 'episode_rundown') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid approval type'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval request is not pending'
                ], 400);
            }

            $episode = Episode::findOrFail($approval->approvable_id);

            // Update approval status
            $approval->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_notes' => $request->rejection_reason
            ]);

            // Notify Producer
            Notification::create([
                'user_id' => $approval->requested_by,
                'type' => 'rundown_edit_rejected',
                'title' => 'Edit Rundown Ditolak',
                'message' => "Permintaan edit rundown untuk Episode {$episode->episode_number}: {$episode->title} telah ditolak oleh Manager Program. Alasan: {$request->rejection_reason}",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'rejection_reason' => $request->rejection_reason
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $approval->load(['approvable', 'requestedBy', 'rejectedBy']),
                'message' => 'Rundown edit rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting rundown edit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quality controls for monitoring (GET only)
     * Manager Program dapat monitoring status QC
     */
    public function getQualityControls(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view quality controls'
            ], 403);
        }

        try {
            $program = Program::findOrFail($programId);
            
            $query = QualityControl::whereHas('episode', function($q) use ($programId) {
                $q->where('program_id', $programId);
            })->with(['episode', 'createdBy', 'qcBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by QC type
            if ($request->has('qc_type')) {
                $query->where('qc_type', $request->qc_type);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            $qualityControls = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $qualityControls,
                'message' => 'Quality controls retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quality controls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quality control by episode
     */
    public function getEpisodeQualityControls(Request $request, int $episodeId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view quality controls'
            ], 403);
        }

        try {
            $episode = Episode::findOrFail($episodeId);
            
            $qualityControls = QualityControl::where('episode_id', $episodeId)
                ->with(['createdBy', 'qcBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'quality_controls' => $qualityControls
                ],
                'message' => 'Episode quality controls retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quality controls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify team about schedule cancellation
     */
    private function notifyScheduleCancellation($schedule, $reason): void
    {
        // Get episode from production_teams_assignment if available
        $episode = null;
        $assignment = ProductionTeamAssignment::where('schedule_id', $schedule->id)->first();
        
        if ($assignment && $assignment->episode_id) {
            $episode = Episode::find($assignment->episode_id);
        }
        
        // If no episode found, try to get from team assignments
        if (!$episode && $schedule->teamAssignments()->count() > 0) {
            $firstAssignment = $schedule->teamAssignments()->first();
            if ($firstAssignment && $firstAssignment->episode_id) {
                $episode = Episode::find($firstAssignment->episode_id);
            }
        }
        
        $episodeInfo = $episode ? "Episode {$episode->episode_number}" : "Schedule";
        
        // Notify production team members if episode exists
        if ($episode && $episode->productionTeam) {
            $teamMembers = $episode->productionTeam->members()->where('is_active', true)->get();
            
            foreach ($teamMembers as $member) {
                \App\Models\Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'schedule_cancelled',
                    'title' => 'Jadwal Dibatalkan oleh Manager Program',
                    'message' => "Jadwal {$schedule->getScheduleTypeLabel()} untuk {$episodeInfo} telah dibatalkan: {$reason}",
                    'data' => [
                        'schedule_id' => $schedule->id,
                        'episode_id' => $episode->id,
                        'schedule_type' => $schedule->schedule_type,
                        'reason' => $reason
                    ],
                    'priority' => 'high'
                ]);
            }
        }
        
        // Also notify users assigned to this schedule via team assignments
        $teamAssignments = $schedule->teamAssignments()->with('members.user')->get();
        $notifiedUserIds = [];
        
        foreach ($teamAssignments as $teamAssignment) {
            $members = $teamAssignment->members;
            foreach ($members as $member) {
                if ($member->user_id && !in_array($member->user_id, $notifiedUserIds)) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'schedule_cancelled',
                        'title' => 'Jadwal Dibatalkan oleh Manager Program',
                        'message' => "Jadwal {$schedule->getScheduleTypeLabel()} telah dibatalkan: {$reason}",
                        'data' => [
                            'schedule_id' => $schedule->id,
                            'episode_id' => $episode ? $episode->id : null,
                            'schedule_type' => $schedule->schedule_type,
                            'reason' => $reason
                        ],
                        'priority' => 'high'
                    ]);
                    $notifiedUserIds[] = $member->user_id;
                }
            }
        }
    }

    /**
     * Notify team about schedule reschedule
     */
    private function notifyScheduleReschedule($schedule, $oldDatetime, $newDatetime, $reason): void
    {
        // Get episode from production_teams_assignment if available
        $episode = null;
        $assignment = ProductionTeamAssignment::where('schedule_id', $schedule->id)->first();
        
        if ($assignment && $assignment->episode_id) {
            $episode = Episode::find($assignment->episode_id);
        }
        
        // If no episode found, try to get from team assignments
        if (!$episode && $schedule->teamAssignments()->count() > 0) {
            $firstAssignment = $schedule->teamAssignments()->first();
            if ($firstAssignment && $firstAssignment->episode_id) {
                $episode = Episode::find($firstAssignment->episode_id);
            }
        }
        
        $oldDatetimeFormatted = $oldDatetime instanceof Carbon ? $oldDatetime->format('d M Y H:i') : (is_string($oldDatetime) ? Carbon::parse($oldDatetime)->format('d M Y H:i') : $oldDatetime);
        $newDatetimeFormatted = $newDatetime instanceof Carbon ? $newDatetime->format('d M Y H:i') : (is_string($newDatetime) ? Carbon::parse($newDatetime)->format('d M Y H:i') : $newDatetime);
        
        $episodeInfo = $episode ? "Episode {$episode->episode_number}" : "Schedule";
        
        // Notify production team members if episode exists
        if ($episode && $episode->productionTeam) {
            $teamMembers = $episode->productionTeam->members()->where('is_active', true)->get();
            
            foreach ($teamMembers as $member) {
                \App\Models\Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'schedule_rescheduled',
                    'title' => 'Jadwal Diubah oleh Manager Program',
                    'message' => "Jadwal {$schedule->getScheduleTypeLabel()} untuk {$episodeInfo} telah diubah dari {$oldDatetimeFormatted} ke {$newDatetimeFormatted}: {$reason}",
                    'data' => [
                        'schedule_id' => $schedule->id,
                        'episode_id' => $episode->id,
                        'schedule_type' => $schedule->schedule_type,
                        'old_datetime' => $oldDatetime,
                        'new_datetime' => $newDatetime,
                        'reason' => $reason
                    ],
                    'priority' => 'high'
                ]);
            }
        }
        
        // Also notify users assigned to this schedule via team assignments
        $teamAssignments = $schedule->teamAssignments()->with('members.user')->get();
        $notifiedUserIds = [];
        
        foreach ($teamAssignments as $teamAssignment) {
            $members = $teamAssignment->members;
            foreach ($members as $member) {
                if ($member->user_id && !in_array($member->user_id, $notifiedUserIds)) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'schedule_rescheduled',
                        'title' => 'Jadwal Diubah oleh Manager Program',
                        'message' => "Jadwal {$schedule->getScheduleTypeLabel()} telah diubah dari {$oldDatetimeFormatted} ke {$newDatetimeFormatted}: {$reason}",
                        'data' => [
                            'schedule_id' => $schedule->id,
                            'episode_id' => $episode ? $episode->id : null,
                            'schedule_type' => $schedule->schedule_type,
                            'old_datetime' => $oldDatetime,
                            'new_datetime' => $newDatetime,
                            'reason' => $reason
                        ],
                        'priority' => 'high'
                    ]);
                    $notifiedUserIds[] = $member->user_id;
                }
            }
        }
    }

    /**
     * Notify about approval override
     */
    private function notifyApprovalOverride($approval, $action, $reason): void
    {
        $approvable = $approval->approvable;
        
        if ($approvable instanceof Episode) {
            $episode = $approvable;
            $program = $episode->program;
            
            // Notify Manager Program (self)
            \App\Models\Notification::create([
                'user_id' => $program->manager_program_id,
                'type' => 'approval_overridden',
                'title' => 'Approval Di-override',
                'message' => "Approval untuk Episode {$episode->episode_number} telah di-{$action} oleh Manager Program: {$reason}",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'action' => $action,
                    'reason' => $reason
                ],
                'priority' => 'normal'
            ]);

            // Notify Production Team
            if ($episode->productionTeam) {
                $teamMembers = $episode->productionTeam->members()->where('is_active', true)->get();
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'approval_overridden',
                        'title' => 'Approval Di-override oleh Manager Program',
                        'message' => "Approval untuk Episode {$episode->episode_number} telah di-{$action}: {$reason}",
                        'data' => [
                            'approval_id' => $approval->id,
                            'episode_id' => $episode->id,
                            'action' => $action
                        ],
                        'priority' => 'normal'
                    ]);
                }
            }
        }
    }

    /**
     * Apply approval to related model
     */
    private function applyApproval($approval): void
    {
        $approvable = $approval->approvable;
        
        if ($approvable instanceof MusicSchedule) {
            // If approving schedule cancellation, ensure it's cancelled
            if ($approval->approval_type === 'schedule_cancellation') {
                $approvable->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $approval->approved_by,
                    'cancelled_at' => now()
                ]);
            }
            // If approving schedule change, apply the changes
            elseif ($approval->approval_type === 'schedule_change' && $approval->request_data) {
                $newDatetime = isset($approval->request_data['new_datetime']) 
                    ? Carbon::parse($approval->request_data['new_datetime']) 
                    : null;
                    
                $approvable->update([
                    'status' => 'rescheduled',
                    'rescheduled_datetime' => $newDatetime,
                    'location' => $approval->request_data['new_location'] ?? $approvable->location,
                    'rescheduled_by' => $approval->approved_by,
                    'rescheduled_at' => now()
                ]);
            }
        }
    }

    /**
     * Get Special Budget Approval Requests
     * GET /api/live-tv/manager-program/special-budget-approvals
     */
    public function getSpecialBudgetApprovals(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = ProgramApproval::where('approval_type', 'special_budget')
                ->where('status', 'pending')
                ->with(['approvable.episode.program', 'requestedBy']);

            $approvals = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Special budget approvals retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve special budget approvals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve Special Budget (dengan atau tanpa edit amount)
     * POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve
     */
    public function approveSpecialBudget(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'approved_amount' => 'nullable|numeric|min:0', // Jika tidak diisi, approve dengan amount yang diminta
                'approval_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $approval = ProgramApproval::with(['approvable.episode.program.productionTeam'])->findOrFail($id);

            if ($approval->approval_type !== 'special_budget') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a special budget approval'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This approval has already been processed'
                ], 400);
            }

            $requestedAmount = $approval->request_data['special_budget_amount'] ?? 0;
            $approvedAmount = $request->approved_amount ?? $requestedAmount;

            // Approve dengan amount yang disetujui
            $approval->approve($user->id, $request->approval_notes ?? "Budget khusus disetujui sebesar Rp " . number_format($approvedAmount, 0, ',', '.'));

            // Update Creative Work
            $creativeWork = $approval->approvable; // CreativeWork
            if ($creativeWork) {
                // Update budget_data dengan menambahkan special budget
                $budgetData = $creativeWork->budget_data ?? [];
                
                // Tambahkan special budget ke budget_data
                $budgetData[] = [
                    'category' => 'Special Budget',
                    'description' => $creativeWork->special_budget_reason ?? 'Budget khusus yang disetujui Manager Program',
                    'amount' => $approvedAmount,
                    'currency' => 'IDR',
                    'approved_by_manager' => true,
                    'approved_amount' => $approvedAmount,
                    'requested_amount' => $requestedAmount,
                    'approved_at' => now()->toDateTimeString()
                ];

                $creativeWork->update([
                    'budget_data' => $budgetData,
                    'requires_special_budget_approval' => false, // Sudah disetujui
                    'budget_approved' => true // Budget sudah disetujui
                ]);

                // Notify Producer
                $producer = $creativeWork->episode->program->productionTeam->producer;
                if ($producer) {
                    $message = $approvedAmount != $requestedAmount 
                        ? "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah disetujui dengan revisi. Diminta: Rp " . number_format($requestedAmount, 0, ',', '.') . ", Disetujui: Rp " . number_format($approvedAmount, 0, ',', '.')
                        : "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah disetujui sebesar Rp " . number_format($approvedAmount, 0, ',', '.');

                    Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'special_budget_approved',
                        'title' => 'Budget Khusus Disetujui',
                        'message' => $message,
                        'data' => [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'requested_amount' => $requestedAmount,
                            'approved_amount' => $approvedAmount,
                            'is_revised' => $approvedAmount != $requestedAmount
                        ]
                    ]);
                }

                // Notify Creative
                Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'special_budget_approved',
                    'title' => 'Budget Khusus Disetujui',
                    'message' => $message,
                    'data' => [
                        'approval_id' => $approval->id,
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'approved_amount' => $approvedAmount
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->fresh(['approvable', 'approvedBy']),
                    'creative_work' => $creativeWork->fresh(['episode'])
                ],
                'message' => $approvedAmount != $requestedAmount 
                    ? 'Special budget approved with revised amount. Producer and Creative have been notified.'
                    : 'Special budget approved successfully. Producer and Creative have been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve special budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject Special Budget
     * POST /api/live-tv/manager-program/special-budget-approvals/{id}/reject
     */
    public function rejectSpecialBudget(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $approval = ProgramApproval::with(['approvable.episode.program.productionTeam'])->findOrFail($id);

            if ($approval->approval_type !== 'special_budget') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a special budget approval'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This approval has already been processed'
                ], 400);
            }

            // Reject approval
            $approval->reject($user->id, $request->rejection_notes);

            // Update Creative Work
            $creativeWork = $approval->approvable; // CreativeWork
            if ($creativeWork) {
                $creativeWork->update([
                    'requires_special_budget_approval' => false, // Sudah diproses
                    'budget_approved' => false // Budget ditolak
                ]);

                // Notify Producer
                $producer = $creativeWork->episode->program->productionTeam->producer;
                if ($producer) {
                    Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'special_budget_rejected',
                        'title' => 'Budget Khusus Ditolak',
                        'message' => "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah ditolak oleh Manager Program. Alasan: {$request->rejection_notes}. Anda dapat mengedit creative work untuk perbaikan.",
                        'data' => [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'rejection_notes' => $request->rejection_notes
                        ]
                    ]);
                }

                // Notify Creative
                Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'special_budget_rejected',
                    'title' => 'Budget Khusus Ditolak',
                    'message' => "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah ditolak oleh Manager Program. Alasan: {$request->rejection_notes}. Silakan perbaiki budget dan ajukan kembali.",
                    'data' => [
                        'approval_id' => $approval->id,
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'rejection_notes' => $request->rejection_notes
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->fresh(['approvable', 'rejectedBy']),
                    'creative_work' => $creativeWork->fresh(['episode'])
                ],
                'message' => 'Special budget rejected. Producer and Creative have been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject special budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

