<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsappBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WhatsappBotController
 * 
 * Endpoint untuk admin panel mengelola status WhatsApp bot
 * (lihat status, ambil QR code, logout bot)
 */
class WhatsappBotController extends Controller
{
    private WhatsappBotService $botService;

    public function __construct(WhatsappBotService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * GET /api/admin/whatsapp-bot/status
     * Ambil status koneksi bot
     */
    public function status()
    {
        try {
            $status = $this->botService->getStatus();

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('[WhatsappBotController] Error get status', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil status bot.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/admin/whatsapp-bot/qr
     * Ambil QR code untuk di-scan
     */
    public function qr()
    {
        try {
            $qr = $this->botService->getQR();

            return response()->json($qr);
        } catch (\Exception $e) {
            Log::error('[WhatsappBotController] Error get QR', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil QR code.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/whatsapp-bot/logout
     * Logout bot (hapus session, perlu scan QR ulang)
     */
    public function logout()
    {
        try {
            $result = $this->botService->logout();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('[WhatsappBotController] Error logout', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal logout bot.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
