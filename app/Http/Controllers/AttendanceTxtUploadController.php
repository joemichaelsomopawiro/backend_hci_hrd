<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\TxtAttendanceUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceTxtUploadController extends Controller
{
    protected $txtService;

    public function __construct(TxtAttendanceUploadService $txtService)
    {
        $this->txtService = $txtService;
    }

    /**
     * POST /api/attendance/upload-txt
     * Upload file TXT attendance dan update tabel attendance
     */
    public function uploadTxt(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'txt_file' => 'required|file|mimes:txt|max:10240', // Max 10MB
            ], [
                'txt_file.required' => 'File TXT wajib diupload.',
                'txt_file.file' => 'File yang diupload tidak valid.',
                'txt_file.mimes' => 'Hanya file .txt yang diperbolehkan.',
                'txt_file.max' => 'Ukuran file maksimal 10MB.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal. Silakan cek format dan ukuran file.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('txt_file');
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan dalam request. Pastikan field name adalah txt_file.',
                ], 422);
            }

            Log::info('TXT upload started', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);

            $result = $this->txtService->processTxtUpload($file);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in TXT upload: ' . $e->getMessage(), [
                'file' => $request->file('txt_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses file TXT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/upload-txt/preview
     * Preview data TXT sebelum upload
     */
    public function previewTxt(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'txt_file' => 'required|file|mimes:txt|max:10240',
            ], [
                'txt_file.required' => 'File TXT wajib diupload.',
                'txt_file.file' => 'File yang diupload tidak valid.',
                'txt_file.mimes' => 'Hanya file .txt yang diperbolehkan.',
                'txt_file.max' => 'Ukuran file maksimal 10MB.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal. Silakan cek format dan ukuran file.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('txt_file');
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan dalam request. Pastikan field name adalah txt_file.',
                ], 422);
            }

            $result = $this->txtService->previewTxtData($file);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in TXT preview: ' . $e->getMessage(), [
                'file' => $request->file('txt_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat preview file TXT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/upload-txt/manual-sync
     * Manual bulk sync untuk semua attendance yang belum ter-sync dengan employee_id
     */
    public function manualBulkSync(): JsonResponse
    {
        try {
            Log::info('Manual bulk sync started by user');
            
            $result = $this->txtService->manualBulkSyncAttendance();
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in manual bulk sync: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat manual sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/upload-txt/sync-status
     * Cek status sync employee_id di tabel attendance
     */
    public function getSyncStatus(): JsonResponse
    {
        try {
            $totalAttendance = \App\Models\Attendance::count();
            $syncedAttendance = \App\Models\Attendance::whereNotNull('employee_id')->count();
            $unsyncedAttendance = $totalAttendance - $syncedAttendance;
            
            // Ambil beberapa contoh data yang belum ter-sync
            $unsyncedSamples = \App\Models\Attendance::whereNull('employee_id')
                                                    ->select('user_name', 'card_number', 'date')
                                                    ->limit(10)
                                                    ->get();
            
            // Ambil beberapa contoh data yang sudah ter-sync
            $syncedSamples = \App\Models\Attendance::whereNotNull('employee_id')
                                                  ->with('employee:id,nama_lengkap')
                                                  ->select('user_name', 'card_number', 'date', 'employee_id')
                                                  ->limit(10)
                                                  ->get();
            
            $syncPercentage = $totalAttendance > 0 ? round(($syncedAttendance / $totalAttendance) * 100, 2) : 0;
            
            return response()->json([
                'success' => true,
                'message' => 'Status sync berhasil diambil',
                'data' => [
                    'total_attendance' => $totalAttendance,
                    'synced_attendance' => $syncedAttendance,
                    'unsynced_attendance' => $unsyncedAttendance,
                    'sync_percentage' => $syncPercentage,
                    'unsynced_samples' => $unsyncedSamples,
                    'synced_samples' => $syncedSamples,
                    'total_employees' => \App\Models\Employee::count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting sync status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/convert-raw-txt
     * Konversi file TXT raw ke format fixed width dan mapping employee_id
     */
    public function convertRawTxt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'txt_file' => 'required|file|mimes:txt|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal. Silakan cek format dan ukuran file.',
                'errors' => $validator->errors()
            ], 422);
        }
        $file = $request->file('txt_file');
        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan dalam request. Pastikan field name adalah txt_file.',
            ], 422);
        }
        $service = $this->txtService;
        $fixedTxt = $service->convertRawTxtToFixedWidth($file, true); // true = mapping employee_id
        return response($fixedTxt, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="converted_fixed_width.txt"'
        ]);
    }
} 