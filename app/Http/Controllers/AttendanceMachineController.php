<?php

namespace App\Http\Controllers;

use App\Models\AttendanceMachine;
use App\Models\AttendanceSyncLog;
use App\Models\AttendanceMachineUser;
use App\Models\Employee;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceMachineController extends Controller
{
    protected $attendanceSyncService;

    public function __construct(AttendanceSyncService $attendanceSyncService)
    {
        $this->attendanceSyncService = $attendanceSyncService;
    }

    /**
     * Display a listing of attendance machines
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AttendanceMachine::query();

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by name or IP
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            }

            $machines = $query->with(['syncLogs' => function($q) {
                $q->latest('started_at')->limit(5);
            }])->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $machines,
                'message' => 'Attendance machines retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving attendance machines', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance machines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created attendance machine
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'ip_address' => 'required|ip|unique:attendance_machines,ip_address',
                'port' => 'nullable|integer|min:1|max:65535',
                'comm_key' => 'nullable|string|max:255',
                'soap_port' => 'nullable|string|max:10',
                'device_id' => 'nullable|string|max:255',
                'serial_number' => 'nullable|string|max:255',
                'status' => 'nullable|in:active,inactive,maintenance',
                'settings' => 'nullable|array',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $machine = AttendanceMachine::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $machine,
                'message' => 'Attendance machine created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating attendance machine', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create attendance machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified attendance machine
     */
    public function show(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $machine = $attendanceMachine->load([
                'syncLogs' => function($q) {
                    $q->latest('started_at')->limit(10);
                },
                'machineUsers.employee',
                'attendances' => function($q) {
                    $q->latest('date')->limit(10);
                }
            ]);

            return response()->json([
                'success' => true,
                'data' => $machine,
                'message' => 'Attendance machine retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving attendance machine', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified attendance machine
     */
    public function update(Request $request, AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'ip_address' => 'sometimes|required|ip|unique:attendance_machines,ip_address,' . $attendanceMachine->id,
                'port' => 'nullable|integer|min:1|max:65535',
                'comm_key' => 'nullable|string|max:255',
                'soap_port' => 'nullable|string|max:10',
                'device_id' => 'nullable|string|max:255',
                'serial_number' => 'nullable|string|max:255',
                'status' => 'nullable|in:active,inactive,maintenance',
                'settings' => 'nullable|array',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendanceMachine->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $attendanceMachine->fresh(),
                'message' => 'Attendance machine updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating attendance machine', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified attendance machine
     */
    public function destroy(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $attendanceMachine->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attendance machine deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting attendance machine', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connection to attendance machine
     */
    public function testConnection(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $service = new AttendanceMachineService($attendanceMachine);
            $result = $service->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'response_time' => $result['response_time'] ?? null
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error testing machine connection', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to test connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pull attendance data from machine
     */
    public function pullAndProcessAttendanceData(Request $request, $id)
    {
        $attendanceMachine = AttendanceMachine::findOrFail($id);
        
        try {
            $validator = Validator::make($request->all(), [
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'process_data' => 'boolean'
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::today();
            $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();
            $processData = $request->boolean('process_data', true);
    
            if ($processData) {
                // Perbaikan: Gunakan AttendanceMachineService untuk pull data dulu
                $machineService = new AttendanceMachineService($attendanceMachine);
                $pullResult = $machineService->pullAttendanceData($attendanceMachine->id, $fromDate, $toDate);
                
                if ($pullResult['success'] && !empty($pullResult['data'])) {
                    // Kemudian proses data menggunakan AttendanceSyncService
                    $result = $this->attendanceSyncService->processAttendanceData($attendanceMachine->id, $pullResult['data']);
                } else {
                    $result = [
                        'success' => true,
                        'processed' => 0,
                        'errors' => 0,
                        'message' => 'No new data to process'
                    ];
                }
            } else {
                // Just pull raw data without processing
                $machineService = new AttendanceMachineService($attendanceMachine);
                $result = $machineService->pullAttendanceData($attendanceMachine->id, $fromDate, $toDate);
            }
    
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Operation completed successfully',
                'data' => $result['data'] ?? null,
                'count' => $result['count'] ?? 0,
                'processed_count' => $result['processed'] ?? 0
            ], $result['success'] ? 200 : 500);
    
        } catch (\Exception $e) {
            Log::error('Error pulling attendance data', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to pull attendance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync users to machine
     */
    public function syncAllUsers(Request $request, $id)
    {
        $attendanceMachine = AttendanceMachine::findOrFail($id);
        
        try {
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'nullable|array',
                'employee_ids.*' => 'exists:employees,id'
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $employeeIds = $request->input('employee_ids');
            
            if (empty($employeeIds)) {
                // Sync all users
                $result = $this->attendanceSyncService->syncAllUsersToMachine($attendanceMachine->id);
            } else {
                // Sync specific users
                $results = [];
                foreach ($employeeIds as $employeeId) {
                    $userResult = $this->attendanceSyncService->syncUserToAllMachines($employeeId);
                    $results[] = $userResult;
                }
                
                $result = [
                    'success' => true,
                    'synced' => count($employeeIds),
                    'errors' => 0,
                    'results' => $results
                ];
            }
    
            return response()->json([
                'success' => $result['success'],
                'message' => 'User synchronization completed',
                'synced' => $result['synced'] ?? 0,
                'errors' => $result['errors'] ?? 0,
                'error_details' => $result['error_details'] ?? []
            ], $result['success'] ? 200 : 500);
    
        } catch (\Exception $e) {
            Log::error('Error syncing users to machine', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart attendance machine
     */
    public function restart(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $service = new AttendanceMachineService($attendanceMachine);
            $result = $service->restartMachine();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error restarting machine', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restart machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete attendance data from machine
     */
    public function deleteData(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $service = new AttendanceMachineService($attendanceMachine);
            $result = $service->deleteAttendanceData();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error deleting machine data', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete machine data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync machine time
     */
    public function syncTime(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $service = new AttendanceMachineService($attendanceMachine);
            $result = $service->syncTime();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'server_time' => $result['server_time'] ?? null,
                'data' => $result['data'] ?? null
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error syncing machine time', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync machine time',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync logs for machine
     */
    public function syncLogs(Request $request, AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $query = $attendanceMachine->syncLogs();

            // Filter by operation
            if ($request->has('operation')) {
                $query->where('operation', $request->operation);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('started_at', '>=', Carbon::parse($request->from_date));
            }

            if ($request->has('to_date')) {
                $query->where('started_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }

            $logs = $query->latest('started_at')
                         ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $logs,
                'message' => 'Sync logs retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving sync logs', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sync logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get machine dashboard data
     */
    public function dashboard(AttendanceMachine $attendanceMachine): JsonResponse
    {
        try {
            $today = Carbon::today();
            $thisWeek = Carbon::now()->startOfWeek();
            $thisMonth = Carbon::now()->startOfMonth();

            $data = [
                'machine_info' => [
                    'id' => $attendanceMachine->id,
                    'name' => $attendanceMachine->name,
                    'ip_address' => $attendanceMachine->ip_address,
                    'status' => $attendanceMachine->status,
                    'last_sync_at' => $attendanceMachine->last_sync_at
                ],
                'statistics' => [
                    'total_users' => $attendanceMachine->machineUsers()->count(),
                    'synced_users' => $attendanceMachine->machineUsers()->where('status', 'synced')->count(),
                    'pending_users' => $attendanceMachine->machineUsers()->where('status', 'pending')->count(),
                    'failed_users' => $attendanceMachine->machineUsers()->where('status', 'failed')->count(),
                    'today_attendance' => $attendanceMachine->attendances()->whereDate('date', $today)->count(),
                    'week_attendance' => $attendanceMachine->attendances()->where('date', '>=', $thisWeek)->count(),
                    'month_attendance' => $attendanceMachine->attendances()->where('date', '>=', $thisMonth)->count()
                ],
                'recent_sync_logs' => $attendanceMachine->syncLogs()
                    ->latest('started_at')
                    ->limit(5)
                    ->get(['operation', 'status', 'message', 'records_processed', 'started_at', 'duration']),
                'recent_attendance' => $attendanceMachine->attendances()
                    ->with('employee:id,nama_lengkap,nip')
                    ->latest('machine_timestamp')
                    ->limit(10)
                    ->get(['employee_id', 'date', 'check_in', 'check_out', 'machine_timestamp'])
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Dashboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving machine dashboard', [
                'machine_id' => $attendanceMachine->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}