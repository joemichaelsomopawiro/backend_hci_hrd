<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeadlineService;
use App\Services\NotificationService;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SystemController extends Controller
{
    protected $deadlineService;
    protected $notificationService;
    protected $fileUploadService;

    public function __construct(
        DeadlineService $deadlineService,
        NotificationService $notificationService,
        FileUploadService $fileUploadService
    ) {
        $this->deadlineService = $deadlineService;
        $this->notificationService = $notificationService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Health check
     */
    public function healthCheck(): JsonResponse
    {
        try {
            // Check database connection
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }
        
        // Check cache
        try {
            Cache::put('health_check', 'ok', 60);
            $cacheStatus = Cache::get('health_check') === 'ok' ? 'working' : 'not_working';
        } catch (\Exception $e) {
            $cacheStatus = 'not_working';
        }
        
        // Check storage
        try {
            $storageStatus = is_writable(storage_path()) ? 'writable' : 'not_writable';
        } catch (\Exception $e) {
            $storageStatus = 'not_writable';
        }
        
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'database' => $dbStatus,
            'cache' => $cacheStatus,
            'storage' => $storageStatus,
            'version' => app()->version(),
            'environment' => app()->environment()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $health,
            'message' => 'Health check completed successfully'
        ]);
    }

    /**
     * Get system status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = [
                'system' => [
                    'name' => 'Live TV Program Management System',
                    'version' => '1.0.0',
                    'environment' => app()->environment(),
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale')
                ],
                'database' => [
                    'driver' => config('database.default'),
                    'host' => config('database.connections.' . config('database.default') . '.host'),
                    'database' => config('database.connections.' . config('database.default') . '.database')
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'prefix' => config('cache.prefix')
                ],
                'storage' => [
                    'driver' => config('filesystems.default'),
                    'disk' => config('filesystems.default')
                ],
                'features' => [
                    'notifications' => true,
                    'file_upload' => true,
                    'workflow_management' => true,
                    'deadline_tracking' => true,
                    'analytics' => true,
                    'role_based_access' => true
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => 'System status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check overdue deadlines
     */
    public function checkOverdueDeadlines(): JsonResponse
    {
        try {
            $updated = $this->deadlineService->checkOverdueDeadlines();
            
            return response()->json([
                'success' => true,
                'data' => $updated,
                'message' => 'Overdue deadlines checked and updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check overdue deadlines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send deadline reminders
     */
    public function sendDeadlineReminders(): JsonResponse
    {
        try {
            $sent = $this->deadlineService->sendDeadlineReminders();
            
            return response()->json([
                'success' => true,
                'data' => $sent,
                'message' => 'Deadline reminders sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send deadline reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup notifications
     */
    public function cleanupNotifications(): JsonResponse
    {
        try {
            $deleted = $this->notificationService->cleanupOldNotifications(30);
            
            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => $deleted],
                'message' => "{$deleted} old notifications cleaned up successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup files
     */
    public function cleanupFiles(): JsonResponse
    {
        try {
            $deleted = $this->fileUploadService->cleanupOrphanedFiles();
            
            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => $deleted],
                'message' => "{$deleted} orphaned files cleaned up successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run system maintenance
     */
    public function runMaintenance(): JsonResponse
    {
        try {
            $results = [];
            
            // Check overdue deadlines
            $overdueResults = $this->deadlineService->checkOverdueDeadlines();
            $results['overdue_deadlines'] = count($overdueResults);
            
            // Send deadline reminders
            $reminderResults = $this->deadlineService->sendDeadlineReminders();
            $results['deadline_reminders'] = count($reminderResults);
            
            // Cleanup notifications
            $notificationCleanup = $this->notificationService->cleanupOldNotifications(30);
            $results['notifications_cleaned'] = $notificationCleanup;
            
            // Cleanup files
            $fileCleanup = $this->fileUploadService->cleanupOrphanedFiles();
            $results['files_cleaned'] = $fileCleanup;
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'System maintenance completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to run system maintenance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system statistics
     */
    public function getSystemStatistics(): JsonResponse
    {
        try {
            $statistics = [
                'programs' => \App\Models\Program::count(),
                'episodes' => \App\Models\Episode::count(),
                'deadlines' => \App\Models\Deadline::count(),
                'notifications' => \App\Models\Notification::count(),
                'media_files' => \App\Models\MediaFile::count(),
                'users' => \App\Models\User::count(),
                'production_teams' => \App\Models\ProductionTeam::count(),
                'workflow_states' => \App\Models\WorkflowState::count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'System statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system logs
     */
    public function getSystemLogs(): JsonResponse
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            
            if (!file_exists($logFile)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No log file found'
                ]);
            }
            
            $logs = file_get_contents($logFile);
            $logLines = explode("\n", $logs);
            $recentLogs = array_slice($logLines, -100); // Get last 100 lines
            
            return response()->json([
                'success' => true,
                'data' => $recentLogs,
                'message' => 'System logs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear system cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');
            
            return response()->json([
                'success' => true,
                'message' => 'System cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear system cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimize system
     */
    public function optimize(): JsonResponse
    {
        try {
            \Artisan::call('config:cache');
            \Artisan::call('route:cache');
            \Artisan::call('view:cache');
            
            return response()->json([
                'success' => true,
                'message' => 'System optimized successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize system',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}














