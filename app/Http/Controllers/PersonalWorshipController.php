<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\MorningReflectionAttendance;
use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Models\Attendance;
use Exception;

class PersonalWorshipController extends Controller
{
    /**
     * Get worship attendance data for specific employee
     * GET /api/personal/worship-attendance?employee_id={id}
     */
    public function getWorshipAttendance(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->get('employee_id');
            
            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter employee_id diperlukan'
                ], 422);
            }

            Log::info('Personal worship attendance request', [
                'employee_id' => $employeeId,
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

            // Ambil data absensi renungan pagi untuk employee ini
            $worshipAttendances = MorningReflectionAttendance::where('employee_id', $employeeId)
                ->orderBy('date', 'desc')
                ->limit(30) // Ambil 30 hari terakhir
                ->get();

            // Transform data
            $transformedData = $worshipAttendances->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date->format('Y-m-d'),
                    'day_name' => $attendance->date->format('l'), // Nama hari dalam bahasa Inggris
                    'attendance_time' => $attendance->join_time ? 
                        Carbon::parse($attendance->join_time)->format('H:i') : null,
                    'status' => $attendance->status,
                    'status_label' => $this->getStatusLabel($attendance->status),
                    'attendance_method' => $attendance->attendance_method ?? 'online',
                    'attendance_source' => $attendance->attendance_source ?? 'zoom',
                    'testing_mode' => $attendance->testing_mode ?? false,
                    'created_at' => $attendance->created_at->format('Y-m-d H:i:s')
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
                    'worship_attendances' => $transformedData,
                    'total_records' => $transformedData->count()
                ],
                'message' => 'Data absensi renungan pagi berhasil diambil'
            ], 200);

        } catch (Exception $e) {
            Log::error('Personal worship attendance error', [
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
     * Get combined attendance data (worship + office) for specific employee
     * GET /api/personal/combined-attendance?employee_id={id}
     */
    public function getCombinedAttendance(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->get('employee_id');
            $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
            
            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter employee_id diperlukan'
                ], 422);
            }

            Log::info('Personal combined attendance request', [
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

            // Ambil data absensi renungan pagi
            $worshipAttendances = MorningReflectionAttendance::where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->keyBy('date');

            // Ambil data absensi kantor
            $officeAttendances = Attendance::where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->keyBy('date');

            // Ambil data cuti yang disetujui
            $leaves = LeaveRequest::where('employee_id', $employeeId)
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

            // Generate semua tanggal dalam rentang
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $allDates = [];
            
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateString = $date->format('Y-m-d');
                $dayOfWeek = $date->dayOfWeek;
                
                // Skip weekend
                if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                    continue;
                }
                
                $allDates[] = $dateString;
            }

            // Gabungkan data
            $combinedData = [];
            foreach ($allDates as $date) {
                $carbonDate = Carbon::parse($date);
                $dayOfWeek = $carbonDate->dayOfWeek;
                
                $record = [
                    'date' => $date,
                    'day_name' => $carbonDate->format('l'),
                    'day_number' => $dayOfWeek,
                    'worship_attendance' => null,
                    'office_attendance' => null,
                    'leave_status' => null,
                    'combined_status' => 'absent'
                ];

                // Cek data cuti terlebih dahulu
                $isOnLeave = $this->checkLeaveStatus($leaves, $date);
                if ($isOnLeave) {
                    $record['leave_status'] = $isOnLeave;
                    $record['combined_status'] = 'leave';
                } else {
                    // Cek data absensi renungan pagi (Senin, Rabu, Jumat)
                    if (in_array($dayOfWeek, [1, 3, 5])) {
                        $worshipData = $worshipAttendances->get($date);
                        if ($worshipData) {
                            $record['worship_attendance'] = [
                                'status' => $worshipData->status,
                                'status_label' => $this->getStatusLabel($worshipData->status),
                                'attendance_time' => $worshipData->join_time ? 
                                    Carbon::parse($worshipData->join_time)->format('H:i') : null,
                                'attendance_method' => $worshipData->attendance_method ?? 'online',
                                'attendance_source' => $worshipData->attendance_source ?? 'zoom'
                            ];
                            $record['combined_status'] = $worshipData->status;
                        }
                    }
                    
                    // Cek data absensi kantor (Selasa, Kamis)
                    if (in_array($dayOfWeek, [2, 4])) {
                        $officeData = $officeAttendances->get($date);
                        if ($officeData) {
                            $record['office_attendance'] = [
                                'status' => $officeData->status,
                                'status_label' => $this->getOfficeStatusLabel($officeData->status),
                                'check_in' => $officeData->check_in ? 
                                    Carbon::parse($officeData->check_in)->format('H:i') : null,
                                'check_out' => $officeData->check_out ? 
                                    Carbon::parse($officeData->check_out)->format('H:i') : null,
                                'work_hours' => $officeData->work_hours ?? 0
                            ];
                            $record['combined_status'] = $officeData->status;
                        }
                    }
                }

                $combinedData[] = $record;
            }

            // Urutkan berdasarkan tanggal (terbaru dulu)
            usort($combinedData, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });

            // Hitung statistik
            $statistics = $this->calculateStatistics($combinedData);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'nama_lengkap' => $employee->nama_lengkap,
                        'jabatan_saat_ini' => $employee->jabatan_saat_ini
                    ],
                    'attendance_records' => $combinedData,
                    'statistics' => $statistics,
                    'date_range' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'total_records' => count($combinedData)
                ],
                'message' => 'Data absensi gabungan berhasil diambil'
            ], 200);

        } catch (Exception $e) {
            Log::error('Personal combined attendance error', [
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
     * Check if employee is on leave for specific date
     */
    private function checkLeaveStatus($leaves, $date)
    {
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);
            $checkDate = Carbon::parse($date);
            
            if ($checkDate->between($startDate, $endDate)) {
                return [
                    'leave_type' => $leave->leave_type,
                    'leave_type_label' => $this->getLeaveTypeLabel($leave->leave_type),
                    'reason' => $leave->reason,
                    'total_days' => $leave->total_days
                ];
            }
        }
        
        return null;
    }

    /**
     * Calculate attendance statistics
     */
    private function calculateStatistics($data)
    {
        $stats = [
            'total_days' => count($data),
            'worship_present' => 0,
            'worship_late' => 0,
            'worship_absent' => 0,
            'office_present' => 0,
            'office_late' => 0,
            'office_absent' => 0,
            'leave_days' => 0,
            'total_work_hours' => 0
        ];

        foreach ($data as $record) {
            if ($record['leave_status']) {
                $stats['leave_days']++;
            } elseif ($record['worship_attendance']) {
                switch ($record['worship_attendance']['status']) {
                    case 'present':
                        $stats['worship_present']++;
                        break;
                    case 'late':
                        $stats['worship_late']++;
                        break;
                    default:
                        $stats['worship_absent']++;
                        break;
                }
            } elseif ($record['office_attendance']) {
                switch ($record['office_attendance']['status']) {
                    case 'present_ontime':
                        $stats['office_present']++;
                        break;
                    case 'present_late':
                        $stats['office_late']++;
                        break;
                    default:
                        $stats['office_absent']++;
                        break;
                }
                
                $stats['total_work_hours'] += $record['office_attendance']['work_hours'] ?? 0;
            } else {
                // Absent
                if (in_array($record['day_number'], [1, 3, 5])) {
                    $stats['worship_absent']++;
                } else {
                    $stats['office_absent']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Get status label untuk absensi renungan pagi
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'leave' => 'Cuti'
        ];
        
        return $labels[$status] ?? $status;
    }

    /**
     * Get status label untuk absensi kantor
     */
    private function getOfficeStatusLabel($status)
    {
        $labels = [
            'present_ontime' => 'Hadir Tepat Waktu',
            'present_late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'on_leave' => 'Sedang Cuti',
            'sick_leave' => 'Cuti Sakit'
        ];
        
        return $labels[$status] ?? $status;
    }

    /**
     * Get leave type label
     */
    private function getLeaveTypeLabel($leaveType)
    {
        $labels = [
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'paternity' => 'Cuti Ayah',
            'marriage' => 'Cuti Menikah',
            'bereavement' => 'Cuti Duka'
        ];
        
        return $labels[$leaveType] ?? $leaveType;
    }
} 