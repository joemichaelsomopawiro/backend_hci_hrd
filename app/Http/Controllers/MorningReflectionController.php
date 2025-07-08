<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MorningReflectionAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class MorningReflectionController extends Controller
{
    /**
     * Get morning reflection status for today
     */
    public function getStatus()
    {
        try {
            $now = Carbon::now();
            $dayOfWeek = $now->dayOfWeek; // 1 = Senin, 3 = Rabu, 5 = Jumat
            
            // Cek apakah hari ini adalah hari renungan pagi
            $isWorshipDay = in_array($dayOfWeek, [1, 3, 5]); // Senin, Rabu, Jumat
            
            // Cek waktu renungan pagi (07:10 - 07:35)
            $startTime = Carbon::today()->setTime(7, 10); // 07:10
            $endTime = Carbon::today()->setTime(7, 35);   // 07:35
            
            $isOpen = $now->gte($startTime) && $now->lte($endTime);
            $isPassed = $now->gt($endTime);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'active' => $isWorshipDay,
                    'is_open' => $isOpen,
                    'is_passed' => $isPassed,
                    'start_time' => '07:10',
                    'end_time' => '07:35',
                    'current_time' => $now->format('H:i'),
                    'day_of_week' => $dayOfWeek,
                    'message' => $isWorshipDay ? 
                        ($isOpen ? 'Renungan pagi sedang berlangsung' : 
                         ($isPassed ? 'Renungan pagi sudah selesai' : 'Renungan pagi belum dimulai')) :
                        'Bukan hari renungan pagi'
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting morning reflection status', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status renungan pagi'
            ], 500);
        }
    }

    public static function getStatusByTime($attendanceTime)
    {
        // Pastikan $attendanceTime adalah Carbon instance di Asia/Jakarta
        $attendanceTime = $attendanceTime->copy()->setTimezone('Asia/Jakarta');
        $minutes = $attendanceTime->hour * 60 + $attendanceTime->minute;
        if ($minutes >= 430 && $minutes < 450) {
            return 'Hadir';      // ✅ BENAR! 07:10-07:30
        } elseif ($minutes >= 450 && $minutes <= 455) {
            return 'Terlambat'; // ✅ BENAR! 07:30-07:35
        } elseif ($minutes > 455 && $minutes <= 480) {
            return 'Absen';     // ✅ BENAR! 07:35-08:00
        } else {
            return 'Hadir';     // ✅ BENAR! fallback
        }
    }

    /**
     * Record morning reflection attendance
     */
    public function attend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|min:1',
            'testing_mode' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Set timezone ke Asia/Jakarta
        $now = Carbon::now('Asia/Jakarta');
        $date = $now->toDateString();
        $dayOfWeek = $now->dayOfWeek;
        $testingMode = $request->input('testing_mode', false);

        // Validasi hari (skip jika testing mode)
        if (!$testingMode && !in_array($dayOfWeek, [1, 3, 5])) {
            return response()->json([
                'success' => false,
                'message' => 'Renungan pagi hanya diadakan pada Senin, Rabu, dan Jumat'
            ], 422);
        }

        // Validasi waktu (skip jika testing mode atau environment local)
        $isTestingEnvironment = config('app.env') === 'local' || config('app.env') === 'testing';
        $startTime = Carbon::today('Asia/Jakarta')->setTime(7, 10);
        $endTime = Carbon::today('Asia/Jakarta')->setTime(8, 0);

        if (!$testingMode && !$isTestingEnvironment) {
            if ($now->lt($startTime) || $now->gt($endTime)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi hanya dapat dilakukan antara pukul 07:10 - 08:00'
                ], 422);
            }
        }

        // Jika lewat dari jam 08:00, tolak request
        if ($now->gt($endTime) && !$testingMode && !$isTestingEnvironment) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi sudah ditutup setelah pukul 08:00'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Tentukan status berdasarkan waktu klik
            $status = self::getStatusByTime($now);

            // Cek apakah sudah absen hari ini
            $existingAttendance = MorningReflectionAttendance::where('employee_id', $request->employee_id)
                                                  ->whereDate('date', $date)
                                                  ->first();

            if ($existingAttendance) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah hadir di renungan pagi hari ini',
                    'existing_data' => [
                        'status' => $existingAttendance->status,
                        'join_time' => $existingAttendance->join_time,
                        'date' => $existingAttendance->date
                    ]
                ], 422);
            }

            // Buat record baru
            $morningReflection = MorningReflectionAttendance::create([
                'employee_id' => $request->employee_id,
                'date' => $date,
                'status' => $status,
                'join_time' => $now,
                'testing_mode' => $testingMode
            ]);

            // 🔥 AUTO-SYNC: Sinkronisasi otomatis untuk employee ini
            $employee = \App\Models\Employee::find($request->employee_id);
            $syncResult = null;
            if ($employee) {
                $syncResult = \App\Services\EmployeeSyncService::autoSyncMorningReflection($employee->nama_lengkap);
            }

            DB::commit();

            Log::info('Morning reflection attendance recorded', [
                'employee_id' => $request->employee_id,
                'date' => $date,
                'status' => $status,
                'testing_mode' => $testingMode,
                'sync_result' => $syncResult
            ]);

            return response()->json([
                'success' => true,
                'data' => $morningReflection,
                'message' => 'Kehadiran renungan pagi berhasil dicatat',
                'sync_result' => $syncResult
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error recording morning reflection attendance', [
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mencatat kehadiran'
            ], 500);
        }
    }

    /**
     * Get user attendance status
     */
    public function getAttendance(Request $request)
    {
        try {
            $employee_id = $request->query('employee_id');
            $date = $request->query('date', \Carbon\Carbon::today()->toDateString());
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $query = \App\Models\MorningReflectionAttendance::with('employee');

            if ($employee_id) {
                $query->where('employee_id', $employee_id);
                $query->whereDate('date', $date);
                $attendance = $query->first();
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => $attendance ? true : false,
                        'attendance' => $attendance,
                        'message' => $attendance ? 'User sudah hadir' : 'User belum hadir'
                    ]
                ]);
            } else {
                // Jika tidak ada employee_id, kembalikan semua data absensi (paginated)
                $reflections = $query->orderBy('date', 'desc')->paginate($perPage, ['*'], 'page', $page);
                return response()->json([
                    'success' => true,
                    'data' => $reflections,
                    'message' => 'Daftar absensi renungan pagi'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error getting attendance', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi'
            ], 500);
        }
    }

    /**
     * Get user attendance by date
     */
    public function getUserAttendance($employee_id, $date)
    {
        try {
            $attendance = \App\Models\MorningReflectionAttendance::with('employee')
                                          ->where('employee_id', $employee_id)
                                          ->whereDate('date', $date)
                                          ->first();

            return response()->json([
                'success' => true,
                'data' => $attendance
            ], 200);

        } catch (Exception $e) {
            \Log::error('Error getting user attendance by date', [
                'employee_id' => $employee_id,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kehadiran'
            ], 500);
        }
    }

    /**
     * Get weekly attendance for user
     */
    public function getWeeklyAttendance($employee_id, Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            if (!$startDate) {
                $startDate = \Carbon\Carbon::now()->startOfWeek()->toDateString();
            }
            
            if (!$endDate) {
                $endDate = \Carbon\Carbon::now()->endOfWeek()->toDateString();
            }

            $attendances = \App\Models\MorningReflectionAttendance::with('employee')
                                           ->where('employee_id', $employee_id)
                                           ->whereBetween('date', [$startDate, $endDate])
                                           ->orderBy('date', 'asc')
                                           ->get();

            return response()->json([
                'success' => true,
                'data' => $attendances,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ], 200);

        } catch (Exception $e) {
            \Log::error('Error getting weekly attendance', [
                'employee_id' => $employee_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kehadiran mingguan'
            ], 500);
        }
    }

    /**
     * Get morning reflection configuration
     */
    public function getConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'worship_days' => [1, 3, 5], // Senin, Rabu, Jumat
                'start_time' => '07:10',
                'end_time' => '07:35',
                'cutoff_time' => '07:30',
                'timezone' => 'Asia/Jakarta',
                'active' => true
            ]
        ], 200);
    }

    /**
     * Get today's attendance for GA dashboard
     */
    public function getTodayAttendance(Request $request)
    {
        try {
            $date = $request->query('date', Carbon::today()->toDateString());
            $perPage = $request->query('per_page', 10);

            $attendances = MorningReflectionAttendance::with('employee')
                                           ->whereDate('date', $date)
                                           ->orderBy('join_time', 'asc')
                                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $attendances->items(),
                'pagination' => [
                    'current_page' => $attendances->currentPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total(),
                    'last_page' => $attendances->lastPage()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error getting today attendance', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kehadiran hari ini'
            ], 500);
        }
    }

    /**
     * Update morning reflection configuration (GA only)
     */
    public function updateConfig(Request $request)
    {
        // This would typically update configuration in database
        // For now, return success response
        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi berhasil diperbarui'
        ], 200);
    }

    /**
     * Get statistics for morning reflection
     */
    public function statistics(Request $request)
    {
        try {
            $period = $request->query('period', 'week');
            $now = Carbon::now();

            switch ($period) {
                case 'today':
                    $startDate = $now->toDateString();
                    $endDate = $now->toDateString();
                    break;
                case 'week':
                    $startDate = $now->startOfWeek()->toDateString();
                    $endDate = $now->endOfWeek()->toDateString();
                    break;
                case 'month':
                    $startDate = $now->startOfMonth()->toDateString();
                    $endDate = $now->endOfMonth()->toDateString();
                    break;
                default:
                    $startDate = $now->startOfWeek()->toDateString();
                    $endDate = $now->endOfWeek()->toDateString();
            }

            $stats = [
                'total_attended' => MorningReflectionAttendance::whereBetween('date', [$startDate, $endDate])
                                                    ->where('status', 'Hadir')
                                                    ->count(),
                'total_late' => MorningReflectionAttendance::whereBetween('date', [$startDate, $endDate])
                                                ->where('status', 'Terlambat')
                                                ->count(),
                'total_absent' => MorningReflectionAttendance::whereBetween('date', [$startDate, $endDate])
                                                  ->where('status', 'Absen')
                                                  ->count(),
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (Exception $e) {
            Log::error('Error getting morning reflection statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik'
            ], 500);
        }
    }

    /**
     * Reset rate limit for testing purposes (only in local/testing environment)
     */
    public function resetRateLimit(Request $request)
    {
        // Only allow in local/testing environment
        if (!in_array(config('app.env'), ['local', 'testing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Reset rate limit hanya tersedia di environment development'
            ], 403);
        }

        $employee_id = $request->input('employee_id');
        
        if (!$employee_id) {
            return response()->json([
                'success' => false,
                'message' => 'Employee ID diperlukan'
            ], 422);
        }

        try {
            // Clear rate limit cache for this user
            $key = 'attendance_attempt_' . $employee_id . '_' . date('Y-m-d');
            Cache::forget($key);
            
            Log::info('Rate limit reset for user', [
                'employee_id' => $employee_id,
                'date' => date('Y-m-d')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rate limit berhasil direset'
            ], 200);

        } catch (Exception $e) {
            Log::error('Error resetting rate limit', [
                'employee_id' => $employee_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat reset rate limit'
            ], 500);
        }
    }

    // ========== DEBUG PANEL ENDPOINTS ==========

    /**
     * Test koneksi database (untuk debug panel)
     */
    public function testDatabase()
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $result = \Illuminate\Support\Facades\DB::select('SELECT 1 as test');
            return response()->json([
                'success' => true,
                'message' => 'Database connection successful',
                'data' => [
                    'connection' => 'OK',
                    'query_test' => 'OK',
                    'timestamp' => \Carbon\Carbon::now('Asia/Jakarta')->toISOString(),
                    'result' => $result
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate join Zoom (untuk debug panel, hanya return data, tidak simpan ke DB)
     */
    public function joinZoom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer',
            'join_time' => 'required|date',
            'meeting_id' => 'required|string',
            'platform' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $joinData = [
                'employee_id' => $request->employee_id,
                'join_time' => $request->join_time,
                'meeting_id' => $request->meeting_id,
                'platform' => $request->platform,
                'created_at' => \Carbon\Carbon::now('Asia/Jakarta')->toISOString(),
                'updated_at' => \Carbon\Carbon::now('Asia/Jakarta')->toISOString()
            ];

            // Tidak perlu simpan ke database, hanya return data untuk debug panel
            return response()->json([
                'success' => true,
                'message' => 'Successfully joined Zoom meeting (debug, not saved to DB)',
                'data' => $joinData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to join Zoom meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate record attendance (untuk debug panel, data masuk ke morning_reflection_attendances)
     */
    public function recordAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'attendance_date' => 'required|date',
            'join_time' => 'required|date',
            'status' => 'required|in:present,late,absent,leave',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Mapping status ke enum di tabel (bahasa Indonesia)
            $statusMap = [
                'present' => 'Hadir',
                'late' => 'Terlambat',
                'absent' => 'Absen',
                'leave' => 'Absen' // Map leave ke Absen karena tidak ada enum khusus
            ];
            $status = $statusMap[$request->status] ?? 'Hadir';

            // Parse dan set timezone ke Asia/Jakarta
            $joinTime = \Carbon\Carbon::parse($request->join_time)->setTimezone('Asia/Jakarta');
            $attendanceDate = \Carbon\Carbon::parse($request->attendance_date)->format('Y-m-d');

            // Data untuk insert/update
            $attendanceData = [
                'employee_id' => $request->employee_id,
                'date' => $attendanceDate,
                'join_time' => $joinTime,
                'status' => $status,
                'testing_mode' => true
            ];

            // Cek apakah sudah ada attendance untuk user dan tanggal ini
            $existing = MorningReflectionAttendance::where('employee_id', $request->employee_id)
                ->whereDate('date', $attendanceDate)
                ->first();

            if ($existing) {
                // Update existing record
                $existing->update($attendanceData);
                $morningReflection = $existing->fresh(['employee']);
                $message = 'Attendance updated successfully (debug)';
            } else {
                // Insert new record
                $morningReflection = MorningReflectionAttendance::create($attendanceData);
                $morningReflection->load('employee');
                $message = 'Attendance created successfully (debug)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $morningReflection
            ]);
        } catch (\Exception $e) {
            Log::error('Error in debug record attendance', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to record attendance',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ]
            ], 500);
        }
    }

    // Alias methods untuk backward compatibility
    public function status()
    {
        return $this->getStatus();
    }

    public function attendUser(Request $request)
    {
        return $this->attend($request);
    }

    public function attendance(Request $request)
    {
        return $this->getAttendance($request);
    }

    public function attendanceByDate($employee_id, $date)
    {
        return $this->getUserAttendance($employee_id, $date);
    }

    public function weeklyAttendance($employee_id, Request $request)
    {
        return $this->getWeeklyAttendance($employee_id, $request);
    }

    public function config()
    {
        return $this->getConfig();
    }

    public function todayAttendance(Request $request)
    {
        return $this->getTodayAttendance($request);
    }

    public function updateConfigAdmin(Request $request)
    {
        return $this->updateConfig($request);
    }
}