<?php

use Illuminate\Support\Facades\Route;

// Music Program API Routes
// File ini sementara kosong karena routes music program sudah dipindah ke live_tv_api.php
// Jika diperlukan routes khusus untuk music program, tambahkan di sini

use App\Http\Controllers\Api\MusicHistoryController;
use App\Http\Controllers\Api\Music\EquipmentLoanController;

Route::group(['prefix' => 'history'], function () {
    Route::get('/', [MusicHistoryController::class, 'index']);
    Route::get('/export', [MusicHistoryController::class, 'export']);
    Route::get('/filters', [MusicHistoryController::class, 'getFilters']);
});

Route::group(['prefix' => 'equipment-loans'], function () {
    Route::post('/', [EquipmentLoanController::class, 'store']);
    Route::post('/{id}/return', [EquipmentLoanController::class, 'returnLoan']);
    Route::post('/return-by-recording/{recordingId}', [EquipmentLoanController::class, 'returnByRecording']);
});

