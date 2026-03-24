<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Pr\PrCreativeController;
use App\Http\Controllers\Api\Pr\PrProduksiController;
use App\Http\Controllers\Api\Pr\PrEditorController;
use App\Http\Controllers\Api\Pr\PrPromosiController;
use App\Http\Controllers\Api\Pr\PrDesignGrafisController;
use App\Http\Controllers\Api\Pr\PrQualityControlController;
use App\Http\Controllers\Api\Pr\PrBroadcastingController;
use App\Http\Controllers\Api\PrProducerController;
use App\Http\Controllers\Api\PrManagerProgramController;
use App\Http\Controllers\Api\Pr\PrDistribusiController;
use App\Http\Controllers\Api\Pr\PrDashboardController;
use App\Http\Controllers\Api\Pr\PrNotificationController;
use App\Http\Controllers\Api\Pr\PrArtController;
use App\Http\Controllers\Api\Pr\PrEditorPromosiController;
use App\Http\Controllers\Api\Pr\PrManagerDistribusiQcController;

/*
|--------------------------------------------------------------------------
| Program Regular API Routes
|--------------------------------------------------------------------------
|
| All API routes for Program Regular workflow
| Prefix: /api/pr/
*/

Route::prefix('pr')->middleware(['auth:sanctum'])->group(function () {

    // ==================== CREATIVE ROUTES ====================
    // Creative Dashboard Highlights
    Route::get('/creative/highlights', [PrCreativeController::class, 'getHighlights']);

    // ==================== NOTIFICATION ROUTES ====================
    Route::prefix('notifications')->group(function () {
        Route::get('/unread-count', [PrNotificationController::class, 'unreadCount']);
        Route::get('/', [PrNotificationController::class, 'index']);
        Route::put('/{id}/read', [PrNotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [PrNotificationController::class, 'markAllAsRead']);
    });

    // ==================== DASHBOARD UTAMA ROUTES ====================
    Route::get('/dashboard/schedules', [PrDashboardController::class, 'getSchedules']);
    Route::post('/dashboard/calendar-events', [PrDashboardController::class, 'saveCalendarEvent']);
    Route::delete('/dashboard/calendar-events/{id}', [PrDashboardController::class, 'deleteCalendarEvent']);

    Route::prefix('creative')->group(function () {
        Route::get('/episodes/available', [PrCreativeController::class, 'getAvailableEpisodes']);
        Route::get('/works', [PrCreativeController::class, 'index']);
        Route::post('/works', [PrCreativeController::class, 'store']);
        Route::get('/works/{id}', [PrCreativeController::class, 'show']);
        Route::post('/works/{id}/accept-work', [PrCreativeController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrCreativeController::class, 'update']);
        Route::post('/works/{id}/submit', [PrCreativeController::class, 'submit']);
        Route::post('/episodes/{id}/files', [PrCreativeController::class, 'uploadFile']);
    });

    // ==================== PRODUCER ROUTES ====================
    Route::prefix('producer')->group(function () {
        Route::get('/episodes/review', [PrProducerController::class, 'getEpisodesForReview']);
        Route::get('/concepts', [PrProducerController::class, 'listConceptsForApproval']);
        Route::post('/concepts/{id}/mark-read', [PrProducerController::class, 'markConceptAsRead']);
        Route::post('/creative-works/{id}/approve-script', [PrProducerController::class, 'approveCreativeWorkScript']);
        Route::post('/creative-works/{id}/approve-budget', [PrProducerController::class, 'approveCreativeWorkBudget']);
        Route::post('/creative-works/{id}/reject', [PrProducerController::class, 'rejectCreativeWork']);
        Route::post('/episodes/{id}/request-budget-approval', [PrProducerController::class, 'requestBudgetApproval']);
        Route::get('/production-schedules', [PrProducerController::class, 'listProductionSchedules']);
        Route::post('/production-schedules', [PrProducerController::class, 'createProductionSchedule']);
        Route::put('/production-schedules/{id}', [PrProducerController::class, 'updateProductionSchedule']);

        // Episode Crews Management
        Route::get('/episodes/{id}/crews', [PrProducerController::class, 'getEpisodeCrews']);
        Route::post('/episodes/{id}/crews', [PrProducerController::class, 'addEpisodeCrew']);
        Route::patch('/episodes/{id}/crews/{crewId}', [PrProducerController::class, 'updateEpisodeCrew']);
        Route::delete('/episodes/{id}/crews/{crewId}', [PrProducerController::class, 'removeEpisodeCrew']);
        Route::post('/episodes/{id}/approve', [PrProducerController::class, 'approveEpisode']);
    });

    // ==================== PRODUKSI ROUTES ====================
    Route::prefix('produksi')->group(function () {
        Route::get('/works', [PrProduksiController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrProduksiController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrProduksiController::class, 'update']); // Generic update
        Route::post('/works/{id}/request-equipment', [PrProduksiController::class, 'requestEquipment']);
        Route::post('/works/{id}/upload-shooting-results', [PrProduksiController::class, 'uploadShootingResults']);
        Route::get('/available-equipment', [PrProduksiController::class, 'getAvailableEquipment']);
        Route::post('/works/{id}/complete', [PrProduksiController::class, 'completeWork']);
        Route::post('/works/{id}/attendance', [PrProduksiController::class, 'submitAttendance']); // Coordinator submits attendance
        Route::post('/works/{id}/request-return', [PrProduksiController::class, 'requestReturn']); // Coordinator requests equipment return
        Route::post('/works/{id}/cancel-loan', [PrProduksiController::class, 'cancelLoan']); // Cancel pending loan
        Route::get('/bundle-episodes', [PrProduksiController::class, 'getBundleEpisodes']); // All non-completed episodes for bundle selection
    });

    // ==================== ART & SET PROPERTY ROUTES ====================
    Route::prefix('art')->group(function () {
        Route::get('/inventory', [PrArtController::class, 'getInventory']);
        Route::post('/inventory', [PrArtController::class, 'createInventoryItem']);
        Route::put('/inventory/{id}', [PrArtController::class, 'updateInventoryItem']);
        Route::delete('/inventory/{id}', [PrArtController::class, 'deleteInventoryItem']);

        Route::get('/loans', [PrArtController::class, 'getLoans']);
        Route::post('/loans/{id}/approve', [PrArtController::class, 'approveLoan']);
        Route::post('/loans/{id}/reject', [PrArtController::class, 'rejectLoan']);
        Route::post('/loans/{id}/borrow', [PrArtController::class, 'markAsBorrowed']);
        Route::post('/loans/{id}/return', [PrArtController::class, 'markAsReturned']);
        Route::post('/loans/{id}/approve-return', [PrArtController::class, 'approveReturn']);

        Route::get('/loan-history', [PrArtController::class, 'getLoanHistory']);
        Route::post('/loan-history/{id}/description', [PrArtController::class, 'updateHistoryDescription']);

        Route::get('/presets', [PrArtController::class, 'getPresets']);
        Route::post('/presets', [PrArtController::class, 'createPreset']);
        Route::put('/presets/{id}', [PrArtController::class, 'updatePreset']);
        Route::delete('/presets/{id}', [PrArtController::class, 'deletePreset']);
    });

    // ==================== EDITOR ROUTES ====================
    Route::prefix('editor')->group(function () {
        Route::get('/works', [PrEditorController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrEditorController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrEditorController::class, 'update']); // Generic update
        Route::post('/works/{id}/upload', [PrEditorController::class, 'upload']);
        Route::post('/works/{id}/request-files', [PrEditorController::class, 'requestFiles']);
    });

    // ==================== PROMOSI ROUTES ====================
    Route::prefix('promosi')->group(function () {
        Route::get('/episodes', [PrPromosiController::class, 'getEpisodes']); // For Share Konten dropdown
        Route::get('/share-konten/{episodeId}', [PrPromosiController::class, 'getShareKonten']); // Get saved share tasks
        Route::post('/share-konten/{episodeId}', [PrPromosiController::class, 'saveShareKonten']); // Save share tasks
        Route::get('/works', [PrPromosiController::class, 'index']);
        Route::post('/works', [PrPromosiController::class, 'store']); // Create new work
        Route::get('/works/{id}', [PrPromosiController::class, 'show']); // Get work detail
        Route::post('/works/{id}/update', [PrPromosiController::class, 'update']); // Alias for frontend
        Route::post('/works/{id}/request-equipment', [PrPromosiController::class, 'requestEquipment']);
        Route::post('/works/{id}/request-return', [PrPromosiController::class, 'requestReturn']);
        Route::post('/works/{id}/cancel-loan', [PrPromosiController::class, 'cancelLoan']);
    });

    // ==================== EDITOR PROMOSI ROUTES ====================
    Route::prefix('editor-promosi')->group(function () {
        Route::get('/works', [PrEditorPromosiController::class, 'index']);
        Route::get('/works/{id}', [PrEditorPromosiController::class, 'show']);
        Route::post('/works/{id}/accept-work', [PrEditorPromosiController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrEditorPromosiController::class, 'updateProgress']);
        Route::post('/works/{id}/submit', [PrEditorPromosiController::class, 'submit']);
        Route::post('/works/{id}/approve', [PrEditorPromosiController::class, 'approve']);
        Route::get('/check-editor-status/{episodeId}', [PrEditorPromosiController::class, 'checkEditorStatus']);
    });

    // ==================== DESIGN GRAFIS ROUTES ====================
    Route::prefix('design-grafis')->group(function () {
        Route::get('/works', [PrDesignGrafisController::class, 'index']);
        Route::get('/works/{id}', [PrDesignGrafisController::class, 'show']);
        Route::post('/works/{id}/accept-work', [PrDesignGrafisController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrDesignGrafisController::class, 'updateProgress']);
        Route::post('/works/{id}/submit', [PrDesignGrafisController::class, 'submit']);
    });

    // ==================== QUALITY CONTROL ROUTES ====================
    Route::prefix('quality-control')->group(function () {
        Route::get('/works', [PrQualityControlController::class, 'index']);
        Route::get('/works/{id}', [PrQualityControlController::class, 'show']);
        Route::post('/works/{id}/accept-work', [PrQualityControlController::class, 'acceptWork']);
        Route::post('/works/{id}/submit-qc-form', [PrQualityControlController::class, 'submitQCForm']);
        Route::post('/works/{id}/approve', [PrQualityControlController::class, 'approve']);
        Route::post('/works/{id}/reject', [PrQualityControlController::class, 'reject']);
        Route::post('/works/{id}/checklist', [PrQualityControlController::class, 'updateChecklistItem']);
        Route::post('/works/{id}/finish', [PrQualityControlController::class, 'finish']);
    });

    // ==================== MANAGER DISTRIBUSI QC ROUTES ====================
    Route::prefix('manager-distribusi-qc')->group(function () {
        Route::get('/pending-count', [PrManagerDistribusiQcController::class, 'pendingCount']);
        Route::get('/works', [PrManagerDistribusiQcController::class, 'index']);
        Route::get('/works/{id}', [PrManagerDistribusiQcController::class, 'show']);
        Route::post('/works/{id}/accept-work', [PrManagerDistribusiQcController::class, 'acceptWork']);
        Route::put('/works/{id}/checklist', [PrManagerDistribusiQcController::class, 'updateChecklistItem']);
        Route::post('/works/{id}/finish', [PrManagerDistribusiQcController::class, 'finish']);
    });

    // ==================== BROADCASTING ROUTES ====================
    Route::prefix('broadcasting')->group(function () {
        Route::get('/works', [PrBroadcastingController::class, 'index']);
        Route::get('/works/{id}', [PrBroadcastingController::class, 'show']);
        Route::post('/works/{id}/accept-work', [PrBroadcastingController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrBroadcastingController::class, 'update']); // Generic update
        Route::post('/works/{id}/upload-youtube', [PrBroadcastingController::class, 'uploadYouTube']);
        Route::post('/works/{id}/publish', [PrBroadcastingController::class, 'publish']);
        Route::post('/works/{id}/finish', [PrBroadcastingController::class, 'finish']);
    });

    // ==================== DISTRIBUSI ROUTES ====================
    Route::prefix('distribusi')->group(function () {
        Route::get('/programs/{id}/concept', [PrDistribusiController::class, 'viewProgramConcept']);
        Route::get('/programs/{id}/production-schedules', [PrDistribusiController::class, 'viewProductionSchedules']);
        Route::get('/episodes/{id}/shooting-schedule', [PrDistribusiController::class, 'viewShootingSchedule']);
        Route::get('/programs/{id}/files', [PrDistribusiController::class, 'viewProgramFiles']);
        Route::post('/programs/{id}/distribution-schedules', [PrDistribusiController::class, 'createDistributionSchedule']);
        Route::put('/distribution-schedules/{id}', [PrDistribusiController::class, 'updateDistributionSchedule']);
        Route::delete('/distribution-schedules/{id}', [PrDistribusiController::class, 'deleteDistributionSchedule']);
        Route::post('/episodes/{id}/mark-aired', [PrDistribusiController::class, 'markAsAired']);
        Route::post('/programs/{id}/distribution-reports', [PrDistribusiController::class, 'createDistributionReport']);
        Route::get('/distribution-reports', [PrDistribusiController::class, 'listDistributionReports']);
        Route::put('/distribution-reports/{id}', [PrDistribusiController::class, 'updateDistributionReport']);
        Route::delete('/distribution-reports/{id}', [PrDistribusiController::class, 'deleteDistributionReport']);
        Route::get('/programs/{id}/revision-history', [PrDistribusiController::class, 'viewDistribusiRevisionHistory']);
    });

    // ==================== MANAGER PROGRAM ROUTES ====================
    // ==================== MANAGER PROGRAM ROUTES ====================
    Route::prefix('manager-program')->group(function () {
        Route::get('/programs', [PrManagerProgramController::class, 'listPrograms']);
        Route::post('/programs', [PrManagerProgramController::class, 'createProgram']);
        Route::get('/programs/{id}', [PrManagerProgramController::class, 'showProgram']);
        Route::put('/programs/{id}', [PrManagerProgramController::class, 'updateProgram']);
        Route::delete('/programs/{id}', [PrManagerProgramController::class, 'deleteProgram']);
        Route::post('/programs/{id}/submit-to-distribusi', [PrManagerProgramController::class, 'submitToDistribusi']);
        Route::post('/programs/{programId}/concepts', [PrManagerProgramController::class, 'createConcept']);
        Route::put('/concepts/{id}', [PrManagerProgramController::class, 'updateConcept']);
        Route::delete('/concepts/{id}', [PrManagerProgramController::class, 'deleteConcept']);
        Route::get('/episodes/{id}', [PrManagerProgramController::class, 'getEpisode']);
        Route::put('/episodes/{id}', [PrManagerProgramController::class, 'updateEpisode']);
        Route::delete('/episodes/{id}', [PrManagerProgramController::class, 'deleteEpisode']);

        // Budget Approvals
        Route::get('/budget-approvals', [PrManagerProgramController::class, 'getPendingBudgetApprovals']);
        Route::post('/episodes/{id}/approve-budget', [PrManagerProgramController::class, 'approveBudget']);
        Route::post('/episodes/{id}/reject-budget', [PrManagerProgramController::class, 'rejectBudget']); // Reject special budget

        // Episode Crews Management (Shooting & Setting Team)
        Route::get('/episodes/{id}/crews', [PrManagerProgramController::class, 'getEpisodeCrews']);
        Route::post('/episodes/{id}/crews', [PrManagerProgramController::class, 'addEpisodeCrew']);
        Route::delete('/episodes/{id}/crews/{crewId}', [PrManagerProgramController::class, 'removeEpisodeCrew']);
    });
});
