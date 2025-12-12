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
Route::prefix('programs')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProgramController::class, 'index']);
    Route::post('/', [ProgramController::class, 'store']);
    Route::get('/{id}', [ProgramController::class, 'show']);
    Route::put('/{id}', [ProgramController::class, 'update']);
    Route::delete('/{id}', [ProgramController::class, 'destroy']);
    
    // Program Workflow Routes
    Route::post('/{id}/submit', [ProgramController::class, 'submit']);
    Route::post('/{id}/approve', [ProgramController::class, 'approve']);
    Route::post('/{id}/reject', [ProgramController::class, 'reject']);
    
    // Budget Approval Routes
    Route::post('/{id}/approve-budget', [ProgramController::class, 'approveBudget']);
    Route::get('/budget-requests', [ProgramController::class, 'getBudgetRequests']);
    
    // Program Analytics Routes
    Route::get('/{id}/analytics', [ProgramController::class, 'analytics']);
    Route::get('/{id}/episodes', [ProgramController::class, 'episodes']);
});

// Program Proposal Routes
Route::prefix('proposals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProgramProposalController::class, 'index']);
    Route::post('/', [ProgramProposalController::class, 'store']);
    Route::get('/{id}', [ProgramProposalController::class, 'show']);
    Route::put('/{id}', [ProgramProposalController::class, 'update']);
    Route::delete('/{id}', [ProgramProposalController::class, 'destroy']);
    
    // Proposal Workflow
    Route::post('/{id}/submit', [ProgramProposalController::class, 'submit']);
    Route::post('/{id}/approve', [ProgramProposalController::class, 'approve']);
    Route::post('/{id}/reject', [ProgramProposalController::class, 'reject']);
    Route::post('/{id}/request-revision', [ProgramProposalController::class, 'requestRevision']);
});

// Manager Program Routes
Route::prefix('manager-program')->middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard', [ManagerProgramController::class, 'dashboard']);
    
    // Episode Management
    Route::post('/episodes/{episodeId}/assign-team', [ManagerProgramController::class, 'assignTeamToEpisode']);
    Route::put('/deadlines/{deadlineId}', [ManagerProgramController::class, 'editDeadlineById']);
    
    // Program Management
    Route::post('/programs/{programId}/generate-episodes', [ManagerProgramController::class, 'generateEpisodes']);
    Route::post('/programs/{programId}/close', [ManagerProgramController::class, 'closeProgram']);
    
    // Performance & Views Tracking (NEW)
    Route::put('/episodes/{episodeId}/views', [ManagerProgramController::class, 'updateEpisodeViews']);
    Route::get('/programs/{programId}/performance', [ManagerProgramController::class, 'getProgramPerformance']);
    Route::get('/programs/{programId}/weekly-performance', [ManagerProgramController::class, 'getWeeklyPerformance']);
    Route::post('/evaluate-all-programs', [ManagerProgramController::class, 'evaluateAllPrograms']);
    
    // Schedule Options (NEW)
    Route::post('/programs/{programId}/submit-schedule-options', [ManagerProgramController::class, 'submitScheduleOptions']);
    Route::get('/programs/{programId}/schedule-options', [ManagerProgramController::class, 'getScheduleOptions']);
    
    // Schedule Interference (Override Authority)
    Route::post('/schedules/{scheduleId}/cancel', [ManagerProgramController::class, 'cancelSchedule']);
    Route::post('/schedules/{scheduleId}/reschedule', [ManagerProgramController::class, 'reschedule']);
    
    // Approval Override (Override Authority)
    Route::post('/approvals/{approvalId}/override', [ManagerProgramController::class, 'overrideApproval']);
    
    // Quality Control Monitoring (GET only)
    Route::get('/programs/{programId}/quality-controls', [ManagerProgramController::class, 'getQualityControls']);
    Route::get('/episodes/{episodeId}/quality-controls', [ManagerProgramController::class, 'getEpisodeQualityControls']);
    
    // Rundown Edit Approval
    Route::get('/rundown-edit-requests', [ManagerProgramController::class, 'getRundownEditRequests']);
    Route::post('/rundown-edit-requests/{approvalId}/approve', [ManagerProgramController::class, 'approveRundownEdit']);
    Route::post('/rundown-edit-requests/{approvalId}/reject', [ManagerProgramController::class, 'rejectRundownEdit']);
    
    // Special Budget Approval
    Route::get('/special-budget-approvals', [ManagerProgramController::class, 'getSpecialBudgetApprovals']);
    Route::post('/special-budget-approvals/{id}/approve', [ManagerProgramController::class, 'approveSpecialBudget']);
    Route::post('/special-budget-approvals/{id}/reject', [ManagerProgramController::class, 'rejectSpecialBudget']);
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
Route::prefix('deadlines')->middleware('auth:sanctum')->group(function () {
    // Deadline Analytics (must be before /{id} routes)
    Route::get('/statistics', [DeadlineController::class, 'statistics']);
    Route::get('/analytics', [DeadlineController::class, 'analytics']);
    
    // Deadline Filters (must be before /{id} routes)
    Route::get('/user/{userId}', [DeadlineController::class, 'userDeadlines']);
    Route::get('/overdue', [DeadlineController::class, 'overdue']);
    Route::get('/upcoming', [DeadlineController::class, 'upcoming']);
    
    // Deadline Automation (must be before /{id} routes)
    Route::post('/check-overdue', [DeadlineController::class, 'checkOverdue']);
    Route::post('/send-reminders', [DeadlineController::class, 'sendReminders']);
    
    // Basic CRUD routes (must be last)
    Route::get('/', [DeadlineController::class, 'index']);
    Route::get('/{id}', [DeadlineController::class, 'show']);
    Route::put('/{id}', [DeadlineController::class, 'update']);
    
    // Deadline Actions
    Route::post('/{id}/complete', [DeadlineController::class, 'complete']);
    Route::post('/{id}/cancel', [DeadlineController::class, 'cancel']);
});

