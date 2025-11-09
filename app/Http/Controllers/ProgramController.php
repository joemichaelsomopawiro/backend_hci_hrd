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
     * 
     * Method ini mendukung 2 format:
     * 1. Assign single team: { "team_id": 2 } atau { "teamId": 2 }
     * 2. Assign multiple teams: { "team_ids": [1, 2, 3] }
     * 
     * Mode:
     * - attach (default): Menambahkan team baru tanpa menghapus yang lama (satu team bisa di banyak program)
     * - sync: Replace semua teams dengan yang baru
     * - detach: Remove team dari program
     */
    public function assignTeams(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);

            // Support both formats: teamId (single) and team_ids (multiple)
            $teamId = $request->input('teamId') ?? $request->input('team_id');
            $teamIds = $request->input('team_ids');
            $mode = $request->input('mode', 'attach'); // attach, sync, detach

            // Convert single team to array
            if ($teamId && !$teamIds) {
                $teamIds = [$teamId];
            }

            $validator = Validator::make(['team_ids' => $teamIds], [
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

            // Handle different modes
            switch ($mode) {
                case 'sync':
                    // Replace all teams
                    $program->teams()->sync($teamIds);
                    break;
                    
                case 'detach':
                    // Remove teams
                    $program->teams()->detach($teamIds);
                    break;
                    
                case 'attach':
                default:
                    // Add teams without removing existing ones
                    // Check for duplicates before attaching
                    $existingTeamIds = $program->teams()->pluck('teams.id')->toArray();
                    $newTeamIds = array_diff($teamIds, $existingTeamIds);
                    
                    if (!empty($newTeamIds)) {
                        $program->teams()->attach($newTeamIds);
                    }
                    break;
            }

            $program->load(['teams.teamLead', 'teams.members']);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => match($mode) {
                    'sync' => 'Teams synced successfully',
                    'detach' => 'Teams removed successfully',
                    default => 'Teams assigned successfully'
                }
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

    /**
     * Get dashboard analytics
     */
    public function getDashboardAnalytics(): JsonResponse
    {
        try {
            $analytics = [
                'total_programs' => Program::count(),
                'active_programs' => Program::where('status', 'active')->count(),
                'total_episodes' => \App\Models\Episode::count(),
                'upcoming_episodes' => \App\Models\Episode::where('air_date', '>=', now())->count(),
                'total_schedules' => \App\Models\Schedule::count(),
                'completed_schedules' => \App\Models\Schedule::where('status', 'completed')->count(),
                'total_teams' => \App\Models\Team::count(),
                'active_teams' => \App\Models\Team::where('is_active', true)->count(),
                'recent_activity' => [
                    'programs' => Program::with(['manager', 'producer'])
                        ->orderBy('created_at', 'desc')
                        ->limit(5)
                        ->get(),
                    'episodes' => \App\Models\Episode::with('program')
                        ->orderBy('created_at', 'desc')
                        ->limit(5)
                        ->get(),
                    'schedules' => \App\Models\Schedule::with(['program', 'episode'])
                        ->orderBy('created_at', 'desc')
                        ->limit(5)
                        ->get(),
                ],
                'performance_metrics' => [
                    'completion_rate' => $this->calculateCompletionRate(),
                    'on_time_delivery' => $this->calculateOnTimeDelivery(),
                    'team_productivity' => $this->calculateTeamProductivity(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Dashboard analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program analytics
     */
    public function getAnalytics(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $analytics = [
                'program' => $program,
                'episodes_count' => $program->episodes()->count(),
                'completed_episodes' => $program->episodes()->where('status', 'Aired')->count(),
                'upcoming_episodes' => $program->episodes()->where('air_date', '>=', now())->count(),
                'schedules_count' => $program->schedules()->count(),
                'completed_schedules' => $program->schedules()->where('status', 'completed')->count(),
                'teams_count' => $program->teams()->count(),
                'media_files_count' => $program->mediaFiles()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Program analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $metrics = [
                'completion_rate' => $this->calculateCompletionRate($program),
                'on_time_delivery' => $this->calculateOnTimeDelivery($program),
                'team_productivity' => $this->calculateTeamProductivity($program),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'message' => 'Performance metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving performance metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KPI summary
     */
    public function getKPISummary(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $kpi = [
                'program_id' => $program->id,
                'program_name' => $program->name,
                'completion_rate' => $this->calculateCompletionRate($program),
                'on_time_delivery' => $this->calculateOnTimeDelivery($program),
                'team_productivity' => $this->calculateTeamProductivity($program),
                'quality_score' => $this->calculateQualityScore($program),
            ];

            return response()->json([
                'success' => true,
                'data' => $kpi,
                'message' => 'KPI summary retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving KPI summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team performance
     */
    public function getTeamPerformance(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $teams = $program->teams()->with(['users', 'schedules'])->get();
            
            $teamPerformance = $teams->map(function($team) {
                return [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'member_count' => $team->users()->count(),
                    'completed_tasks' => $team->schedules()->where('status', 'completed')->count(),
                    'total_tasks' => $team->schedules()->count(),
                    'completion_rate' => $team->schedules()->count() > 0 
                        ? ($team->schedules()->where('status', 'completed')->count() / $team->schedules()->count()) * 100 
                        : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $teamPerformance,
                'message' => 'Team performance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving team performance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get content analytics
     */
    public function getContentAnalytics(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $contentAnalytics = [
                'total_media_files' => $program->mediaFiles()->count(),
                'images_count' => $program->mediaFiles()->where('file_type', 'image')->count(),
                'videos_count' => $program->mediaFiles()->where('file_type', 'video')->count(),
                'audio_count' => $program->mediaFiles()->where('file_type', 'audio')->count(),
                'documents_count' => $program->mediaFiles()->where('file_type', 'document')->count(),
                'recent_uploads' => $program->mediaFiles()
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $contentAnalytics,
                'message' => 'Content analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving content analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trends
     */
    public function getTrends(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $trends = [
                'episode_trends' => $this->getEpisodeTrends($program),
                'schedule_trends' => $this->getScheduleTrends($program),
                'team_trends' => $this->getTeamTrends($program),
            ];

            return response()->json([
                'success' => true,
                'data' => $trends,
                'message' => 'Trends retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving trends: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get views tracking
     */
    public function getViewsTracking(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            // Placeholder for views tracking - implement based on your analytics system
            $viewsTracking = [
                'total_views' => 0,
                'unique_viewers' => 0,
                'average_watch_time' => 0,
                'engagement_rate' => 0,
                'top_episodes' => [],
            ];

            return response()->json([
                'success' => true,
                'data' => $viewsTracking,
                'message' => 'Views tracking retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving views tracking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comparative analytics
     */
    public function getComparativeAnalytics(): JsonResponse
    {
        try {
            $comparative = [
                'programs_comparison' => $this->getProgramsComparison(),
                'monthly_comparison' => $this->getMonthlyComparison(),
                'team_comparison' => $this->getTeamComparison(),
            ];

            return response()->json([
                'success' => true,
                'data' => $comparative,
                'message' => 'Comparative analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving comparative analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export analytics
     */
    public function exportAnalytics(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            // Placeholder for analytics export - implement based on your export system
            $exportData = [
                'program' => $program,
                'analytics' => $this->getAnalytics($id)->getData(),
                'exported_at' => now(),
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Analytics exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for calculations
    private function calculateCompletionRate($program = null)
    {
        if ($program) {
            $total = $program->schedules()->count();
            $completed = $program->schedules()->where('status', 'completed')->count();
            return $total > 0 ? ($completed / $total) * 100 : 0;
        }
        
        $total = \App\Models\Schedule::count();
        $completed = \App\Models\Schedule::where('status', 'completed')->count();
        return $total > 0 ? ($completed / $total) * 100 : 0;
    }

    private function calculateOnTimeDelivery($program = null)
    {
        if ($program) {
            $total = $program->schedules()->count();
            $onTime = $program->schedules()
                ->where('status', 'completed')
                ->where('completed_at', '<=', DB::raw('deadline'))
                ->count();
            return $total > 0 ? ($onTime / $total) * 100 : 0;
        }
        
        $total = \App\Models\Schedule::count();
        $onTime = \App\Models\Schedule::where('status', 'completed')
            ->where('completed_at', '<=', DB::raw('deadline'))
            ->count();
        return $total > 0 ? ($onTime / $total) * 100 : 0;
    }

    private function calculateTeamProductivity($program = null)
    {
        if ($program) {
            $teams = $program->teams()->withCount('schedules')->get();
            return $teams->avg('schedules_count') ?? 0;
        }
        
        return \App\Models\Team::withCount('schedules')->get()->avg('schedules_count') ?? 0;
    }

    private function calculateQualityScore($program)
    {
        // Placeholder for quality score calculation
        return 85; // Default quality score
    }

    private function getEpisodeTrends($program)
    {
        return $program->episodes()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getScheduleTrends($program)
    {
        return $program->schedules()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getTeamTrends($program)
    {
        return $program->teams()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getProgramsComparison()
    {
        return Program::withCount(['episodes', 'schedules', 'teams'])
            ->get()
            ->map(function($program) {
                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'episodes_count' => $program->episodes_count,
                    'schedules_count' => $program->schedules_count,
                    'teams_count' => $program->teams_count,
                ];
            });
    }

    private function getMonthlyComparison()
    {
        return Program::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    private function getTeamComparison()
    {
        return \App\Models\Team::withCount('schedules')
            ->get()
            ->map(function($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'schedules_count' => $team->schedules_count,
            ];
        });
    }

    /**
     * Get pending approvals
     */
    public function getPendingApprovals(): JsonResponse
    {
        try {
            $pendingApprovals = [
                'programs' => Program::where('status', 'submitted')
                    ->with(['manager', 'producer'])
                    ->get(),
                'episodes' => Episode::where('status', 'submitted')
                    ->with(['program'])
                    ->get(),
                'schedules' => Schedule::where('status', 'submitted')
                    ->with(['program', 'episode'])
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $pendingApprovals,
                'message' => 'Pending approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pending approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approval history
     */
    public function getApprovalHistory(): JsonResponse
    {
        try {
            $approvalHistory = [
                'programs' => Program::whereIn('status', ['approved', 'rejected'])
                    ->with(['manager', 'producer'])
                    ->orderBy('updated_at', 'desc')
                    ->get(),
                'episodes' => Episode::whereIn('status', ['approved', 'rejected'])
                    ->with(['program'])
                    ->orderBy('updated_at', 'desc')
                    ->get(),
                'schedules' => Schedule::whereIn('status', ['approved', 'rejected'])
                    ->with(['program', 'episode'])
                    ->orderBy('updated_at', 'desc')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $approvalHistory,
                'message' => 'Approval history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approval history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program for approval
     */
    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'submission_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->update([
                'status' => 'submitted',
                'submission_notes' => $request->submission_notes,
                'submitted_at' => now(),
                'submitted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting program for approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
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

            $program->update([
                'status' => 'approved',
                'approval_notes' => $request->approval_notes,
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

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
            $program = Program::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->update([
                'status' => 'rejected',
                'rejection_notes' => $request->rejection_notes,
                'rejected_by' => auth()->id(),
                'rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export program data
     */
    public function exportProgramData(string $id): JsonResponse
    {
        try {
            $program = Program::with(['manager', 'producer', 'teams', 'episodes', 'schedules', 'mediaFiles', 'productionEquipment'])
                ->findOrFail($id);
            
            // Placeholder for program export - implement based on your export system
            $exportData = [
                'program' => $program,
                'exported_at' => now(),
                'format' => 'json'
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Program data exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting program data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export media files
     */
    public function exportMediaFiles(string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $mediaFiles = $program->mediaFiles()->with(['uploader'])->get();
            
            // Placeholder for media files export - implement based on your export system
            $exportData = [
                'program' => $program,
                'media_files' => $mediaFiles,
                'exported_at' => now(),
                'format' => 'json'
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Media files exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting media files: ' . $e->getMessage()
            ], 500);
        }
    }
}
