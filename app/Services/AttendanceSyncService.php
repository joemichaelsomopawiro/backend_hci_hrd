<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceMachine;
use App\Models\AttendanceSyncLog;
use App\Models\AttendanceMachineUser;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class AttendanceSyncService
{
    protected $machineService;

    public function __construct(AttendanceMachineService $machineService)
    {
        $this->machineService = $machineService;
    }

    /**
     * Process attendance data from machine to database
     */
    public function processAttendanceData($machineId, $attendanceData)
    {
        $machine = AttendanceMachine::findOrFail($machineId);
        $processedCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($attendanceData as $record) {
                try {
                    // Validate required fields
                    if (!isset($record['badge_number']) || !isset($record['timestamp']) || !isset($record['log_id'])) {
                        $errors[] = "Missing required fields in record: " . json_encode($record);
                        $errorCount++;
                        continue;
                    }

                    // Find employee by badge number (NIP)
                    $employee = Employee::where('nip', $record['badge_number'])->first();
                    
                    if (!$employee) {
                        $errors[] = "Employee not found for badge number: {$record['badge_number']}";
                        $errorCount++;
                        continue;
                    }

                    // Parse timestamp
                    $timestamp = Carbon::parse($record['timestamp']);
                    $date = $timestamp->format('Y-m-d');

                    // Check if this attendance record already exists
                    $existingAttendance = Attendance::where([
                        'employee_id' => $employee->id,
                        'machine_log_id' => $record['log_id'],
                        'attendance_machine_id' => $machineId
                    ])->first();

                    if ($existingAttendance) {
                        continue; // Skip duplicate
                    }

                    // Find existing attendance for the same date
                    $dailyAttendance = Attendance::where([
                        'employee_id' => $employee->id,
                        'date' => $date
                    ])->first();

                    if ($dailyAttendance) {
                        // Update existing record with check-in or check-out
                        $this->updateDailyAttendance($dailyAttendance, $record, $timestamp, $machineId);
                    } else {
                        // Create new attendance record
                        $this->createNewAttendance($employee->id, $record, $timestamp, $date, $machineId);
                    }

                    $processedCount++;
                } catch (Exception $e) {
                    $errors[] = "Error processing record {$record['log_id']}: " . $e->getMessage();
                    $errorCount++;
                    Log::error('Attendance sync error', [
                        'record' => $record,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            // Log sync result
            $this->logSyncResult($machineId, 'attendance_pull', $processedCount, $errorCount, $errors);

            return [
                'success' => true,
                'processed' => $processedCount,
                'errors' => $errorCount,
                'error_details' => $errors
            ];

        } catch (Exception $e) {
            DB::rollback();
            
            $this->logSyncResult($machineId, 'attendance_pull', 0, 1, [$e->getMessage()], 'failed');

            Log::error('Attendance sync transaction failed', [
                'machine_id' => $machineId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Update existing daily attendance record
     */
    private function updateDailyAttendance($attendance, $record, $timestamp, $machineId)
    {
        $timeString = $timestamp->format('H:i:s');
        $hour = $timestamp->hour;

        // Determine if this is check-in or check-out based on time and existing data
        if ($hour < 12 && !$attendance->check_in) {
            // Morning time and no check-in yet - set as check-in
            $attendance->check_in = $timeString;
        } elseif ($hour >= 12 && !$attendance->check_out) {
            // Afternoon/evening time and no check-out yet - set as check-out
            $attendance->check_out = $timeString;
        } elseif (!$attendance->check_in) {
            // No check-in yet, regardless of time
            $attendance->check_in = $timeString;
        } elseif (!$attendance->check_out) {
            // Has check-in but no check-out
            $attendance->check_out = $timeString;
        }

        // Update machine reference if not set
        if (!$attendance->attendance_machine_id) {
            $attendance->attendance_machine_id = $machineId;
        }

        $attendance->machine_timestamp = $timestamp;
        $attendance->save();
    }

    /**
     * Create new attendance record
     */
    private function createNewAttendance($employeeId, $record, $timestamp, $date, $machineId)
    {
        $attendance = new Attendance();
        $attendance->employee_id = $employeeId;
        $attendance->date = $date;
        $attendance->machine_log_id = $record['log_id'];
        $attendance->source = 'machine';
        $attendance->machine_timestamp = $timestamp;
        $attendance->attendance_machine_id = $machineId;

        // Determine check-in or check-out based on time
        $timeString = $timestamp->format('H:i:s');
        $hour = $timestamp->hour;

        if ($hour < 12) {
            $attendance->check_in = $timeString;
        } else {
            $attendance->check_out = $timeString;
        }

        $attendance->save();
    }

    /**
     * Log sync result
     */
    private function logSyncResult($machineId, $syncType, $processedCount, $errorCount, $errors, $status = null)
    {
        if ($status === null) {
            $status = $errorCount > 0 ? 'partial_success' : 'success';
        }

        AttendanceSyncLog::create([
            'attendance_machine_id' => $machineId,
            'sync_type' => $syncType,
            'status' => $status,
            'records_processed' => $processedCount,
            'error_count' => $errorCount,
            'error_details' => $errorCount > 0 ? json_encode($errors) : null,
            'synced_at' => now()
        ]);
    }

    /**
     * Sync all users to machine
     */
    public function syncAllUsersToMachine($machineId)
    {
        $machine = AttendanceMachine::findOrFail($machineId);
        $employees = Employee::whereNotNull('nip')->get(); // Only employees with NIP
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($employees as $employee) {
    try {
        // Perbaikan: Gunakan parameter individual, bukan array
        $result = $this->machineService->uploadUser(
            $employee->nip,                    // badge_number
            $employee->nama_lengkap,           // name
            ''                                 // password (optional, default empty)
        );

        if ($result['success']) {
            // Update or create machine user mapping
            AttendanceMachineUser::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_machine_id' => $machineId
                ],
                [
                    'badge_number' => $employee->nip,
                    'name' => $employee->nama_lengkap,
                    'status' => 'active',
                    'last_synced_at' => now(),
                    'sync_error' => null
                ]
            );
                    $successCount++;
                } else {
                    $errorMessage = $result['message'] ?? 'Unknown error';
                    $errors[] = "Failed to sync employee {$employee->nip}: {$errorMessage}";
                    $errorCount++;
                    
                    // Update machine user with error
                    AttendanceMachineUser::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'attendance_machine_id' => $machineId
                        ],
                        [
                            'badge_number' => $employee->nip,
                            'name' => $employee->nama_lengkap,
                            'status' => 'error',
                            'sync_error' => $errorMessage
                        ]
                    );
                }
            } catch (Exception $e) {
                $errors[] = "Error syncing employee {$employee->nip}: " . $e->getMessage();
                $errorCount++;
                
                Log::error('User sync error', [
                    'employee_id' => $employee->id,
                    'machine_id' => $machineId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Log sync result
        $this->logSyncResult($machineId, 'user_upload', $successCount, $errorCount, $errors);

        return [
            'success' => true,
            'synced' => $successCount,
            'errors' => $errorCount,
            'error_details' => $errors
        ];
    }

    /**
     * Sync new user to all machines
     */
    public function syncUserToAllMachines($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        
        if (!$employee->nip) {
            throw new Exception('Employee does not have NIP (badge number)');
        }
        
        $machines = AttendanceMachine::where('is_active', true)->get();
        $results = [];

        foreach ($machines as $machine) {
            try {
                // Perbaikan: Gunakan parameter individual, bukan array
                $result = $this->machineService->uploadUser(
                    $employee->nip,                    // badge_number
                    $employee->nama_lengkap,           // name
                    ''                                 // password (optional, default empty)
                );

                if ($result['success']) {
                    // Update or create machine user mapping
                    AttendanceMachineUser::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'attendance_machine_id' => $machine->id
                        ],
                        [
                            'badge_number' => $employee->nip,
                            'name' => $employee->nama_lengkap,
                            'status' => 'active',
                            'last_synced_at' => now(),
                            'sync_error' => null
                        ]
                    );
                }

                $results[$machine->name] = $result;
            } catch (Exception $e) {
                $results[$machine->name] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                
                Log::error('Single user sync error', [
                    'employee_id' => $employeeId,
                    'machine_id' => $machine->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Remove user from all machines
     */
    public function removeUserFromAllMachines($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $machines = AttendanceMachine::where('is_active', true)->get();
        $results = [];

        foreach ($machines as $machine) {
            try {
                $result = $this->machineService->deleteUser($machine->id, $employee->nip);
                
                if ($result['success']) {
                    AttendanceMachineUser::where([
                        'employee_id' => $employee->id,
                        'attendance_machine_id' => $machine->id
                    ])->delete();
                }

                $results[$machine->name] = $result;
            } catch (Exception $e) {
                $results[$machine->name] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                
                Log::error('User removal error', [
                    'employee_id' => $employeeId,
                    'machine_id' => $machine->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Auto sync attendance data from all active machines
     */
    public function autoSyncAttendanceData()
    {
        $machines = AttendanceMachine::where('is_active', true)->get();
        $results = [];

        foreach ($machines as $machine) {
            try {
                Log::info("Starting auto sync for machine: {$machine->name}");
                
                $attendanceData = $this->machineService->pullAttendanceData($machine->id);
                
                if ($attendanceData['success'] && !empty($attendanceData['data'])) {
                    $result = $this->processAttendanceData($machine->id, $attendanceData['data']);
                    $results[$machine->name] = $result;
                    
                    Log::info("Auto sync completed for machine: {$machine->name}", $result);
                } else {
                    $results[$machine->name] = [
                        'success' => true,
                        'processed' => 0,
                        'errors' => 0,
                        'message' => 'No new data to sync'
                    ];
                    
                    Log::info("No new data to sync for machine: {$machine->name}");
                }
            } catch (Exception $e) {
                $results[$machine->name] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                
                Log::error("Auto sync failed for machine: {$machine->name}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $results;
    }

    /**
     * Get sync statistics for a machine
     */
    public function getSyncStatistics($machineId, $days = 30)
    {
        $machine = AttendanceMachine::findOrFail($machineId);
        $startDate = Carbon::now()->subDays($days);

        $stats = [
            'machine_name' => $machine->name,
            'total_syncs' => AttendanceSyncLog::where('attendance_machine_id', $machineId)
                ->where('synced_at', '>=', $startDate)
                ->count(),
            'successful_syncs' => AttendanceSyncLog::where('attendance_machine_id', $machineId)
                ->where('synced_at', '>=', $startDate)
                ->where('status', 'success')
                ->count(),
            'failed_syncs' => AttendanceSyncLog::where('attendance_machine_id', $machineId)
                ->where('synced_at', '>=', $startDate)
                ->where('status', 'failed')
                ->count(),
            'total_records_processed' => AttendanceSyncLog::where('attendance_machine_id', $machineId)
                ->where('synced_at', '>=', $startDate)
                ->sum('records_processed'),
            'total_errors' => AttendanceSyncLog::where('attendance_machine_id', $machineId)
                ->where('synced_at', '>=', $startDate)
                ->sum('error_count'),
            'last_sync' => AttendanceSyncLog::where('attendance_machine_id', $machineId)
                ->latest('synced_at')
                ->first()
        ];

        return $stats;
    }
}