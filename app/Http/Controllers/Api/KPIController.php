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
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
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
     * Get user deadline performance
     */
    private function getUserDeadlinePerformance($userId)
    {
        // For now, return mock data since we don't have assigned_to field in deadlines
        return [
            'total_deadlines' => rand(10, 50),
            'met_deadlines' => rand(5, 40),
            'compliance_rate' => rand(70, 95)
        ];
    }

    /**
     * Get user work quality
     */
    private function getUserWorkQuality($userId)
    {
        // This would be based on QC results, feedback, etc.
        // For now, return mock data
        return [
            'average_quality_score' => rand(70, 95),
            'qc_pass_rate' => rand(80, 100),
            'revision_rate' => rand(5, 20)
        ];
    }

    /**
     * Get user collaboration score
     */
    private function getUserCollaborationScore($userId)
    {
        // This would be based on team interactions, feedback, etc.
        // For now, return mock data
        return [
            'collaboration_score' => rand(70, 95),
            'team_feedback_rating' => rand(3, 5),
            'communication_score' => rand(70, 95)
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

    private function getOnTimeCompletionRate()
    {
        // Mock data for now
        return rand(70, 95);
    }

    private function getAverageQualityScore()
    {
        // Mock data for now
        return rand(75, 95);
    }

    private function getWorkCountByRole($role)
    {
        // This would need to be implemented based on the specific work types for each role
        return rand(10, 50);
    }

    private function getCompletedWorkCountByRole($role)
    {
        return rand(5, 40);
    }

    private function getOnTimeWorkCountByRole($role)
    {
        return rand(3, 35);
    }

    private function getTeamWorkCount($roles)
    {
        return rand(20, 100);
    }

    private function getTeamCompletedCount($roles)
    {
        return rand(15, 80);
    }

    private function getWorkCompletedInMonth($year, $month)
    {
        return rand(5, 25);
    }

    private function getUserWorkCount($userId, $role)
    {
        return rand(5, 30);
    }

    private function getUserCompletedWorkCount($userId, $role)
    {
        return rand(3, 25);
    }

    private function getUserOnTimeWorkCount($userId, $role)
    {
        return rand(2, 20);
    }

    private function getUserAverageCompletionTime($userId, $role)
    {
        return rand(1, 7) . ' days';
    }

    private function getUserWorkCompletedInMonth($userId, $year, $month)
    {
        return rand(1, 10);
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





