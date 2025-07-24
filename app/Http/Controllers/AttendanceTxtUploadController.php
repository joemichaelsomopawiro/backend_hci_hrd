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
            Log::error('Error in TXT preview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat preview file TXT: ' . $e->getMessage()
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