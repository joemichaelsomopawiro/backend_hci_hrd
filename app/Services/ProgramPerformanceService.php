<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Episode;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProgramPerformanceService
{
    /**
     * Update views untuk episode
     */
    public function updateEpisodeViews(int $episodeId, int $views): void
    {
        $episode = Episode::findOrFail($episodeId);
        
        $oldViews = $episode->actual_views;
        $episode->update([
            'actual_views' => $views,
            'views_last_updated' => now()
        ]);
        
        // Calculate growth rate jika ada views sebelumnya
        if ($oldViews > 0) {
            $growthRate = (($views - $oldViews) / $oldViews) * 100;
            $episode->update(['views_growth_rate' => $growthRate]);
        }
        
        // Update program statistics
        $this->updateProgramStatistics($episode->program_id);
    }
    
    /**
     * Update statistik program berdasarkan episode views
     */
    public function updateProgramStatistics(int $programId): void
    {
        $program = Program::findOrFail($programId);
        
        // Calculate total dan average views
        $stats = $program->episodes()
            ->selectRaw('SUM(actual_views) as total, AVG(actual_views) as average, COUNT(*) as count')
            ->where('status', 'aired')
            ->first();
        
        $program->update([
            'total_actual_views' => $stats->total ?? 0,
            'average_views_per_episode' => $stats->average ?? 0,
            'last_performance_check' => now()
        ]);
        
        // Evaluate performance
        $this->evaluateProgramPerformance($program);
    }
    
    /**
     * Evaluasi performa program
     */
    public function evaluateProgramPerformance(Program $program): void
    {
        $airedEpisodes = $program->episodes()->where('status', 'aired')->count();
        
        // Jangan evaluasi jika belum cukup episode
        if ($airedEpisodes < $program->min_episodes_before_evaluation) {
            $program->update(['performance_status' => 'pending']);
            return;
        }
        
        $averageViews = $program->average_views_per_episode;
        $targetViews = $program->target_views_per_episode;
        
        if ($targetViews <= 0) {
            $program->update(['performance_status' => 'pending']);
            return;
        }
        
        // Calculate percentage achievement
        $achievement = ($averageViews / $targetViews) * 100;
        
        $oldStatus = $program->performance_status;
        
        if ($achievement >= 80) {
            $newStatus = 'good';
        } elseif ($achievement >= 50) {
            $newStatus = 'warning';
        } else {
            $newStatus = 'poor';
        }
        
        $program->update(['performance_status' => $newStatus]);
        
        // Notifikasi jika status berubah
        if ($oldStatus !== $newStatus) {
            $this->sendPerformanceNotification($program, $newStatus, $achievement);
        }
        
        // Auto-close jika performa buruk
        if ($newStatus === 'poor' && $program->auto_close_enabled) {
            $this->considerAutoClose($program, $achievement);
        }
    }
    
    /**
     * Pertimbangkan auto-close program
     */
    private function considerAutoClose(Program $program, float $achievement): void
    {
        $airedEpisodes = $program->episodes()->where('status', 'aired')->count();
        
        // Auto-close jika:
        // 1. Sudah 8+ episode aired
        // 2. Achievement < 30%
        // 3. Status sudah 'active' atau 'in_production'
        if ($airedEpisodes >= 8 && $achievement < 30 && in_array($program->status, ['active', 'in_production'])) {
            
            $program->update([
                'status' => 'cancelled',
                'rejection_notes' => "Program ditutup otomatis karena performa rendah. Achievement: " . round($achievement, 2) . "% dari target."
            ]);
            
            // Notifikasi ke Manager Program
            Notification::create([
                'user_id' => $program->manager_program_id,
                'type' => 'program_auto_closed',
                'title' => 'Program Ditutup Otomatis',
                'message' => "Program '{$program->name}' ditutup otomatis karena performa rendah (Achievement: " . round($achievement, 2) . "%)",
                'data' => [
                    'program_id' => $program->id,
                    'achievement' => round($achievement, 2),
                    'aired_episodes' => $airedEpisodes,
                    'average_views' => $program->average_views_per_episode,
                    'target_views' => $program->target_views_per_episode
                ]
            ]);
        }
    }
    
    /**
     * Kirim notifikasi perubahan performa
     */
    private function sendPerformanceNotification(Program $program, string $status, float $achievement): void
    {
        $messages = [
            'good' => "Program '{$program->name}' memiliki performa BAIK (Achievement: " . round($achievement, 2) . "%)",
            'warning' => "Program '{$program->name}' memiliki performa WARNING (Achievement: " . round($achievement, 2) . "%). Perlu peningkatan!",
            'poor' => "Program '{$program->name}' memiliki performa BURUK (Achievement: " . round($achievement, 2) . "%). Segera lakukan perbaikan!"
        ];
        
        Notification::create([
            'user_id' => $program->manager_program_id,
            'type' => 'performance_update',
            'title' => 'Update Performa Program',
            'message' => $messages[$status] ?? 'Update performa program',
            'data' => [
                'program_id' => $program->id,
                'status' => $status,
                'achievement' => round($achievement, 2),
                'average_views' => $program->average_views_per_episode,
                'target_views' => $program->target_views_per_episode
            ]
        ]);
    }
    
    /**
     * Get weekly performance report untuk program
     */
    public function getWeeklyPerformanceReport(int $programId): array
    {
        $program = Program::with('episodes')->findOrFail($programId);
        
        // Get episodes aired dalam 4 minggu terakhir
        $recentEpisodes = $program->episodes()
            ->where('status', 'aired')
            ->where('air_date', '>=', now()->subWeeks(4))
            ->orderBy('air_date', 'desc')
            ->get();
        
        $targetViews = $program->target_views_per_episode ?? 0;
        $weeklyData = [];
        
        foreach ($recentEpisodes as $episode) {
            $week = $episode->air_date->format('Y-W');
            $weekStart = Carbon::parse($episode->air_date)->startOfWeek()->format('Y-m-d');
            $weekEnd = Carbon::parse($episode->air_date)->endOfWeek()->format('Y-m-d');
            
            if (!isset($weeklyData[$week])) {
                $weeklyData[$week] = [
                    'week' => $week,
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'episodes' => [],
                    'total_views' => 0,
                    'target_total_views' => 0,
                    'average_views' => 0,
                    'achievement_percentage' => 0
                ];
            }
            
            $episodeAchievement = $targetViews > 0 
                ? ($episode->actual_views / $targetViews) * 100 
                : 0;
            
            $weeklyData[$week]['episodes'][] = [
                'episode_number' => $episode->episode_number,
                'title' => $episode->title,
                'actual_views' => $episode->actual_views ?? 0,
                'target_views' => $targetViews,
                'achievement_percentage' => round($episodeAchievement, 2),
                'air_date' => $episode->air_date->format('Y-m-d')
            ];
            
            $weeklyData[$week]['total_views'] += $episode->actual_views ?? 0;
            $weeklyData[$week]['target_total_views'] += $targetViews;
        }
        
        // Calculate averages and achievement
        foreach ($weeklyData as $week => &$data) {
            $episodeCount = count($data['episodes']);
            $data['average_views'] = $episodeCount > 0 
                ? $data['total_views'] / $episodeCount 
                : 0;
            $data['achievement_percentage'] = $data['target_total_views'] > 0 
                ? ($data['total_views'] / $data['target_total_views']) * 100 
                : 0;
        }
        
        return [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'target_views_per_episode' => $program->target_views_per_episode,
                'total_actual_views' => $program->total_actual_views,
                'average_views_per_episode' => $program->average_views_per_episode,
                'performance_status' => $program->performance_status
            ],
            'weekly_data' => array_values($weeklyData),
            'total_aired_episodes' => $recentEpisodes->count(),
            'overall_achievement_percentage' => $program->target_views_per_episode > 0 
                ? ($program->average_views_per_episode / $program->target_views_per_episode) * 100 
                : 0
        ];
    }
    
    /**
     * Check semua program dan evaluasi performa
     * (Dijalankan via cron job harian)
     */
    public function evaluateAllActivePrograms(): array
    {
        $programs = Program::whereIn('status', ['active', 'in_production'])
            ->get();
        
        $results = [
            'total_evaluated' => 0,
            'good' => 0,
            'warning' => 0,
            'poor' => 0,
            'auto_closed' => 0
        ];
        
        foreach ($programs as $program) {
            $oldStatus = $program->performance_status;
            $this->updateProgramStatistics($program->id);
            $program->refresh();
            
            $results['total_evaluated']++;
            $results[$program->performance_status]++;
            
            if ($program->status === 'cancelled' && $oldStatus !== 'cancelled') {
                $results['auto_closed']++;
            }
        }
        
        return $results;
    }
}









