<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Employee;
use Exception;

class PersonalAttendanceController extends Controller
{
    /**
     * Get personal office attendance data for dashboard
     * GET /api/personal/office-attendance?employee_id={id}
     */
    public function getPersonalOfficeAttendance(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->get('employee_id');
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
            
            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter employee_id diperlukan'
                ], 422);
            }

            Log::info('Personal office attendance request', [
                'employee_id' => $employeeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => auth()->id()
            ]);

            // Ambil data employee
            $employee = Employee::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee tidak ditemukan'
                ], 404);
            }

            // Ambil data absensi kantor
            $officeAttendances = Attendance::where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'desc')
                ->get();

            // Ambil data cuti yang disetujui
            $approvedLeaves = LeaveRequest::where('employee_id', $employeeId)
                ->where('overall_status', 'approved')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                          });
                })
                ->get();

            // Transform data absensi
            $transformedAttendances = $officeAttendances->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date->format('Y-m-d'),
                    'day_name' => $attendance->date->format('l'),
                    'check_in' => $attendance->check_in ? 
                        Carbon::parse($attendance->check_in)->format('H:i') : null,
                    'check_out' => $attendance->check_out ? 
                        Carbon::parse($attendance->check_out)->format('H:i') : null,
                    'status' => $attendance->status,
                    'status_label' => $this->getStatusLabel($attendance->status),
                    'work_hours' => $attendance->work_hours ?? 0,
                    'late_minutes' => $attendance->late_minutes ?? 0,
                    'early_leave_minutes' => $attendance->early_leave_minutes ?? 0,
                    'overtime_hours' => $attendance->overtime_hours ?? 0
                ];
            });

            // Hitung statistik
            $statistics = $this->calculateAttendanceStatistics($officeAttendances, $approvedLeaves);

            // Transform data cuti untuk frontend (leave_records)
            $transformedLeaves = $approvedLeaves->map(function ($leave) {
                $startDate = Carbon::parse($leave->start_date)->toDateString();
                $endDate = Carbon::parse($leave->end_date ?? $leave->start_date)->toDateString();

                // Generate daftar tanggal inklusif untuk rentang cuti
                $dates = [];
                $cursor = Carbon::parse($startDate);
                $last = Carbon::parse($endDate);
                while ($cursor->lte($last)) {
                    $dates[] = $cursor->toDateString();
                    $cursor->addDay();
                }

                // Normalisasi tipe (frontend membaca leave_type atau type)
                $normalizedType = strtolower($leave->leave_type);

                return [
                    'id' => $leave->id,
                    'employee_id' => $leave->employee_id,
                    'leave_type' => $normalizedType, // e.g. 'annual', 'sick', 'emergency', ...
                    'type' => $normalizedType,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $this->calculateLeaveDays($leave),
                    'overall_status' => $leave->overall_status,
                    'leave_dates' => $dates,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'nama_lengkap' => $employee->nama_lengkap,
                        'jabatan_saat_ini' => $employee->jabatan_saat_ini
                    ],
                    'attendance_records' => $transformedAttendances,
                    'statistics' => $statistics,
                    // Tambahkan summary untuk kompatibilitas frontend (mirroring statistics)
                    'summary' => [
                        'hadir' => $statistics['hadir'] ?? 0,
                        'izin' => $statistics['izin'] ?? 0,
                        'sakit' => $statistics['sakit'] ?? 0,
                        'total_work_hours' => $statistics['total_work_hours'] ?? 0,
                    ],
                    // Kirim daftar cuti yang disetujui untuk dihitung di frontend sebagai total cuti dan penggabungan baris tabel
                    'leave_records' => $transformedLeaves,
                    'date_range' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'total_records' => $transformedAttendances->count()
                ],
                'message' => 'Data absensi kantor berhasil diambil'
            ], 200);

        } catch (Exception $e) {
            Log::error('Personal office attendance error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'employee_id' => $employeeId ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate attendance statistics
     */
    private function calculateAttendanceStatistics($attendances, $leaves)
    {
        $statistics = [
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'total_work_hours' => 0,
            'total_overtime_hours' => 0,
            'total_late_minutes' => 0,
            'total_early_leave_minutes' => 0
        ];

        // Hitung dari absensi kantor
        foreach ($attendances as $attendance) {
            switch ($attendance->status) {
                case 'present_ontime':
                case 'present_late':
                    $statistics['hadir']++;
                    break;
                case 'permission':
                    $statistics['izin']++;
                    break;
                case 'sick_leave':
                    $statistics['sakit']++;
                    break;
            }

            $statistics['total_work_hours'] += $attendance->work_hours ?? 0;
            $statistics['total_overtime_hours'] += $attendance->overtime_hours ?? 0;
            $statistics['total_late_minutes'] += $attendance->late_minutes ?? 0;
            $statistics['total_early_leave_minutes'] += $attendance->early_leave_minutes ?? 0;
        }

        // Hitung dari cuti yang disetujui
        foreach ($leaves as $leave) {
            $leaveDays = $this->calculateLeaveDays($leave);
            
            switch ($leave->leave_type) {
                case 'permission':
                    $statistics['izin'] += $leaveDays;
                    break;
                case 'sick_leave':
                    $statistics['sakit'] += $leaveDays;
                    break;
            }
        }

        return $statistics;
    }

    /**
     * Calculate number of leave days
     */
    private function calculateLeaveDays($leave)
    {
        $startDate = Carbon::parse($leave->start_date);
        $endDate = Carbon::parse($leave->end_date);
        
        return $startDate->diffInDays($endDate) + 1;
    }

    /**
     * Get status label in Indonesian
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'present_ontime' => 'Hadir Tepat Waktu',
            'present_late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'permission' => 'Izin',
            'sick_leave' => 'Sakit',
            'on_leave' => 'Cuti',
            'weekend' => 'Weekend',
            'holiday' => 'Hari Libur'
        ];

        return $labels[$status] ?? $status;
    }
} 