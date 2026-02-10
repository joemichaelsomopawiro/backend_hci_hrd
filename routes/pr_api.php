<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Pr\PrCreativeController;
use App\Http\Controllers\Api\Pr\PrProduksiController;
use App\Http\Controllers\Api\Pr\PrEditorController;
use App\Http\Controllers\Api\Pr\PrPromosiController;
use App\Http\Controllers\Api\Pr\PrQualityControlController;
use App\Http\Controllers\Api\Pr\PrBroadcastingController;
use App\Http\Controllers\Api\PrProducerController;
use App\Http\Controllers\Api\PrManagerProgramController;
use App\Http\Controllers\Api\Pr\PrDistribusiController;

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
    Route::get('/creative/highlights', [\App\Http\Controllers\Api\Pr\PrCreativeController::class, 'getHighlights']);

    // ==================== NOTIFICATION ROUTES ====================
    Route::prefix('notifications')->group(function () {
        Route::get('/unread-count', [\App\Http\Controllers\Api\Pr\PrNotificationController::class, 'unreadCount']);
        Route::get('/', [\App\Http\Controllers\Api\Pr\PrNotificationController::class, 'index']);
        Route::put('/{id}/read', [\App\Http\Controllers\Api\Pr\PrNotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [\App\Http\Controllers\Api\Pr\PrNotificationController::class, 'markAllAsRead']);
    });

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
        Route::post('/production-schedules', [PrProducerController::class, 'createProductionSchedule']);
        Route::put('/production-schedules/{id}', [PrProducerController::class, 'updateProductionSchedule']);
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
    });

    // ==================== ART & SET PROPERTY ROUTES ====================
    Route::prefix('art')->group(function () {
        Route::get('/inventory', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'getInventory']);
        Route::post('/inventory', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'createInventoryItem']);
        Route::put('/inventory/{id}', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'updateInventoryItem']);
        Route::delete('/inventory/{id}', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'deleteInventoryItem']);

        Route::get('/loans', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'getLoans']);
        Route::post('/loans/{id}/approve', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'approveLoan']);
        Route::post('/loans/{id}/reject', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'rejectLoan']);
        Route::post('/loans/{id}/borrow', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'markAsBorrowed']);
        Route::post('/loans/{id}/return', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'markAsReturned']);

        Route::get('/loan-history', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'getLoanHistory']);
        Route::post('/loan-history/{id}/description', [\App\Http\Controllers\Api\Pr\PrArtController::class, 'updateHistoryDescription']);
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
        Route::get('/works', [PrPromosiController::class, 'index']);
        Route::post('/works', [PrPromosiController::class, 'store']); // Create new work
        Route::get('/works/{id}', [PrPromosiController::class, 'show']); // Get work detail
        Route::put('/works/{id}', [PrPromosiController::class, 'update']); // Update work
        Route::post('/works/{id}/accept', [PrPromosiController::class, 'acceptWork']); // Accept work
        Route::post('/works/{id}/complete', [PrPromosiController::class, 'complete']); // Complete work
        Route::post('/works/{id}/upload-content', [PrPromosiController::class, 'uploadContent']);
        Route::post('/works/{id}/share-content', [PrPromosiController::class, 'shareContent']);
        Route::post('/works/{id}/share', [PrPromosiController::class, 'shareContent']); // Alias for frontend
    });

    // ==================== QUALITY CONTROL ROUTES ====================
    Route::prefix('quality-control')->group(function () {
        Route::get('/works', [PrQualityControlController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrQualityControlController::class, 'acceptWork']);
        Route::post('/works/{id}/submit-qc-form', [PrQualityControlController::class, 'submitQCForm']);
        Route::post('/works/{id}/approve', [PrQualityControlController::class, 'approve']);
        Route::post('/works/{id}/reject', [PrQualityControlController::class, 'reject']);
    });

    // ==================== BROADCASTING ROUTES ====================
    Route::prefix('broadcasting')->group(function () {
        Route::get('/works', [PrBroadcastingController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrBroadcastingController::class, 'acceptWork']);
        Route::put('/works/{id}', [PrBroadcastingController::class, 'update']); // Generic update
        Route::post('/works/{id}/upload-youtube', [PrBroadcastingController::class, 'uploadYouTube']);
        Route::post('/works/{id}/publish', [PrBroadcastingController::class, 'publish']);
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