// Notification Management Routes
Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/{id}', [NotificationController::class, 'show']);
    
    // Notification Actions
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('/{id}/archive', [NotificationController::class, 'archive']);
    
    // Notification Analytics
    Route::get('/statistics', [NotificationController::class, 'statistics']);
    
    // Notification Filters
    Route::get('/unread', [NotificationController::class, 'unread']);
    Route::get('/urgent', [NotificationController::class, 'urgent']);
    
    // Notification Sending
    Route::post('/send', [NotificationController::class, 'send']);
    Route::post('/send-to-users', [NotificationController::class, 'sendToUsers']);
    Route::post('/send-to-role', [NotificationController::class, 'sendToRole']);
    
    // Notification Maintenance
    Route::post('/cleanup', [NotificationController::class, 'cleanup']);
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

// Role-Specific Routes
Route::prefix('roles')->group(function () {
    // Music Arranger Routes
    Route::prefix('music-arranger')->middleware('auth:sanctum')->group(function () {
        Route::get('/arrangements', [MusicArrangerController::class, 'index']);
        Route::post('/arrangements', [MusicArrangerController::class, 'store'])->middleware('throttle:uploads'); // Rate limit untuk upload
        Route::get('/arrangements/{id}', [MusicArrangerController::class, 'show']);
        Route::put('/arrangements/{id}', [MusicArrangerController::class, 'update'])->middleware('throttle:uploads'); // Rate limit untuk upload
        Route::post('/arrangements/{id}/submit-song-proposal', [MusicArrangerController::class, 'submitSongProposal'])->middleware('throttle:sensitive'); // Ajukan lagu & penyanyi
        Route::post('/arrangements/{id}/submit', [MusicArrangerController::class, 'submit'])->middleware('throttle:uploads'); // Submit arrangement file
        Route::post('/arrangements/{id}/accept-work', [MusicArrangerController::class, 'acceptWork'])->middleware('throttle:sensitive'); // Terima pekerjaan
        Route::post('/arrangements/{id}/complete-work', [MusicArrangerController::class, 'completeWork'])->middleware('throttle:sensitive'); // Selesaikan pekerjaan
        Route::get('/statistics', [MusicArrangerController::class, 'statistics']);
        // Optional: Pilih lagu dari database
        Route::get('/songs', [MusicArrangerController::class, 'getAvailableSongs']);
        Route::get('/singers', [MusicArrangerController::class, 'getAvailableSingers']);
    });

    // Production Equipment Routes
    Route::prefix('production')->middleware('auth:sanctum')->group(function () {
        Route::get('/equipment', [ProductionEquipmentController::class, 'index']);
        Route::post('/equipment/request', [ProductionEquipmentController::class, 'requestEquipment']);
        Route::post('/equipment/{id}/upload', [ProductionEquipmentController::class, 'uploadFiles']);
        Route::post('/equipment/{id}/return', [ProductionEquipmentController::class, 'returnEquipment']);
        Route::get('/equipment/statistics', [ProductionEquipmentController::class, 'statistics']);
    });
    
    // Creative Routes
    Route::prefix('creative')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [CreativeController::class, 'index']);
        Route::post('/works', [CreativeController::class, 'store']);
        Route::get('/works/{id}', [CreativeController::class, 'show']);
        Route::put('/works/{id}', [CreativeController::class, 'update']);
        Route::post('/works/{id}/submit', [CreativeController::class, 'submit']);
        Route::post('/works/{id}/accept-work', [CreativeController::class, 'acceptWork']); // Terima pekerjaan
        Route::post('/works/{id}/complete-work', [CreativeController::class, 'completeWork']); // Selesaikan pekerjaan
        Route::put('/works/{id}/revise', [CreativeController::class, 'reviseCreativeWork']); // Revisi setelah budget ditolak
        Route::post('/works/{id}/resubmit', [CreativeController::class, 'resubmitCreativeWork']); // Resubmit setelah revisi
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
    Route::prefix('editor')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [EditorController::class, 'index']);
        Route::post('/works', [EditorController::class, 'store']);
        Route::get('/works/{id}', [EditorController::class, 'show']);
        Route::put('/works/{id}', [EditorController::class, 'update']);
        Route::post('/works/{id}/submit', [EditorController::class, 'submit']);
        Route::post('/works/{id}/report-missing-files', [EditorController::class, 'reportMissingFiles']);
        Route::get('/episodes/{episodeId}/approved-audio', [EditorController::class, 'getApprovedAudioFiles']);
        Route::get('/episodes/{id}/run-sheet', [EditorController::class, 'getRunSheet']); // Lihat catatan syuting (run sheet)
    });
    
    // Design Grafis Routes
    Route::prefix('design-grafis')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [DesignGrafisController::class, 'index']);
        Route::post('/works', [DesignGrafisController::class, 'store']);
        Route::get('/works/{id}', [DesignGrafisController::class, 'show']);
        Route::put('/works/{id}', [DesignGrafisController::class, 'update']);
        Route::post('/works/{id}/upload', [DesignGrafisController::class, 'uploadFiles']);
        Route::get('/shared-files', [DesignGrafisController::class, 'getSharedFiles']);
        Route::get('/statistics', [DesignGrafisController::class, 'statistics']);
        Route::post('/works/{id}/submit-to-qc', [DesignGrafisController::class, 'submitToQC']); // Ajukan ke QC
    });

    // Editor Promosi Routes
    Route::prefix('editor-promosi')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [EditorPromosiController::class, 'index']);
        Route::post('/works', [EditorPromosiController::class, 'store']);
        Route::get('/works/{id}', [EditorPromosiController::class, 'show']);
        Route::put('/works/{id}', [EditorPromosiController::class, 'update']);
        Route::post('/works/{id}/upload', [EditorPromosiController::class, 'uploadFiles']);
        Route::get('/source-files', [EditorPromosiController::class, 'getSourceFiles']);
        Route::get('/statistics', [EditorPromosiController::class, 'statistics']);
        Route::post('/works/{id}/submit-to-qc', [EditorPromosiController::class, 'submitToQC']); // Ajukan ke QC
    });

    // File Sharing Routes
    Route::prefix('file-sharing')->middleware('auth:sanctum')->group(function () {
        Route::post('/share', [FileSharingController::class, 'shareFiles']);
        Route::get('/shared-files', [FileSharingController::class, 'getSharedFiles']);
        Route::get('/files-from-role', [FileSharingController::class, 'getFilesFromRole']);
        Route::get('/download/{id}', [FileSharingController::class, 'downloadFile']);
        Route::get('/statistics', [FileSharingController::class, 'statistics']);
    });

    // Quality Control Routes
    Route::prefix('quality-control')->middleware('auth:sanctum')->group(function () {
        Route::get('/controls', [QualityControlController::class, 'index']);
        Route::get('/controls/{id}', [QualityControlController::class, 'show']);
        Route::post('/controls/{id}/start', [QualityControlController::class, 'startQC']);
        Route::post('/controls/{id}/complete', [QualityControlController::class, 'completeQC']);
        Route::post('/controls/{id}/submit-form', [QualityControlController::class, 'submitQCForm']);
        Route::post('/controls/{id}/approve', [QualityControlController::class, 'approve']);
        Route::post('/controls/{id}/reject', [QualityControlController::class, 'reject']);
        Route::get('/statistics', [QualityControlController::class, 'statistics']);
        
        // New workflow methods
        Route::get('/works', [QualityControlController::class, 'index']); // Get QC works
        Route::post('/works/{id}/receive-editor-promosi-files', [QualityControlController::class, 'receiveEditorPromosiFiles']); // Terima lokasi file dari Editor Promosi
        Route::post('/works/{id}/receive-design-grafis-files', [QualityControlController::class, 'receiveDesignGrafisFiles']); // Terima lokasi file dari Design Grafis
        Route::post('/works/{id}/accept-work', [QualityControlController::class, 'acceptWork']); // Terima pekerjaan
        Route::post('/works/{id}/qc-content', [QualityControlController::class, 'qcContent']); // QC berbagai konten
        Route::post('/works/{id}/finalize', [QualityControlController::class, 'finalize']); // Selesaikan pekerjaan (approve/reject)
    });

    // Broadcasting Routes
    Route::prefix('broadcasting')->middleware('auth:sanctum')->group(function () {
        Route::get('/schedules', [BroadcastingController::class, 'index']);
        Route::post('/schedules', [BroadcastingController::class, 'store']);
        Route::get('/schedules/{id}', [BroadcastingController::class, 'show']);
        Route::put('/schedules/{id}', [BroadcastingController::class, 'update']);
        Route::post('/schedules/{id}/upload', [BroadcastingController::class, 'upload']);
        Route::post('/schedules/{id}/publish', [BroadcastingController::class, 'publish']);
        Route::post('/schedules/{id}/schedule-playlist', [BroadcastingController::class, 'schedulePlaylist']);
        Route::get('/statistics', [BroadcastingController::class, 'statistics']);
        
        // New workflow methods
        Route::get('/works', [BroadcastingController::class, 'index']); // Get broadcasting works
        Route::post('/works/{id}/accept-work', [BroadcastingController::class, 'acceptWork']); // Terima pekerjaan
        Route::post('/works/{id}/upload-youtube', [BroadcastingController::class, 'uploadYouTube']); // Upload ke YouTube dengan SEO
        Route::post('/works/{id}/upload-website', [BroadcastingController::class, 'uploadWebsite']); // Upload ke website
        Route::post('/works/{id}/input-youtube-link', [BroadcastingController::class, 'inputYouTubeLink']); // Input link YT ke sistem
        Route::post('/works/{id}/complete-work', [BroadcastingController::class, 'completeWork']); // Selesaikan pekerjaan
    });

    // Art & Set Properti Routes
    Route::prefix('art-set-properti')->middleware('auth:sanctum')->group(function () {
        Route::get('/equipment', [ArtSetPropertiController::class, 'index']);
        Route::get('/requests', [ArtSetPropertiController::class, 'getRequests']);
        Route::post('/requests/{id}/approve', [ArtSetPropertiController::class, 'approveRequest']);
        Route::post('/requests/{id}/reject', [ArtSetPropertiController::class, 'rejectRequest']);
        Route::post('/equipment/{id}/return', [ArtSetPropertiController::class, 'returnEquipment']);
        Route::get('/statistics', [ArtSetPropertiController::class, 'statistics']);
    });

    // Promosi Routes
    Route::prefix('promosi')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [PromosiController::class, 'index']);
        Route::post('/works', [PromosiController::class, 'store']);
        Route::post('/works/{id}/upload-bts', [PromosiController::class, 'uploadBTSContent']);
        Route::post('/works/{id}/accept-schedule', [PromosiController::class, 'acceptSchedule']); // Terima jadwal syuting
        Route::post('/works/{id}/accept-work', [PromosiController::class, 'acceptWork']); // Terima pekerjaan
        Route::post('/works/{id}/upload-bts-video', [PromosiController::class, 'uploadBTSVideo']); // Upload BTS video
        Route::post('/works/{id}/upload-talent-photos', [PromosiController::class, 'uploadTalentPhotos']); // Upload foto talent
        Route::post('/works/{id}/complete-work', [PromosiController::class, 'completeWork']); // Selesaikan pekerjaan
        Route::post('/social-media', [PromosiController::class, 'createSocialMediaPost']);
        Route::get('/social-media', [PromosiController::class, 'getSocialMediaPosts']);
        Route::post('/social-media/{id}/submit-proof', [PromosiController::class, 'submitSocialProof']);
        Route::get('/statistics', [PromosiController::class, 'statistics']);
        
        // New workflow methods (setelah QC/Broadcasting)
        Route::post('/episodes/{id}/receive-links', [PromosiController::class, 'receiveLinks']); // Terima link YouTube dan website
        Route::post('/works/{id}/accept-promotion-work', [PromosiController::class, 'acceptPromotionWork']); // Terima pekerjaan promosi
        Route::post('/episodes/{id}/share-facebook', [PromosiController::class, 'shareFacebook']); // Share link website ke Facebook dengan bukti
        Route::post('/episodes/{id}/create-ig-story-highlight', [PromosiController::class, 'createIGStoryHighlight']); // Buat video highlight untuk story IG dengan bukti
        Route::post('/episodes/{id}/create-fb-reels-highlight', [PromosiController::class, 'createFBReelsHighlight']); // Buat video highlight untuk reels Facebook dengan bukti
        Route::post('/episodes/{id}/share-wa-group', [PromosiController::class, 'shareWAGroup']); // Share ke grup promosi WA dengan bukti
        Route::post('/works/{id}/complete-promotion-work', [PromosiController::class, 'completePromotionWork']); // Selesaikan pekerjaan promosi
    });

    // Produksi Routes
    Route::prefix('produksi')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [ProduksiController::class, 'index']);
        Route::post('/works/{id}/accept-work', [ProduksiController::class, 'acceptWork']); // Terima pekerjaan
        Route::post('/works/{id}/request-equipment', [ProduksiController::class, 'requestEquipment']); // Input list alat dan ajukan ke Art & Set Properti
        Route::post('/works/{id}/request-needs', [ProduksiController::class, 'requestNeeds']); // Ajukan kebutuhan
        Route::post('/works/{id}/create-run-sheet', [ProduksiController::class, 'createRunSheet']); // Input form catatan syuting (run sheet)
        Route::post('/works/{id}/upload-shooting-results', [ProduksiController::class, 'uploadShootingResults']); // Upload hasil syuting ke storage
        Route::post('/works/{id}/input-file-links', [ProduksiController::class, 'inputFileLinks']); // Input link file di sistem
        Route::post('/works/{id}/complete-work', [ProduksiController::class, 'completeWork']); // Selesaikan pekerjaan
        Route::get('/qc-results/{episode_id}', [ProduksiController::class, 'getQCResults']); // Baca hasil QC
    });

    // Manager Broadcasting Routes
    Route::prefix('manager-broadcasting')->middleware('auth:sanctum')->group(function () {
        Route::get('/schedules', [ManagerBroadcastingController::class, 'index']);
        Route::get('/works', [ManagerBroadcastingController::class, 'getBroadcastingWorks']);
        Route::post('/schedules/{id}/approve', [ManagerBroadcastingController::class, 'approveSchedule']);
        Route::post('/schedules/{id}/reject', [ManagerBroadcastingController::class, 'rejectSchedule']);
        Route::post('/schedules/{id}/revise', [ManagerBroadcastingController::class, 'reviseSchedule']);
        Route::post('/works/{id}/approve', [ManagerBroadcastingController::class, 'approveWork']);
        Route::get('/schedule-options', [ManagerBroadcastingController::class, 'getScheduleOptions']);
        Route::post('/schedule-options/{id}/approve', [ManagerBroadcastingController::class, 'approveScheduleOption']);
        Route::post('/schedule-options/{id}/reject', [ManagerBroadcastingController::class, 'rejectScheduleOption']);
        Route::get('/statistics', [ManagerBroadcastingController::class, 'statistics']);
    });

    // Sound Engineer Editing Routes
    Route::prefix('sound-engineer-editing')->middleware('auth:sanctum')->group(function () {
        Route::get('/works', [SoundEngineerEditingController::class, 'index']);
        Route::post('/works', [SoundEngineerEditingController::class, 'store']);
        Route::get('/works/{id}', [SoundEngineerEditingController::class, 'show']);
        Route::put('/works/{id}', [SoundEngineerEditingController::class, 'update']);
        Route::post('/works/{id}/submit', [SoundEngineerEditingController::class, 'submit']);
        Route::post('/upload-vocal', [SoundEngineerEditingController::class, 'uploadVocal']);
        Route::get('/statistics', [SoundEngineerEditingController::class, 'statistics']);
    });

    // General Affairs Routes
    Route::prefix('general-affairs')->middleware('auth:sanctum')->group(function () {
        Route::get('/budget-requests', [GeneralAffairsController::class, 'index']);
        Route::get('/budget-requests/{id}', [GeneralAffairsController::class, 'show']);
        Route::post('/budget-requests/{id}/approve', [GeneralAffairsController::class, 'approve']);
        Route::post('/budget-requests/{id}/reject', [GeneralAffairsController::class, 'reject']);
        Route::post('/budget-requests/{id}/process-payment', [GeneralAffairsController::class, 'processPayment']);
        Route::get('/budget-requests/program/{programId}', [GeneralAffairsController::class, 'getByProgram']);
        Route::get('/budget-requests/from-creative-work', [GeneralAffairsController::class, 'getCreativeWorkBudgetRequests']); // Permohonan dana dari Creative Work
        Route::get('/statistics', [GeneralAffairsController::class, 'statistics']);
    });

    // Social Media Routes
    Route::prefix('social-media')->middleware('auth:sanctum')->group(function () {
        Route::post('/youtube/upload', [SocialMediaController::class, 'uploadToYouTube']);
        Route::post('/facebook/share', [SocialMediaController::class, 'shareToFacebook']);
        Route::post('/instagram/story', [SocialMediaController::class, 'createInstagramStory']);
        Route::post('/whatsapp/group', [SocialMediaController::class, 'shareToWhatsAppGroup']);
        Route::get('/posts', [SocialMediaController::class, 'getPosts']);
        Route::get('/statistics', [SocialMediaController::class, 'statistics']);
    });

    // KPI Routes
    Route::prefix('kpi')->middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [KPIController::class, 'dashboard']);
        Route::get('/user/{userId?}', [KPIController::class, 'userKPI']);
        Route::get('/team', [KPIController::class, 'teamKPI']);
        Route::get('/program/{programId}', [KPIController::class, 'programKPI']);
    });

    // Analytics Routes
    Route::prefix('analytics')->middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [AnalyticsApiController::class, 'dashboard']);
        Route::get('/program/{programId}', [AnalyticsApiController::class, 'programAnalytics']);
        Route::get('/role', [AnalyticsApiController::class, 'roleAnalytics']);
        Route::get('/time', [AnalyticsApiController::class, 'timeAnalytics']);
        Route::get('/social-media', [AnalyticsApiController::class, 'socialMediaAnalytics']);
    });

    // Distribution Manager Routes
    Route::prefix('manager-distribution')->middleware('auth:sanctum')->group(function () {
        Route::get('/schedules', [DistributionManagerController::class, 'index']);
        Route::get('/schedules/{id}', [DistributionManagerController::class, 'show']);
        Route::post('/schedules/{id}/approve', [DistributionManagerController::class, 'approve']);
        Route::post('/schedules/{id}/reject', [DistributionManagerController::class, 'reject']);
        Route::get('/promotions', [DistributionManagerController::class, 'promotionMaterials']);
        Route::get('/statistics', [DistributionManagerController::class, 'statistics']);
        Route::get('/dashboard', [DistributionManagerController::class, 'dashboard']);
    });
});

