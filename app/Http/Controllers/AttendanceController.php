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
    protected $leaveService;

    public function __construct(
        AttendanceMachineService $machineService, 
        AttendanceProcessingService $processingService,
        \App\Services\LeaveAttendanceIntegrationService $leaveService
    ) {
        $this->machineService = $machineService;
        $this->processingService = $processingService;
        $this->leaveService = $leaveService;
    }

    /**
     * Get registered user PINs (hanya PIN utama karena di logs sudah disimpan PIN utama)
     */
    private function getAllRegisteredPins(): array
    {
        // Hanya get PIN utama dari employee_attendance karena di logs sudah disimpan PIN utama
        return \App\Models\EmployeeAttendance::where('is_active', true)
            ->pluck('machine_user_id')
            ->toArray();
    }

    /**
     * GET /api/attendance/dashboard
     * Dashboard attendance hari ini (hanya user terdaftar)
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', now()->format('Y-m-d'));
            
            $summary = $this->processingService->getAttendanceSummary($date);
            
            // Get all registered user PINs (main + PIN2)
            $registeredUserPins = $this->getAllRegisteredPins();

            // Get latest attendance records for today (hanya user terdaftar)
            $latestAttendances = Attendance::where('date', $date)
                ->whereIn('user_pin', $registeredUserPins) // Only registered users
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
//tesfwfewefwef
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
     * Sync data dari mesin absensi (FULL SYNC - SEMUA DATA)
     */
    public function syncFromMachine(Request $request): JsonResponse
    {
        // Set longer timeout untuk full sync
        set_time_limit(300); // 5 menit timeout
        ini_set('memory_limit', '512M'); // Increase memory limit
        
        try {
            Log::info('Full Sync: Started', ['requested_by' => $request->ip()]);
            
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan. Silakan setup mesin terlebih dahulu.'
                ], 404);
            }

            // Step 1: Test connection dengan timeout yang lebih panjang
            Log::info('Full Sync: Testing connection...');
            $connectionTest = $this->machineService->testConnection($machine);
            if (!$connectionTest['success']) {
                Log::error('Full Sync: Connection failed', ['error' => $connectionTest['message']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }
            Log::info('Full Sync: Connection successful');

            // Step 2: Pull SEMUA data dari mesin
            Log::info('Full Sync: Pulling all data from machine...');
            $pin = $request->get('pin', 'All'); // Default ambil semua
            $pullResult = $this->machineService->pullAttendanceData($machine, $pin);

            if (!$pullResult['success']) {
                Log::error('Full Sync: Pull data failed', ['error' => $pullResult['message']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari mesin: ' . $pullResult['message']
                ], 400);
            }
            
            $totalFromMachine = count($pullResult['data'] ?? []);
            Log::info('Full Sync: Pull data successful', ['total_records' => $totalFromMachine]);

            // Step 3: Process semua logs yang belum diproses
            Log::info('Full Sync: Processing unprocessed logs...');
            $processResult = $this->processingService->processUnprocessedLogs();
            Log::info('Full Sync: Processing successful', ['processed' => $processResult['processed']]);

            // Step 4: AUTO-SYNC - Link employee data
            Log::info('Full Sync: Auto-sync employee linking...');
            $syncResults = [];
            $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                                    ->whereNull('employee_id')
                                                    ->distinct()
                                                    ->pluck('user_name');
            
            $syncedCount = 0;
            foreach ($uniqueUserNames as $userName) {
                try {
                    $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
                    $syncResults[$userName] = $syncResult;
                    if ($syncResult['success']) {
                        $syncedCount++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Full Sync: Auto-sync failed for user', ['user' => $userName, 'error' => $e->getMessage()]);
                    $syncResults[$userName] = ['success' => false, 'message' => $e->getMessage()];
                }
            }
            
            Log::info('Full Sync: Auto-sync completed', [
                'total_users' => count($uniqueUserNames),
                'synced_count' => $syncedCount
            ]);

            // Tambahan: Sinkronisasi employee_id di attendances berdasarkan nama
            $attendancesWithoutEmployee = \App\Models\Attendance::whereNull('employee_id')
                ->whereNotNull('user_name')
                ->get();
            $autoLinked = 0;
            foreach ($attendancesWithoutEmployee as $attendance) {
                $employee = \App\Models\Employee::where('nama_lengkap', $attendance->user_name)->first();
                if ($employee) {
                    $attendance->employee_id = $employee->id;
                    $attendance->save();
                    $autoLinked++;
                }
            }
            Log::info('Full Sync: Auto-link employee_id in attendances', [
                'auto_linked' => $autoLinked
            ]);

            // Final response - format yang kompatibel dengan frontend
            $responseData = [
                'pull_result' => [
                    'success' => $pullResult['success'],
                    'message' => $pullResult['message'],
                    'data' => $pullResult['data'] ?? [], // Array data untuk frontend stats
                    'stats' => $pullResult['stats'] ?? [],
                    'total_from_machine' => $totalFromMachine
                ],
                'process_result' => [
                    'success' => $processResult['success'],
                    'message' => $processResult['message'],
                    'processed' => $processResult['processed'],
                    'details' => $processResult
                ],
                'sync_results' => [
                    'total_users' => count($uniqueUserNames),
                    'synced_count' => $syncedCount,
                    'details' => $syncResults
                ],
                'summary' => [
                    'total_pulled' => $totalFromMachine,
                    'total_processed' => $processResult['processed'],
                    'total_synced' => $syncedCount,
                    'operation' => 'FULL SYNC - Semua data dari mesin'
                ]
            ];

            Log::info('Full Sync: Completed successfully', $responseData['summary']);

            return response()->json([
                'success' => true,
                'message' => "Full sync berhasil! Pulled: {$totalFromMachine}, Processed: {$processResult['processed']}, Synced: {$syncedCount} users",
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Full Sync: Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada full sync: ' . $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
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

            // 🔥 AUTO-SYNC: Sinkronisasi otomatis untuk semua employee yang ada di attendance
            $syncResults = [];
            $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                                    ->whereNull('employee_id')
                                                    ->distinct()
                                                    ->pluck('user_name');
            
            foreach ($uniqueUserNames as $userName) {
                $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
                $syncResults[$userName] = $syncResult;
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => array_merge($result, ['sync_results' => $syncResults])
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
     * Data attendance hari ini real-time (hanya user terdaftar)
     */
    public function todayRealtime(Request $request): JsonResponse
    {
        try {
            $date = now()->format('Y-m-d');
            
            // Get all registered user PINs (main + PIN2)
            $registeredUserPins = $this->getAllRegisteredPins();

            // Get attendance for today only dari user terdaftar, sorted by latest check-in
            $todayAttendances = Attendance::where('date', $date)
                ->whereIn('user_pin', $registeredUserPins) // Only registered users
                ->whereNotNull('check_in')
                ->orderBy('check_in', 'desc')
                ->get()
                ->map(function($attendance) {
                    return [
                        'id' => $attendance->id,
                        'user_name' => $attendance->user_name,
                        'user_pin' => $attendance->user_pin,
                        'date' => $attendance->date,
                        'check_in' => $attendance->check_in,
                        'check_out' => $attendance->check_out,
                        'status' => $attendance->status,
                        'work_hours' => $attendance->work_hours,
                        'late_minutes' => $attendance->late_minutes,
                        'updated_at' => $attendance->updated_at
                    ];
                });

            // Get latest 10 tap logs for today dari user terdaftar
            $latestLogs = AttendanceLog::whereDate('datetime', $date)
                ->whereIn('user_pin', $registeredUserPins) // Only registered users
                ->orderBy('datetime', 'desc')
                ->take(10)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'user_pin' => $log->user_pin,
                        'user_name' => $log->user_name,
                        'datetime' => $log->datetime,
                        'is_processed' => $log->is_processed
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
                    'total_logs_today' => AttendanceLog::whereDate('datetime', $date)
                        ->whereIn('user_pin', $registeredUserPins)
                        ->count(),
                    'registered_users' => count($registeredUserPins)
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
            // Step 1: Find machine
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                Log::error('Sync Today: Machine not found with IP 10.10.10.85');
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan.'
                ], 404);
            }

            Log::info('Sync Today: Machine found', ['machine' => $machine->name]);

            // Step 2: Test connection first
            try {
                $connectionTest = $this->machineService->testConnection($machine);
                if (!$connectionTest['success']) {
                    Log::warning('Sync Today: Connection failed', ['message' => $connectionTest['message']]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                    ], 400);
                }
                Log::info('Sync Today: Connection successful');
            } catch (\Exception $e) {
                Log::error('Sync Today: Connection test exception', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error testing connection: ' . $e->getMessage()
                ], 500);
            }

            // Step 3: Pull HANYA data hari ini dari mesin
            try {
                $today = now()->format('Y-m-d');
                $pullResult = $this->machineService->pullTodayAttendanceData($machine, $today);

                if (!$pullResult['success']) {
                    Log::error('Sync Today: Pull today data failed', ['message' => $pullResult['message']]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal mengambil data hari ini dari mesin: ' . $pullResult['message']
                    ], 400);
                }
                Log::info('Sync Today: Pull today data successful', ['stats' => $pullResult['stats']]);
            } catch (\Exception $e) {
                Log::error('Sync Today: Pull today data exception', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error pulling today data: ' . $e->getMessage()
                ], 500);
            }

            // Step 4: Process only today's unprocessed logs
            try {
                $result = $this->processingService->processTodayOnly($today);
                Log::info('Sync Today: Processing successful', ['processed' => $result['processed']]);
            } catch (\Exception $e) {
                Log::error('Sync Today: Processing exception', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing logs: ' . $e->getMessage()
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync hari ini berhasil - hanya data hari ini yang diproses',
                'data' => [
                    'pull_result' => $pullResult,
                    'process_result' => $result,
                    'date' => $today,
                    'optimization' => 'Hanya data hari ini yang diambil dan diproses'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error syncing today: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync-today-only
     * Sync KHUSUS hanya data hari ini dari mesin (optimized)
     */
    public function syncTodayOnly(Request $request): JsonResponse
    {
        try {
            // Ambil tanggal target (default hari ini)
            $targetDate = $request->get('date', now()->format('Y-m-d'));
            
            // Validasi format tanggal
            if (!Carbon::createFromFormat('Y-m-d', $targetDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format tanggal tidak valid. Gunakan format: Y-m-d'
                ], 422);
            }

            // Find machine
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan.'
                ], 404);
            }

            Log::info('Sync Today Only: Started', ['target_date' => $targetDate, 'machine' => $machine->name]);

            // Test connection
            $connectionTest = $this->machineService->testConnection($machine);
            if (!$connectionTest['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }

            // Pull data khusus hari ini
            $pullResult = $this->machineService->pullTodayAttendanceData($machine, $targetDate);

            if (!$pullResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data: ' . $pullResult['message']
                ], 400);
            }

            // Process hanya data hari ini
            $processResult = $this->processingService->processTodayOnly($targetDate);

            Log::info('Sync Today Only: Completed', [
                'target_date' => $targetDate,
                'pull_stats' => $pullResult['stats'] ?? null,
                'process_result' => $processResult
            ]);

            return response()->json([
                'success' => true,
                'message' => "Sync berhasil untuk {$targetDate} - hanya data hari ini",
                'data' => [
                    'date' => $targetDate,
                    'pull_result' => $pullResult,
                    'process_result' => $processResult,
                    'optimization_info' => [
                        'total_from_machine' => $pullResult['stats']['total_from_machine'] ?? 0,
                        'filtered_today' => $pullResult['stats']['today_filtered'] ?? 0,
                        'processed' => $pullResult['stats']['processed'] ?? 0,
                        'message' => 'Hanya mengambil dan memproses data untuk tanggal yang diminta'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in sync today only: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/attendance/debug-sync
     * Debug sync functionality step by step
     */
    public function debugSync(Request $request): JsonResponse
    {
        $steps = [];
        $errors = [];
        
        try {
            // Step 1: Check machine exists
            $steps[] = "1. Checking if attendance machine exists...";
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();
            
            if (!$machine) {
                $errors[] = "Machine not found with IP 10.10.10.85";
                return response()->json([
                    'success' => false,
                    'message' => 'Machine not found',
                    'steps' => $steps,
                    'errors' => $errors
                ], 404);
            }
            $steps[] = "✅ Machine found: {$machine->name}";
            
            // Step 2: Test service instantiation
            $steps[] = "2. Testing service instantiation...";
            $machineService = $this->machineService;
            $processingService = $this->processingService;
            $steps[] = "✅ Services instantiated successfully";
            
            // Step 3: Test connection (with timeout)
            $steps[] = "3. Testing connection to machine...";
            try {
                $connectionTest = $machineService->testConnection($machine);
                if ($connectionTest['success']) {
                    $steps[] = "✅ Connection successful";
                } else {
                    $steps[] = "⚠️ Connection failed: " . $connectionTest['message'];
                }
            } catch (\Exception $e) {
                $steps[] = "❌ Connection test error: " . $e->getMessage();
            }
            
            // Step 4: Test processTodayOnly method
            $steps[] = "4. Testing processTodayOnly method...";
            $today = now()->format('Y-m-d');
            $result = $processingService->processTodayOnly($today);
            $steps[] = "✅ ProcessTodayOnly successful: " . $result['message'];
            
            return response()->json([
                'success' => true,
                'message' => 'Debug completed successfully',
                'data' => [
                    'machine' => [
                        'name' => $machine->name,
                        'ip_address' => $machine->ip_address,
                        'status' => $machine->status
                    ],
                    'today' => $today,
                    'result' => $result
                ],
                'steps' => $steps,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            $errors[] = "Exception: " . $e->getMessage();
            $steps[] = "❌ Error occurred: " . $e->getMessage();
            
            return response()->json([
                'success' => false,
                'message' => 'Debug failed: ' . $e->getMessage(),
                'steps' => $steps,
                'errors' => $errors,
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/link-employees
     * Link employee dengan employee_attendance berdasarkan nama yang sama
     */
    public function linkEmployees(Request $request): JsonResponse
    {
        try {
            $successCount = 0;
            $employees = Employee::all();
            $attendanceUsers = EmployeeAttendance::whereNull('employee_id')
                ->where('is_active', true)
                ->get();

            foreach ($employees as $employee) {
                $employeeName = trim(strtolower($employee->nama_lengkap));
                
                // Cari matching berdasarkan nama (exact match only)
                $matchingAttendanceUser = $attendanceUsers->first(function ($attendanceUser) use ($employeeName) {
                    $attendanceName = trim(strtolower($attendanceUser->name));
                    return $attendanceName === $employeeName;
                });

                if ($matchingAttendanceUser) {
                    $matchingAttendanceUser->update(['employee_id' => $employee->id]);
                    $successCount++;
                    
                    // Remove from collection agar tidak dipakai lagi
                    $attendanceUsers = $attendanceUsers->reject(function ($user) use ($matchingAttendanceUser) {
                        return $user->id === $matchingAttendanceUser->id;
                    });
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil link {$successCount} employee",
                'data' => [
                    'linked' => $successCount,
                    'total_employees' => $employees->count(),
                    'total_attendance_users' => $attendanceUsers->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in linkEmployees: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync-current-month
     * Sync attendance data untuk bulan saat ini dari mesin
     */
    public function syncCurrentMonth(Request $request): JsonResponse
    {
        // Set longer timeout untuk monthly sync
        set_time_limit(300); // 5 menit timeout
        ini_set('memory_limit', '512M'); // Increase memory limit
        
        try {
            Log::info('Monthly Sync: Started', ['requested_by' => $request->ip()]);
            
            $currentDate = Carbon::now();
            $currentYear = $currentDate->year;
            $currentMonth = $currentDate->month;
            $monthName = $currentDate->format('F');
            
            Log::info('Monthly Sync: Target month', [
                'month' => $monthName,
                'year' => $currentYear,
                'month_number' => $currentMonth
            ]);
            
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan. Silakan setup mesin terlebih dahulu.'
                ], 404);
            }

            // Step 1: Test connection
            Log::info('Monthly Sync: Testing connection...');
            $connectionTest = $this->machineService->testConnection($machine);
            if (!$connectionTest['success']) {
                Log::error('Monthly Sync: Connection failed', ['error' => $connectionTest['message']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }
            Log::info('Monthly Sync: Connection successful');

            // Step 2: Pull data untuk bulan saat ini
            Log::info('Monthly Sync: Pulling current month data from machine...');
            $pullResult = $this->machineService->pullCurrentMonthAttendanceData($machine);

            if (!$pullResult['success']) {
                Log::error('Monthly Sync: Pull data failed', ['error' => $pullResult['message']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari mesin: ' . $pullResult['message']
                ], 400);
            }
            
            $totalFromMachine = count($pullResult['data'] ?? []);
            $monthFiltered = $pullResult['stats']['month_filtered'] ?? 0;
            Log::info('Monthly Sync: Pull data successful', [
                'total_records' => $totalFromMachine,
                'month_filtered' => $monthFiltered
            ]);

            // Step 3: Process semua logs yang belum diproses
            Log::info('Monthly Sync: Processing unprocessed logs...');
            $processResult = $this->processingService->processUnprocessedLogs();
            Log::info('Monthly Sync: Processing successful', ['processed' => $processResult['processed']]);

            // Step 4: AUTO-SYNC - Link employee data
            Log::info('Monthly Sync: Auto-sync employee linking...');
            $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                                    ->whereNull('employee_id')
                                                    ->distinct()
                                                    ->pluck('user_name');
            
            $syncedCount = 0;
            foreach ($uniqueUserNames as $userName) {
                try {
                    $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
                    if ($syncResult['success']) {
                        $syncedCount++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Monthly Sync: Auto-sync failed for user', ['user' => $userName, 'error' => $e->getMessage()]);
                }
            }
            
            Log::info('Monthly Sync: Auto-sync completed', [
                'total_users' => count($uniqueUserNames),
                'synced_count' => $syncedCount
            ]);

            // Step 5: Sinkronisasi employee_id di attendances berdasarkan nama
            Log::info('Monthly Sync: Syncing employee_id in attendances...');
            $attendanceUpdateCount = 0;
            $attendancesWithoutEmployee = \App\Models\Attendance::whereNull('employee_id')
                                                                ->whereNotNull('user_name')
                                                                ->get();
            
            foreach ($attendancesWithoutEmployee as $attendance) {
                $employee = \App\Models\Employee::where('nama_lengkap', $attendance->user_name)->first();
                if ($employee) {
                    $attendance->update(['employee_id' => $employee->id]);
                    $attendanceUpdateCount++;
                }
            }
            
            Log::info('Monthly Sync: Employee ID sync completed', ['updated_count' => $attendanceUpdateCount]);

            return response()->json([
                'success' => true,
                'message' => "Monthly sync berhasil untuk {$monthName} {$currentYear}",
                'data' => [
                    'month' => $monthName,
                    'year' => $currentYear,
                    'month_number' => $currentMonth,
                    'pull_result' => $pullResult,
                    'process_result' => $processResult,
                    'auto_sync_result' => [
                        'total_users' => count($uniqueUserNames),
                        'synced_count' => $syncedCount
                    ],
                    'employee_id_sync' => [
                        'updated_count' => $attendanceUpdateCount
                    ],
                    'monthly_stats' => [
                        'total_from_machine' => $totalFromMachine,
                        'month_filtered' => $monthFiltered,
                        'processed_to_logs' => $pullResult['stats']['processed_to_logs'] ?? 0,
                        'processed_to_attendances' => $processResult['processed'] ?? 0,
                        'start_date' => $pullResult['stats']['start_date'] ?? null,
                        'end_date' => $pullResult['stats']['end_date'] ?? null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Monthly Sync: Error in sync current month: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync-current-month-fast
     * Sync data absensi bulan saat ini dari mesin (FAST VERSION)
     */
    public function syncCurrentMonthFast(Request $request): JsonResponse
    {
        // Set optimized timeout untuk fast monthly sync
        set_time_limit(180); // 3 menit timeout (reduced from 5)
        ini_set('memory_limit', '256M'); // Reduced memory limit
        
        try {
            Log::info('Fast Monthly Sync: Started', ['requested_by' => $request->ip()]);
            
            $currentDate = Carbon::now();
            $currentYear = $currentDate->year;
            $currentMonth = $currentDate->month;
            $monthName = $currentDate->format('F');
            
            Log::info('Fast Monthly Sync: Target month', [
                'month' => $monthName,
                'year' => $currentYear,
                'month_number' => $currentMonth
            ]);
            
            $machine = AttendanceMachine::where('ip_address', '10.10.10.85')->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin absensi tidak ditemukan. Silakan setup mesin terlebih dahulu.'
                ], 404);
            }

            // Step 1: Test connection
            Log::info('Fast Monthly Sync: Testing connection...');
            $connectionTest = $this->machineService->testConnection($machine);
            if (!$connectionTest['success']) {
                Log::error('Fast Monthly Sync: Connection failed', ['error' => $connectionTest['message']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }
            Log::info('Fast Monthly Sync: Connection successful');

            // Step 2: Pull data untuk bulan saat ini (FAST VERSION)
            Log::info('Fast Monthly Sync: Pulling current month data from machine (FAST)...');
            $pullResult = $this->machineService->pullCurrentMonthAttendanceDataFast($machine);

            if (!$pullResult['success']) {
                Log::error('Fast Monthly Sync: Pull data failed', ['error' => $pullResult['message']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari mesin: ' . $pullResult['message']
                ], 400);
            }
            
            $totalFromMachine = count($pullResult['data'] ?? []);
            $monthFiltered = $pullResult['stats']['month_filtered'] ?? 0;
            Log::info('Fast Monthly Sync: Pull data successful', [
                'total_records' => $totalFromMachine,
                'month_filtered' => $monthFiltered
            ]);

            // Step 3: Process semua logs yang belum diproses
            Log::info('Fast Monthly Sync: Processing unprocessed logs...');
            $processResult = $this->processingService->processUnprocessedLogs();
            Log::info('Fast Monthly Sync: Processing successful', ['processed' => $processResult['processed']]);

            // Step 4: AUTO-SYNC - Link employee data
            Log::info('Fast Monthly Sync: Auto-sync employee linking...');
            $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                                    ->whereNull('employee_id')
                                                    ->distinct()
                                                    ->pluck('user_name');
            
            $syncedCount = 0;
            foreach ($uniqueUserNames as $userName) {
                try {
                    $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
                    if ($syncResult['success']) {
                        $syncedCount++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Fast Monthly Sync: Auto-sync failed for user', ['user' => $userName, 'error' => $e->getMessage()]);
                }
            }
            
            Log::info('Fast Monthly Sync: Auto-sync completed', [
                'total_users' => count($uniqueUserNames),
                'synced_count' => $syncedCount
            ]);

            // Step 5: Sinkronisasi employee_id di attendances berdasarkan nama
            Log::info('Fast Monthly Sync: Syncing employee_id in attendances...');
            $attendanceUpdateCount = 0;
            $attendancesWithoutEmployee = \App\Models\Attendance::whereNull('employee_id')
                                                                ->whereNotNull('user_name')
                                                                ->get();
            
            foreach ($attendancesWithoutEmployee as $attendance) {
                $employee = \App\Models\Employee::where('nama_lengkap', $attendance->user_name)->first();
                if ($employee) {
                    $attendance->update(['employee_id' => $employee->id]);
                    $attendanceUpdateCount++;
                }
            }
            
            Log::info('Fast Monthly Sync: Employee ID sync completed', ['updated_count' => $attendanceUpdateCount]);

            // Step 6: Auto Export Excel
            Log::info('Fast Monthly Sync: Auto-exporting Excel...');
            $exportResult = null;
            $downloadUrl = null;
            $filename = null;
            try {
                $exportResponse = $this->autoExportMonthlyExcel($currentYear, $currentMonth);
                $exportResult = $exportResponse;
                
                // Extract download info
                if (isset($exportResponse['success']) && $exportResponse['success']) {
                    $downloadUrl = $exportResponse['download_url'] ?? null;
                    $filename = $exportResponse['filename'] ?? null;
                }
                
                Log::info('Fast Monthly Sync: Auto-export successful', [
                    'export_result' => $exportResult,
                    'download_url' => $downloadUrl,
                    'filename' => $filename
                ]);
            } catch (\Exception $e) {
                Log::warning('Fast Monthly Sync: Auto-export failed', ['error' => $e->getMessage()]);
                $exportResult = ['error' => $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'message' => "FAST Monthly sync berhasil untuk {$monthName} {$currentYear}",
                'data' => [
                    'month' => $monthName,
                    'year' => $currentYear,
                    'month_number' => $currentMonth,
                    'pull_result' => $pullResult,
                    'process_result' => $processResult,
                    'auto_sync_result' => [
                        'total_users' => count($uniqueUserNames),
                        'synced_count' => $syncedCount
                    ],
                    'employee_id_sync' => [
                        'updated_count' => $attendanceUpdateCount
                    ],
                    'export_result' => $exportResult,
                    'download_url' => $downloadUrl,
                    'filename' => $filename,
                    'monthly_stats' => [
                        'total_from_machine' => $totalFromMachine,
                        'month_filtered' => $monthFiltered,
                        'processed_to_logs' => $pullResult['stats']['processed_to_logs'] ?? 0,
                        'processed_to_attendances' => $processResult['processed'] ?? 0,
                        'start_date' => $pullResult['stats']['start_date'] ?? null,
                        'end_date' => $pullResult['stats']['end_date'] ?? null,
                        'sync_type' => 'fast_monthly_sync'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Fast Monthly Sync: Error in sync current month: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto export Excel untuk bulan tertentu
     */
    private function autoExportMonthlyExcel(int $year, int $month): array
    {
        try {
            // Sinkronisasi status cuti untuk seluruh bulan
            $startDate = Carbon::create($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');
            $this->leaveService->syncLeaveStatusForDateRange($startDate, $endDate);
            
            // Ambil semua karyawan yang terdaftar
            $employees = \App\Models\EmployeeAttendance::where('is_active', true)
                ->with('employee')
                ->get()
                ->sortBy(function($emp) {
                    if ($emp->employee) {
                        return $emp->employee->nama_lengkap;
                    }
                    return $emp->name;
                });

            // Ambil data absensi untuk bulan tertentu
            $attendances = \App\Models\Attendance::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->get()
                ->groupBy('user_pin');

            // Generate working days
            $workingDays = $this->generateWorkingDays($year, $month);
            $monthName = Carbon::create($year, $month)->format('F');
            $yearName = $year;

            // Generate Excel file
            $result = $this->generateMonthlyExcelFile($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName);
            
            return [
                'success' => true,
                'filename' => $result['filename'],
                'download_url' => $result['download_url'],
                'direct_download_url' => $result['direct_download_url'],
                'total_employees' => $result['total_employees'],
                'working_days' => $result['working_days'],
                'month' => $result['month']
            ];

        } catch (\Exception $e) {
            Log::error('Auto export Excel error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate working days untuk bulan tertentu
     */
    private function generateWorkingDays($year, $month): array
    {
        $workingDays = [];
        $startDate = Carbon::create($year, $month, 1);
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
                $workingDays[] = [
                    'day' => $currentDate->day,
                    'date' => $currentDate->format('Y-m-d'),
                    'dayName' => $currentDate->format('D')
                ];
            }
            $currentDate->addDay();
        }
        
        return $workingDays;
    }

    /**
     * Generate Excel file untuk bulan tertentu
     */
    private function generateMonthlyExcelFile($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName): array
    {
        // Buat mapping dari user_pin ke employee data
        $employeeMap = [];
        foreach ($employees as $emp) {
            $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
            $employeeMap[$emp->machine_user_id] = $nama;
        }

        // Siapkan data matrix
        $matrix = [];
        foreach ($employees as $emp) {
            $userPin = $emp->machine_user_id;
            $nama = $employeeMap[$userPin] ?? $userPin;
            $matrix[$userPin] = [
                'nama' => $nama,
                'data' => [],
                'total_hadir' => 0,
                'total_jam' => 0.0
            ];
        }
        
        // Isi data matrix
        foreach ($employees as $emp) {
            $userPin = $emp->machine_user_id;
            $empAttendances = $attendances->get($userPin, collect());
            
            foreach ($workingDays as $workingDay) {
                $date = $workingDay['date'];
                $day = $workingDay['day'];
                $att = $empAttendances->filter(function($item) use ($date) {
                    return $item->date->format('Y-m-d') === $date;
                })->first();
                
                if ($att) {
                    if (in_array($att->status, ['on_leave', 'sick_leave'])) {
                        $leaveType = $att->status === 'sick_leave' ? 'Sakit' : 'Cuti';
                        $matrix[$userPin]['data'][$day] = 'CUTI_' . $leaveType;
                        $matrix[$userPin]['total_hadir']++;
                    } elseif ($att->check_in) {
                        if ($att->check_out) {
                            $jamKerja = $att->work_hours ? number_format($att->work_hours, 2) : '0.00';
                            $matrix[$userPin]['data'][$day] = $att->check_in->format('H:i') . '-' . $att->check_out->format('H:i');
                            $matrix[$userPin]['total_jam'] += (float)$jamKerja;
                        } else {
                            $matrix[$userPin]['data'][$day] = $att->check_in->format('H:i') . '-';
                        }
                        $matrix[$userPin]['total_hadir']++;
                    } else {
                        $matrix[$userPin]['data'][$day] = 'ABSEN';
                    }
                } else {
                    $matrix[$userPin]['data'][$day] = '';
                }
            }
        }

        // Urutkan matrix berdasarkan nama
        $matrix = collect($matrix)->sortBy('nama')->toArray();

        // Generate HTML table
        $html = $this->generateExcelHTML($matrix, $workingDays, $monthName, $yearName);

        // Save file
        $filename = 'Absensi_' . $monthName . '_' . $yearName . '_Hope_Channel_Indonesia.xls';
        $filePath = storage_path('app/public/exports/' . $filename);
        
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        file_put_contents($filePath, $html);

        return [
            'filename' => $filename,
            'download_url' => url('storage/exports/' . $filename),
            'direct_download_url' => url('api/attendance/export/download/' . $filename),
            'total_employees' => count($matrix),
            'working_days' => count($workingDays),
            'month' => $monthName . ' ' . $yearName
        ];
    }

    /**
     * Generate HTML untuk Excel
     */
    private function generateExcelHTML($matrix, $workingDays, $monthName, $yearName): string
    {
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Absensi ' . $monthName . ' ' . $yearName . ' Hope Channel Indonesia</title>';
        $html .= '<style>';
        $html .= 'table { border-collapse: collapse; width: 100%; font-size: 11px; }';
        $html .= 'th, td { border: 1px solid #333; padding: 6px; text-align: center; }';
        $html .= 'th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; }';
        $html .= '.nama { text-align: left; font-weight: bold; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: black; }';
        $html .= '.hadir-lengkap { background-color: #4CAF50; color: white; font-weight: bold; }';
        $html .= '.hadir-masuk { background-color: #FFC107; color: #333; font-weight: bold; }';
        $html .= '.tidak-hadir { background-color: #F44336; color: white; font-weight: bold; }';
        $html .= '.cuti { background-color: #1565C0; color: white; font-weight: bold; }';
        $html .= '.total-col { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); font-weight: bold; color: #333; }';
        $html .= '.title { text-align: center; font-weight: bold; font-size: 18px; margin: 15px 0; color: #333; }';
        $html .= '.subtitle { text-align: center; font-size: 14px; margin: 10px 0; color: #666; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="title">📊 LAPORAN ABSENSI ' . strtoupper($monthName) . ' ' . $yearName . '</div>';
        $html .= '<div class="title" style="font-size: 16px;">Hope Channel Indonesia</div>';
        $html .= '<div class="subtitle">📅 Hari Kerja (Senin-Jumat)</div>';
        $html .= '<table>';
        $html .= '<thead><tr><th>👤 Nama Karyawan</th>';
        foreach ($workingDays as $workingDay) {
            $html .= '<th>' . $workingDay['day'] . '<br><small>(' . $workingDay['dayName'] . ')</small></th>';
        }
        $html .= '<th class="total-col">✅ Total Hadir</th><th class="total-col">⏰ Total Jam Kerja</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($matrix as $row) {
            $html .= '<tr>';
            $html .= '<td class="nama">' . htmlspecialchars($row['nama']) . '</td>';
            foreach ($workingDays as $workingDay) {
                $day = $workingDay['day'];
                $cellData = $row['data'][$day] ?? '';
                
                if ($cellData === '') {
                    $cellClass = 'tidak-hadir';
                    $cellContent = '-';
                } elseif ($cellData === 'ABSEN') {
                    $cellClass = 'tidak-hadir';
                    $cellContent = '-';
                } elseif (strpos($cellData, 'CUTI_') === 0) {
                    $cellClass = 'cuti';
                    $leaveType = str_replace('CUTI_', '', $cellData);
                    $cellContent = $leaveType;
                } elseif (strpos($cellData, '-') !== false && substr($cellData, -1) === '-') {
                    $cellClass = 'hadir-masuk';
                    $cellContent = $cellData . ' (Hanya Masuk)';
                } elseif (strpos($cellData, '-') !== false && substr($cellData, -1) !== '-') {
                    $cellClass = 'hadir-lengkap';
                    $cellContent = $cellData;
                } else {
                    $cellClass = '';
                    $cellContent = $cellData;
                }
                
                $html .= '<td class="' . $cellClass . '">' . $cellContent . '</td>';
            }
            $html .= '<td class="total-col">' . $row['total_hadir'] . '</td>';
            $html .= '<td class="total-col">' . number_format($row['total_jam'], 2) . ' jam</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        // Legend
        $html .= '<div style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">';
        $html .= '<h3 style="margin-top: 0; color: #333;">📋 Keterangan Warna:</h3>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #4CAF50; margin-right: 10px; border-radius: 3px;"></span><strong>Hijau:</strong> Hadir Lengkap (Check-in & Check-out)</div>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #FFC107; margin-right: 10px; border-radius: 3px;"></span><strong>Kuning:</strong> Hanya Check-in</div>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #1565C0; margin-right: 10px; border-radius: 3px;"></span><strong>Biru Tua:</strong> Cuti/Sakit</div>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #F44336; margin-right: 10px; border-radius: 3px;"></span><strong>Merah:</strong> Tidak Hadir (-)</div>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * POST /api/attendance/sync-leave
     * Sync leave status to attendance for specific date
     */
    public function syncLeaveToAttendance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'employee_id' => 'nullable|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = $request->get('date', now()->format('Y-m-d'));
            $employeeId = $request->get('employee_id');

            $leaveService = app(\App\Services\LeaveAttendanceIntegrationService::class);
            
            if ($employeeId) {
                $result = $leaveService->syncLeaveStatusToAttendance($date, $employeeId);
            } else {
                $result = $leaveService->syncLeaveStatusToAttendance($date);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave status berhasil disinkronisasi ke attendance',
                'data' => [
                    'date' => $date,
                    'employee_id' => $employeeId,
                    'result' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in syncLeaveToAttendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/attendance/sync-leave-date-range
     * Sync leave status to attendance for date range
     */
    public function syncLeaveToAttendanceDateRange(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'employee_id' => 'nullable|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $employeeId = $request->get('employee_id');

            $leaveService = app(\App\Services\LeaveAttendanceIntegrationService::class);
            
            if ($employeeId) {
                $result = $leaveService->syncLeaveStatusForDateRange($startDate, $endDate, $employeeId);
            } else {
                $result = $leaveService->syncLeaveStatusForDateRange($startDate, $endDate);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave status berhasil disinkronisasi ke attendance untuk rentang tanggal',
                'data' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'employee_id' => $employeeId,
                    'result' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in syncLeaveToAttendanceDateRange: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}