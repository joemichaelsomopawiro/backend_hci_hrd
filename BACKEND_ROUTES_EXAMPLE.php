<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProgramRegulerController;
use App\Http\Controllers\API\TeamController;
use App\Http\Controllers\API\ScheduleController;
use App\Http\Controllers\API\EpisodeController;
use App\Http\Controllers\API\WorkflowController;
use App\Http\Controllers\API\FileManagementController;
use App\Http\Controllers\API\ArtSetPropertiController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ApprovalController;
use App\Http\Controllers\API\AnalyticsController;

Route::prefix('programs')->group(function () {
    Route::get('/', [ProgramRegulerController::class, 'index']);
    Route::post('/', [ProgramRegulerController::class, 'store']);
    Route::get('/{id}', [ProgramRegulerController::class, 'show']);
    Route::put('/{id}', [ProgramRegulerController::class, 'update']);
    Route::delete('/{id}', [ProgramRegulerController::class, 'destroy']);
    Route::get('/{id}/dashboard', [ProgramRegulerController::class, 'getDashboard']);
    Route::get('/{id}/statistics', [ProgramRegulerController::class, 'getStatistics']);
    Route::post('/{id}/assign-teams', [ProgramRegulerController::class, 'assignTeams']);
    Route::post('/{id}/remove-teams', [ProgramRegulerController::class, 'removeTeams']);
});

Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index']);
    Route::post('/', [TeamController::class, 'store']);
    Route::get('/by-role', [TeamController::class, 'getByRole']);
    Route::get('/{id}', [TeamController::class, 'show']);
    Route::put('/{id}', [TeamController::class, 'update']);
    Route::delete('/{id}', [TeamController::class, 'destroy']);
    Route::post('/{id}/members', [TeamController::class, 'addMember']);
    Route::delete('/{id}/members', [TeamController::class, 'removeMember']);
    Route::put('/{id}/members', [TeamController::class, 'updateMemberRole']);
    Route::get('/user-teams', [TeamController::class, 'getUserTeams'])->middleware('auth:sanctum');
});

Route::prefix('schedules')->group(function () {
    Route::get('/', [ScheduleController::class, 'index']);
    Route::post('/', [ScheduleController::class, 'store']);
    Route::get('/upcoming', [ScheduleController::class, 'getUpcoming']);
    Route::get('/today', [ScheduleController::class, 'getToday']);
    Route::get('/overdue', [ScheduleController::class, 'getOverdue']);
    Route::get('/{id}', [ScheduleController::class, 'show']);
    Route::put('/{id}', [ScheduleController::class, 'update']);
    Route::delete('/{id}', [ScheduleController::class, 'destroy']);
    Route::put('/{id}/update-status', [ScheduleController::class, 'updateStatus']);
});

Route::prefix('episodes')->group(function () {
    Route::get('/', [EpisodeController::class, 'index']);
    Route::post('/', [EpisodeController::class, 'store']);
    Route::get('/{id}', [EpisodeController::class, 'show']);
    Route::put('/{id}', [EpisodeController::class, 'update']);
    Route::delete('/{id}', [EpisodeController::class, 'destroy']);
    Route::put('/{id}/update-status', [EpisodeController::class, 'updateStatus']);
});

Route::prefix('workflow')->group(function () {
    Route::get('/states', [WorkflowController::class, 'getStates']);
    Route::get('/steps', [WorkflowController::class, 'getSteps']);
    Route::get('/dashboard', [WorkflowController::class, 'getDashboard'])->middleware('auth:sanctum');
    Route::get('/program/{id}/transitions', [WorkflowController::class, 'getAvailableTransitions']);
    Route::post('/program/{id}/execute', [WorkflowController::class, 'executeTransition'])->middleware('auth:sanctum');
    Route::get('/program/{id}/status', [WorkflowController::class, 'getWorkflowStatus']);
    Route::get('/episode/{id}/transitions', [WorkflowController::class, 'getEpisodeTransitions']);
    Route::post('/episode/{id}/execute', [WorkflowController::class, 'executeEpisodeTransition'])->middleware('auth:sanctum');
});

Route::prefix('files')->group(function () {
    Route::post('/upload', [FileManagementController::class, 'upload']);
    Route::post('/bulk-upload', [FileManagementController::class, 'bulkUpload']);
    Route::get('/program/{id}', [FileManagementController::class, 'getProgramFiles']);
    Route::get('/episode/{id}', [FileManagementController::class, 'getEpisodeFiles']);
    Route::get('/{entityType}/{entityId}', [FileManagementController::class, 'getFiles']);
    Route::get('/{id}/download', [FileManagementController::class, 'download']);
    Route::put('/{id}', [FileManagementController::class, 'update']);
    Route::delete('/{id}', [FileManagementController::class, 'destroy']);
    Route::get('/statistics', [FileManagementController::class, 'getStatistics']);
});

