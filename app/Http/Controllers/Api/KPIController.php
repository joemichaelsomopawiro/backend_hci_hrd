<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\Deadline;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KPIController extends Controller
{
    /**
     * Get KPI dashboard data
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $kpiData = [
                'overview' => $this->getOverviewKPIs(),
                'role_performance' => $this->getRolePerformance(),
                'deadline_compliance' => $this->getDeadlineCompliance(),
                'work_completion' => $this->getWorkCompletion(),
                'team_performance' => $this->getTeamPerformance(),
                'monthly_trends' => $this->getMonthlyTrends()
            ];

            return response()->json([
                'success' => true,
                'data' => $kpiData
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in KPIController::dashboard', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KPI',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get user-specific KPI
     */
    public function userKPI($userId = null): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $targetUserId = $userId ?? $user->id;
        
        // Check if user can view other user's KPI
        if ($userId && !in_array($user->role, ['Manager Program', 'Producer', 'HR'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $userKPI = [
            'user_info' => User::find($targetUserId),
            'performance_metrics' => $this->getUserPerformanceMetrics($targetUserId),
            'deadline_performance' => $this->getUserDeadlinePerformance($targetUserId),
            'work_quality' => $this->getUserWorkQuality($targetUserId),
            'collaboration_score' => $this->getUserCollaborationScore($targetUserId),
            'monthly_progress' => $this->getUserMonthlyProgress($targetUserId)
        ];

        return response()->json([
            'success' => true,
            'data' => $userKPI
        ]);
    }

    /**
     * Get team KPI
     */
    public function teamKPI(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, ['Manager Program', 'Producer', 'HR'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $teamKPI = [
            'team_overview' => $this->getTeamOverview(),
            'role_performance' => $this->getRolePerformance(),
            'collaboration_metrics' => $this->getCollaborationMetrics(),
            'deadline_analysis' => $this->getDeadlineAnalysis(),
            'quality_metrics' => $this->getQualityMetrics()
        ];

        return response()->json([
            'success' => true,
            'data' => $teamKPI
        ]);
    }

    /**
     * Get program-specific KPI
     */
    public function programKPI($programId): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $program = Program::findOrFail($programId);
        
        $programKPI = [
            'program_info' => $program,
            'episode_progress' => $this->getProgramEpisodeProgress($programId),
            'team_performance' => $this->getProgramTeamPerformance($programId),
            'deadline_compliance' => $this->getProgramDeadlineCompliance($programId),
            'quality_metrics' => $this->getProgramQualityMetrics($programId),
            'budget_performance' => $this->getProgramBudgetPerformance($programId)
        ];

        return response()->json([
            'success' => true,
            'data' => $programKPI
        ]);
    }

    /**
     * Get overview KPIs
     */
    private function getOverviewKPIs()
    {
        $totalPrograms = Program::count();
        $activePrograms = Program::where('status', 'active')->count();
        $totalEpisodes = Episode::count();
        $completedEpisodes = Episode::where('status', 'completed')->count();
        
        $totalWork = $this->getTotalWorkCount();
        $completedWork = $this->getCompletedWorkCount();
        
        $onTimeCompletion = $this->getOnTimeCompletionRate();
        $averageQuality = $this->getAverageQualityScore();

        return [
            'total_programs' => $totalPrograms,
            'active_programs' => $activePrograms,
            'total_episodes' => $totalEpisodes,
            'completed_episodes' => $completedEpisodes,
            'completion_rate' => $totalEpisodes > 0 ? round(($completedEpisodes / $totalEpisodes) * 100, 2) : 0,
            'total_work' => $totalWork,
            'completed_work' => $completedWork,
            'work_completion_rate' => $totalWork > 0 ? round(($completedWork / $totalWork) * 100, 2) : 0,
            'on_time_completion' => $onTimeCompletion,
            'average_quality' => $averageQuality
        ];
    }

    /**
     * Get role performance
     */
    private function getRolePerformance()
    {
        $roles = ['Music Arranger', 'Producer', 'Creative', 'Production', 'Editor', 'Design Grafis', 'Editor Promosi', 'Quality Control', 'Broadcasting', 'Promosi'];
        $performance = [];

        foreach ($roles as $role) {
            $users = User::where('role', $role)->get();
            $totalWork = $this->getWorkCountByRole($role);
            $completedWork = $this->getCompletedWorkCountByRole($role);
            $onTimeWork = $this->getOnTimeWorkCountByRole($role);

            $performance[] = [
                'role' => $role,
                'user_count' => $users->count(),
                'total_work' => $totalWork,
                'completed_work' => $completedWork,
                'completion_rate' => $totalWork > 0 ? round(($completedWork / $totalWork) * 100, 2) : 0,
                'on_time_rate' => $completedWork > 0 ? round(($onTimeWork / $completedWork) * 100, 2) : 0
            ];
        }

        return $performance;
    }

    /**
     * Get deadline compliance
     */
    private function getDeadlineCompliance()
    {
        $totalDeadlines = Deadline::count();
        $metDeadlines = Deadline::where('status', 'completed')->count();
        $missedDeadlines = Deadline::where('status', 'overdue')->count();
        $pendingDeadlines = Deadline::where('status', 'pending')->count();

        return [
            'total_deadlines' => $totalDeadlines,
            'met_deadlines' => $metDeadlines,
            'missed_deadlines' => $missedDeadlines,
            'pending_deadlines' => $pendingDeadlines,
            'compliance_rate' => $totalDeadlines > 0 ? round(($metDeadlines / $totalDeadlines) * 100, 2) : 0
        ];
    }

    /**
     * Get work completion
     */
    private function getWorkCompletion()
    {
        // Use Deadlines grouped by role
        $roles = ['kreatif', 'produksi', 'editor'];
        
        $completion = [];
        foreach ($roles as $role) {
            $total = \App\Models\Deadline::where('role', $role)->count();
            $completed = \App\Models\Deadline::where('role', $role)->where('status', 'completed')->count();
            $inProgress = \App\Models\Deadline::where('role', $role)->where('status', 'in_progress')->count();
            $pending = \App\Models\Deadline::where('role', $role)->where('status', 'pending')->count();

            $completion[] = [
                'work_type' => $role,
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'pending' => $pending,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
            ];
        }

        return $completion;
    }

    /**
     * Get team performance
     */
    private function getTeamPerformance()
    {
        $teams = [
            'Music Team' => ['Music Arranger', 'Sound Engineer'],
            'Creative Team' => ['Creative', 'Design Grafis'],
            'Production Team' => ['Production', 'Art & Set Properti'],
            'Post Production' => ['Editor', 'Editor Promosi'],
            'Quality Team' => ['Quality Control'],
            'Distribution Team' => ['Broadcasting', 'Promosi']
        ];

        $teamPerformance = [];
        foreach ($teams as $teamName => $roles) {
            $teamUsers = User::whereIn('role', $roles)->get();
            $teamWork = $this->getTeamWorkCount($roles);
            $teamCompleted = $this->getTeamCompletedCount($roles);

            $teamPerformance[] = [
                'team_name' => $teamName,
                'member_count' => $teamUsers->count(),
                'total_work' => $teamWork,
                'completed_work' => $teamCompleted,
                'completion_rate' => $teamWork > 0 ? round(($teamCompleted / $teamWork) * 100, 2) : 0
            ];
        }

        return $teamPerformance;
    }

    /**
     * Get monthly trends
     */
    private function getMonthlyTrends()
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'programs_created' => Program::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count(),
                'episodes_created' => Episode::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count(),
                'deadlines_completed' => Deadline::where('status', 'completed')
                    ->whereYear('updated_at', $date->year)
                    ->whereMonth('updated_at', $date->month)->count()
            ];
        }

        return $months;
    }

    /**
     * Get user performance metrics
     */
    private function getUserPerformanceMetrics($userId)
    {
        $user = User::find($userId);
        $role = $user->role;

        $totalWork = $this->getUserWorkCount($userId, $role);
        $completedWork = $this->getUserCompletedWorkCount($userId, $role);
        $onTimeWork = $this->getUserOnTimeWorkCount($userId, $role);

        return [
            'total_work' => $totalWork,
            'completed_work' => $completedWork,
            'completion_rate' => $totalWork > 0 ? round(($completedWork / $totalWork) * 100, 2) : 0,
            'on_time_rate' => $completedWork > 0 ? round(($onTimeWork / $completedWork) * 100, 2) : 0,
            'average_completion_time' => $this->getUserAverageCompletionTime($userId, $role)
        ];
    }

    /**
     * Get user deadline performance based on real data
     */
    private function getUserDeadlinePerformance($userId)
    {
        $totalDeadlines = Deadline::where('completed_by', $userId)
            ->orWhereHas('episode.program.productionTeam.members', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('is_active', true);
            })
            ->count();

        $metDeadlines = Deadline::where('completed_by', $userId)
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->whereNotNull('deadline_date')
            ->whereRaw('completed_at <= deadline_date')
            ->count();

        $complianceRate = $totalDeadlines > 0 
            ? round(($metDeadlines / $totalDeadlines) * 100, 2) 
            : 0;

        return [
            'total_deadlines' => $totalDeadlines,
            'met_deadlines' => $metDeadlines,
            'missed_deadlines' => $totalDeadlines - $metDeadlines,
            'compliance_rate' => $complianceRate
        ];
    }

    /**
     * Get user work quality based on QC results
     */
    private function getUserWorkQuality($userId)
    {
        // Get QC works reviewed for user's work
        $qcWorks = \App\Models\QualityControlWork::whereHas('qualityControl', function($q) use ($userId) {
            $q->where('qc_by', $userId)
              ->orWhereHas('episode.program.productionTeam.members', function($q2) use ($userId) {
                  $q2->where('user_id', $userId)->where('is_active', true);
              });
        })->get();

        if ($qcWorks->isEmpty()) {
            return [
                'average_quality_score' => 0,
                'qc_pass_rate' => 0,
                'revision_rate' => 0
            ];
        }

        $totalScore = $qcWorks->whereNotNull('quality_score')->sum('quality_score');
        $scoredCount = $qcWorks->whereNotNull('quality_score')->count();
        $averageScore = $scoredCount > 0 ? round($totalScore / $scoredCount, 2) : 0;

        $passedCount = $qcWorks->where('status', 'approved')->count();
        $passRate = $qcWorks->count() > 0 ? round(($passedCount / $qcWorks->count()) * 100, 2) : 0;

        $revisionCount = $qcWorks->where('status', 'rejected')->count();
        $revisionRate = $qcWorks->count() > 0 ? round(($revisionCount / $qcWorks->count()) * 100, 2) : 0;

        return [
            'average_quality_score' => $averageScore,
            'qc_pass_rate' => $passRate,
            'revision_rate' => $revisionRate
        ];
    }

    /**
     * Get user collaboration score based on team participation
     */
    private function getUserCollaborationScore($userId)
    {
        // Count team assignments
        $teamAssignments = \App\Models\ProductionTeamAssignment::whereHas('members', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        // Count notifications sent/received (as proxy for communication)
        $notificationsReceived = \App\Models\Notification::where('user_id', $userId)->count();
        
        // Count completed collaborative tasks
        $collaborativeTasks = Deadline::where('completed_by', $userId)
            ->where('is_completed', true)
            ->whereHas('episode.program.productionTeam')
            ->count();

        // Simple scoring based on participation
        $participationScore = min(100, ($teamAssignments * 10) + ($collaborativeTasks * 5));
        $communicationScore = min(100, ($notificationsReceived / 10));

        $collaborationScore = round(($participationScore + $communicationScore) / 2, 2);

        return [
            'collaboration_score' => $collaborationScore,
            'team_assignments' => $teamAssignments,
            'collaborative_tasks_completed' => $collaborativeTasks,
            'communication_score' => $communicationScore
        ];
    }

    /**
     * Get user monthly progress
     */
    private function getUserMonthlyProgress($userId)
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'work_completed' => $this->getUserWorkCompletedInMonth($userId, $date->year, $date->month)
            ];
        }

        return $months;
    }

    // Helper methods for data calculation
    private function getTotalWorkCount()
    {
        // Use Deadlines as work items
        return \App\Models\Deadline::count();
    }

    private function getCompletedWorkCount()
    {
        return \App\Models\Deadline::where('status', 'completed')->count();
    }

    /**
     * Calculate on-time completion rate based on deadline vs completion time
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
     * Calculate average quality score based on QC results
     */
    private function getAverageQualityScore()
    {
        $qcWorks = \App\Models\QualityControlWork::whereNotNull('quality_score')->get();
        
        if ($qcWorks->isEmpty()) {
            return 0;
        }

        $totalScore = $qcWorks->sum('quality_score');
        return round($totalScore / $qcWorks->count(), 2);
    }

    /**
     * Get work count by role based on deadlines
     */
    private function getWorkCountByRole($role)
    {
        // Map role names to deadline role enum
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi', // Producer manages production
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);
        
        return Deadline::where('role', $deadlineRole)->count();
    }

    /**
     * Get completed work count by role
     */
    private function getCompletedWorkCountByRole($role)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi',
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);
        
        return Deadline::where('role', $deadlineRole)
            ->where('is_completed', true)
            ->count();
    }

    /**
     * Get on-time work count by role (completed before or on deadline)
     */
    private function getOnTimeWorkCountByRole($role)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi',
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);
        
        return Deadline::where('role', $deadlineRole)
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->whereNotNull('deadline_date')
            ->whereRaw('completed_at <= deadline_date')
            ->count();
    }

    /**
     * Get team work count based on roles
     */
    private function getTeamWorkCount($roles)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Sound Engineer' => 'sound_eng',
            'Creative' => 'kreatif',
            'Design Grafis' => 'design_grafis',
            'Production' => 'produksi',
            'Art & Set Properti' => 'art_set_design',
            'Editor' => 'editor',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRoles = [];
        foreach ($roles as $role) {
            $deadlineRole = $roleMap[$role] ?? strtolower($role);
            $deadlineRoles[] = $deadlineRole;
        }

        return Deadline::whereIn('role', $deadlineRoles)->count();
    }

    /**
     * Get team completed count
     */
    private function getTeamCompletedCount($roles)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Sound Engineer' => 'sound_eng',
            'Creative' => 'kreatif',
            'Design Grafis' => 'design_grafis',
            'Production' => 'produksi',
            'Art & Set Properti' => 'art_set_design',
            'Editor' => 'editor',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRoles = [];
        foreach ($roles as $role) {
            $deadlineRole = $roleMap[$role] ?? strtolower($role);
            $deadlineRoles[] = $deadlineRole;
        }

        return Deadline::whereIn('role', $deadlineRoles)
            ->where('is_completed', true)
            ->count();
    }

    /**
     * Get work completed in specific month
     */
    private function getWorkCompletedInMonth($year, $month)
    {
        return Deadline::where('is_completed', true)
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month)
            ->count();
    }

    /**
     * Get user work count based on deadlines assigned to user's role
     */
    private function getUserWorkCount($userId, $role)
    {
        $user = User::find($userId);
        if (!$user) return 0;

        // Get user's production team and find deadlines for their role
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi',
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);

        // Count deadlines for episodes in user's production teams
        return Deadline::where('role', $deadlineRole)
            ->whereHas('episode.program.productionTeam.members', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('is_active', true);
            })
            ->count();
    }

    /**
     * Get user completed work count
     */
    private function getUserCompletedWorkCount($userId, $role)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi',
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);

        return Deadline::where('role', $deadlineRole)
            ->where('is_completed', true)
            ->where('completed_by', $userId)
            ->count();
    }

    /**
     * Get user on-time work count (completed before deadline)
     */
    private function getUserOnTimeWorkCount($userId, $role)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi',
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);

        return Deadline::where('role', $deadlineRole)
            ->where('is_completed', true)
            ->where('completed_by', $userId)
            ->whereNotNull('completed_at')
            ->whereNotNull('deadline_date')
            ->whereRaw('completed_at <= deadline_date')
            ->count();
    }

    /**
     * Calculate average completion time for user
     */
    private function getUserAverageCompletionTime($userId, $role)
    {
        $roleMap = [
            'Music Arranger' => 'musik_arr',
            'Producer' => 'produksi',
            'Creative' => 'kreatif',
            'Production' => 'produksi',
            'Editor' => 'editor',
            'Design Grafis' => 'design_grafis',
            'Editor Promosi' => 'promotion',
            'Quality Control' => 'quality_control',
            'Broadcasting' => 'broadcasting',
            'Promosi' => 'promotion'
        ];

        $deadlineRole = $roleMap[$role] ?? strtolower($role);

        $deadlines = Deadline::where('role', $deadlineRole)
            ->where('is_completed', true)
            ->where('completed_by', $userId)
            ->whereNotNull('completed_at')
            ->whereNotNull('deadline_date')
            ->get();

        if ($deadlines->isEmpty()) {
            return '0 days';
        }

        $totalDays = $deadlines->sum(function($deadline) {
            return $deadline->deadline_date->diffInDays($deadline->completed_at, false);
        });

        $averageDays = round($totalDays / $deadlines->count(), 1);
        
        return abs($averageDays) . ' days ' . ($averageDays < 0 ? 'early' : 'late');
    }

    /**
     * Get user work completed in specific month
     */
    private function getUserWorkCompletedInMonth($userId, $year, $month)
    {
        return Deadline::where('is_completed', true)
            ->where('completed_by', $userId)
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month)
            ->count();
    }

    // Additional helper methods for program-specific KPIs
    private function getProgramEpisodeProgress($programId)
    {
        $episodes = Episode::where('program_id', $programId)->get();
        return [
            'total_episodes' => $episodes->count(),
            'completed_episodes' => $episodes->where('status', 'completed')->count(),
            'in_progress_episodes' => $episodes->where('status', 'in_progress')->count(),
            'pending_episodes' => $episodes->where('status', 'pending')->count()
        ];
    }

    private function getProgramTeamPerformance($programId)
    {
        // Mock data for now
        return [
            'team_efficiency' => rand(70, 95),
            'collaboration_score' => rand(75, 95),
            'communication_rating' => rand(3, 5)
        ];
    }

    private function getProgramDeadlineCompliance($programId)
    {
        // Mock data for now
        return [
            'compliance_rate' => rand(70, 95),
            'average_delay' => rand(0, 3) . ' days',
            'missed_deadlines' => rand(0, 5)
        ];
    }

    private function getProgramQualityMetrics($programId)
    {
        // Mock data for now
        return [
            'average_quality_score' => rand(75, 95),
            'qc_pass_rate' => rand(80, 100),
            'revision_rate' => rand(5, 20)
        ];
    }

    private function getProgramBudgetPerformance($programId)
    {
        // Mock data for now
        return [
            'budget_utilization' => rand(60, 100) . '%',
            'cost_efficiency' => rand(70, 95),
            'budget_variance' => rand(-10, 10) . '%'
        ];
    }

    private function getTeamOverview()
    {
        return [
            'total_team_members' => User::count(),
            'active_members' => User::where('status', 'active')->count(),
            'average_performance' => rand(75, 95)
        ];
    }

    private function getCollaborationMetrics()
    {
        return [
            'team_collaboration_score' => rand(70, 95),
            'cross_team_projects' => rand(5, 20),
            'communication_effectiveness' => rand(75, 95)
        ];
    }

    private function getDeadlineAnalysis()
    {
        return [
            'overall_compliance' => rand(70, 95),
            'critical_deadlines_met' => rand(80, 100),
            'average_delay' => rand(0, 2) . ' days'
        ];
    }

    private function getQualityMetrics()
    {
        return [
            'overall_quality_score' => rand(75, 95),
            'qc_pass_rate' => rand(80, 100),
            'customer_satisfaction' => rand(70, 95)
        ];
    }
}





