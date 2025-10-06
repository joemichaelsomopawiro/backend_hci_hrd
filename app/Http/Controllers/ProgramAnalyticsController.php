<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\ProgramAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProgramAnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(ProgramAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get program analytics
     */
    public function getProgramAnalytics(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);

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
    public function getPerformanceMetrics(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            $metrics = $analytics['performance_metrics'];

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
    public function getKPISummary(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            $kpi = $analytics['kpi_summary'];

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
    public function getTeamPerformance(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            $teamPerformance = $analytics['team_performance'];

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
    public function getContentAnalytics(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            $contentAnalytics = $analytics['content_analytics'];

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
     * Get trends data
     */
    public function getTrends(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            $trends = $analytics['trends'];

            return response()->json([
                'success' => true,
                'data' => $trends,
                'message' => 'Trends data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving trends data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get views tracking
     */
    public function getViewsTracking(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            $viewsTracking = $analytics['performance_metrics']['views_tracking'];

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
     * Get dashboard analytics for all programs
     */
    public function getDashboardAnalytics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $period = $request->get('period', '30d');
            
            // Get programs accessible by user
            $programs = Program::query();
            
            // Filter by user role
            if (in_array($user->role, ['Manager', 'Program Manager'])) {
                // Managers can see all programs
            } elseif ($user->role === 'Producer') {
                $programs->where('producer_id', $user->id);
            } else {
                // Other roles can see programs they're assigned to
                $programs->whereHas('teams.users', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                });
            }
            
            $programs = $programs->get();
            
            $dashboardData = [
                'total_programs' => $programs->count(),
                'active_programs' => $programs->where('status', 'active')->count(),
                'completed_programs' => $programs->where('status', 'completed')->count(),
                'total_episodes' => $programs->sum(function($program) {
                    return $program->episodes()->count();
                }),
                'total_schedules' => $programs->sum(function($program) {
                    return $program->schedules()->count();
                }),
                'overdue_schedules' => $programs->sum(function($program) {
                    return $program->schedules()
                        ->where('deadline', '<', now())
                        ->where('status', '!=', 'completed')
                        ->count();
                }),
                'programs_performance' => $programs->map(function($program) use ($period) {
                    $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
                    return [
                        'program_id' => $program->id,
                        'program_name' => $program->name,
                        'status' => $program->status,
                        'completion_rate' => $analytics['performance_metrics']['completion_rate'],
                        'on_time_rate' => $analytics['performance_metrics']['on_time_rate'],
                        'total_views' => $analytics['performance_metrics']['views_tracking']['total_views']
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
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
     * Get comparative analytics
     */
    public function getComparativeAnalytics(Request $request): JsonResponse
    {
        try {
            $programIds = $request->get('program_ids', []);
            $period = $request->get('period', '30d');
            
            if (empty($programIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program IDs are required'
                ], 400);
            }
            
            $comparativeData = [];
            
            foreach ($programIds as $programId) {
                $program = Program::find($programId);
                if ($program) {
                    $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
                    $comparativeData[] = [
                        'program_id' => $program->id,
                        'program_name' => $program->name,
                        'performance_metrics' => $analytics['performance_metrics'],
                        'kpi_summary' => $analytics['kpi_summary']
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $comparativeData,
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
     * Export analytics data
     */
    public function exportAnalytics(Request $request, string $id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            $period = $request->get('period', '30d');
            $format = $request->get('format', 'csv');
            
            $analytics = $this->analyticsService->getProgramAnalytics($program->id, $period);
            
            // Create export data
            $exportData = [
                'program_info' => $analytics['program_info'],
                'performance_metrics' => $analytics['performance_metrics'],
                'kpi_summary' => $analytics['kpi_summary'],
                'team_performance' => $analytics['team_performance'],
                'content_analytics' => $analytics['content_analytics'],
                'trends' => $analytics['trends']
            ];
            
            $filename = 'analytics_' . $program->id . '_' . time() . '.' . $format;
            $filepath = storage_path('app/exports/' . $filename);
            
            // Ensure directory exists
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }
            
            // Create CSV file
            $csvContent = $this->arrayToCSV($exportData);
            file_put_contents($filepath, $csvContent);

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'download_url' => url('storage/exports/' . $filename)
                ],
                'message' => 'Analytics data exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting analytics data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to convert array to CSV
     */
    private function arrayToCSV(array $data): string
    {
        $output = '';
        
        foreach ($data as $section => $rows) {
            if (is_string($section)) {
                $output .= "\n=== " . strtoupper($section) . " ===\n";
            }
            
            if (isset($rows[0]) && is_array($rows[0])) {
                // Multiple rows
                $headers = array_keys($rows[0]);
                $output .= implode(',', $headers) . "\n";
                
                foreach ($rows as $row) {
                    $output .= implode(',', array_map([$this, 'escapeCSV'], $row)) . "\n";
                }
            } else {
                // Single row
                foreach ($rows as $key => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $output .= $key . ',' . $this->escapeCSV($value) . "\n";
                }
            }
        }
        
        return $output;
    }

    /**
     * Helper method to escape CSV values
     */
    private function escapeCSV($value): string
    {
        $value = str_replace('"', '""', $value);
        return '"' . $value . '"';
    }
}