Route::prefix('art-set-properti')->group(function () {
    Route::get('/', [ArtSetPropertiController::class, 'index']);
    Route::post('/', [ArtSetPropertiController::class, 'store']);
    Route::get('/{id}', [ArtSetPropertiController::class, 'show']);
    Route::put('/{id}', [ArtSetPropertiController::class, 'update']);
    Route::delete('/{id}', [ArtSetPropertiController::class, 'destroy']);
    Route::post('/{id}/approve', [ArtSetPropertiController::class, 'approve']);
    Route::post('/{id}/reject', [ArtSetPropertiController::class, 'reject']);
    Route::post('/{id}/assign', [ArtSetPropertiController::class, 'assign']);
    Route::post('/{id}/return', [ArtSetPropertiController::class, 'return']);
    Route::get('/inventory/summary', [ArtSetPropertiController::class, 'getInventorySummary']);
    Route::get('/maintenance-alerts', [ArtSetPropertiController::class, 'getMaintenanceAlerts']);
});

Route::middleware('auth:sanctum')->group(function () {
    
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/statistics', [NotificationController::class, 'getStatistics']);
        Route::get('/workflow', [NotificationController::class, 'getWorkflowNotifications']);
        Route::post('/test', [NotificationController::class, 'sendTestNotification']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
    });
    
    Route::prefix('approvals')->group(function () {
        Route::post('/programs/{id}/submit', [ApprovalController::class, 'submitProgram']);
        Route::post('/programs/{id}/approve', [ApprovalController::class, 'approveProgram']);
        Route::post('/programs/{id}/reject', [ApprovalController::class, 'rejectProgram']);
        Route::post('/rundowns/{id}/approve', [ApprovalController::class, 'approveRundown']);
        Route::post('/schedules/{id}/approve', [ApprovalController::class, 'approveSchedule']);
        Route::get('/pending', [ApprovalController::class, 'getPendingApprovals']);
        Route::get('/history', [ApprovalController::class, 'getApprovalHistory']);
    });
    
    Route::prefix('analytics')->group(function () {
        Route::get('/programs/{id}', [AnalyticsController::class, 'getProgramAnalytics']);
        Route::get('/programs/{id}/performance', [AnalyticsController::class, 'getPerformanceMetrics']);
        Route::get('/programs/{id}/kpi', [AnalyticsController::class, 'getKPISummary']);
        Route::get('/programs/{id}/content', [AnalyticsController::class, 'getContentAnalytics']);
        Route::get('/programs/{id}/views', [AnalyticsController::class, 'getViewsTracking']);
        Route::get('/teams/{id}/performance', [AnalyticsController::class, 'getTeamPerformance']);
        Route::get('/trends', [AnalyticsController::class, 'getTrends']);
        Route::get('/dashboard', [AnalyticsController::class, 'getDashboardAnalytics']);
        Route::get('/comparative', [AnalyticsController::class, 'getComparativeAnalytics']);
        Route::get('/export', [AnalyticsController::class, 'exportAnalytics']);
    });
    
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
    });
});

Route::prefix('program-notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
});

Route::get('/media-files', [FileManagementController::class, 'index']);
Route::get('/media-files/{id}', [FileManagementController::class, 'show']);
Route::post('/media-files/upload', [FileManagementController::class, 'upload']);
Route::put('/media-files/{id}', [FileManagementController::class, 'update']);
Route::delete('/media-files/{id}', [FileManagementController::class, 'destroy']);
Route::get('/media-files/by-type/{type}', [FileManagementController::class, 'getByType']);
Route::get('/media-files/by-program/{programId}', [FileManagementController::class, 'getByProgram']);
Route::get('/media-files/by-episode/{episodeId}', [FileManagementController::class, 'getByEpisode']);

Route::get('/production-equipment', [ArtSetPropertiController::class, 'getProductionEquipment']);
Route::get('/production-equipment/{id}', [ArtSetPropertiController::class, 'show']);
Route::post('/production-equipment', [ArtSetPropertiController::class, 'store']);
Route::put('/production-equipment/{id}', [ArtSetPropertiController::class, 'update']);
Route::delete('/production-equipment/{id}', [ArtSetPropertiController::class, 'destroy']);
Route::put('/production-equipment/{id}/assign', [ArtSetPropertiController::class, 'assignEquipment']);
Route::put('/production-equipment/{id}/unassign', [ArtSetPropertiController::class, 'unassignEquipment']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/manager/dashboard', [ProgramRegulerController::class, 'getManagerDashboard']);
    Route::get('/producer/dashboard', [ProgramRegulerController::class, 'getProducerDashboard']);
    Route::get('/creative/dashboard', [ProgramRegulerController::class, 'getCreativeDashboard']);
    Route::get('/promotion/dashboard', [ProgramRegulerController::class, 'getPromotionDashboard']);
    Route::get('/design/dashboard', [ProgramRegulerController::class, 'getDesignDashboard']);
    Route::get('/production/dashboard', [ProgramRegulerController::class, 'getProductionDashboard']);
    Route::get('/editor/dashboard', [ProgramRegulerController::class, 'getEditorDashboard']);
});

