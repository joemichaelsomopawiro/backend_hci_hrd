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
    // Step 3: Menulis Script & Mengatur Syuting
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
    // Step 2: Menerima Program & Step 4: Approve Script & Budget
    Route::prefix('producer')->group(function () {
        // Existing concept routes
        Route::get('/concepts', [PrProducerController::class, 'listConceptsForApproval']);
        Route::post('/concepts/{id}/mark-read', [PrProducerController::class, 'markConceptAsRead']);

        // Creative Work Approval
        Route::post('/creative-works/{id}/approve-script', [PrProducerController::class, 'approveCreativeWorkScript']);
        Route::post('/creative-works/{id}/approve-budget', [PrProducerController::class, 'approveCreativeWorkBudget']);
        Route::post('/creative-works/{id}/reject', [PrProducerController::class, 'rejectCreativeWork']);

        // Production schedules
        Route::post('/production-schedules', [PrProducerController::class, 'createProductionSchedule']);
        Route::put('/production-schedules/{id}', [PrProducerController::class, 'updateProductionSchedule']);
    });

    // ==================== PRODUKSI ROUTES ====================
    // Step 5: Pinjam Alat dan Syuting
    Route::prefix('produksi')->group(function () {
        Route::get('/works', [PrProduksiController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrProduksiController::class, 'acceptWork']);
        Route::post('/works/{id}/request-equipment', [PrProduksiController::class, 'requestEquipment']);
        Route::post('/works/{id}/upload-shooting-results', [PrProduksiController::class, 'uploadShootingResults']);
    });

    // ==================== EDITOR ROUTES ====================
    // Step 6: Edit Konten
    Route::prefix('editor')->group(function () {
        Route::get('/works', [PrEditorController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrEditorController::class, 'acceptWork']);
        Route::post('/works/{id}/upload', [PrEditorController::class, 'upload']);
    });

    // ==================== PROMOSI ROUTES ====================
    // Step 10: Share Konten
    Route::prefix('promosi')->group(function () {
        Route::get('/works', [PrPromosiController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrPromosiController::class, 'acceptWork']);
        Route::post('/works/{id}/upload-content', [PrPromosiController::class, 'uploadContent']);
        Route::post('/works/{id}/share-content', [PrPromosiController::class, 'shareContent']);
    });

    // ==================== QUALITY CONTROL ROUTES ====================
    // Steps 7 & 8: Quality Check (Manager Distribusi & QC)
    Route::prefix('quality-control')->group(function () {
        Route::get('/works', [PrQualityControlController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrQualityControlController::class, 'acceptWork']);
        Route::post('/works/{id}/submit-qc-form', [PrQualityControlController::class, 'submitQCForm']);
        Route::post('/works/{id}/approve', [PrQualityControlController::class, 'approve']);
        Route::post('/works/{id}/reject', [PrQualityControlController::class, 'reject']);
    });

    // ==================== BROADCASTING ROUTES ====================
    // Step 9: Upload
    Route::prefix('broadcasting')->group(function () {
        Route::get('/works', [PrBroadcastingController::class, 'index']);
        Route::post('/works/{id}/accept-work', [PrBroadcastingController::class, 'acceptWork']);
        Route::post('/works/{id}/upload-youtube', [PrBroadcastingController::class, 'uploadYouTube']);
        Route::post('/works/{id}/publish', [PrBroadcastingController::class, 'publish']);
    });

    // ==================== MANAGER PROGRAM ROUTES ====================
    // Step 1: Membuat Program & Overall Management
    Route::prefix('manager')->group(function () {
        // Existing routes
        Route::get('/programs', [PrManagerProgramController::class, 'listPrograms']);
        Route::post('/programs', [PrManagerProgramController::class, 'createProgram']);
        Route::get('/programs/{id}', [PrManagerProgramController::class, 'showProgram']);
        Route::put('/programs/{id}', [PrManagerProgramController::class, 'updateProgram']);
        Route::delete('/programs/{id}', [PrManagerProgramController::class, 'deleteProgram']);
        Route::post('/programs/{id}/submit-to-distribusi', [PrManagerProgramController::class, 'submitToDistribusi']);

        // Concepts
        Route::post('/programs/{programId}/concepts', [PrManagerProgramController::class, 'createConcept']);
        Route::put('/concepts/{id}', [PrManagerProgramController::class, 'updateConcept']);
        Route::delete('/concepts/{id}', [PrManagerProgramController::class, 'deleteConcept']);

        // Episodes
        Route::put('/episodes/{id}', [PrManagerProgramController::class, 'updateEpisode']);
        Route::delete('/episodes/{id}', [PrManagerProgramController::class, 'deleteEpisode']);
    });
});
