<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MorningReflectionAttendance;
use App\Models\LeaveRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GaDashboardController extends Controller
{
    /**
     * Mendapatkan semua data absensi renungan pagi untuk GA Dashboard
     * Menampilkan SEMUA data tanpa batasan role
     */
    public function getAllWorshipAttendance(Request $request)
    {
        try {
            $dateFilter = $request->date;
            $allData = $request->boolean('all', false);
            
            Log::info('GA Dashboard: Loading worship attendance data', [
                'date_filter' => $dateFilter,
                'all_data' => $allData,
                'user_id' => auth()->id()
            ]);

            $query = MorningReflectionAttendance::with(['employee.user'])
                ->select([
                    'morning_reflection_attendance.*',
                    'employees.nama_lengkap as employee_name',
                    'employees.jabatan_saat_ini as employee_position',
                    'users.role as user_role'
                ])
                ->leftJoin('employees', 'morning_reflection_attendance.employee_id', '=', 'employees.id')
                ->leftJoin('users', 'employees.id', '=', 'users.employee_id');

            // Filter berdasarkan tanggal jika tidak meminta semua data
            if (!$allData && $dateFilter) {
                $query->whereDate('morning_reflection_attendance.date', $dateFilter);
            }

            // Urutkan berdasarkan tanggal terbaru
            $query->orderBy('morning_reflection_attendance.date', 'desc')
                  ->orderBy('morning_reflection_attendance.created_at', 'desc');

            $attendances = $query->get();

            // Transform data untuk frontend
            $transformedData = $attendances->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'employee_id' => $attendance->employee_id,
                    'name' => $attendance->employee_name ?? 
                             ($attendance->employee->nama_lengkap ?? 'Unknown Employee'),
                    'position' => $attendance->employee_position ?? 
                                 ($attendance->employee->jabatan_saat_ini ?? '-'),
                    'date' => $attendance->date,
                    'attendance_time' => $attendance->join_time ? 
                        Carbon::parse($attendance->join_time)->format('H:i') : 
                        ($attendance->created_at ? Carbon::parse($attendance->created_at)->format('H:i') : '-'),
                    'status' => $this->calculateAttendanceStatus($attendance),
                    'testing_mode' => $attendance->testing_mode ?? false,
                    'created_at' => $attendance->created_at,
                    'raw_data' => $attendance // Untuk debugging
                ];
            });

            Log::info('GA Dashboard: Worship attendance data loaded', [
                'total_records' => $transformedData->count(),
                'date_filter' => $dateFilter
            ]);

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'message' => 'Data absensi renungan pagi berhasil diambil',
                'total_records' => $transformedData->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error loading worship attendance data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan semua data permohonan cuti untuk GA Dashboard
     * Menampilkan SEMUA data tanpa batasan role
     */
    public function getAllLeaveRequests(Request $request)
    {
        try {
            Log::info('GA Dashboard: Loading leave requests data', [
                'user_id' => auth()->id()
            ]);

            $query = LeaveRequest::with(['employee.user', 'approvedBy.user'])
                ->select([
                    'leave_requests.*',
                    'employees.nama_lengkap as employee_name',
                    'employees.jabatan_saat_ini as employee_position',
                    'users.role as user_role'
                ])
                ->leftJoin('employees', 'leave_requests.employee_id', '=', 'employees.id')
                ->leftJoin('users', 'employees.id', '=', 'users.employee_id');

            // Urutkan berdasarkan tanggal terbaru
            $query->orderBy('leave_requests.created_at', 'desc');

            $leaveRequests = $query->get();

            // Transform data untuk frontend
            $transformedData = $leaveRequests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'employee_id' => $request->employee_id,
                    'employee' => [
                        'id' => $request->employee_id,
                        'nama_lengkap' => $request->employee_name ?? 
                                        ($request->employee->nama_lengkap ?? 'Unknown Employee'),
                        'jabatan_saat_ini' => $request->employee_position ?? 
                                            ($request->employee->jabatan_saat_ini ?? '-')
                    ],
                    'leave_type' => $request->leave_type,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'total_days' => $request->total_days,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'overall_status' => $request->overall_status,
                    'status' => $request->overall_status, // Alias untuk kompatibilitas
                    'approved_by' => $request->approved_by,
                    'approved_at' => $request->approved_at,
                    'created_at' => $request->created_at,
                    'updated_at' => $request->updated_at,
                    'raw_data' => $request // Untuk debugging
                ];
            });

            Log::info('GA Dashboard: Leave requests data loaded', [
                'total_records' => $transformedData->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'message' => 'Data permohonan cuti berhasil diambil',
                'total_records' => $transformedData->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error loading leave requests data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data cuti: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan statistik absensi renungan pagi
     */
    public function getWorshipStatistics(Request $request)
    {
        try {
            $dateFilter = $request->date ?? Carbon::today()->toDateString();
            
            $query = MorningReflectionAttendance::whereDate('date', $dateFilter);
            
            $total = $query->count();
            $present = $query->clone()->where('status', 'present')->count();
            $late = $query->clone()->where('status', 'late')->count();
            $absent = $query->clone()->where('status', 'absent')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'date' => $dateFilter
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error getting worship statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik'
            ], 500);
        }
    }

    /**
     * Mendapatkan statistik permohonan cuti
     */
    public function getLeaveStatistics(Request $request)
    {
        try {
            $query = LeaveRequest::query();
            
            $total = $query->count();
            $pending = $query->where('overall_status', 'pending')->count();
            $approved = $query->where('overall_status', 'approved')->count();
            $rejected = $query->where('overall_status', 'rejected')->count();
            $expired = $query->where('overall_status', 'expired')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'expired' => $expired
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error getting leave statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik cuti'
            ], 500);
        }
    }

    /**
     * Menghitung status absensi berdasarkan waktu
     */
    private function calculateAttendanceStatus($attendance)
    {
        // Jika sudah ada status dari database, gunakan itu
        if ($attendance->status && in_array($attendance->status, ['present', 'late', 'absent'])) {
            return $attendance->status;
        }

        // Hitung berdasarkan waktu kehadiran
        $attendanceTime = null;
        if ($attendance->join_time) {
            $attendanceTime = Carbon::parse($attendance->join_time);
        } elseif ($attendance->created_at) {
            $attendanceTime = Carbon::parse($attendance->created_at);
        }

        if ($attendanceTime) {
            $totalMinutes = $attendanceTime->hour * 60 + $attendanceTime->minute;
            
            // Logika status berdasarkan waktu:
            // 07:10-07:30 (430-450 menit) = Present
            // 07:31-07:35 (451-455 menit) = Late  
            // 07:36-08:00 (456-480 menit) = Absent
            // Setelah 08:00 (>480 menit) = Absent
            
            if ($totalMinutes >= 430 && $totalMinutes <= 450) {
                return 'present'; // 07:10-07:30 (termasuk 07.30)
            } elseif ($totalMinutes >= 451 && $totalMinutes <= 455) {
                return 'late'; // 07:31-07:35
            } elseif ($totalMinutes >= 456) {
                return 'absent'; // 07:36 ke atas
            } else {
                return 'absent'; // Sebelum 07:10
            }
        }

        // Default fallback
        return 'present';
    }
} 