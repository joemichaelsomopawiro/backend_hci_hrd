<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\EpisodeController;
use App\Http\Controllers\Api\DeadlineController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\ProductionTeamController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\WorkflowStateController;
use App\Http\Controllers\Api\MusicArrangerController;
use App\Http\Controllers\Api\CreativeController;
use App\Http\Controllers\Api\ProducerController;
use App\Http\Controllers\Api\SoundEngineerController;
use App\Http\Controllers\Api\EditorController;
use App\Http\Controllers\Api\DesignGrafisController;
use App\Http\Controllers\Api\EditorPromosiController;
use App\Http\Controllers\Api\FileSharingController;
use App\Http\Controllers\Api\SoundEngineerEditingController;
use App\Http\Controllers\Api\GeneralAffairsController;
use App\Http\Controllers\Api\SocialMediaController;
use App\Http\Controllers\Api\KPIController;
use App\Http\Controllers\Api\AnalyticsController as AnalyticsApiController;
use App\Http\Controllers\Api\QualityControlController;
use App\Http\Controllers\Api\BroadcastingController;
use App\Http\Controllers\Api\ArtSetPropertiController;
use App\Http\Controllers\Api\PromosiController;
use App\Http\Controllers\Api\ProduksiController;
use App\Http\Controllers\Api\ManagerBroadcastingController;
use App\Http\Controllers\Api\ProductionEquipmentController;
use App\Http\Controllers\Api\DistributionManagerController;
use App\Http\Controllers\Api\PublicDashboardController;
use App\Http\Controllers\Api\ProgramProposalController;
use App\Http\Controllers\Api\ManagerProgramController;
use App\Http\Controllers\Api\ProgramMusicScheduleController;

