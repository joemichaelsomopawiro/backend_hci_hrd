<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMachine;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceProcessingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    protected $machineService;
    protected $processingService;

    public function __construct(
        AttendanceMachineService $machineService, 
        AttendanceProcessingService $processingService
    ) {
        $this->machineService = $machineService;
        $this->processingService = $processingService;
    }

    /**
     * GET /api/attendance/dashboard
     * Dashboard attendance hari ini
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', now()->format('Y-m-d'));
            
            $summary = $this->processingService->getAttendanceSummary($date);
            
            // Get latest attendance records for today (all records, not just present)
            $latestAttendances = Attendance::where('date', $date)
                ->orderBy('check_in', 'desc')
                ->get()
                ->map(function($attendance) {
                    return [
                        'id' => $attendance->id,
                        'user_name' => $attendance->user_name,
                        'user_pin' => $attendance->user_pin,
                        'card_number' => $attendance->card_number,
                        'date' => $attendance->date,
                        'check_in' => $attendance->check_in,
                        'check_out' => $attendance->check_out,
                        'status' => $attendance->status,
                        'total_taps' => $attendance->total_taps,
                        'work_hours' => $attendance->work_hours,
                        'late_minutes' => $attendance->late_minutes,
                    ];
                });

            // Get machine status
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();
            $machineStatus = null;
            if ($machine) {
                $connectionTest = $this->machineService->testConnection($machine);
                $machineStatus = [
                    'name' => $machine->name,
                    'ip_address' => $machine->ip_address,
                    'status' => $machine->status,
                    'connected' => $connectionTest['success'],
                    'last_sync' => $machine->last_sync_at
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'latest_attendances' => $latestAttendances,
                    'machine_status' => $machineStatus
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in attendance dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/list
     * List attendance dengan filter
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'employee_id' => 'nullable|exists:employees,id',
                'status' => 'nullable|in:present_ontime,present_late,absent,on_leave,sick_leave,permission',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Attendance::with(['employee']);

            // Filter by date
            if ($request->has('date')) {
                $query->where('date', $request->date);
            } elseif ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            } else {
                // Default hari ini
                $query->where('date', now()->format('Y-m-d'));
            }

            // Filter by employee
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $perPage = $request->get('per_page', 20);
            $attendances = $query->orderBy('date', 'desc')
                               ->orderBy('check_in')
                               ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);

        } catch (\Exception $e) {
            Log::error('Error in attendance index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/employee/{employeeId}
     * Detail attendance employee
     */
    public function employeeDetail(Request $request, $employeeId): JsonResponse
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['employeeId' => $employeeId]), [
                'employeeId' => 'required|exists:employees,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->format('Y-m-d'));

            $detail = $this->processingService->getEmployeeAttendanceDetail($employeeId, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $detail
            ]);

        } catch (\Exception $e) {
            Log::error('Error in employee attendance detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync
     * Sync data dari mesin absensi
     */
    public function syncFromMachine(Request $request): JsonResponse
    {
        try {
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan. Silakan setup mesin terlebih dahulu.'
                ], 404);
            }

            // Test connection first
            $connectionTest = $this->machineService->testConnection($machine);
            if (!$connectionTest['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }

            // Pull data from machine
            $pin = $request->get('pin', 'All'); // Default ambil semua
            $pullResult = $this->machineService->pullAttendanceData($machine, $pin);

            if (!$pullResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari mesin: ' . $pullResult['message']
                ], 400);
            }

            // Process the data
            $processResult = $this->processingService->processUnprocessedLogs();

            return response()->json([
                'success' => true,
                'message' => 'Sync berhasil',
                'data' => [
                    'pull_result' => $pullResult,
                    'process_result' => $processResult
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error syncing from machine: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/process
     * Proses attendance logs yang belum diproses
     */
    public function processLogs(): JsonResponse
    {
        try {
            $result = $this->processingService->processUnprocessedLogs();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/process-today
     * Proses attendance untuk hari ini
     */
    public function processToday(): JsonResponse
    {
        try {
            $result = $this->processingService->processTodayAttendance();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing today attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/reprocess
     * Proses ulang attendance untuk tanggal tertentu
     */
    public function reprocessDate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->processingService->reprocessAttendanceForDate($request->date);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error reprocessing date: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/logs
     * List attendance logs
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'employee_id' => 'nullable|exists:employees,id',
                'processed' => 'nullable|boolean',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = AttendanceLog::with(['employee', 'attendanceMachine']);

            // Filter by date
            if ($request->has('date')) {
                $query->whereDate('datetime', $request->date);
            }

            // Filter by employee
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            // Filter by processed status
            if ($request->has('processed')) {
                $query->where('is_processed', $request->boolean('processed'));
            }

            $perPage = $request->get('per_page', 20);
            $logs = $query->orderBy('datetime', 'desc')
                         ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);

        } catch (\Exception $e) {
            Log::error('Error in attendance logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/summary
     * Summary attendance untuk periode tertentu
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->format('Y-m-d'));

            $summaries = [];
            $period = Carbon::parse($startDate);
            $endPeriod = Carbon::parse($endDate);

            while ($period <= $endPeriod) {
                $date = $period->format('Y-m-d');
                $summaries[] = $this->processingService->getAttendanceSummary($date);
                $period->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'summaries' => $summaries
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in attendance summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/attendance/{id}/recalculate
     * Recalculate attendance tertentu
     */
    public function recalculate($id): JsonResponse
    {
        try {
            $attendance = Attendance::find($id);
            
            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance tidak ditemukan'
                ], 404);
            }

                    $result = $this->processingService->recalculateAttendance(
            $attendance->user_pin, 
            $attendance->date
        );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['attendance'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Error recalculating attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/machine/status
     * Status mesin absensi
     */
    public function machineStatus(): JsonResponse
    {
        try {
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi belum dikonfigurasi'
                ], 404);
            }

            $connectionTest = $this->machineService->testConnection($machine);

            return response()->json([
                'success' => true,
                'data' => [
                    'machine' => $machine,
                    'connected' => $connectionTest['success'],
                    'connection_message' => $connectionTest['message'],
                    'last_sync' => $machine->last_sync_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking machine status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync/users
     * Sync user data dari mesin ke tabel employee_attendance
     */
    public function syncUserData(Request $request): JsonResponse
    {
        try {
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan. Silakan setup mesin terlebih dahulu.'
                ], 404);
            }

            $service = new AttendanceMachineService($machine);
            $result = $service->pullAndSyncUserData();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in sync user data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/users
     * Lihat data user yang ada di tabel employee_attendance
     */
    public function getUserList(Request $request): JsonResponse
    {
        try {
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan'
                ], 404);
            }

            $query = EmployeeAttendance::where('attendance_machine_id', $machine->id);

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by privilege
            if ($request->has('privilege')) {
                $query->where('privilege', $request->privilege);
            }

            // Search by name or PIN
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('machine_user_id', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 50);
            
            if ($request->has('all') && $request->boolean('all')) {
                $users = $query->orderBy('machine_user_id')->get();
                return response()->json([
                    'success' => true,
                    'message' => 'Data user berhasil diambil',
                    'data' => $users,
                    'total' => $users->count()
                ]);
            } else {
                $users = $query->orderBy('machine_user_id')->paginate($perPage);
                return response()->json([
                    'success' => true,
                    'message' => 'Data user berhasil diambil',
                    'data' => $users
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in get user list: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/today-realtime
     * Data attendance hari ini real-time (untuk testing tap terbaru)
     */
    public function todayRealtime(Request $request): JsonResponse
    {
        try {
            $date = now()->format('Y-m-d');
            
            // Get attendance for today only, sorted by latest check-in
            $todayAttendances = Attendance::where('date', $date)
                ->whereNotNull('check_in')
                ->orderBy('check_in', 'desc')
                ->get()
                ->map(function($attendance) {
                    return [
                        'id' => $attendance->id,
                        'user_name' => $attendance->user_name,
                        'user_pin' => $attendance->user_pin,
                        'card_number' => $attendance->card_number,
                        'check_in' => $attendance->check_in,
                        'check_out' => $attendance->check_out,
                        'status' => $attendance->status,
                        'total_taps' => $attendance->total_taps,
                        'late_minutes' => $attendance->late_minutes,
                        'updated_at' => $attendance->updated_at
                    ];
                });

            // Get latest logs for today (unprocessed or recent)
            $latestLogs = AttendanceLog::whereDate('datetime', $date)
                ->orderBy('datetime', 'desc')
                ->take(10)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'user_pin' => $log->user_pin,
                        'user_name' => $log->user_name,
                        'card_number' => $log->card_number,
                        'datetime' => $log->datetime,
                        'is_processed' => $log->is_processed,
                        'verified_method' => $log->verified_method
                    ];
                });

            // Summary for today
            $summary = $this->processingService->getAttendanceSummary($date);

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'current_time' => now()->format('Y-m-d H:i:s'),
                    'summary' => $summary,
                    'attendances' => $todayAttendances,
                    'latest_logs' => $latestLogs,
                    'total_attendances' => $todayAttendances->count(),
                    'total_logs_today' => AttendanceLog::whereDate('datetime', $date)->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in today realtime: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync-today
     * Sync hanya data hari ini dari mesin
     */
    public function syncToday(Request $request): JsonResponse
    {
        try {
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan.'
                ], 404);
            }

            // Test connection first
            $connectionTest = $this->machineService->testConnection($machine);
            if (!$connectionTest['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }

            // Pull data from machine
            $pullResult = $this->machineService->pullAttendanceData($machine);

            if (!$pullResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari mesin: ' . $pullResult['message']
                ], 400);
            }

            // Process only today's unprocessed logs
            $today = now()->format('Y-m-d');
            $result = $this->processingService->processTodayOnly($today);

            return response()->json([
                'success' => true,
                'message' => 'Sync hari ini berhasil',
                'data' => [
                    'pull_result' => $pullResult,
                    'process_result' => $result,
                    'date' => $today
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error syncing today: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
} 