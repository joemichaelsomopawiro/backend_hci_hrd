<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AttendanceProcessingService
{
    /**
     * Proses semua attendance logs yang belum diproses (hanya PIN terdaftar)
     */
    public function processUnprocessedLogs(): array
    {
        // Get registered PINs from employee_attendance 
        $registeredPins = $this->getRegisteredUserPins();
        
        $logs = AttendanceLog::unprocessed()
            ->whereIn('user_pin', $registeredPins) // Only process registered PINs
            ->orderBy('datetime')
            ->get();

        if ($logs->isEmpty()) {
            return ['success' => true, 'message' => 'Tidak ada data yang perlu diproses dari user terdaftar', 'processed' => 0];
        }

        // Group logs by user_pin and date
        $groupedLogs = $logs->groupBy(function ($log) {
            return $log->user_pin . '_' . Carbon::parse($log->datetime)->format('Y-m-d');
        });

        $processedCount = 0;
        
        foreach ($groupedLogs as $key => $userDayLogs) {
            [$userPin, $date] = explode('_', $key);
            
            try {
                $this->processUserDayAttendance($userPin, $date, $userDayLogs);
                
                // Mark logs as processed
                $userDayLogs->each(function ($log) {
                    $log->markAsProcessed();
                });
                
                $processedCount++;
                
            } catch (\Exception $e) {
                Log::error("Error processing attendance for user PIN {$userPin} on {$date}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => "Berhasil memproses {$processedCount} hari absensi dari user terdaftar",
            'processed' => $processedCount
        ];
    }

    /**
     * Proses attendance untuk user PIN tertentu pada tanggal tertentu
     */
    public function processUserDayAttendance($userPin, $date, Collection $logs): void
    {
        // Sort logs by datetime
        $sortedLogs = $logs->sortBy('datetime');
        
        // Get first and last tap
        $firstTap = $sortedLogs->first();
        $lastTap = $sortedLogs->last();
        
        // Get user info dari employee_attendance berdasarkan user_pin
        $employee = \App\Models\EmployeeAttendance::where('machine_user_id', $userPin)
            ->where('is_active', true)
            ->first();
            
        $userName = $employee ? $employee->name : ($firstTap->user_name ?? "User_{$userPin}");
        $cardNumber = $firstTap->card_number;

        // Find or create attendance record based on user_pin
        $attendance = Attendance::firstOrCreate(
            [
                'user_pin' => $userPin,
                'date' => $date
            ],
            [
                'user_name' => $userName,
                'card_number' => $cardNumber,
                'status' => 'absent',
                'total_taps' => 0
            ]
        );

        // Update attendance data
        $checkInTime = Carbon::parse($firstTap->datetime);
        $attendance->check_in = $checkInTime;
        $attendance->user_name = $userName;
        $attendance->card_number = $cardNumber;
        $attendance->total_taps = $sortedLogs->count();

        // Set check_out only if there are multiple taps or if it's different from check_in
        if ($sortedLogs->count() > 1) {
            $checkOutTime = Carbon::parse($lastTap->datetime);
            // Only set check_out if it's different from check_in (menggunakan env untuk minimum gap)
            $minGapMinutes = env('ATTENDANCE_DUPLICATE_DETECTION_MINUTES', 1);
            if ($checkOutTime->diffInMinutes($checkInTime) >= $minGapMinutes) {
                $attendance->check_out = $checkOutTime;
            }
        }

        // Update all calculations
        $attendance->updateCalculations();
        
        Log::info("Processed attendance for user {$userName} (PIN: {$userPin}) on {$date}: {$attendance->status}");
    }

    /**
     * Proses attendance untuk employee tertentu pada tanggal tertentu (legacy method)
     */
    public function processEmployeeDayAttendance($employeeId, $date, Collection $logs): void
    {
        // Sort logs by datetime
        $sortedLogs = $logs->sortBy('datetime');
        
        // Get first and last tap
        $firstTap = $sortedLogs->first();
        $lastTap = $sortedLogs->last();
        
        $employee = Employee::find($employeeId);
        if (!$employee) {
            throw new \Exception("Employee not found: {$employeeId}");
        }

        // Find or create attendance record
        $attendance = Attendance::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'date' => $date
            ],
            [
                'status' => 'absent',
                'total_taps' => 0
            ]
        );

        // Update attendance data
        $checkInTime = Carbon::parse($firstTap->datetime);
        $attendance->check_in = $checkInTime;
        $attendance->total_taps = $sortedLogs->count();

        // Set check_out only if there are multiple taps or if it's different from check_in
        if ($sortedLogs->count() > 1) {
            $checkOutTime = Carbon::parse($lastTap->datetime);
            // Only set check_out if it's different from check_in (menggunakan env untuk minimum gap)
            $minGapMinutes = env('ATTENDANCE_DUPLICATE_DETECTION_MINUTES', 1);
            if ($checkOutTime->diffInMinutes($checkInTime) >= $minGapMinutes) {
                $attendance->check_out = $checkOutTime;
            }
        }

        // Update all calculations
        $attendance->updateCalculations();
        
        Log::info("Processed attendance for employee {$employee->nama_lengkap} on {$date}: {$attendance->status}");
    }

    /**
     * Proses ulang attendance untuk tanggal tertentu
     */
    public function reprocessAttendanceForDate($date): array
    {
        $logs = AttendanceLog::whereDate('datetime', $date)
            ->orderBy('datetime')
            ->get();

        if ($logs->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data attendance log untuk tanggal tersebut'];
        }

        // Group by employee
        $groupedLogs = $logs->groupBy('employee_id');
        $processedCount = 0;

        foreach ($groupedLogs as $employeeId => $employeeLogs) {
            try {
                $this->processEmployeeDayAttendance($employeeId, $date, $employeeLogs);
                $processedCount++;
            } catch (\Exception $e) {
                Log::error("Error reprocessing attendance for employee {$employeeId} on {$date}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => "Berhasil memproses ulang {$processedCount} employee untuk tanggal {$date}",
            'processed' => $processedCount
        ];
    }

    /**
     * Generate attendance untuk user yang tidak tap sama sekali
     */
    public function generateAbsentAttendance($date): array
    {
        // Skip generation untuk sistem yang menggunakan PIN-based attendance
        // Karena kita tidak tahu semua PIN yang ada tanpa tap
        return [
            'success' => true,
            'message' => "Skip generate absent - sistem menggunakan PIN-based attendance",
            'processed' => 0
        ];
    }

    /**
     * Proses attendance untuk hari ini
     */
    public function processTodayAttendance(): array
    {
        $today = now()->format('Y-m-d');
        
        // Process unprocessed logs first
        $logsResult = $this->processUnprocessedLogs();
        
        // Generate absent records for employees without any taps
        $absentResult = $this->generateAbsentAttendance($today);

        return [
            'success' => true,
            'message' => "Proses attendance hari ini selesai. Logs: {$logsResult['processed']}, Absent: {$absentResult['processed']}",
            'logs_processed' => $logsResult['processed'],
            'absent_generated' => $absentResult['processed']
        ];
    }

    /**
     * Proses hanya logs hari ini (hanya PIN terdaftar)
     */
    public function processTodayOnly($date): array
    {
        // Get registered PINs from employee_attendance 
        $registeredPins = $this->getRegisteredUserPins();
        
        // Get unprocessed logs for today only from registered users
        $logs = AttendanceLog::where('is_processed', false)
            ->whereDate('datetime', $date)
            ->whereIn('user_pin', $registeredPins) // Only process registered PINs
            ->orderBy('datetime')
            ->get();

        if ($logs->isEmpty()) {
            return ['success' => true, 'message' => 'Tidak ada logs baru untuk hari ini dari user terdaftar', 'processed' => 0];
        }

        // Group logs by user_pin and date
        $groupedLogs = $logs->groupBy(function ($log) {
            return $log->user_pin . '_' . Carbon::parse($log->datetime)->format('Y-m-d');
        });

        $processedCount = 0;
        
        foreach ($groupedLogs as $key => $userDayLogs) {
            [$userPin, $logDate] = explode('_', $key);
            
            // Only process if it's today
            if ($logDate === $date) {
                try {
                    $this->processUserDayAttendance($userPin, $logDate, $userDayLogs);
                    
                    // Mark logs as processed
                    $userDayLogs->each(function ($log) {
                        $log->markAsProcessed();
                    });
                    
                    $processedCount++;
                    
                } catch (\Exception $e) {
                    Log::error("Error processing today attendance for user PIN {$userPin}: " . $e->getMessage());
                }
            }
        }

        return [
            'success' => true,
            'message' => "Berhasil memproses {$processedCount} user terdaftar untuk hari ini",
            'processed' => $processedCount,
            'date' => $date
        ];
    }

    /**
     * Get attendance summary untuk tanggal tertentu
     */
    public function getAttendanceSummary($date): array
    {
        // Get registered user PINs
        $registeredUserPins = \App\Models\EmployeeAttendance::where('is_active', true)
            ->pluck('machine_user_id')
            ->toArray();

        // Count unique users yang benar-benar tap hari ini
        $actualTappedUsers = AttendanceLog::whereDate('datetime', $date)
            ->whereIn('user_pin', $registeredUserPins)
            ->distinct('user_pin')
            ->count('user_pin');

        // Get attendances for the date (hanya user terdaftar)
        $attendances = Attendance::where('date', $date)
            ->whereIn('user_pin', $registeredUserPins)
            ->get();

        // Initialize summary
        $summary = [
            'date' => $date,
            'total_users' => $actualTappedUsers, // Hanya user terdaftar yang tap hari ini
            'total_registered_users' => count($registeredUserPins), // Total user terdaftar di mesin (32)
            'present_ontime' => 0,
            'present_late' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'sick_leave' => 0,
            'permission' => 0,
            'attendance_rate' => 0,
            'total_attendance_records' => $attendances->count(),
            'total_logs_today' => AttendanceLog::whereDate('datetime', $date)
                ->whereIn('user_pin', $registeredUserPins)
                ->count()
        ];

        // Count by status
        foreach ($attendances as $attendance) {
            if (isset($summary[$attendance->status])) {
                $summary[$attendance->status]++;
            }
        }

        // Calculate attendance rate (present users / total registered users * 100)
        $presentCount = $summary['present_ontime'] + $summary['present_late'];
        $summary['attendance_rate'] = count($registeredUserPins) > 0 
            ? round(($presentCount / count($registeredUserPins)) * 100, 2) 
            : 0;

        return $summary;
    }

    /**
     * Recalculate attendance untuk user PIN tertentu pada tanggal tertentu
     */
    public function recalculateAttendance($userPin, $date): array
    {
        $attendance = Attendance::where('user_pin', $userPin)
            ->where('date', $date)
            ->first();

        if (!$attendance) {
            return ['success' => false, 'message' => 'Attendance record tidak ditemukan'];
        }

        try {
            $attendance->updateCalculations();
            
            return [
                'success' => true,
                'message' => 'Attendance berhasil direcalculate',
                'attendance' => $attendance
            ];
        } catch (\Exception $e) {
            Log::error("Error recalculating attendance: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get detailed attendance data untuk user PIN tertentu pada periode tertentu
     */
    public function getUserAttendanceDetail($userPin, $startDate, $endDate): array
    {
        $attendances = Attendance::where('user_pin', $userPin)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $logs = AttendanceLog::where('user_pin', $userPin)
            ->whereBetween('datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('datetime')
            ->get();

        // Get nama dari employee_attendance
        $employee = \App\Models\EmployeeAttendance::where('machine_user_id', $userPin)
            ->where('is_active', true)
            ->first();
        $userName = $employee ? $employee->name : ($attendances->first()->user_name ?? "User_{$userPin}");

        return [
            'user_pin' => $userPin,
            'user_name' => $userName,
            'attendances' => $attendances,
            'logs' => $logs,
            'summary' => [
                'total_days' => $attendances->count(),
                'present_days' => $attendances->whereIn('status', ['present_ontime', 'present_late'])->count(),
                'late_days' => $attendances->where('status', 'present_late')->count(),
                'absent_days' => $attendances->where('status', 'absent')->count(),
                'leave_days' => $attendances->whereIn('status', ['on_leave', 'sick_leave', 'permission'])->count(),
                'total_work_hours' => $attendances->sum('work_hours'),
                'total_overtime_hours' => $attendances->sum('overtime_hours'),
            ]
        ];
    }

    /**
     * Get detailed attendance data untuk employee tertentu pada periode tertentu (legacy)
     */
    public function getEmployeeAttendanceDetail($employeeId, $startDate, $endDate): array
    {
        $attendances = Attendance::with(['employee'])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('datetime')
            ->get();

        return [
            'employee' => Employee::find($employeeId),
            'attendances' => $attendances,
            'logs' => $logs,
            'summary' => [
                'total_days' => $attendances->count(),
                'present_days' => $attendances->whereIn('status', ['present_ontime', 'present_late'])->count(),
                'late_days' => $attendances->where('status', 'present_late')->count(),
                'absent_days' => $attendances->where('status', 'absent')->count(),
                'leave_days' => $attendances->whereIn('status', ['on_leave', 'sick_leave', 'permission'])->count(),
                'total_work_hours' => $attendances->sum('work_hours'),
                'total_overtime_hours' => $attendances->sum('overtime_hours'),
            ]
        ];
    }

    /**
     * Get registered user PINs from employee_attendance table (hanya PIN utama)
     */
    private function getRegisteredUserPins(): array
    {
        // Hanya get PIN utama dari employee_attendance karena di logs sudah disimpan PIN utama
        $mainPins = \App\Models\EmployeeAttendance::where('is_active', true)
            ->pluck('machine_user_id')
            ->toArray();
            
        Log::info("Registered main PINs", [
            'main_pins_count' => count($mainPins)
        ]);
        
        return $mainPins;
    }
} 