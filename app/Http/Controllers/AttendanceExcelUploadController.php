<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceExcelUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceExcelUploadController extends Controller
{
    protected $excelService;

    public function __construct(AttendanceExcelUploadService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * POST /api/attendance/upload-excel
     * Upload file Excel attendance dan update tabel attendance
     */
    public function uploadExcel(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
                'overwrite_existing' => 'nullable|boolean',
                'date_range_start' => 'nullable|date',
                'date_range_end' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('excel_file');
            $overwriteExisting = $request->get('overwrite_existing', false);
            $dateRangeStart = $request->get('date_range_start');
            $dateRangeEnd = $request->get('date_range_end');

            Log::info('Excel upload started', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'overwrite_existing' => $overwriteExisting,
                'date_range' => $dateRangeStart ? "{$dateRangeStart} to {$dateRangeEnd}" : 'all dates'
            ]);

            // Proses upload Excel
            $result = $this->excelService->processExcelUpload(
                $file,
                $overwriteExisting,
                $dateRangeStart,
                $dateRangeEnd
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in Excel upload: ' . $e->getMessage(), [
                'file' => $request->file('excel_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses file Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/upload-excel/preview
     * Preview data Excel sebelum upload
     */
    public function previewExcel(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('excel_file');
            
            // Preview data Excel tanpa menyimpan ke database
            $result = $this->excelService->previewExcelData($file);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in Excel preview: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat preview file Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/upload-excel/template
     * Download template Excel untuk attendance
     */
    public function downloadTemplate(): JsonResponse
    {
        try {
            $templatePath = $this->excelService->generateTemplate();
            
            return response()->json([
                'success' => true,
                'message' => 'Template berhasil dibuat',
                'data' => [
                    'template_path' => $templatePath,
                    'download_url' => url('/api/attendance/upload-excel/download-template')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating template: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/upload-excel/download-template
     * Download file template Excel
     */
    public function downloadTemplateFile()
    {
        try {
            $templatePath = storage_path('app/templates/attendance_template.xlsx');
            
            if (!file_exists($templatePath)) {
                $this->excelService->generateTemplate();
            }

            return response()->download($templatePath, 'attendance_template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]);

        } catch (\Exception $e) {
            Log::error('Error downloading template: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat download template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/upload-excel/validation-rules
     * Get validation rules untuk Excel upload
     */
    public function getValidationRules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'required_columns' => [
                    'No. ID' => 'Nomor ID karyawan (bisa NIK atau ID mesin)',
                    'Nama' => 'Nama lengkap karyawan',
                    'Tanggal' => 'Tanggal absensi (format: DD-MMM-YY)',
                    'Scan Masuk' => 'Waktu scan masuk (format: HH:MM)',
                    'Scan Pulang' => 'Waktu scan pulang (format: HH:MM)',
                    'Absent' => 'Status absen (True/False)',
                    'Jml Jam Kerja' => 'Total jam kerja (format: HH:MM)',
                    'Jml Kehadiran' => 'Total kehadiran (format: HH:MM)'
                ],
                'file_requirements' => [
                    'format' => 'Excel (.xlsx, .xls)',
                    'max_size' => '10MB',
                    'encoding' => 'UTF-8'
                ],
                'date_formats' => [
                    'accepted' => ['DD-MMM-YY', 'DD/MM/YYYY', 'YYYY-MM-DD'],
                    'example' => '14-Jul-25, 14/07/2025, 2025-07-14'
                ],
                'time_formats' => [
                    'accepted' => ['HH:MM', 'HH:MM:SS'],
                    'example' => '07:05, 16:40'
                ]
            ]
        ]);
    }
} 