<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EmployeeSyncService;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmployeeSyncController extends Controller
{
    /**
     * POST /api/employee-sync/sync-by-name
     * Sync employee by name
     */
    public function syncByName(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_name' => 'required|string|max:255',
                'employee_id' => 'nullable|integer|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = EmployeeSyncService::syncEmployeeByName(
                $request->employee_name,
                $request->employee_id
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Error in sync by name', [
                'employee_name' => $request->employee_name ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/employee-sync/sync-by-id
     * Sync employee by ID
     */
    public function syncById(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|integer|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = EmployeeSyncService::syncEmployeeById($request->employee_id);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Error in sync by ID', [
                'employee_id' => $request->employee_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/employee-sync/bulk-sync
     * Bulk sync all employees
     */
    public function bulkSync(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'nullable|array',
                'employee_ids.*' => 'integer|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('employee_ids') && !empty($request->employee_ids)) {
                // Sync specific employees
                $results = [];
                foreach ($request->employee_ids as $employeeId) {
                    $result = EmployeeSyncService::syncEmployeeById($employeeId);
                    $results[$employeeId] = $result;
                }
            } else {
                // Sync all employees
                $results = EmployeeSyncService::bulkSyncAllEmployees();
            }

            $successCount = 0;
            $errorCount = 0;
            foreach ($results as $result) {
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk sync completed. Success: {$successCount}, Errors: {$errorCount}",
                'data' => [
                    'total_processed' => count($results),
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results' => $results
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in bulk sync', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat bulk sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/employee-sync/status
     * Get sync status for all employees
     */
    public function getSyncStatus(Request $request)
    {
        try {
            $employees = Employee::with(['user', 'employeeAttendance'])->get();
            $statusData = [];

            foreach ($employees as $employee) {
                $status = [
                    'employee_id' => $employee->id,
                    'nama_lengkap' => $employee->nama_lengkap,
                    'nik' => $employee->nik,
                    'jabatan_saat_ini' => $employee->jabatan_saat_ini,
                    'sync_status' => [
                        'has_user' => $employee->user ? true : false,
                        'user_employee_id' => $employee->user ? $employee->user->employee_id : null,
                        'has_employee_attendance' => $employee->employeeAttendance ? true : false,
                        'employee_attendance_employee_id' => $employee->employeeAttendance ? $employee->employeeAttendance->employee_id : null,
                        'needs_sync' => false,
                        'sync_issues' => []
                    ]
                ];

                // Check if sync is needed
                if (!$employee->user) {
                    $status['sync_status']['needs_sync'] = true;
                    $status['sync_status']['sync_issues'][] = 'No user account linked';
                } elseif ($employee->user->employee_id !== $employee->id) {
                    $status['sync_status']['needs_sync'] = true;
                    $status['sync_status']['sync_issues'][] = 'User employee_id mismatch';
                }

                if (!$employee->employeeAttendance) {
                    $status['sync_status']['needs_sync'] = true;
                    $status['sync_status']['sync_issues'][] = 'No employee attendance record';
                } elseif ($employee->employeeAttendance->employee_id !== $employee->id) {
                    $status['sync_status']['needs_sync'] = true;
                    $status['sync_status']['sync_issues'][] = 'Employee attendance employee_id mismatch';
                }

                $statusData[] = $status;
            }

            $needsSyncCount = collect($statusData)->where('sync_status.needs_sync', true)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => count($statusData),
                    'needs_sync_count' => $needsSyncCount,
                    'synced_count' => count($statusData) - $needsSyncCount,
                    'employees' => $statusData
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting sync status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/employee-sync/sync-orphaned-records
     * Sync orphaned records (records without employee_id)
     */
    public function syncOrphanedRecords(Request $request)
    {
        try {
            $results = [
                'attendance_updated' => 0,
                'attendance_logs_updated' => 0,
                'morning_reflection_updated' => 0,
                'users_updated' => 0,
                'errors' => []
            ];

            // Sync orphaned attendance records
            $orphanedAttendances = \App\Models\Attendance::whereNull('employee_id')
                                                        ->whereNotNull('user_name')
                                                        ->get();

            foreach ($orphanedAttendances as $attendance) {
                $employee = Employee::where('nama_lengkap', $attendance->user_name)->first();
                if ($employee) {
                    $attendance->update(['employee_id' => $employee->id]);
                    $results['attendance_updated']++;
                }
            }

            // Sync orphaned attendance logs
            $orphanedLogs = \App\Models\AttendanceLog::whereNull('employee_id')
                                                    ->whereNotNull('user_pin')
                                                    ->get();

            foreach ($orphanedLogs as $log) {
                $employeeAttendance = \App\Models\EmployeeAttendance::where('machine_user_id', $log->user_pin)->first();
                if ($employeeAttendance && $employeeAttendance->employee_id) {
                    $log->update(['employee_id' => $employeeAttendance->employee_id]);
                    $results['attendance_logs_updated']++;
                }
            }

            // Sync orphaned morning reflection records
            $orphanedMorningReflections = \App\Models\MorningReflectionAttendance::whereNull('employee_id')->get();

            foreach ($orphanedMorningReflections as $reflection) {
                if ($reflection->employee && $reflection->employee->id) {
                    $reflection->update(['employee_id' => $reflection->employee->id]);
                    $results['morning_reflection_updated']++;
                }
            }

            // Sync orphaned users
            $orphanedUsers = \App\Models\User::whereNull('employee_id')->get();

            foreach ($orphanedUsers as $user) {
                $employee = Employee::where('nama_lengkap', $user->name)->first();
                if ($employee) {
                    $user->update([
                        'employee_id' => $employee->id,
                        'role' => $employee->jabatan_saat_ini
                    ]);
                    $results['users_updated']++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Orphaned records sync completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error syncing orphaned records', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi orphaned records: ' . $e->getMessage()
            ], 500);
        }
    }
} 