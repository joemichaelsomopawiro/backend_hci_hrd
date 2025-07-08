<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttendanceMachine;
use App\Models\AttendanceLog;
use App\Models\EmployeeAttendance;
use App\Models\AttendanceSyncLog;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceProcessingService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AttendanceMachineController extends Controller
{
    private $attendanceMachineService;
    private $processingService;

    public function __construct(
        AttendanceMachineService $attendanceMachineService,
        AttendanceProcessingService $processingService
    ) {
        $this->attendanceMachineService = $attendanceMachineService;
        $this->processingService = $processingService;
    }

    /**
     * GET /api/attendance-machines
     * Get all attendance machines
     */
    public function index()
    {
        try {
            $machines = AttendanceMachine::with(['syncLogs' => function($query) {
                $query->latest()->limit(5);
            }])->get();

            return response()->json([
                'success' => true,
                'data' => $machines,
                'message' => 'Attendance machines retrieved successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error getting attendance machines', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mesin absensi'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines
     * Create new attendance machine
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'ip_address' => 'required|string|ip',
                'port' => 'required|integer|min:1|max:65535',
                'comm_key' => 'required|integer|min:1',
                'serial_number' => 'required|string|unique:attendance_machines,serial_number',
                'model' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $machine = AttendanceMachine::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $machine,
                'message' => 'Attendance machine created successfully'
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating attendance machine', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat mesin absensi'
            ], 500);
        }
    }

    /**
     * GET /api/attendance-machines/{id}
     * Get specific attendance machine
     */
    public function show($id)
    {
        try {
            $machine = AttendanceMachine::with(['syncLogs' => function($query) {
                $query->latest()->limit(10);
            }])->find($id);

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $machine,
                'message' => 'Attendance machine retrieved successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error getting attendance machine', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mesin absensi'
            ], 500);
        }
    }

    /**
     * PUT /api/attendance-machines/{id}
     * Update attendance machine
     */
    public function update(Request $request, $id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'ip_address' => 'sometimes|required|string|ip',
                'port' => 'sometimes|required|integer|min:1|max:65535',
                'comm_key' => 'sometimes|required|integer|min:1',
                'serial_number' => 'sometimes|required|string|unique:attendance_machines,serial_number,' . $id,
                'model' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $machine->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $machine,
                'message' => 'Attendance machine updated successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error updating attendance machine', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate mesin absensi'
            ], 500);
        }
    }

    /**
     * DELETE /api/attendance-machines/{id}
     * Delete attendance machine
     */
    public function destroy($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $machine->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attendance machine deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting attendance machine', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mesin absensi'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/test-connection
     * Test connection to attendance machine
     */
    public function testConnection($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $result = $this->attendanceMachineService->testConnection($machine);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        } catch (Exception $e) {
            Log::error('Error testing connection', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat test koneksi'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/pull-attendance
     * Pull attendance data from machine
     */
    public function pullAttendanceData($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $result = $this->attendanceMachineService->pullAttendanceData($machine);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        } catch (Exception $e) {
            Log::error('Error pulling attendance data', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menarik data absensi'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/pull-attendance-process
     * Pull and process attendance data
     */
    public function pullAndProcessAttendanceData($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            // Pull data from machine
            $pullResult = $this->attendanceMachineService->pullAttendanceData($machine);
            
            if (!$pullResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menarik data dari mesin: ' . $pullResult['message']
                ], 400);
            }

            // Process the data
            $processResult = $this->processingService->processUnprocessedLogs();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditarik dan diproses',
                'data' => [
                    'pull_result' => $pullResult,
                    'process_result' => $processResult
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error pulling and processing attendance data', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menarik dan memproses data absensi'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/sync-user/{employeeId}
     * Sync specific user to machine
     */
    public function syncSpecificUser($id, $employeeId)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            // Get employee data
            $employee = \App\Models\Employee::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Sync user data to machine
            $result = $this->attendanceMachineService->syncUserToMachine($machine, $employee);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        } catch (Exception $e) {
            Log::error('Error syncing specific user', ['id' => $id, 'employee_id' => $employeeId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi user'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/sync-all-users
     * Sync all users to machine
     */
    public function syncAllUsers($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            // Get all active employees
            $employees = \App\Models\Employee::where('is_active', true)->get();
            
            $syncResults = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($employees as $employee) {
                try {
                    $result = $this->attendanceMachineService->syncUserToMachine($machine, $employee);
                    $syncResults[$employee->id] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                } catch (Exception $e) {
                    $syncResults[$employee->id] = [
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    $failedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Sync selesai. Berhasil: {$successCount}, Gagal: {$failedCount}",
                'data' => [
                    'total_employees' => $employees->count(),
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'sync_results' => $syncResults
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error syncing all users', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi semua user'
            ], 500);
        }
    }

    /**
     * DELETE /api/attendance-machines/{id}/remove-user/{employeeId}
     * Remove user from machine
     */
    public function removeUser($id, $employeeId)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            // Remove user from employee_attendance table
            $deleted = EmployeeAttendance::where('attendance_machine_id', $id)
                ->where('machine_user_id', $employeeId)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'User berhasil dihapus dari mesin'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan di mesin'
                ], 404);
            }
        } catch (Exception $e) {
            Log::error('Error removing user', ['id' => $id, 'employee_id' => $employeeId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus user'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/restart
     * Restart attendance machine
     */
    public function restartMachine($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $result = $this->attendanceMachineService->restartMachine($machine);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ]);
        } catch (Exception $e) {
            Log::error('Error restarting machine', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat restart mesin'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/clear-data
     * Clear attendance data from machine
     */
    public function clearAttendanceData($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $result = $this->attendanceMachineService->clearAttendanceData($machine);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ]);
        } catch (Exception $e) {
            Log::error('Error clearing attendance data', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data absensi'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/sync-time
     * Sync time with machine
     */
    public function syncTime($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $result = $this->attendanceMachineService->syncTime($machine);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ]);
        } catch (Exception $e) {
            Log::error('Error syncing time', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi waktu'
            ], 500);
        }
    }

    /**
     * GET /api/attendance-machines/{id}/sync-logs
     * Get sync logs for machine
     */
    public function getSyncLogs($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $logs = AttendanceSyncLog::where('machine_id', $id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $logs,
                'message' => 'Sync logs retrieved successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error getting sync logs', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil log sinkronisasi'
            ], 500);
        }
    }

    /**
     * GET /api/attendance-machines/dashboard
     * Get dashboard data for all machines
     */
    public function getDashboard()
    {
        try {
            $machines = AttendanceMachine::with(['syncLogs' => function($query) {
                $query->latest()->limit(5);
            }])->get();

            $dashboardData = [
                'total_machines' => $machines->count(),
                'active_machines' => $machines->where('is_active', true)->count(),
                'inactive_machines' => $machines->where('is_active', false)->count(),
                'machines' => $machines->map(function($machine) {
                    $lastSync = $machine->syncLogs->first();
                    return [
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'ip_address' => $machine->ip_address,
                        'is_active' => $machine->is_active,
                        'last_sync' => $lastSync ? $lastSync->created_at : null,
                        'last_sync_status' => $lastSync ? $lastSync->status : null,
                        'total_sync_logs' => $machine->syncLogs->count()
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'message' => 'Dashboard data retrieved successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error getting dashboard data', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dashboard'
            ], 500);
        }
    }

    /**
     * POST /api/attendance-machines/{id}/pull-today
     * Pull today's attendance data from machine
     */
    public function pullTodayData($id, Request $request)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $targetDate = $request->get('date', now()->format('Y-m-d'));
            
            // Test connection first
            $connectionTest = $this->attendanceMachineService->testConnection($machine);
            if (!$connectionTest['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke mesin: ' . $connectionTest['message']
                ], 400);
            }

            // Pull today's data
            $pullResult = $this->attendanceMachineService->pullTodayAttendanceData($machine, $targetDate);
            
            if (!$pullResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menarik data hari ini: ' . $pullResult['message']
                ], 400);
            }

            // Process today's data
            $processResult = $this->processingService->processTodayOnly($targetDate);

            return response()->json([
                'success' => true,
                'message' => 'Data hari ini berhasil ditarik dan diproses',
                'data' => [
                    'pull_result' => $pullResult,
                    'process_result' => $processResult,
                    'target_date' => $targetDate
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error pulling today data', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menarik data hari ini'
            ], 500);
        }
    }

    /**
     * GET /api/attendance-machines/{id}/users
     * Get users registered in the machine
     */
    public function getMachineUsers($id)
    {
        try {
            $machine = AttendanceMachine::find($id);
            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance machine not found'
                ], 404);
            }

            $users = EmployeeAttendance::where('attendance_machine_id', $id)
                ->orderBy('name')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Machine users retrieved successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error getting machine users', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data user mesin'
            ], 500);
        }
    }
} 