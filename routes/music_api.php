<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MusicArrangerController;
use App\Http\Controllers\ProducerMusicController;
use App\Http\Controllers\MusicWorkflowController;
use App\Http\Controllers\MusicNotificationController;
use App\Http\Controllers\AudioController;

/*
|--------------------------------------------------------------------------
| Music Program API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the music program.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// ===== HEALTH CHECK ROUTES (NO AUTH) =====
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Music API is running',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Music API is running',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

// ===== MUSIC ARRANGER ROUTES =====
Route::prefix('music-arranger')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [MusicArrangerController::class, 'dashboard']);
    
    // Songs management
    Route::get('/songs', [MusicArrangerController::class, 'getSongs']);
    Route::post('/songs', [MusicArrangerController::class, 'addSong']);
    Route::put('/songs/{id}', [MusicArrangerController::class, 'updateSong']);
    Route::patch('/songs/{id}', [MusicArrangerController::class, 'updateSong']);
    Route::delete('/songs/{id}', [MusicArrangerController::class, 'deleteSong']);
    Route::get('/songs/{id}/audio', [MusicArrangerController::class, 'getSongAudio']);
    
    // Singers management
    Route::get('/singers', [MusicArrangerController::class, 'getSingers']);
    Route::post('/singers', [MusicArrangerController::class, 'addSinger']);
    Route::put('/singers/{id}', [MusicArrangerController::class, 'updateSinger']);
    Route::patch('/singers/{id}', [MusicArrangerController::class, 'updateSinger']);
    Route::delete('/singers/{id}', [MusicArrangerController::class, 'deleteSinger']);
    
    // Music requests (legacy)
    Route::get('/requests', [MusicArrangerController::class, 'getMyRequests']);
    Route::get('/requests/{id}', [MusicArrangerController::class, 'getRequest']);
    Route::put('/requests/{id}', [MusicArrangerController::class, 'updateRequest']);
    Route::delete('/requests/{id}', [MusicArrangerController::class, 'cancelRequest']);
    
    // Test endpoint
    Route::get('/test-profile-url', [MusicArrangerController::class, 'testProfileUrl']);
    
    // Submissions management
    Route::get('/submissions', [MusicArrangerController::class, 'getSubmissions']);
    Route::get('/submissions/{id}', [MusicArrangerController::class, 'getSubmission']);
    Route::post('/submissions', [MusicWorkflowController::class, 'createSubmission']);
    Route::put('/submissions/{id}', [MusicWorkflowController::class, 'update']);
    Route::delete('/submissions/{id}', [MusicWorkflowController::class, 'destroy']);
});

// ===== PRODUCER ROUTES =====
Route::prefix('producer')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [ProducerMusicController::class, 'dashboard']);
    
    // Music requests management
    Route::get('/pending-requests', [ProducerMusicController::class, 'getPendingRequests']);
    Route::get('/approved-requests', [ProducerMusicController::class, 'getApprovedRequests']);
    Route::get('/rejected-requests', [ProducerMusicController::class, 'getRejectedRequests']);
    Route::get('/my-requests', [ProducerMusicController::class, 'getMyRequests']);
    Route::get('/requests/{id}', [ProducerMusicController::class, 'getRequest']);
});

Route::prefix('producer/music')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [ProducerMusicController::class, 'dashboard']);
    
    // Music requests management
    Route::get('/requests', [ProducerMusicController::class, 'getAllRequests']);
    Route::get('/requests/pending', [ProducerMusicController::class, 'getPendingRequests']);
    Route::get('/requests/approved', [ProducerMusicController::class, 'getApprovedRequests']);
    Route::get('/requests/rejected', [ProducerMusicController::class, 'getRejectedRequests']);
    Route::get('/requests/my', [ProducerMusicController::class, 'getMyRequests']);
    Route::get('/requests/status/{status}', [ProducerMusicController::class, 'getAllRequests']);
    Route::get('/requests/{id}', [ProducerMusicController::class, 'getRequest']);
    Route::post('/requests/{id}/approve', [ProducerMusicController::class, 'approveRequest']);
    Route::post('/requests/{id}/reject', [ProducerMusicController::class, 'rejectRequest']);
    Route::post('/requests/{id}/take', [ProducerMusicController::class, 'takeRequest']);
    
    // Songs management
    Route::get('/songs', [ProducerMusicController::class, 'getSongs']);
    Route::post('/songs', [ProducerMusicController::class, 'addSong']);
    Route::put('/songs/{id}', [ProducerMusicController::class, 'updateSong']);
    Route::patch('/songs/{id}', [ProducerMusicController::class, 'updateSong']);
    Route::delete('/songs/{id}', [ProducerMusicController::class, 'deleteSong']);
    Route::get('/songs/{id}/audio', [ProducerMusicController::class, 'getSongAudio']);
    
    // Singers management
    Route::get('/singers', [ProducerMusicController::class, 'getSingers']);
    Route::post('/singers', [ProducerMusicController::class, 'addSinger']);
    Route::put('/singers/{id}', [ProducerMusicController::class, 'updateSinger']);
    Route::patch('/singers/{id}', [ProducerMusicController::class, 'updateSinger']);
    Route::delete('/singers/{id}', [ProducerMusicController::class, 'deleteSinger']);
});

// ===== MUSIC WORKFLOW ROUTES =====
Route::prefix('music-workflow')->middleware(['auth:sanctum'])->group(function () {
    // General workflow endpoints
    Route::get('/current-submission', [MusicWorkflowController::class, 'getCurrentSubmission']);
    Route::get('/list', [MusicWorkflowController::class, 'getWorkflowList']);
    Route::post('/submissions', [MusicWorkflowController::class, 'createSubmission']);
    Route::put('/submissions/{id}', [MusicWorkflowController::class, 'update']);
    Route::delete('/submissions/{id}', [MusicWorkflowController::class, 'destroy']);
    Route::post('/submissions/{id}/transition', [MusicWorkflowController::class, 'transitionState']);
    Route::get('/submissions/{id}/history', [MusicWorkflowController::class, 'getWorkflowHistory']);
    Route::get('/stats', [MusicWorkflowController::class, 'getWorkflowStats']);
    Route::get('/analytics', [MusicWorkflowController::class, 'getAnalytics']);
    
    // Notifications
    Route::get('/notifications', [MusicWorkflowController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [MusicWorkflowController::class, 'markNotificationAsRead']);
    Route::post('/notifications/mark-all-read', [MusicWorkflowController::class, 'markAllNotificationsAsRead']);
    
    // Music Arranger workflow endpoints
    Route::post('/music-arranger/workflow/{id}/arrange', [MusicWorkflowController::class, 'submitArrangement']);
    Route::post('/music-arranger/workflow/{id}/start-arranging', [MusicWorkflowController::class, 'startArranging']);
    Route::post('/music-arranger/workflow/{id}/submit-arrangement', [MusicWorkflowController::class, 'submitArrangement']);
    
    // Music Arranger initial submission (with song/singer choice)
    Route::post('/music-arranger/submissions/create-with-choice', [MusicWorkflowController::class, 'createSubmissionWithChoice']);
    
    // Music Arranger workflow endpoints
    Route::post('/arranger/start-arranging/{id}', [MusicWorkflowController::class, 'startArranging']);
    Route::post('/arranger/submit-arrangement/{id}', [MusicWorkflowController::class, 'submitArrangement']);
    
    // Alternative endpoints for frontend compatibility
    Route::post('/submissions/{id}/start-arranging', [MusicWorkflowController::class, 'startArranging']);
    Route::post('/submissions/{id}/submit-arrangement', [MusicWorkflowController::class, 'submitArrangement']);
    
    // Producer workflow endpoints
    Route::get('/producer/workflow/{id}/details', [MusicWorkflowController::class, 'getSubmissionDetails']); // Get submission details
    Route::get('/producer/workflow/{id}/arrangement-audio', [MusicWorkflowController::class, 'getArrangementAudio']); // Get arrangement audio
    Route::get('/producer/workflow/{id}/download-audio', [MusicWorkflowController::class, 'downloadArrangementAudio'])->name('arrangement.audio.download'); // Download arrangement audio
    Route::post('/producer/workflow/{id}/review-initial', [MusicWorkflowController::class, 'reviewInitialSubmission']);
    Route::post('/producer/workflow/{id}/review', [MusicWorkflowController::class, 'reviewSubmission']); // Universal review endpoint
    Route::post('/producer/workflow/{id}/review-arrangement', [MusicWorkflowController::class, 'reviewArrangement']);
    Route::post('/producer/workflow/{id}/arrangement-review', [MusicWorkflowController::class, 'reviewArrangement']); // Alias for frontend compatibility
    
    // Producer approve/reject arrangement endpoints
    Route::post('/producer/workflow/{id}/approve-arrangement', [MusicWorkflowController::class, 'approveArrangement']);
    Route::post('/producer/workflow/{id}/reject-arrangement', [MusicWorkflowController::class, 'rejectArrangement']);
    Route::post('/producer/workflow/{id}/quality-control', [MusicWorkflowController::class, 'qualityControl']);
    Route::post('/producer/workflow/{id}/final-approve', [MusicWorkflowController::class, 'finalApprove']);
    
    // Legacy endpoints (for backward compatibility)
    Route::post('/producer/workflow/{id}/approve', [MusicWorkflowController::class, 'approveArrangement']);
    Route::post('/producer/workflow/{id}/reject', [MusicWorkflowController::class, 'rejectArrangement']);
    Route::post('/producer/workflow/{id}/approve-arrangement', [MusicWorkflowController::class, 'approveArrangement']);
    Route::post('/producer/workflow/{id}/reject-arrangement', [MusicWorkflowController::class, 'rejectArrangement']);
    Route::post('/producer/workflow/{id}/request-sound-engineering', [MusicWorkflowController::class, 'transitionState']);
    Route::post('/producer/workflow/{id}/approve-quality', [MusicWorkflowController::class, 'approveQuality']);
    
    // Sound Engineer workflow endpoints
    Route::post('/sound-engineer/workflow/{id}/complete', [MusicWorkflowController::class, 'completeSoundEngineering']);
    
    // Creative workflow endpoints
    Route::post('/creative/workflow/{id}/submit-work', [MusicWorkflowController::class, 'submitCreativeWork']);
    
    // Extended workflow endpoints
    Route::post('/producer/workflow/{id}/review-creative', [MusicWorkflowController::class, 'reviewCreativeWork']);
    Route::post('/producer/workflow/{id}/final-review', [MusicWorkflowController::class, 'producerFinalReview']);
    Route::post('/manager/workflow/{id}/approval', [MusicWorkflowController::class, 'managerApproval']);
    Route::post('/general-affairs/workflow/{id}/release', [MusicWorkflowController::class, 'generalAffairsRelease']);
    Route::post('/promotion/workflow/{id}/complete', [MusicWorkflowController::class, 'promotionComplete']);
    Route::post('/production/workflow/{id}/complete', [MusicWorkflowController::class, 'productionComplete']);
    Route::post('/sound-engineer/workflow/{id}/final-complete', [MusicWorkflowController::class, 'soundEngineeringFinalComplete']);
    
    // Notification endpoints
    Route::get('/notifications', [MusicNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [MusicNotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/mark-read', [MusicNotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [MusicNotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/{id}/read-status', [MusicNotificationController::class, 'getReadStatus']);
});

// ===== GENERAL NOTIFICATION ROUTES (for frontend compatibility) =====
Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/count', [MusicNotificationController::class, 'unreadCount']);
    Route::get('/read-status/{id}', [MusicNotificationController::class, 'getReadStatus']);
    Route::post('/{id}/mark-read', [MusicNotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [MusicNotificationController::class, 'markAllAsRead']);
    Route::get('/', [MusicNotificationController::class, 'index']);
});

// ===== AUDIO ROUTES =====
Route::prefix('audio')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/{songId}', [AudioController::class, 'stream']);
    Route::get('/{songId}/info', [AudioController::class, 'info']);
    Route::post('/{songId}/upload', [AudioController::class, 'upload']);
    Route::delete('/{songId}', [AudioController::class, 'delete']);
});

// ===== PHASE 2: CREATIVE WORKFLOW ROUTES =====

// ===== CREATIVE (KREATIF) ROUTES =====
Route::prefix('creative')->middleware(['auth:sanctum'])->group(function () {
    // Get creative work
    Route::get('/submissions/{id}/creative-work', [\App\Http\Controllers\CreativeController::class, 'getCreativeWork']);
    
    // Submit creative work (script, storyboard, budget, schedules)
    Route::post('/submissions/{id}/submit-creative-work', [\App\Http\Controllers\CreativeController::class, 'submitCreativeWork']);
    
    // Update creative work (for revision)
    Route::patch('/submissions/{id}/creative-work', [\App\Http\Controllers\CreativeController::class, 'updateCreativeWork']);
});

// ===== PRODUCER ROUTES (PHASE 2 ADDITIONS) =====
Route::prefix('producer')->middleware(['auth:sanctum'])->group(function () {
    // Get creative work for review
    Route::get('/submissions/{id}/creative-work', [ProducerMusicController::class, 'getCreativeWorkForReview']);
    
    // Review creative work (script, storyboard, budget)
    Route::post('/submissions/{id}/review-creative-work', [ProducerMusicController::class, 'reviewCreativeWork']);
    
    // Assign production teams (shooting, setting, recording)
    Route::post('/submissions/{id}/assign-teams', [ProducerMusicController::class, 'assignProductionTeams']);
    
    // Schedule management
    Route::post('/schedules/{id}/cancel', [ProducerMusicController::class, 'cancelSchedule']);
    Route::post('/schedules/{id}/reschedule', [ProducerMusicController::class, 'rescheduleSchedule']);
});

// ===== MANAGER PROGRAM ROUTES =====
Route::prefix('manager-program')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\ManagerProgramController::class, 'getDashboard']);
    
    // Budget approvals
    Route::get('/budget-approvals', [\App\Http\Controllers\ManagerProgramController::class, 'getBudgetApprovals']);
    Route::get('/budget-approvals/{id}', [\App\Http\Controllers\ManagerProgramController::class, 'getBudgetApprovalDetail']);
    Route::post('/budget-approvals/{id}/approve', [\App\Http\Controllers\ManagerProgramController::class, 'approveBudget']);
    Route::post('/budget-approvals/{id}/reject', [\App\Http\Controllers\ManagerProgramController::class, 'rejectBudget']);
});

// ===== PHASE 4: PRODUCTION SYSTEM ROUTES =====

// ===== GENERAL AFFAIRS ROUTES =====
Route::prefix('general-affairs')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/budget-requests', [\App\Http\Controllers\GeneralAffairsController::class, 'getBudgetRequests']);
    Route::post('/budget-requests', [\App\Http\Controllers\GeneralAffairsController::class, 'createBudgetRequest']);
    Route::post('/budget-requests/{id}/approve', [\App\Http\Controllers\GeneralAffairsController::class, 'approveBudgetRequest']);
    Route::post('/budget-requests/{id}/reject', [\App\Http\Controllers\GeneralAffairsController::class, 'rejectBudgetRequest']);
    Route::post('/budget-requests/{id}/release', [\App\Http\Controllers\GeneralAffairsController::class, 'releaseFunds']);
    Route::get('/budget-requests/{id}/status', [\App\Http\Controllers\GeneralAffairsController::class, 'getBudgetStatus']);
});

// ===== PROMOSI ROUTES =====
Route::prefix('promosi')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/bts-assignments', [\App\Http\Controllers\PromosiController::class, 'getBTSAssignments']);
    Route::post('/bts', [\App\Http\Controllers\PromosiController::class, 'createBTS']);
    Route::post('/bts/{id}/start', [\App\Http\Controllers\PromosiController::class, 'startBTS']);
    Route::post('/bts/{id}/upload-video', [\App\Http\Controllers\PromosiController::class, 'uploadBTSVideo']);
    Route::post('/bts/{id}/upload-photos', [\App\Http\Controllers\PromosiController::class, 'uploadTalentPhotos']);
    Route::post('/bts/{id}/complete', [\App\Http\Controllers\PromosiController::class, 'completeBTS']);
});

// ===== PRODUKSI ROUTES =====
Route::prefix('produksi')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/equipment-requests', [\App\Http\Controllers\ProduksiController::class, 'getEquipmentRequests']);
    Route::post('/equipment-requests', [\App\Http\Controllers\ProduksiController::class, 'createEquipmentRequest']);
    Route::post('/run-sheets', [\App\Http\Controllers\ProduksiController::class, 'createRunSheet']);
    Route::put('/run-sheets/{id}', [\App\Http\Controllers\ProduksiController::class, 'updateRunSheet']);
    Route::post('/run-sheets/{id}/start', [\App\Http\Controllers\ProduksiController::class, 'startShooting']);
    Route::post('/run-sheets/{id}/complete', [\App\Http\Controllers\ProduksiController::class, 'completeShooting']);
});

// ===== SOUND ENGINEER RECORDING ROUTES =====
Route::prefix('sound-engineer-recording')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/assignments', [\App\Http\Controllers\SoundEngineerRecordingController::class, 'getRecordingAssignments']);
    Route::post('/recordings', [\App\Http\Controllers\SoundEngineerRecordingController::class, 'createRecording']);
    Route::get('/recordings/{id}', [\App\Http\Controllers\SoundEngineerRecordingController::class, 'getRecordingDetails']);
    Route::post('/recordings/{id}/start', [\App\Http\Controllers\SoundEngineerRecordingController::class, 'startRecording']);
    Route::post('/recordings/{id}/upload-audio', [\App\Http\Controllers\SoundEngineerRecordingController::class, 'uploadAudioFiles']);
    Route::post('/recordings/{id}/complete', [\App\Http\Controllers\SoundEngineerRecordingController::class, 'completeRecording']);
});