// Program Music Schedule Routes (Accessible by ALL authenticated users)
// MOVED OUTSIDE 'roles' prefix group
Route::prefix('schedules')->middleware('auth:sanctum')->group(function () {
    Route::get('/shooting', [ProgramMusicScheduleController::class, 'getShootingSchedules']);
    Route::get('/airing', [ProgramMusicScheduleController::class, 'getAirSchedules']);
    Route::get('/calendar', [ProgramMusicScheduleController::class, 'getCalendar']);
    Route::get('/today', [ProgramMusicScheduleController::class, 'getTodaySchedules']);
    Route::get('/week', [ProgramMusicScheduleController::class, 'getWeekSchedules']);
});

// Producer Routes (MOVED OUTSIDE 'roles' prefix group)
Route::prefix('producer')->middleware('auth:sanctum')->group(function () {
    Route::get('/approvals', [ProducerController::class, 'getApprovals']);
    Route::get('/pending-approvals', [ProducerController::class, 'getApprovals']); // Alias untuk frontend
    Route::post('/approvals/{id}/approve', [ProducerController::class, 'approve']);
    Route::post('/approvals/{id}/reject', [ProducerController::class, 'reject']);
    Route::get('/programs', [ProducerController::class, 'getPrograms']);
    Route::get('/episodes', [ProducerController::class, 'getEpisodes']);
    Route::get('/production-overview', [ProducerController::class, 'getProductionOverview']);
    Route::get('/team-performance', [ProducerController::class, 'getTeamPerformance']);
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
Route::prefix('production')->middleware('auth:sanctum')->group(function () {
    Route::get('/shooting-schedules', [ProgramMusicScheduleController::class, 'getShootingSchedules']);
});

// Public Dashboard Routes (Accessible by ALL authenticated users)
Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('/overview', [PublicDashboardController::class, 'getDashboardOverview']);
    Route::get('/calendar', [PublicDashboardController::class, 'getCalendar']);
    Route::get('/shooting-schedules', [PublicDashboardController::class, 'getApprovedShootingSchedules']);
    Route::get('/air-schedules', [PublicDashboardController::class, 'getApprovedAirSchedules']);
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

