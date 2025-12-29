<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Episode;
use App\Models\User;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\ProductionWork;
use App\Models\BroadcastingWork;
use App\Models\SocialMediaPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get comprehensive analytics dashboard
     */
    public function dashboard(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $analytics = [
            'overview' => $this->getOverviewAnalytics(),
            'program_performance' => $this->getProgramPerformance(),
            'episode_analytics' => $this->getEpisodeAnalytics(),
            'work_analytics' => $this->getWorkAnalytics(),
            'social_media_analytics' => $this->getSocialMediaAnalytics(),
            'team_analytics' => $this->getTeamAnalytics(),
            'trends' => $this->getTrends(),
            'predictions' => $this->getPredictions()
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get program-specific analytics
     */
    public function programAnalytics($programId): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $program = Program::findOrFail($programId);
        
        $analytics = [
            'program_info' => $program,
            'episode_breakdown' => $this->getProgramEpisodeBreakdown($programId),
            'work_breakdown' => $this->getProgramWorkBreakdown($programId),
            'team_performance' => $this->getProgramTeamPerformance($programId),
            'budget_analysis' => $this->getProgramBudgetAnalysis($programId),
            'timeline_analysis' => $this->getProgramTimelineAnalysis($programId),
            'quality_metrics' => $this->getProgramQualityMetrics($programId)
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get role-specific analytics
     */
    public function roleAnalytics(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $role = $request->input('role', $user->role);
        
        $analytics = [
            'role' => $role,
            'work_distribution' => $this->getRoleWorkDistribution($role),
            'performance_metrics' => $this->getRolePerformanceMetrics($role),
            'deadline_analysis' => $this->getRoleDeadlineAnalysis($role),
            'collaboration_metrics' => $this->getRoleCollaborationMetrics($role),
            'quality_metrics' => $this->getRoleQualityMetrics($role),
            'efficiency_metrics' => $this->getRoleEfficiencyMetrics($role)
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get time-based analytics
     */
    public function timeAnalytics(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $period = $request->input('period', 'month'); // day, week, month, year
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $analytics = [
            'period' => $period,
            'date_range' => $this->getDateRange($period, $startDate, $endDate),
            'work_completion_trend' => $this->getWorkCompletionTrend($period, $startDate, $endDate),
            'deadline_performance' => $this->getDeadlinePerformanceTrend($period, $startDate, $endDate),
            'team_activity' => $this->getTeamActivityTrend($period, $startDate, $endDate),
            'quality_trend' => $this->getQualityTrend($period, $startDate, $endDate)
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get social media analytics
     */
    public function socialMediaAnalytics(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $analytics = [
            'platform_breakdown' => $this->getSocialMediaPlatformBreakdown(),
            'engagement_metrics' => $this->getSocialMediaEngagementMetrics(),
            'content_performance' => $this->getSocialMediaContentPerformance(),
            'posting_schedule' => $this->getSocialMediaPostingSchedule(),
            'audience_insights' => $this->getSocialMediaAudienceInsights()
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get overview analytics
     */
    private function getOverviewAnalytics()
    {
        return [
            'total_programs' => Program::count(),
            'active_programs' => Program::where('status', 'active')->count(),
            'total_episodes' => Episode::count(),
            'completed_episodes' => Episode::where('status', 'completed')->count(),
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_work' => $this->getTotalWorkCount(),
            'completed_work' => $this->getCompletedWorkCount(),
            'overall_efficiency' => $this->getOverallEfficiency(),
            'quality_score' => $this->getOverallQualityScore()
        ];
    }

    /**
     * Get program performance analytics
     */
    private function getProgramPerformance()
    {
        $programs = Program::with(['episodes', 'managerProgram'])->get();
        
        $performance = [];
        foreach ($programs as $program) {
            $episodes = $program->episodes;
            $performance[] = [
                'program_id' => $program->id,
                'program_name' => $program->name,
                'manager' => $program->managerProgram->name ?? 'N/A',
                'total_episodes' => $episodes->count(),
                'completed_episodes' => $episodes->where('status', 'completed')->count(),
                'completion_rate' => $episodes->count() > 0 ? 
                    round(($episodes->where('status', 'completed')->count() / $episodes->count()) * 100, 2) : 0,
                'average_episode_duration' => $this->getAverageEpisodeDuration($program->id),
                'budget_utilization' => $this->getBudgetUtilization($program->id),
                'quality_score' => $this->getProgramQualityScore($program->id)
            ];
        }

        return $performance;
    }

    /**
     * Get episode analytics
     */
    private function getEpisodeAnalytics()
    {
        $episodes = Episode::all();
        
        return [
            'total_episodes' => $episodes->count(),
            'status_breakdown' => [
                'pending' => $episodes->where('status', 'pending')->count(),
                'in_progress' => $episodes->where('status', 'in_progress')->count(),
                'completed' => $episodes->where('status', 'completed')->count(),
                'cancelled' => $episodes->where('status', 'cancelled')->count()
            ],
            'average_duration' => $this->getAverageEpisodeDuration(),
            'deadline_performance' => $this->getEpisodeDeadlinePerformance(),
            'quality_distribution' => $this->getEpisodeQualityDistribution()
        ];
    }

    /**
     * Get work analytics
     */
    private function getWorkAnalytics()
    {
        $workTypes = [
            'music_arrangements' => MusicArrangement::class,
            'creative_works' => CreativeWork::class,
            'production_works' => ProductionWork::class,
            'broadcasting_works' => BroadcastingWork::class
        ];

        $analytics = [];
        foreach ($workTypes as $type => $model) {
            $total = $model::count();
            $completed = $model::where('status', 'completed')->count();
            $inProgress = $model::where('status', 'in_progress')->count();
            $pending = $model::where('status', 'pending')->count();

            $analytics[] = [
                'work_type' => $type,
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'pending' => $pending,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'average_completion_time' => $this->getAverageCompletionTime($type)
            ];
        }

        return $analytics;
    }

    /**
     * Get social media analytics
     */
    private function getSocialMediaAnalytics()
    {
        $posts = SocialMediaPost::all();
        
        return [
            'total_posts' => $posts->count(),
            'platform_breakdown' => [
                'youtube' => $posts->where('platform', 'youtube')->count(),
                'facebook' => $posts->where('platform', 'facebook')->count(),
                'instagram' => $posts->where('platform', 'instagram')->count(),
                'whatsapp' => $posts->where('platform', 'whatsapp')->count()
            ],
            'status_breakdown' => [
                'published' => $posts->where('status', 'published')->count(),
                'scheduled' => $posts->where('status', 'scheduled')->count(),
                'draft' => $posts->where('status', 'draft')->count()
            ],
            'engagement_metrics' => $this->getSocialMediaEngagementMetrics(),
            'content_performance' => $this->getSocialMediaContentPerformance()
        ];
    }

    /**
     * Get team analytics
     */
    private function getTeamAnalytics()
    {
        $teams = [
            'Music Team' => ['Music Arranger', 'Sound Engineer'],
            'Creative Team' => ['Creative', 'Graphic Design'],
            'Production Team' => ['Production', 'Art & Set Properti'],
            'Post Production' => ['Editor', 'Editor Promotion'],
            'Quality Team' => ['Quality Control'],
            'Distribution Team' => ['Broadcasting', 'Promotion']
        ];

        $teamAnalytics = [];
        foreach ($teams as $teamName => $roles) {
            $teamUsers = User::whereIn('role', $roles)->get();
            $teamWork = $this->getTeamWorkCount($roles);
            $teamCompleted = $this->getTeamCompletedCount($roles);

            $teamAnalytics[] = [
                'team_name' => $teamName,
                'member_count' => $teamUsers->count(),
                'total_work' => $teamWork,
                'completed_work' => $teamCompleted,
                'completion_rate' => $teamWork > 0 ? round(($teamCompleted / $teamWork) * 100, 2) : 0,
                'average_performance' => $this->getTeamAveragePerformance($roles),
                'collaboration_score' => $this->getTeamCollaborationScore($roles)
            ];
        }

        return $teamAnalytics;
    }

    /**
     * Get trends
     */
    private function getTrends()
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'programs_created' => Program::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count(),
                'episodes_completed' => Episode::whereYear('completed_at', $date->year)
                    ->whereMonth('completed_at', $date->month)->count(),
                'work_completed' => $this->getWorkCompletedInMonth($date->year, $date->month),
                'social_media_posts' => SocialMediaPost::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count()
            ];
        }

        return $months;
    }

    /**
     * Get predictions
     */
    private function getPredictions()
    {
        return [
            'next_month_programs' => $this->predictNextMonthPrograms(),
            'workload_prediction' => $this->predictWorkload(),
            'deadline_risk' => $this->predictDeadlineRisk(),
            'resource_requirements' => $this->predictResourceRequirements()
        ];
    }

    // Helper methods for data calculation
    private function getTotalWorkCount()
    {
        return MusicArrangement::count() + 
               CreativeWork::count() + 
               ProductionWork::count() + 
               BroadcastingWork::count();
    }

    private function getCompletedWorkCount()
    {
        return MusicArrangement::where('status', 'completed')->count() + 
               CreativeWork::where('status', 'completed')->count() + 
               ProductionWork::where('status', 'completed')->count() + 
               BroadcastingWork::where('status', 'completed')->count();
    }

    private function getOverallEfficiency()
    {
        // Mock data for now
        return rand(75, 95);
    }

    private function getOverallQualityScore()
    {
        // Mock data for now
        return rand(80, 95);
    }

    private function getAverageEpisodeDuration($programId = null)
    {
        // Mock data for now
        return rand(30, 120) . ' minutes';
    }

    private function getBudgetUtilization($programId)
    {
        // Mock data for now
        return rand(60, 100) . '%';
    }

    private function getProgramQualityScore($programId)
    {
        // Mock data for now
        return rand(75, 95);
    }

    private function getEpisodeDeadlinePerformance()
    {
        // Mock data for now
        return [
            'on_time' => rand(70, 90),
            'late' => rand(10, 30),
            'average_delay' => rand(0, 3) . ' days'
        ];
    }

    private function getEpisodeQualityDistribution()
    {
        // Mock data for now
        return [
            'excellent' => rand(20, 40),
            'good' => rand(30, 50),
            'average' => rand(15, 30),
            'poor' => rand(5, 15)
        ];
    }

    private function getAverageCompletionTime($workType)
    {
        // Mock data for now
        return rand(1, 7) . ' days';
    }

    private function getSocialMediaEngagementMetrics()
    {
        // Mock data for now
        return [
            'total_engagement' => rand(1000, 10000),
            'average_engagement_rate' => rand(2, 8) . '%',
            'top_performing_platform' => 'YouTube',
            'engagement_trend' => 'increasing'
        ];
    }

    private function getSocialMediaContentPerformance()
    {
        // Mock data for now
        return [
            'most_engaged_content' => 'Episode Highlights',
            'best_posting_time' => 'Evening (7-9 PM)',
            'content_frequency' => 'Daily',
            'audience_growth' => rand(5, 15) . '%'
        ];
    }

    private function getTeamWorkCount($roles)
    {
        return rand(20, 100);
    }

    private function getTeamCompletedCount($roles)
    {
        return rand(15, 80);
    }

    private function getTeamAveragePerformance($roles)
    {
        return rand(75, 95);
    }

    private function getTeamCollaborationScore($roles)
    {
        return rand(70, 95);
    }

    private function getWorkCompletedInMonth($year, $month)
    {
        return rand(5, 25);
    }

    private function predictNextMonthPrograms()
    {
        return rand(2, 8);
    }

    private function predictWorkload()
    {
        return [
            'high' => rand(2, 5),
            'medium' => rand(3, 7),
            'low' => rand(1, 3)
        ];
    }

    private function predictDeadlineRisk()
    {
        return [
            'high_risk' => rand(1, 3),
            'medium_risk' => rand(2, 5),
            'low_risk' => rand(3, 8)
        ];
    }

    private function predictResourceRequirements()
    {
        return [
            'additional_staff' => rand(0, 3),
            'equipment_needs' => rand(1, 5),
            'budget_increase' => rand(5, 20) . '%'
        ];
    }

    // Additional helper methods for specific analytics
    private function getProgramEpisodeBreakdown($programId)
    {
        $episodes = Episode::where('program_id', $programId)->get();
        return [
            'total' => $episodes->count(),
            'completed' => $episodes->where('status', 'completed')->count(),
            'in_progress' => $episodes->where('status', 'in_progress')->count(),
            'pending' => $episodes->where('status', 'pending')->count()
        ];
    }

    private function getProgramWorkBreakdown($programId)
    {
        // Mock data for now
        return [
            'music_arrangements' => rand(5, 20),
            'creative_works' => rand(3, 15),
            'production_works' => rand(2, 10),
            'broadcasting_works' => rand(1, 8)
        ];
    }

    private function getProgramTeamPerformance($programId)
    {
        // Mock data for now
        return [
            'efficiency' => rand(75, 95),
            'collaboration' => rand(70, 90),
            'communication' => rand(80, 95)
        ];
    }

    private function getProgramBudgetAnalysis($programId)
    {
        // Mock data for now
        return [
            'allocated' => rand(100000, 500000),
            'spent' => rand(60000, 400000),
            'remaining' => rand(20000, 100000),
            'utilization_rate' => rand(60, 100) . '%'
        ];
    }

    private function getProgramTimelineAnalysis($programId)
    {
        // Mock data for now
        return [
            'on_schedule' => rand(70, 90) . '%',
            'ahead_of_schedule' => rand(5, 20) . '%',
            'behind_schedule' => rand(5, 25) . '%'
        ];
    }

    private function getProgramQualityMetrics($programId)
    {
        // Mock data for now
        return [
            'overall_score' => rand(75, 95),
            'qc_pass_rate' => rand(80, 100) . '%',
            'revision_rate' => rand(5, 20) . '%'
        ];
    }

    private function getDateRange($period, $startDate, $endDate)
    {
        if ($startDate && $endDate) {
            return [
                'start' => $startDate,
                'end' => $endDate
            ];
        }

        $end = Carbon::now();
        switch ($period) {
            case 'day':
                $start = $end->copy()->subDay();
                break;
            case 'week':
                $start = $end->copy()->subWeek();
                break;
            case 'month':
                $start = $end->copy()->subMonth();
                break;
            case 'year':
                $start = $end->copy()->subYear();
                break;
            default:
                $start = $end->copy()->subMonth();
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
    }

    private function getWorkCompletionTrend($period, $startDate, $endDate)
    {
        // Mock data for now
        return [
            'trend' => 'increasing',
            'growth_rate' => rand(5, 20) . '%',
            'peak_performance' => 'Week 3'
        ];
    }

    private function getDeadlinePerformanceTrend($period, $startDate, $endDate)
    {
        // Mock data for now
        return [
            'compliance_rate' => rand(70, 95) . '%',
            'trend' => 'stable',
            'improvement' => rand(5, 15) . '%'
        ];
    }

    private function getTeamActivityTrend($period, $startDate, $endDate)
    {
        // Mock data for now
        return [
            'most_active_team' => 'Production Team',
            'activity_level' => 'high',
            'collaboration_score' => rand(75, 95)
        ];
    }

    private function getQualityTrend($period, $startDate, $endDate)
    {
        // Mock data for now
        return [
            'quality_score' => rand(75, 95),
            'trend' => 'improving',
            'qc_pass_rate' => rand(80, 100) . '%'
        ];
    }

    private function getSocialMediaPlatformBreakdown()
    {
        // Mock data for now
        return [
            'youtube' => rand(20, 40),
            'facebook' => rand(15, 35),
            'instagram' => rand(10, 25),
            'whatsapp' => rand(5, 15)
        ];
    }

    private function getSocialMediaPostingSchedule()
    {
        // Mock data for now
        return [
            'optimal_times' => ['7:00 PM', '8:00 PM', '9:00 PM'],
            'frequency' => 'Daily',
            'consistency_score' => rand(70, 95)
        ];
    }

    private function getSocialMediaAudienceInsights()
    {
        // Mock data for now
        return [
            'total_reach' => rand(10000, 100000),
            'engagement_rate' => rand(2, 8) . '%',
            'audience_growth' => rand(5, 20) . '%',
            'top_demographics' => '18-34 years old'
        ];
    }
}