<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ManualAttendanceService;
use App\Http\Requests\ManualWorshipAttendanceRequest;
use Illuminate\Support\Facades\Log;
use Exception;

class ManualWorshipAttendanceController extends Controller
{
    protected $manualAttendanceService;

    public function __construct(ManualAttendanceService $manualAttendanceService)
    {
        $this->manualAttendanceService = $manualAttendanceService;
    }

    /**
     * Simpan data absensi manual untuk semua hari
     * POST /api/ga-dashboard/manual-worship-attendance
     */
    public function store(ManualWorshipAttendanceRequest $request)
    {
        try {
            // Validasi role GA/Program Manager/HR
            $user = auth()->user();
            if (!$user || !in_array($user->role, ['General Affairs', 'Admin', 'Program Manager', 'HR'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya GA/Admin/Program Manager/HR yang dapat mengakses endpoint ini.'
                ], 403);
            }

            $attendanceData = $request->input('attendance_data');
            $date = $request->input('tanggal');

            Log::info('Manual worship attendance request', [
                'user_id' => $user->id,
                'date' => $date,
                'data_count' => count($attendanceData)
            ]);

            // Proses data melalui service
            $result = $this->manualAttendanceService->storeManualAttendance($attendanceData, $date);

            return response()->json([
                'success' => true,
                'message' => 'Data absensi manual berhasil disimpan',
                'saved_count' => $result['saved_count'],
                'total_data' => $result['total_data'],
                'data' => [
                    'date' => $date,
                    'errors' => $result['errors']
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in manual worship attendance store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data absensi dengan filter metode
     * GET /api/ga-dashboard/worship-attendance
     */
    public function index(Request $request)
    {
        try {
            $date = $request->get('date');
            $attendanceMethod = $request->get('attendance_method');

            $data = $this->manualAttendanceService->getWorshipAttendanceWithMethod($date, $attendanceMethod);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data absensi renungan berhasil diambil',
                'total_records' => $data->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Error getting worship attendance with method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update data existing untuk set attendance_method dan attendance_source
     * POST /api/ga-dashboard/update-existing-worship-data
     */
    public function updateExistingData()
    {
        try {
            // Validasi role GA/Program Manager/HR
            $user = auth()->user();
            if (!$user || !in_array($user->role, ['General Affairs', 'Admin', 'Program Manager', 'HR'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya GA/Admin/Program Manager/HR yang dapat mengakses endpoint ini.'
                ], 403);
            }

            $updatedCount = $this->manualAttendanceService->updateExistingData();

            return response()->json([
                'success' => true,
                'message' => 'Data existing berhasil diupdate',
                'data' => [
                    'updated_count' => $updatedCount
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error updating existing worship data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat update data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daftar employee untuk form input manual
     * GET /api/ga-dashboard/employees-for-manual-input
     */
    public function getEmployeesForManualInput()
    {
        try {
            $employees = \App\Models\Employee::select('id', 'nama_lengkap', 'jabatan_saat_ini')
                ->orderBy('nama_lengkap')
                ->get()
                ->map(function ($employee) {
                    return [
                        'pegawai_id' => $employee->id,
                        'nama_lengkap' => $employee->nama_lengkap,
                        'jabatan' => $employee->jabatan_saat_ini ?? '-'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $employees,
                'message' => 'Daftar pegawai berhasil diambil',
                'total_records' => $employees->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Error getting employees for manual input', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar pegawai: ' . $e->getMessage()
            ], 500);
        }
    }
}
