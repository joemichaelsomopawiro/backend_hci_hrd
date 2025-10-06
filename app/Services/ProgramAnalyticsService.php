<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\MediaFile;
use App\Models\ProgramNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProgramAnalyticsService
{
    /**
     * Get comprehensive program analytics
     */
    public function getProgramAnalytics(int $programId, ?string $period = '30d'): array
    {
        $program = Program::findOrFail($programId);
        $dateRange = $this->getDateRange($period);
        
        return [
            'program_info' => $program,
            'performance_metrics' => $this->getPerformanceMetrics($program, $dateRange),
            'content_analytics' => $this->getContentAnalytics($program, $dateRange),
            'team_performance' => $this->getTeamPerformance($program, $dateRange),
            'schedule_analytics' => $this->getScheduleAnalytics($program, $dateRange),
            'kpi_summary' => $this->getKPISummary($program, $dateRange),
            'trends' => $this->getTrends($program, $dateRange)
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(Program $program, array $dateRange): array
    {
        $episodes = $program->episodes()
            ->whereBetween('air_date', $dateRange)
            ->get();
            
        $totalEpisodes = $episodes->count();
        $completedEpisodes = $episodes->where('status', 'aired')->count();
        $onTimeEpisodes = $episodes->where('status', 'aired')
            ->filter(function($episode) {
                return $episode->air_date <= $episode->production_deadline;
            })->count();
            
        return [
            'total_episodes' => $totalEpisodes,
            'completed_episodes' => $completedEpisodes,
            'on_time_episodes' => $onTimeEpisodes,
            'completion_rate' => $totalEpisodes > 0 ? round(($completedEpisodes / $totalEpisodes) * 100, 2) : 0,
            'on_time_rate' => $totalEpisodes > 0 ? round(($onTimeEpisodes / $totalEpisodes) * 100, 2) : 0,
            'average_production_time' => $this->getAverageProductionTime($episodes),
            'views_tracking' => $this->getViewsTracking($program, $dateRange)
        ];
    }

    /**
     * Get content analytics
     */
    private function getContentAnalytics(Program $program, array $dateRange): array
    {
        $mediaFiles = $program->mediaFiles()
            ->whereBetween('created_at', $dateRange)
            ->get();
            
        return [
            'total_media_files' => $mediaFiles->count(),
            'media_by_type' => $mediaFiles->groupBy('file_type')->map->count(),
            'total_file_size' => $mediaFiles->sum('file_size'),
            'average_file_size' => $mediaFiles->avg('file_size'),
            'processed_files' => $mediaFiles->where('is_processed', true)->count(),
            'processing_rate' => $mediaFiles->count() > 0 ? 
                round(($mediaFiles->where('is_processed', true)->count() / $mediaFiles->count()) * 100, 2) : 0
        ];
    }

    /**
     * Get team performance
     */
    private function getTeamPerformance(Program $program, array $dateRange): array
    {
        $teams = $program->teams()->with(['schedules' => function($query) use ($dateRange) {
            $query->whereBetween('created_at', $dateRange);
        }])->get();
        
        $teamPerformance = [];
        
        foreach ($teams as $team) {
            $schedules = $team->schedules;
            $completedSchedules = $schedules->where('status', 'completed');
            $onTimeSchedules = $completedSchedules->filter(function($schedule) {
                return $schedule->completed_at <= $schedule->deadline;
            });
            
            $teamPerformance[] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'role' => $team->role,
                'total_schedules' => $schedules->count(),
                'completed_schedules' => $completedSchedules->count(),
                'on_time_schedules' => $onTimeSchedules->count(),
                'completion_rate' => $schedules->count() > 0 ? 
                    round(($completedSchedules->count() / $schedules->count()) * 100, 2) : 0,
                'on_time_rate' => $completedSchedules->count() > 0 ? 
                    round(($onTimeSchedules->count() / $completedSchedules->count()) * 100, 2) : 0,
                'average_completion_time' => $this->getAverageCompletionTime($completedSchedules)
            ];
        }
        
        return $teamPerformance;
    }

    /**
     * Get schedule analytics
     */
    private function getScheduleAnalytics(Program $program, array $dateRange): array
    {
        $schedules = $program->schedules()
            ->whereBetween('created_at', $dateRange)
            ->get();
            
        $completedSchedules = $schedules->where('status', 'completed');
        $overdueSchedules = $schedules->where('deadline', '<', now())
            ->where('status', '!=', 'completed');
            
        return [
            'total_schedules' => $schedules->count(),
            'completed_schedules' => $completedSchedules->count(),
            'overdue_schedules' => $overdueSchedules->count(),
            'completion_rate' => $schedules->count() > 0 ? 
                round(($completedSchedules->count() / $schedules->count()) * 100, 2) : 0,
            'overdue_rate' => $schedules->count() > 0 ? 
                round(($overdueSchedules->count() / $schedules->count()) * 100, 2) : 0,
            'schedules_by_type' => $schedules->groupBy('type')->map->count(),
            'schedules_by_status' => $schedules->groupBy('status')->map->count()
        ];
    }

    /**
     * Get KPI summary
     */
    private function getKPISummary(Program $program, array $dateRange): array
    {
        $episodes = $program->episodes()->whereBetween('air_date', $dateRange)->get();
        $schedules = $program->schedules()->whereBetween('created_at', $dateRange)->get();
        
        return [
            'episode_kpi' => [
                'target_episodes' => $this->getTargetEpisodes($program, $dateRange),
                'actual_episodes' => $episodes->where('status', 'aired')->count(),
                'achievement_rate' => $this->getAchievementRate($program, $episodes, $dateRange)
            ],
            'quality_kpi' => [
                'on_time_delivery' => $this->getOnTimeDeliveryRate($episodes),
                'content_quality_score' => $this->getContentQualityScore($program, $dateRange),
                'team_satisfaction' => $this->getTeamSatisfactionScore($program, $dateRange)
            ],
            'efficiency_kpi' => [
                'resource_utilization' => $this->getResourceUtilization($program, $dateRange),
                'cost_efficiency' => $this->getCostEfficiency($program, $dateRange),
                'time_efficiency' => $this->getTimeEfficiency($schedules)
            ]
        ];
    }

    /**
     * Get trends data
     */
    private function getTrends(Program $program, array $dateRange): array
    {
        $episodes = $program->episodes()
            ->whereBetween('air_date', $dateRange)
            ->orderBy('air_date')
            ->get();
            
        $schedules = $program->schedules()
            ->whereBetween('created_at', $dateRange)
            ->orderBy('created_at')
            ->get();
            
        return [
            'episode_trend' => $this->getEpisodeTrend($episodes),
            'schedule_trend' => $this->getScheduleTrend($schedules),
            'performance_trend' => $this->getPerformanceTrend($episodes),
            'team_productivity_trend' => $this->getTeamProductivityTrend($program, $dateRange)
        ];
    }

    /**
     * Get views tracking (simulated - integrate with actual analytics)
     */
    private function getViewsTracking(Program $program, array $dateRange): array
    {
        // This would integrate with actual analytics platform
        // For now, return simulated data
        $episodes = $program->episodes()
            ->whereBetween('air_date', $dateRange)
            ->where('status', 'aired')
            ->get();
            
        $totalViews = 0;
        $viewsByEpisode = [];
        
        foreach ($episodes as $episode) {
            // Simulate views based on episode number and air date
            $views = rand(1000, 10000) + ($episode->episode_number * 500);
            $totalViews += $views;
            $viewsByEpisode[] = [
                'episode_id' => $episode->id,
                'episode_title' => $episode->title,
                'air_date' => $episode->air_date,
                'views' => $views
            ];
        }
        
        return [
            'total_views' => $totalViews,
            'average_views_per_episode' => count($viewsByEpisode) > 0 ? 
                round($totalViews / count($viewsByEpisode)) : 0,
            'views_by_episode' => $viewsByEpisode,
            'growth_rate' => $this->calculateGrowthRate($viewsByEpisode)
        ];
    }

    /**
     * Helper methods
     */
    private function getDateRange(string $period): array
    {
        switch ($period) {
            case '7d':
                return [now()->subDays(7), now()];
            case '30d':
                return [now()->subDays(30), now()];
            case '90d':
                return [now()->subDays(90), now()];
            case '1y':
                return [now()->subYear(), now()];
            default:
                return [now()->subDays(30), now()];
        }
    }

    private function getAverageProductionTime($episodes): float
    {
        $productionTimes = $episodes->where('status', 'aired')
            ->map(function($episode) {
                if ($episode->production_started_at && $episode->production_completed_at) {
                    return Carbon::parse($episode->production_completed_at)
                        ->diffInHours(Carbon::parse($episode->production_started_at));
                }
                return null;
            })
            ->filter()
            ->values();
            
        return $productionTimes->count() > 0 ? round($productionTimes->avg(), 2) : 0;
    }

    private function getAverageCompletionTime($schedules): float
    {
        $completionTimes = $schedules->map(function($schedule) {
            if ($schedule->start_time && $schedule->completed_at) {
                return Carbon::parse($schedule->completed_at)
                    ->diffInHours(Carbon::parse($schedule->start_time));
            }
            return null;
        })->filter()->values();
        
        return $completionTimes->count() > 0 ? round($completionTimes->avg(), 2) : 0;
    }

    private function getTargetEpisodes(Program $program, array $dateRange): int
    {
        // Calculate target based on program type and date range
        $days = Carbon::parse($dateRange[1])->diffInDays(Carbon::parse($dateRange[0]));
        
        switch ($program->type) {
            case 'daily':
                return $days;
            case 'weekly':
                return ceil($days / 7);
            case 'monthly':
                return ceil($days / 30);
            default:
                return 1;
        }
    }

    private function getAchievementRate(Program $program, $episodes, array $dateRange): float
    {
        $target = $this->getTargetEpisodes($program, $dateRange);
        $actual = $episodes->where('status', 'aired')->count();
        
        return $target > 0 ? round(($actual / $target) * 100, 2) : 0;
    }

    private function getOnTimeDeliveryRate($episodes): float
    {
        $airedEpisodes = $episodes->where('status', 'aired');
        $onTimeEpisodes = $airedEpisodes->filter(function($episode) {
            return $episode->air_date <= $episode->production_deadline;
        });
        
        return $airedEpisodes->count() > 0 ? 
            round(($onTimeEpisodes->count() / $airedEpisodes->count()) * 100, 2) : 0;
    }

    private function getContentQualityScore(Program $program, array $dateRange): float
    {
        // Simulate quality score based on various factors
        $episodes = $program->episodes()->whereBetween('air_date', $dateRange)->get();
        $onTimeRate = $this->getOnTimeDeliveryRate($episodes);
        $completionRate = $episodes->count() > 0 ? 
            round(($episodes->where('status', 'aired')->count() / $episodes->count()) * 100, 2) : 0;
            
        return round(($onTimeRate + $completionRate) / 2, 2);
    }

    private function getTeamSatisfactionScore(Program $program, array $dateRange): float
    {
        // Simulate team satisfaction score
        return round(rand(70, 95), 2);
    }

    private function getResourceUtilization(Program $program, array $dateRange): float
    {
        // Simulate resource utilization
        return round(rand(75, 90), 2);
    }

    private function getCostEfficiency(Program $program, array $dateRange): float
    {
        // Simulate cost efficiency
        return round(rand(80, 95), 2);
    }

    private function getTimeEfficiency($schedules): float
    {
        $completedSchedules = $schedules->where('status', 'completed');
        $onTimeSchedules = $completedSchedules->filter(function($schedule) {
            return $schedule->completed_at <= $schedule->deadline;
        });
        
        return $completedSchedules->count() > 0 ? 
            round(($onTimeSchedules->count() / $completedSchedules->count()) * 100, 2) : 0;
    }

    private function getEpisodeTrend($episodes): array
    {
        return $episodes->groupBy(function($episode) {
            return Carbon::parse($episode->air_date)->format('Y-m');
        })->map->count()->toArray();
    }

    private function getScheduleTrend($schedules): array
    {
        return $schedules->groupBy(function($schedule) {
            return Carbon::parse($schedule->created_at)->format('Y-m-d');
        })->map->count()->toArray();
    }

    private function getPerformanceTrend($episodes): array
    {
        return $episodes->groupBy(function($episode) {
            return Carbon::parse($episode->air_date)->format('Y-m');
        })->map(function($monthEpisodes) {
            $aired = $monthEpisodes->where('status', 'aired')->count();
            $total = $monthEpisodes->count();
            return $total > 0 ? round(($aired / $total) * 100, 2) : 0;
        })->toArray();
    }

    private function getTeamProductivityTrend(Program $program, array $dateRange): array
    {
        // Simulate team productivity trend
        $months = [];
        $startDate = Carbon::parse($dateRange[0]);
        $endDate = Carbon::parse($dateRange[1]);
        
        while ($startDate->lte($endDate)) {
            $months[$startDate->format('Y-m')] = rand(70, 95);
            $startDate->addMonth();
        }
        
        return $months;
    }

    private function calculateGrowthRate(array $viewsByEpisode): float
    {
        if (count($viewsByEpisode) < 2) return 0;
        
        $firstViews = $viewsByEpisode[0]['views'];
        $lastViews = end($viewsByEpisode)['views'];
        
        return $firstViews > 0 ? round((($lastViews - $firstViews) / $firstViews) * 100, 2) : 0;
    }
}
