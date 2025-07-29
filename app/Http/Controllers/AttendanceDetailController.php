<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\Employee;
use Exception;

class AttendanceDetailController extends Controller
{
    /**
     * Get detailed attendance data for all employees
     */
    public function getAllAttendanceDetail(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $searchQuery = $request->get('search');
            $month = $request->get('month'); // Format: YYYY-MM

            // Build query
            $query = Attendance::with(['employee'])
                ->where('type', 'office')
                ->orderBy('date', 'desc');

            // Filter by date range
            if ($startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }

            // Filter by month (YYYY-MM format)
            if ($month) {
                $year = substr($month, 0, 4);
                $monthNum = substr($month, 5, 2);
                $query->whereYear('date', $year)
                      ->whereMonth('date', $monthNum);
            }

            // Filter by employee name or position
            if ($searchQuery) {
                $query->whereHas('employee', function ($q) use ($searchQuery) {
                    $q->where('name', 'like', "%{$searchQuery}%")
                      ->orWhere('position', 'like', "%{$searchQuery}%");
                });
            }

            $attendances = $query->get();

            // Transform data
            $transformedData = $attendances->map(function ($attendance) {
                return $this->transformAttendanceData($attendance);
            });

            // Calculate statistics
            $statistics = $this->calculateStatistics($attendances);

            return response()->json([
                'success' => true,
                'message' => 'Data detail absensi berhasil diambil',
                'data' => [
                    'attendances' => $transformedData,
                    'statistics' => $statistics,
                    'total_records' => $transformedData->count(),
                    'filtered_by' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'month' => $month,
                        'search' => $searchQuery
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error in getAllAttendanceDetail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data detail absensi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance detail for specific employee
     */
    public function getEmployeeAttendanceDetail(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->get('employee_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter employee_id diperlukan'
                ], 422);
            }

            // Check if employee exists
            $employee = Employee::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Karyawan tidak ditemukan'
                ], 404);
            }

            // Build query
            $query = Attendance::with(['employee'])
                ->where('employee_id', $employeeId)
                ->where('type', 'office')
                ->orderBy('date', 'desc');

            // Filter by date range
            if ($startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }

            $attendances = $query->get();

            // Transform data
            $transformedData = $attendances->map(function ($attendance) {
                return $this->transformAttendanceData($attendance);
            });

            // Calculate statistics
            $statistics = $this->calculateStatistics($attendances);

            return response()->json([
                'success' => true,
                'message' => 'Data detail absensi karyawan berhasil diambil',
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'position' => $employee->position,
                        'employee_id' => $employee->employee_id
                    ],
                    'attendances' => $transformedData,
                    'statistics' => $statistics,
                    'total_records' => $transformedData->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error in getEmployeeAttendanceDetail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data detail absensi karyawan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform attendance data to frontend format
     */
    private function transformAttendanceData($attendance): array
    {
        $date = Carbon::parse($attendance->date);
        $checkIn = $attendance->check_in ? Carbon::parse($attendance->check_in) : null;
        $checkOut = $attendance->check_out ? Carbon::parse($attendance->check_out) : null;

        // Calculate work hours
        $workHours = null;
        if ($checkIn && $checkOut) {
            $workHours = round($checkOut->diffInMinutes($checkIn) / 60, 2);
        }

        // Calculate late minutes
        $lateMinutes = 0;
        if ($checkIn) {
            $expectedCheckIn = Carbon::parse($attendance->date . ' 08:00:00');
            if ($checkIn->gt($expectedCheckIn)) {
                $lateMinutes = $checkIn->diffInMinutes($expectedCheckIn);
            }
        }

        // Calculate early leave minutes
        $earlyLeaveMinutes = 0;
        if ($checkOut) {
            $expectedCheckOut = Carbon::parse($attendance->date . ' 17:00:00');
            if ($checkOut->lt($expectedCheckOut)) {
                $earlyLeaveMinutes = $checkOut->diffInMinutes($expectedCheckOut);
            }
        }

        return [
            'id' => $attendance->id,
            'employee_name' => $attendance->employee->name ?? 'Unknown',
            'employee_position' => $attendance->employee->position ?? 'Unknown',
            'date' => $attendance->date,
            'day_name' => $date->format('l'), // Monday, Tuesday, etc.
            'check_in' => $attendance->check_in ? Carbon::parse($attendance->check_in)->format('H:i') : null,
            'check_out' => $attendance->check_out ? Carbon::parse($attendance->check_out)->format('H:i') : null,
            'status' => $attendance->status,
            'status_label' => $this->getStatusLabel($attendance->status),
            'work_hours' => $workHours,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'notes' => $attendance->notes,
            'created_at' => $attendance->created_at,
            'updated_at' => $attendance->updated_at
        ];
    }

    /**
     * Calculate attendance statistics
     */
    private function calculateStatistics($attendances): array
    {
        $totalRecords = $attendances->count();
        $presentCount = $attendances->whereIn('status', ['present_ontime', 'present_late'])->count();
        $absentCount = $attendances->where('status', 'absent')->count();
        $permissionCount = $attendances->where('status', 'permission')->count();
        $sickCount = $attendances->where('status', 'sick_leave')->count();
        $lateCount = $attendances->where('status', 'present_late')->count();

        // Calculate total work hours
        $totalWorkHours = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $checkIn = Carbon::parse($attendance->check_in);
                $checkOut = Carbon::parse($attendance->check_out);
                $totalWorkHours += $checkOut->diffInMinutes($checkIn) / 60;
            }
        }

        return [
            'total_records' => $totalRecords,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'permission_count' => $permissionCount,
            'sick_count' => $sickCount,
            'late_count' => $lateCount,
            'total_work_hours' => round($totalWorkHours, 2),
            'attendance_rate' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0
        ];
    }

    /**
     * Get status label in Indonesian
     */
    private function getStatusLabel($status): string
    {
        $labels = [
            'present_ontime' => 'Hadir Tepat Waktu',
            'present_late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'permission' => 'Izin',
            'sick_leave' => 'Sakit',
            'holiday' => 'Libur',
            'weekend' => 'Weekend'
        ];

        return $labels[$status] ?? 'Unknown';
    }
} 