/*
|--------------------------------------------------------------------------
| Live TV Program API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the Live TV Program system.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Wrap all Live TV routes with 'live-tv' prefix
Route::prefix('live-tv')->group(function () {

// Program Management Routes
Route::prefix('programs')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/', [ProgramController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/', [ProgramController::class, 'store'])->middleware('throttle:sensitive');
    
    // Budget Approval Routes - HARUS SEBELUM route {id} agar tidak ter-match sebagai parameter
    Route::get('/budget-requests', [ProgramController::class, 'getBudgetRequests'])->middleware('throttle:60,1');
    
    // Program Routes dengan ID - HARUS SETELAH route spesifik
    Route::get('/{id}', [ProgramController::class, 'show'])->middleware('throttle:60,1');
    Route::put('/{id}', [ProgramController::class, 'update'])->middleware('throttle:sensitive');
    Route::delete('/{id}', [ProgramController::class, 'destroy'])->middleware('throttle:sensitive');
    
    // Program Workflow Routes
    Route::post('/{id}/submit', [ProgramController::class, 'submit'])->middleware('throttle:sensitive');
    Route::post('/{id}/approve', [ProgramController::class, 'approve'])->middleware('throttle:sensitive');
    Route::post('/{id}/reject', [ProgramController::class, 'reject'])->middleware('throttle:sensitive');
    
    // Budget Approval Routes dengan ID
    Route::post('/{id}/approve-budget', [ProgramController::class, 'approveBudget'])->middleware('throttle:sensitive');
    
    // Program Analytics Routes
    Route::get('/{id}/analytics', [ProgramController::class, 'analytics'])->middleware('throttle:60,1');
    Route::get('/{id}/episodes', [ProgramController::class, 'episodes'])->middleware('throttle:60,1');
});

// Program Proposal Routes
Route::prefix('proposals')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/', [ProgramProposalController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/', [ProgramProposalController::class, 'store'])->middleware('throttle:sensitive');
    Route::get('/{id}', [ProgramProposalController::class, 'show'])->middleware('throttle:60,1');
    Route::put('/{id}', [ProgramProposalController::class, 'update'])->middleware('throttle:sensitive');
    Route::delete('/{id}', [ProgramProposalController::class, 'destroy'])->middleware('throttle:sensitive');
    
    // Proposal Workflow
    Route::post('/{id}/submit', [ProgramProposalController::class, 'submit'])->middleware('throttle:sensitive');
    Route::post('/{id}/approve', [ProgramProposalController::class, 'approve'])->middleware('throttle:sensitive');
    Route::post('/{id}/reject', [ProgramProposalController::class, 'reject'])->middleware('throttle:sensitive');
    Route::post('/{id}/request-revision', [ProgramProposalController::class, 'requestRevision'])->middleware('throttle:sensitive');
});

// Manager Program Routes
Route::prefix('manager-program')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [ManagerProgramController::class, 'dashboard'])->middleware('throttle:60,1');
    
    // Episode Management
    Route::post('/episodes/{episodeId}/assign-team', [ManagerProgramController::class, 'assignTeamToEpisode'])->middleware('throttle:sensitive');
    Route::put('/deadlines/{deadlineId}', [ManagerProgramController::class, 'editDeadlineById'])->middleware('throttle:sensitive');
    
    // Program Management
    Route::post('/programs/{programId}/generate-episodes', [ManagerProgramController::class, 'generateEpisodes'])->middleware('throttle:sensitive');
    Route::post('/programs/{programId}/close', [ManagerProgramController::class, 'closeProgram'])->middleware('throttle:sensitive');
    
    // Performance & Views Tracking (NEW)
    Route::put('/episodes/{episodeId}/views', [ManagerProgramController::class, 'updateEpisodeViews'])->middleware('throttle:sensitive');
    Route::get('/programs/{programId}/performance', [ManagerProgramController::class, 'getProgramPerformance'])->middleware('throttle:60,1');
    Route::get('/programs/{programId}/weekly-performance', [ManagerProgramController::class, 'getWeeklyPerformance'])->middleware('throttle:60,1');
    Route::post('/evaluate-all-programs', [ManagerProgramController::class, 'evaluateAllPrograms'])->middleware('throttle:sensitive');
    
    // Schedule Options (NEW)
    Route::post('/programs/{programId}/submit-schedule-options', [ManagerProgramController::class, 'submitScheduleOptions'])->middleware('throttle:sensitive');
    Route::get('/programs/{programId}/schedule-options', [ManagerProgramController::class, 'getScheduleOptions'])->middleware('throttle:60,1');
    
    // Schedule Interference (Override Authority)
    Route::post('/schedules/{scheduleId}/cancel', [ManagerProgramController::class, 'cancelSchedule'])->middleware('throttle:sensitive');
    Route::post('/schedules/{scheduleId}/reschedule', [ManagerProgramController::class, 'reschedule'])->middleware('throttle:sensitive');
    
    // Approval Override (Override Authority)
    Route::post('/approvals/{approvalId}/override', [ManagerProgramController::class, 'overrideApproval'])->middleware('throttle:sensitive');
    
    // Quality Control Monitoring (GET only)
    Route::get('/programs/{programId}/quality-controls', [ManagerProgramController::class, 'getQualityControls'])->middleware('throttle:60,1');
    Route::get('/episodes/{episodeId}/quality-controls', [ManagerProgramController::class, 'getEpisodeQualityControls'])->middleware('throttle:60,1');
    
    // Rundown Edit Approval
    Route::get('/rundown-edit-requests', [ManagerProgramController::class, 'getRundownEditRequests'])->middleware('throttle:60,1');
    Route::post('/rundown-edit-requests/{approvalId}/approve', [ManagerProgramController::class, 'approveRundownEdit'])->middleware('throttle:sensitive');
    Route::post('/rundown-edit-requests/{approvalId}/reject', [ManagerProgramController::class, 'rejectRundownEdit'])->middleware('throttle:sensitive');
    
    // Special Budget Approval
    Route::get('/special-budget-approvals', [ManagerProgramController::class, 'getSpecialBudgetApprovals'])->middleware('throttle:60,1');
    Route::post('/special-budget-approvals/{id}/approve', [ManagerProgramController::class, 'approveSpecialBudget'])->middleware('throttle:sensitive');
    Route::post('/special-budget-approvals/{id}/reject', [ManagerProgramController::class, 'rejectSpecialBudget'])->middleware('throttle:sensitive');
    
    // Revised Schedules (Schedules revised by Manager Broadcasting)
    Route::get('/revised-schedules', [ManagerProgramController::class, 'getRevisedSchedules'])->middleware('throttle:60,1');
});

// Episode Management Routes
Route::prefix('episodes')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [EpisodeController::class, 'index']);
    Route::get('/{id}', [EpisodeController::class, 'show']);
    Route::put('/{id}', [EpisodeController::class, 'update']);
    
    // Episode Workflow Routes
    Route::post('/{id}/workflow-state', [EpisodeController::class, 'updateWorkflowState']);
    Route::post('/{id}/complete', [EpisodeController::class, 'complete']);
    
    // Episode Data Routes
    Route::get('/{id}/workflow-history', [EpisodeController::class, 'workflowHistory']);
    Route::get('/{id}/current-workflow-state', [EpisodeController::class, 'currentWorkflowState']);
    Route::get('/{id}/progress', [EpisodeController::class, 'progress']);
    Route::get('/{id}/deadlines', [EpisodeController::class, 'deadlines']);
    Route::get('/{id}/media-files', [EpisodeController::class, 'mediaFiles']);
    
    // Episode Filter Routes
    Route::get('/workflow-state/{state}', [EpisodeController::class, 'byWorkflowState']);
    Route::get('/user/{userId}/tasks', [EpisodeController::class, 'userTasks']);
});

// Deadline Management Routes
Route::prefix('deadlines')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Deadline Analytics (must be before /{id} routes)
    Route::get('/statistics', [DeadlineController::class, 'statistics'])->middleware('throttle:60,1');
    Route::get('/analytics', [DeadlineController::class, 'analytics'])->middleware('throttle:60,1');
    
    // Deadline Filters (must be before /{id} routes)
    Route::get('/user/{userId}', [DeadlineController::class, 'userDeadlines'])->middleware('throttle:60,1');
    Route::get('/overdue', [DeadlineController::class, 'overdue'])->middleware('throttle:60,1');
    Route::get('/upcoming', [DeadlineController::class, 'upcoming'])->middleware('throttle:60,1');
    
    // Deadline Automation (must be before /{id} routes)
    Route::post('/check-overdue', [DeadlineController::class, 'checkOverdue'])->middleware('throttle:sensitive');
    Route::post('/send-reminders', [DeadlineController::class, 'sendReminders'])->middleware('throttle:sensitive');
    
    // Basic CRUD routes (must be last)
    Route::get('/', [DeadlineController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/{id}', [DeadlineController::class, 'show'])->middleware('throttle:60,1');
    Route::put('/{id}', [DeadlineController::class, 'update'])->middleware('throttle:sensitive');
    
    // Deadline Actions
    Route::post('/{id}/complete', [DeadlineController::class, 'complete'])->middleware('throttle:sensitive');
    Route::post('/{id}/cancel', [DeadlineController::class, 'cancel'])->middleware('throttle:sensitive');
});

// Notification Management Routes
Route::prefix('notifications')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/{id}', [NotificationController::class, 'show'])->middleware('throttle:60,1');
    
    // Notification Actions
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:sensitive');
    Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->middleware('throttle:sensitive'); // Alias untuk frontend
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:sensitive');
    Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:sensitive'); // Alias untuk frontend
    Route::post('/{id}/archive', [NotificationController::class, 'archive'])->middleware('throttle:sensitive');
    
    // Notification Analytics
    Route::get('/statistics', [NotificationController::class, 'statistics'])->middleware('throttle:60,1');
    
    // Notification Filters
    Route::get('/unread', [NotificationController::class, 'unread'])->middleware('throttle:60,1');
    Route::get('/urgent', [NotificationController::class, 'urgent'])->middleware('throttle:60,1');
    
    // Notification Sending
    Route::post('/send', [NotificationController::class, 'send'])->middleware('throttle:sensitive');
    Route::post('/send-to-users', [NotificationController::class, 'sendToUsers'])->middleware('throttle:sensitive');
    Route::post('/send-to-role', [NotificationController::class, 'sendToRole'])->middleware('throttle:sensitive');
    
    // Notification Maintenance
    Route::post('/cleanup', [NotificationController::class, 'cleanup'])->middleware('throttle:sensitive');
});

// Music Notification Routes (Compatibility for frontend)
Route::prefix('music/notifications')->middleware('auth:sanctum')->group(function () {
    Route::get('/read-status/{id}', [NotificationController::class, 'getReadStatus']);
});

// Analytics Routes
Route::prefix('analytics')->group(function () {
    Route::get('/system', [AnalyticsController::class, 'system']);
    Route::get('/program/{id}', [AnalyticsController::class, 'program']);
    Route::get('/user/{id}/performance', [AnalyticsController::class, 'userPerformance']);
    Route::get('/deadlines', [AnalyticsController::class, 'deadlines']);
    Route::get('/workflow', [AnalyticsController::class, 'workflow']);
    Route::get('/files', [AnalyticsController::class, 'files']);
    Route::get('/notifications', [AnalyticsController::class, 'notifications']);
    Route::get('/time-based', [AnalyticsController::class, 'timeBased']);
    Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
});

// Production Team Management Routes
Route::prefix('production-teams')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProductionTeamController::class, 'index']);
    Route::post('/', [ProductionTeamController::class, 'store']);
    Route::get('/{id}', [ProductionTeamController::class, 'show']);
    Route::put('/{id}', [ProductionTeamController::class, 'update']);
    Route::delete('/{id}', [ProductionTeamController::class, 'destroy']);
    
    // Team Member Management
    Route::post('/{id}/members', [ProductionTeamController::class, 'addMember']);
    Route::delete('/{id}/members/{memberId}', [ProductionTeamController::class, 'removeMember']);
    Route::put('/{id}/members/{memberId}', [ProductionTeamController::class, 'updateMember']);
    
    // Team Analytics
    Route::get('/{id}/members', [ProductionTeamController::class, 'getMembers']);
    Route::get('/{id}/statistics', [ProductionTeamController::class, 'getStatistics']);
    Route::get('/{id}/workload', [ProductionTeamController::class, 'getWorkload']);
    
    // Get available users for role
    Route::get('/available-users/{role}', [ProductionTeamController::class, 'getAvailableUsersForRole']);
    
    // Team Actions
    Route::post('/{id}/deactivate', [ProductionTeamController::class, 'deactivate']);
    Route::post('/{id}/reactivate', [ProductionTeamController::class, 'reactivate']);
});

// File Upload Routes - dengan rate limiting
Route::prefix('files')->middleware(['auth:sanctum', 'throttle:uploads'])->group(function () {
    Route::post('/upload', [FileUploadController::class, 'upload']);
    Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);
    Route::delete('/{id}', [FileUploadController::class, 'delete']);
    Route::get('/{id}/url', [FileUploadController::class, 'getUrl']);
    Route::get('/{id}/thumbnail', [FileUploadController::class, 'getThumbnail']);
    
    // File Analytics
    Route::get('/statistics', [FileUploadController::class, 'getStatistics']);
    Route::get('/storage-usage', [FileUploadController::class, 'getStorageUsage']);
    Route::post('/cleanup', [FileUploadController::class, 'cleanupOrphaned']);
});

// Workflow State Management Routes
Route::prefix('workflow-states')->group(function () {
    Route::get('/transitions', [WorkflowStateController::class, 'getTransitions']);
    Route::get('/labels', [WorkflowStateController::class, 'getLabels']);
    Route::get('/episodes/{state}', [WorkflowStateController::class, 'getEpisodesByState']);
    Route::get('/user/{userId}/tasks', [WorkflowStateController::class, 'getUserTasks']);
    Route::get('/analytics', [WorkflowStateController::class, 'getAnalytics']);
});

// Music Arranger Routes (Alias untuk kompatibilitas frontend - tanpa prefix 'roles')
// Signed URL route untuk file download (tidak perlu auth, hanya perlu valid signature)
Route::prefix('music-arranger')->group(function () {
    Route::get('/arrangements/{id}/file', [MusicArrangerController::class, 'downloadFile'])
        ->name('music-arrangement.file')
        ->middleware(['signed', 'throttle:60,1']);
});

Route::prefix('music-arranger')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/arrangements', [MusicArrangerController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/arrangements', [MusicArrangerController::class, 'store'])->middleware('throttle:uploads');
    Route::get('/arrangements/{id}', [MusicArrangerController::class, 'show'])->middleware('throttle:60,1');
    Route::put('/arrangements/{id}', [MusicArrangerController::class, 'update'])->middleware('throttle:uploads');
    Route::post('/arrangements/{id}/submit-song-proposal', [MusicArrangerController::class, 'submitSongProposal'])->middleware('throttle:sensitive');
    Route::post('/arrangements/{id}/submit', [MusicArrangerController::class, 'submit'])->middleware('throttle:uploads');
    Route::post('/arrangements/{id}/accept-work', [MusicArrangerController::class, 'acceptWork'])->middleware('throttle:sensitive');
    Route::post('/arrangements/{id}/complete-work', [MusicArrangerController::class, 'completeWork'])->middleware('throttle:sensitive');
    Route::get('/statistics', [MusicArrangerController::class, 'statistics'])->middleware('throttle:60,1');
    Route::get('/approved-arrangements', [MusicArrangerController::class, 'getApprovedArrangementsHistory'])->middleware('throttle:60,1'); // History arrangement yang sudah disetujui
    Route::get('/songs', [MusicArrangerController::class, 'getAvailableSongs'])->middleware('throttle:60,1');
    Route::get('/singers', [MusicArrangerController::class, 'getAvailableSingers'])->middleware('throttle:60,1');
    
    // Fallback route dengan auth (untuk backward compatibility)
    Route::get('/arrangements/{id}/file-auth', [MusicArrangerController::class, 'downloadFile'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);
});

// Role-Specific Routes
Route::prefix('roles')->group(function () {
    // Signed URL route untuk file download (tidak perlu auth, hanya perlu valid signature)
    Route::prefix('music-arranger')->group(function () {
        Route::get('/arrangements/{id}/file', [MusicArrangerController::class, 'downloadFile'])
            ->name('music-arrangement.file')
            ->middleware(['signed', 'throttle:60,1']);
    });
    
    // Music Arranger Routes
    Route::prefix('music-arranger')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/arrangements', [MusicArrangerController::class, 'index'])->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('/arrangements', [MusicArrangerController::class, 'store'])->middleware('throttle:uploads'); // Rate limit untuk upload
        Route::get('/arrangements/{id}', [MusicArrangerController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/arrangements/{id}', [MusicArrangerController::class, 'update'])->middleware('throttle:uploads'); // Rate limit untuk upload
        Route::post('/arrangements/{id}/submit-song-proposal', [MusicArrangerController::class, 'submitSongProposal'])->middleware('throttle:sensitive'); // Ajukan lagu & penyanyi
        Route::post('/arrangements/{id}/submit', [MusicArrangerController::class, 'submit'])->middleware('throttle:uploads'); // Submit arrangement file
        // Upload arrangement file via POST (useful for clients that cannot send multipart PUT)
        Route::post('/arrangements/{id}/upload-file', [MusicArrangerController::class, 'uploadFile'])->middleware('throttle:uploads');
    
        // Upload arrangement file via POST (compat alias)
        Route::post('/arrangements/{id}/upload-file', [MusicArrangerController::class, 'uploadFile'])->middleware('throttle:uploads');
    
        Route::post('/arrangements/{id}/accept-work', [MusicArrangerController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/arrangements/{id}/complete-work', [MusicArrangerController::class, 'completeWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan
        Route::get('/statistics', [MusicArrangerController::class, 'statistics'])->middleware('throttle:60,1');
        // Optional: Pilih lagu dari database
        Route::get('/songs', [MusicArrangerController::class, 'getAvailableSongs'])->middleware('throttle:60,1');
        Route::get('/singers', [MusicArrangerController::class, 'getAvailableSingers'])->middleware('throttle:60,1');
        
        // Fallback route dengan auth (untuk backward compatibility)
        Route::get('/arrangements/{id}/file-auth', [MusicArrangerController::class, 'downloadFile'])
            ->middleware(['auth:sanctum', 'throttle:60,1']);
    });

    // Production Equipment Routes
    Route::prefix('production')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/equipment', [ProductionEquipmentController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/equipment/request', [ProductionEquipmentController::class, 'requestEquipment'])->middleware('throttle:sensitive');
        Route::post('/equipment/{id}/upload', [ProductionEquipmentController::class, 'uploadFiles'])->middleware('throttle:uploads');
        Route::post('/equipment/{id}/return', [ProductionEquipmentController::class, 'returnEquipment'])->middleware('throttle:sensitive');
        Route::get('/equipment/statistics', [ProductionEquipmentController::class, 'statistics'])->middleware('throttle:60,1');
    });
    
    // Creative Routes
    Route::prefix('creative')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [CreativeController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works', [CreativeController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('/works/{id}', [CreativeController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/works/{id}', [CreativeController::class, 'update'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/submit', [CreativeController::class, 'submit'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/accept-work', [CreativeController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/works/{id}/complete-work', [CreativeController::class, 'completeWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan
        Route::post('/works/{id}/upload-storyboard', [CreativeController::class, 'uploadStoryboard'])->middleware('throttle:uploads'); // Upload storyboard
        Route::put('/works/{id}/revise', [CreativeController::class, 'reviseCreativeWork'])->middleware('throttle:sensitive'); // Revisi setelah budget ditolak
        Route::post('/works/{id}/resubmit', [CreativeController::class, 'resubmitCreativeWork'])->middleware('throttle:sensitive'); // Resubmit setelah revisi
    });
    
    // Sound Engineer Routes
    Route::prefix('sound-engineer')->middleware('auth:sanctum')->group(function () {
        // Approved Arrangements (new endpoints)
        Route::get('/approved-arrangements', [SoundEngineerController::class, 'getApprovedArrangements']);
        Route::get('/episodes/{episodeId}/arrangement', [SoundEngineerController::class, 'getArrangementByEpisode']);
        Route::post('/arrangements/{arrangementId}/create-recording', [SoundEngineerController::class, 'createRecordingFromArrangement']);

        // Recording Management
        Route::get('/recordings', [SoundEngineerController::class, 'index']);
        Route::post('/recordings', [SoundEngineerController::class, 'store']);
        Route::get('/recordings/{id}', [SoundEngineerController::class, 'show']);
        Route::put('/recordings/{id}', [SoundEngineerController::class, 'update']);
        Route::post('/recordings/{id}/start', [SoundEngineerController::class, 'startRecording']);
        Route::post('/recordings/{id}/complete', [SoundEngineerController::class, 'completeRecording']);
        Route::get('/statistics', [SoundEngineerController::class, 'getStatistics']);
        
        // Help fix rejected arrangements
        Route::get('/rejected-arrangements', [SoundEngineerController::class, 'getRejectedArrangementsNeedingHelp']);
        Route::post('/arrangements/{arrangementId}/help-fix', [SoundEngineerController::class, 'helpFixArrangement']);
        
        // Bantu perbaikan song proposal yang ditolak
        Route::get('/rejected-song-proposals', [SoundEngineerController::class, 'getRejectedSongProposals']); // Terima Notifikasi
        Route::post('/song-proposals/{arrangementId}/help-fix', [SoundEngineerController::class, 'helpFixSongProposal']); // Bantu Perbaikan
        
        // Terima jadwal rekaman vokal dan request equipment
        Route::post('/recordings/{id}/accept-work', [SoundEngineerController::class, 'acceptWork']); // Terima pekerjaan
        Route::post('/recordings/{id}/accept-schedule', [SoundEngineerController::class, 'acceptRecordingSchedule']); // Terima jadwal rekaman vokal
        Route::post('/recordings/{id}/request-equipment', [SoundEngineerController::class, 'requestEquipment']); // Input list alat dan ajukan ke Art & Set Properti
        Route::post('/recordings/{id}/complete-work', [SoundEngineerController::class, 'completeWork']); // Selesaikan pekerjaan setelah input list alat
    });
    
    // Editor Routes
    Route::prefix('editor')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [EditorController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works', [EditorController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('/works/{id}', [EditorController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/works/{id}', [EditorController::class, 'update'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/submit', [EditorController::class, 'submit'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/report-missing-files', [EditorController::class, 'reportMissingFiles'])->middleware('throttle:sensitive');
        Route::get('/episodes/{episodeId}/approved-audio', [EditorController::class, 'getApprovedAudioFiles'])->middleware('throttle:60,1');
        Route::get('/episodes/{id}/run-sheet', [EditorController::class, 'getRunSheet'])->middleware('throttle:60,1'); // Lihat catatan syuting (run sheet)
    });
    
    // Design Grafis Routes
    Route::prefix('design-grafis')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [DesignGrafisController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works', [DesignGrafisController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('/works/{id}', [DesignGrafisController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/works/{id}', [DesignGrafisController::class, 'update'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/upload', [DesignGrafisController::class, 'uploadFiles'])->middleware('throttle:uploads');
        Route::get('/shared-files', [DesignGrafisController::class, 'getSharedFiles'])->middleware('throttle:60,1');
        Route::get('/statistics', [DesignGrafisController::class, 'statistics'])->middleware('throttle:60,1');
        Route::post('/works/{id}/submit-to-qc', [DesignGrafisController::class, 'submitToQC'])->middleware('throttle:sensitive'); // Ajukan ke QC
    });

    // Editor Promosi Routes
    Route::prefix('editor-promosi')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [EditorPromosiController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works', [EditorPromosiController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('/works/{id}', [EditorPromosiController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/works/{id}', [EditorPromosiController::class, 'update'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/upload', [EditorPromosiController::class, 'uploadFiles'])->middleware('throttle:uploads');
        Route::get('/source-files', [EditorPromosiController::class, 'getSourceFiles'])->middleware('throttle:60,1');
        Route::get('/statistics', [EditorPromosiController::class, 'statistics'])->middleware('throttle:60,1');
        Route::post('/works/{id}/submit-to-qc', [EditorPromosiController::class, 'submitToQC'])->middleware('throttle:sensitive'); // Ajukan ke QC
    });

    // File Sharing Routes
    Route::prefix('file-sharing')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/share', [FileSharingController::class, 'shareFiles'])->middleware('throttle:sensitive');
        Route::get('/shared-files', [FileSharingController::class, 'getSharedFiles'])->middleware('throttle:60,1');
        Route::get('/files-from-role', [FileSharingController::class, 'getFilesFromRole'])->middleware('throttle:60,1');
        Route::get('/download/{id}', [FileSharingController::class, 'downloadFile'])->middleware('throttle:60,1');
        Route::get('/statistics', [FileSharingController::class, 'statistics'])->middleware('throttle:60,1');
    });

    // Quality Control Routes
    Route::prefix('quality-control')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/controls', [QualityControlController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/controls/{id}', [QualityControlController::class, 'show'])->middleware('throttle:60,1');
        Route::post('/controls/{id}/start', [QualityControlController::class, 'startQC'])->middleware('throttle:sensitive');
        Route::post('/controls/{id}/complete', [QualityControlController::class, 'completeQC'])->middleware('throttle:sensitive');
        Route::post('/controls/{id}/submit-form', [QualityControlController::class, 'submitQCForm'])->middleware('throttle:sensitive');
        Route::post('/controls/{id}/approve', [QualityControlController::class, 'approve'])->middleware('throttle:sensitive');
        Route::post('/controls/{id}/reject', [QualityControlController::class, 'reject'])->middleware('throttle:sensitive');
        Route::get('/statistics', [QualityControlController::class, 'statistics'])->middleware('throttle:60,1');
        
        // New workflow methods
        Route::get('/works', [QualityControlController::class, 'index'])->middleware('throttle:60,1'); // Get QC works
        Route::post('/works/{id}/receive-editor-promosi-files', [QualityControlController::class, 'receiveEditorPromosiFiles'])->middleware('throttle:sensitive'); // Terima lokasi file dari Editor Promosi
        Route::post('/works/{id}/receive-design-grafis-files', [QualityControlController::class, 'receiveDesignGrafisFiles'])->middleware('throttle:sensitive'); // Terima lokasi file dari Design Grafis
        Route::post('/works/{id}/accept-work', [QualityControlController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/works/{id}/qc-content', [QualityControlController::class, 'qcContent'])->middleware('throttle:sensitive'); // QC berbagai konten
        Route::post('/works/{id}/finalize', [QualityControlController::class, 'finalize'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan (approve/reject)
    });

    // Broadcasting Routes
    Route::prefix('broadcasting')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/schedules', [BroadcastingController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/schedules', [BroadcastingController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('/schedules/{id}', [BroadcastingController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/schedules/{id}', [BroadcastingController::class, 'update'])->middleware('throttle:sensitive');
        Route::post('/schedules/{id}/upload', [BroadcastingController::class, 'upload'])->middleware('throttle:uploads');
        Route::post('/schedules/{id}/publish', [BroadcastingController::class, 'publish'])->middleware('throttle:sensitive');
        Route::post('/schedules/{id}/schedule-playlist', [BroadcastingController::class, 'schedulePlaylist'])->middleware('throttle:sensitive');
        Route::get('/statistics', [BroadcastingController::class, 'statistics'])->middleware('throttle:60,1');
        
        // New workflow methods
        Route::get('/works', [BroadcastingController::class, 'index'])->middleware('throttle:60,1'); // Get broadcasting works
        Route::post('/works/{id}/accept-work', [BroadcastingController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/works/{id}/upload-youtube', [BroadcastingController::class, 'uploadYouTube'])->middleware('throttle:uploads'); // Upload ke YouTube dengan SEO
        Route::post('/works/{id}/upload-website', [BroadcastingController::class, 'uploadWebsite'])->middleware('throttle:uploads'); // Upload ke website
        Route::post('/works/{id}/input-youtube-link', [BroadcastingController::class, 'inputYouTubeLink'])->middleware('throttle:sensitive'); // Input link YT ke sistem
        Route::post('/works/{id}/complete-work', [BroadcastingController::class, 'completeWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan
    });

    // Art & Set Properti Routes
    Route::prefix('art-set-properti')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/equipment', [ArtSetPropertiController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/requests', [ArtSetPropertiController::class, 'getRequests'])->middleware('throttle:60,1');
        Route::post('/requests/{id}/approve', [ArtSetPropertiController::class, 'approveRequest'])->middleware('throttle:sensitive');
        Route::post('/requests/{id}/reject', [ArtSetPropertiController::class, 'rejectRequest'])->middleware('throttle:sensitive');
        Route::post('/equipment/{id}/return', [ArtSetPropertiController::class, 'returnEquipment'])->middleware('throttle:sensitive');
        Route::get('/statistics', [ArtSetPropertiController::class, 'statistics'])->middleware('throttle:60,1');
    });

    // Promosi Routes
    Route::prefix('promosi')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [PromosiController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works', [PromosiController::class, 'store'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/upload-bts', [PromosiController::class, 'uploadBTSContent'])->middleware('throttle:uploads');
        Route::post('/works/{id}/accept-schedule', [PromosiController::class, 'acceptSchedule'])->middleware('throttle:sensitive'); // Terima jadwal syuting
        Route::post('/works/{id}/accept-work', [PromosiController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/works/{id}/upload-bts-video', [PromosiController::class, 'uploadBTSVideo'])->middleware('throttle:uploads'); // Upload BTS video
        Route::post('/works/{id}/upload-talent-photos', [PromosiController::class, 'uploadTalentPhotos'])->middleware('throttle:uploads'); // Upload foto talent
        Route::post('/works/{id}/complete-work', [PromosiController::class, 'completeWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan
        Route::post('/social-media', [PromosiController::class, 'createSocialMediaPost'])->middleware('throttle:sensitive');
        Route::get('/social-media', [PromosiController::class, 'getSocialMediaPosts'])->middleware('throttle:60,1');
        Route::post('/social-media/{id}/submit-proof', [PromosiController::class, 'submitSocialProof'])->middleware('throttle:sensitive');
        Route::get('/statistics', [PromosiController::class, 'statistics'])->middleware('throttle:60,1');
        
        // New workflow methods (setelah QC/Broadcasting)
        Route::post('/episodes/{id}/receive-links', [PromosiController::class, 'receiveLinks'])->middleware('throttle:sensitive'); // Terima link YouTube dan website
        Route::post('/works/{id}/accept-promotion-work', [PromosiController::class, 'acceptPromotionWork'])->middleware('throttle:sensitive'); // Terima pekerjaan promosi
        Route::post('/episodes/{id}/share-facebook', [PromosiController::class, 'shareFacebook'])->middleware('throttle:sensitive'); // Share link website ke Facebook dengan bukti
        Route::post('/episodes/{id}/create-ig-story-highlight', [PromosiController::class, 'createIGStoryHighlight'])->middleware('throttle:sensitive'); // Buat video highlight untuk story IG dengan bukti
        Route::post('/episodes/{id}/create-fb-reels-highlight', [PromosiController::class, 'createFBReelsHighlight'])->middleware('throttle:sensitive'); // Buat video highlight untuk reels Facebook dengan bukti
        Route::post('/episodes/{id}/share-wa-group', [PromosiController::class, 'shareWAGroup'])->middleware('throttle:sensitive'); // Share ke grup promosi WA dengan bukti
        Route::post('/works/{id}/complete-promotion-work', [PromosiController::class, 'completePromotionWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan promosi
    });

    // Produksi Routes
    Route::prefix('produksi')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [ProduksiController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works/{id}/accept-work', [ProduksiController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/works/{id}/request-equipment', [ProduksiController::class, 'requestEquipment'])->middleware('throttle:sensitive'); // Input list alat dan ajukan ke Art & Set Properti
        Route::post('/works/{id}/request-needs', [ProduksiController::class, 'requestNeeds'])->middleware('throttle:sensitive'); // Ajukan kebutuhan
        Route::post('/works/{id}/create-run-sheet', [ProduksiController::class, 'createRunSheet'])->middleware('throttle:sensitive'); // Input form catatan syuting (run sheet)
        Route::post('/works/{id}/upload-shooting-results', [ProduksiController::class, 'uploadShootingResults'])->middleware('throttle:uploads'); // Upload hasil syuting ke storage
        Route::post('/works/{id}/input-file-links', [ProduksiController::class, 'inputFileLinks'])->middleware('throttle:sensitive'); // Input link file di sistem
        Route::post('/works/{id}/complete-work', [ProduksiController::class, 'completeWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan
        Route::get('/qc-results/{episode_id}', [ProduksiController::class, 'getQCResults'])->middleware('throttle:60,1'); // Baca hasil QC
    });

    // Manager Broadcasting Routes
    Route::prefix('manager-broadcasting')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/schedules', [ManagerBroadcastingController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/works', [ManagerBroadcastingController::class, 'getBroadcastingWorks'])->middleware('throttle:60,1');
        Route::post('/schedules/{id}/approve', [ManagerBroadcastingController::class, 'approveSchedule'])->middleware('throttle:sensitive');
        Route::post('/schedules/{id}/reject', [ManagerBroadcastingController::class, 'rejectSchedule'])->middleware('throttle:sensitive');
        Route::post('/schedules/{id}/revise', [ManagerBroadcastingController::class, 'reviseSchedule'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/approve', [ManagerBroadcastingController::class, 'approveWork'])->middleware('throttle:sensitive');
        Route::get('/schedule-options', [ManagerBroadcastingController::class, 'getScheduleOptions'])->middleware('throttle:60,1');
        Route::post('/schedule-options/{id}/approve', [ManagerBroadcastingController::class, 'approveScheduleOption'])->middleware('throttle:sensitive');
        Route::post('/schedule-options/{id}/reject', [ManagerBroadcastingController::class, 'rejectScheduleOption'])->middleware('throttle:sensitive');
        Route::get('/statistics', [ManagerBroadcastingController::class, 'statistics'])->middleware('throttle:60,1');
    });

    // Sound Engineer Editing Routes
    Route::prefix('sound-engineer-editing')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/works', [SoundEngineerEditingController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/works', [SoundEngineerEditingController::class, 'store'])->middleware('throttle:sensitive');
        Route::get('/works/{id}', [SoundEngineerEditingController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/works/{id}', [SoundEngineerEditingController::class, 'update'])->middleware('throttle:sensitive');
        Route::post('/works/{id}/submit', [SoundEngineerEditingController::class, 'submit'])->middleware('throttle:sensitive');
        Route::post('/upload-vocal', [SoundEngineerEditingController::class, 'uploadVocal'])->middleware('throttle:uploads');
        Route::get('/statistics', [SoundEngineerEditingController::class, 'statistics'])->middleware('throttle:60,1');
    });

    // General Affairs Routes
    Route::prefix('general-affairs')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/budget-requests', [GeneralAffairsController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/budget-requests/{id}', [GeneralAffairsController::class, 'show'])->middleware('throttle:60,1');
        Route::post('/budget-requests/{id}/approve', [GeneralAffairsController::class, 'approve'])->middleware('throttle:sensitive');
        Route::post('/budget-requests/{id}/reject', [GeneralAffairsController::class, 'reject'])->middleware('throttle:sensitive');
        Route::post('/budget-requests/{id}/process-payment', [GeneralAffairsController::class, 'processPayment'])->middleware('throttle:sensitive');
        Route::get('/budget-requests/program/{programId}', [GeneralAffairsController::class, 'getByProgram'])->middleware('throttle:60,1');
        Route::get('/budget-requests/from-creative-work', [GeneralAffairsController::class, 'getCreativeWorkBudgetRequests'])->middleware('throttle:60,1'); // Permohonan dana dari Creative Work
        Route::get('/statistics', [GeneralAffairsController::class, 'statistics'])->middleware('throttle:60,1');
    });

    // Social Media Routes
    Route::prefix('social-media')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/youtube/upload', [SocialMediaController::class, 'uploadToYouTube'])->middleware('throttle:uploads');
        Route::post('/facebook/share', [SocialMediaController::class, 'shareToFacebook'])->middleware('throttle:sensitive');
        Route::post('/instagram/story', [SocialMediaController::class, 'createInstagramStory'])->middleware('throttle:sensitive');
        Route::post('/whatsapp/group', [SocialMediaController::class, 'shareToWhatsAppGroup'])->middleware('throttle:sensitive');
        Route::get('/posts', [SocialMediaController::class, 'getPosts'])->middleware('throttle:60,1');
        Route::get('/statistics', [SocialMediaController::class, 'statistics'])->middleware('throttle:60,1');
    });

    // KPI Routes
    Route::prefix('kpi')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/dashboard', [KPIController::class, 'dashboard'])->middleware('throttle:60,1');
        Route::get('/user/{userId?}', [KPIController::class, 'userKPI'])->middleware('throttle:60,1');
        Route::get('/team', [KPIController::class, 'teamKPI'])->middleware('throttle:60,1');
        Route::get('/program/{programId}', [KPIController::class, 'programKPI'])->middleware('throttle:60,1');
    });

    // Analytics Routes
    Route::prefix('analytics')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/dashboard', [AnalyticsApiController::class, 'dashboard'])->middleware('throttle:60,1');
        Route::get('/program/{programId}', [AnalyticsApiController::class, 'programAnalytics'])->middleware('throttle:60,1');
        Route::get('/role', [AnalyticsApiController::class, 'roleAnalytics'])->middleware('throttle:60,1');
        Route::get('/time', [AnalyticsApiController::class, 'timeAnalytics'])->middleware('throttle:60,1');
        Route::get('/social-media', [AnalyticsApiController::class, 'socialMediaAnalytics'])->middleware('throttle:60,1');
    });

    // Distribution Manager Routes
    Route::prefix('manager-distribution')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/schedules', [DistributionManagerController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/schedules/{id}', [DistributionManagerController::class, 'show'])->middleware('throttle:60,1');
        Route::post('/schedules/{id}/approve', [DistributionManagerController::class, 'approve'])->middleware('throttle:sensitive');
        Route::post('/schedules/{id}/reject', [DistributionManagerController::class, 'reject'])->middleware('throttle:sensitive');
        Route::get('/promotions', [DistributionManagerController::class, 'promotionMaterials'])->middleware('throttle:60,1');
        Route::get('/statistics', [DistributionManagerController::class, 'statistics'])->middleware('throttle:60,1');
        Route::get('/dashboard', [DistributionManagerController::class, 'dashboard'])->middleware('throttle:60,1');
    });
});

// Program Music Schedule Routes (Accessible by ALL authenticated users)
// MOVED OUTSIDE 'roles' prefix group
Route::prefix('schedules')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/shooting', [ProgramMusicScheduleController::class, 'getShootingSchedules'])->middleware('throttle:60,1');
    Route::get('/airing', [ProgramMusicScheduleController::class, 'getAirSchedules'])->middleware('throttle:60,1');
    Route::get('/calendar', [ProgramMusicScheduleController::class, 'getCalendar'])->middleware('throttle:60,1');
    Route::get('/today', [ProgramMusicScheduleController::class, 'getTodaySchedules'])->middleware('throttle:60,1');
    Route::get('/week', [ProgramMusicScheduleController::class, 'getWeekSchedules'])->middleware('throttle:60,1');
});

// Producer Routes (MOVED OUTSIDE 'roles' prefix group)
Route::prefix('producer')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/approvals', [ProducerController::class, 'getApprovals'])->middleware('throttle:120,1'); // 120 requests per minute
    Route::get('/pending-approvals', [ProducerController::class, 'getApprovals'])->middleware('throttle:120,1'); // Alias untuk frontend - 120 requests per minute
    Route::get('/rejected-arrangements', [ProducerController::class, 'getRejectedArrangementsHistory'])->middleware('throttle:60,1'); // History arrangement yang di-reject
    Route::get('/approved-arrangements', [ProducerController::class, 'getApprovedArrangementsHistory'])->middleware('throttle:60,1'); // History arrangement yang sudah disetujui
    Route::post('/approvals/{id}/approve', [ProducerController::class, 'approve'])->middleware('throttle:sensitive');
    Route::post('/approvals/{id}/reject', [ProducerController::class, 'reject'])->middleware('throttle:sensitive');
    Route::get('/programs', [ProducerController::class, 'getPrograms'])->middleware('throttle:60,1');
    Route::get('/episodes', [ProducerController::class, 'getEpisodes'])->middleware('throttle:60,1');
    Route::get('/production-overview', [ProducerController::class, 'getProductionOverview'])->middleware('throttle:60,1');
    Route::get('/team-performance', [ProducerController::class, 'getTeamPerformance']);
    Route::get('/songs', [ProducerController::class, 'getAvailableSongs'])->middleware('throttle:60,1'); // Get available songs for editing song proposal
    Route::get('/singers', [ProducerController::class, 'getAvailableSingers'])->middleware('throttle:60,1'); // Get available singers for editing song proposal
    Route::post('/creative-works/{creativeWorkId}/assign-teams', [ProducerController::class, 'assignProductionTeams']);
    Route::post('/schedules/{id}/cancel', [ProducerController::class, 'cancelSchedule']);
    Route::put('/schedules/{scheduleId}/emergency-reassign-team', [ProducerController::class, 'emergencyReassignTeam']);
    Route::post('/episodes/{episodeId}/edit-rundown', [ProducerController::class, 'editRundown']);
    
    // Edit arrangement song/singer sebelum approve
    Route::put('/arrangements/{arrangementId}/edit-song-singer', [ProducerController::class, 'editArrangementSongSinger']);
    
    // Kirim reminder manual ke crew
    Route::post('/send-reminder-to-crew', [ProducerController::class, 'sendReminderToCrew']);
    
    // Weekly Airing Control
    Route::get('/weekly-airing-control', [ProducerController::class, 'getWeeklyAiringControl']);
    Route::get('/episodes/upcoming-this-week', [ProducerController::class, 'getUpcomingEpisodesThisWeek']);
    Route::get('/episodes/ready-this-week', [ProducerController::class, 'getReadyEpisodesThisWeek']);
    
    // Creative Work Management
    Route::get('/crew-members', [ProducerController::class, 'getCrewMembers']); // Ambil daftar kru untuk dipilih
    Route::post('/creative-works/{id}/review', [ProducerController::class, 'reviewCreativeWork']); // Cek script, storyboard, budget
    Route::post('/creative-works/{id}/assign-team', [ProducerController::class, 'assignTeamToCreativeWork']); // Tambah tim syuting/setting/rekam vokal
    Route::put('/creative-works/{id}/edit', [ProducerController::class, 'editCreativeWork']); // Edit creative work langsung
    Route::post('/creative-works/{id}/cancel-shooting', [ProducerController::class, 'cancelShootingSchedule']); // Cancel jadwal syuting
    Route::post('/creative-works/{id}/request-special-budget', [ProducerController::class, 'requestSpecialBudget']); // Ajukan budget khusus ke Manager Program
    Route::post('/creative-works/{id}/final-approval', [ProducerController::class, 'finalApproveCreativeWork']); // Approve/reject dengan review detail
    
    // Team Management
    Route::put('/team-assignments/{assignmentId}/replace-team', [ProducerController::class, 'replaceTeamMembers']); // Ganti tim syuting secara dadakan
});

// Production Routes (Alias untuk shooting schedules)
Route::prefix('production')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/shooting-schedules', [ProgramMusicScheduleController::class, 'getShootingSchedules'])->middleware('throttle:60,1');
});

// Public Dashboard Routes (Accessible by ALL authenticated users)
Route::prefix('dashboard')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/overview', [PublicDashboardController::class, 'getDashboardOverview'])->middleware('throttle:60,1');
    Route::get('/calendar', [PublicDashboardController::class, 'getCalendar'])->middleware('throttle:60,1');
    Route::get('/shooting-schedules', [PublicDashboardController::class, 'getApprovedShootingSchedules'])->middleware('throttle:60,1');
    Route::get('/air-schedules', [PublicDashboardController::class, 'getApprovedAirSchedules'])->middleware('throttle:60,1');
    Route::get('/team-progress', [PublicDashboardController::class, 'getTeamProgress']);
});

// System Maintenance Routes
Route::prefix('system')->group(function () {
    Route::post('/deadlines/check-overdue', [SystemController::class, 'checkOverdueDeadlines']);
    Route::post('/deadlines/send-reminders', [SystemController::class, 'sendDeadlineReminders']);
    Route::post('/notifications/cleanup', [SystemController::class, 'cleanupNotifications']);
    Route::post('/files/cleanup', [SystemController::class, 'cleanupFiles']);
    Route::get('/health', [SystemController::class, 'healthCheck']);
    Route::get('/status', [SystemController::class, 'getStatus']);
});

}); // End of live-tv prefix

