<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\EmployeeAttendance;
use App\Models\MorningReflectionAttendance;
use App\Models\LeaveQuota;
use App\Models\LeaveRequest;
use App\Models\EmployeeDocument;
use App\Models\EmploymentHistory;
use App\Models\PromotionHistory;
use App\Models\Benefit;
use App\Models\Training;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmployeeSyncService
{
    /**
     * Sync employee data across all tables based on exact name match
     * 
     * @param string $employeeName - Nama lengkap employee
     * @param int|null $employeeId - ID employee (optional, akan dicari jika null)
     * @return array - Result of sync operation
     */
    public static function syncEmployeeByName($employeeName, $employeeId = null)
    {
        try {
            DB::beginTransaction();

            $syncResults = [
                'employee_found' => false,
                'employee_id' => null,
                'sync_operations' => [],
                'errors' => []
            ];

            // Find employee by name or ID
            if ($employeeId) {
                $employee = Employee::find($employeeId);
            } else {
                $employee = Employee::where('nama_lengkap', $employeeName)->first();
            }

            if (!$employee) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Employee not found',
                    'data' => $syncResults
                ];
            }

            $syncResults['employee_found'] = true;
            $syncResults['employee_id'] = $employee->id;

            // 1. Sync Users table
            $userSync = self::syncUsersTable($employee);
            $syncResults['sync_operations']['users'] = $userSync;

            // 2. Sync Attendance tables
            $attendanceSync = self::syncAttendanceTables($employee);
            $syncResults['sync_operations']['attendance'] = $attendanceSync;

            // 3. Sync Morning Reflection Attendance
            $morningReflectionSync = self::syncMorningReflectionAttendance($employee);
            $syncResults['sync_operations']['morning_reflection'] = $morningReflectionSync;

            // 4. Sync Leave tables
            $leaveSync = self::syncLeaveTables($employee);
            $syncResults['sync_operations']['leave'] = $leaveSync;

            // 5. Sync Employee related tables
            $employeeRelatedSync = self::syncEmployeeRelatedTables($employee);
            $syncResults['sync_operations']['employee_related'] = $employeeRelatedSync;

            DB::commit();

            Log::info('Employee sync completed successfully', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->nama_lengkap,
                'sync_results' => $syncResults
            ]);

            return [
                'success' => true,
                'message' => 'Employee sync completed successfully',
                'data' => $syncResults
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Employee sync failed', [
                'employee_name' => $employeeName,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Employee sync failed: ' . $e->getMessage(),
                'data' => $syncResults
            ];
        }
    }

    /**
     * Sync Users table
     */
    private static function syncUsersTable($employee)
    {
        $results = [
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        try {
            // Find users with matching name but no employee_id
            $users = User::where('name', $employee->nama_lengkap)
                        ->whereNull('employee_id')
                        ->get();

            foreach ($users as $user) {
                $user->update([
                    'employee_id' => $employee->id,
                    'role' => $employee->jabatan_saat_ini // Sync role with jabatan
                ]);
                $results['updated']++;
            }

            // Create user if doesn't exist
            $existingUser = User::where('employee_id', $employee->id)->first();
            if (!$existingUser) {
                User::create([
                    'name' => $employee->nama_lengkap,
                    'email' => strtolower(str_replace(' ', '.', $employee->nama_lengkap)) . '@company.com',
                    'password' => bcrypt('password123'), // Default password
                    'employee_id' => $employee->id,
                    'role' => $employee->jabatan_saat_ini
                ]);
                $results['created']++;
            }

        } catch (\Exception $e) {
            $results['errors'][] = 'Users sync error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Sync Attendance tables
     */
    private static function syncAttendanceTables($employee)
    {
        $results = [
            'attendance_updated' => 0,
            'attendance_logs_updated' => 0,
            'employee_attendance_updated' => 0,
            'errors' => []
        ];

        try {
            // Get employee's machine user ID
            $employeeAttendance = EmployeeAttendance::where('name', $employee->nama_lengkap)
                                                   ->whereNull('employee_id')
                                                   ->first();

            if ($employeeAttendance) {
                // Update employee_attendance table
                $employeeAttendance->update(['employee_id' => $employee->id]);
                $results['employee_attendance_updated']++;

                $machineUserId = $employeeAttendance->machine_user_id;

                // Update attendance table
                $attendanceUpdated = Attendance::where('user_name', $employee->nama_lengkap)
                                              ->whereNull('employee_id')
                                              ->update(['employee_id' => $employee->id]);
                $results['attendance_updated'] += $attendanceUpdated;

                // Update attendance_logs table
                $logsUpdated = AttendanceLog::where('user_pin', $machineUserId)
                                           ->whereNull('employee_id')
                                           ->update(['employee_id' => $employee->id]);
                $results['attendance_logs_updated'] += $logsUpdated;
            }

        } catch (\Exception $e) {
            $results['errors'][] = 'Attendance sync error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Sync Morning Reflection Attendance
     */
    private static function syncMorningReflectionAttendance($employee)
    {
        $results = [
            'updated' => 0,
            'errors' => []
        ];

        try {
            // Update morning reflection attendance records with matching name but no employee_id
            $updated = MorningReflectionAttendance::where('employee_id', null)
                                                 ->whereHas('employee', function($query) use ($employee) {
                                                     $query->where('nama_lengkap', $employee->nama_lengkap);
                                                 })
                                                 ->update(['employee_id' => $employee->id]);

            $results['updated'] = $updated;

        } catch (\Exception $e) {
            $results['errors'][] = 'Morning reflection sync error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Sync Leave tables
     */
    private static function syncLeaveTables($employee)
    {
        $results = [
            'leave_quotas_updated' => 0,
            'leave_requests_updated' => 0,
            'errors' => []
        ];

        try {
            // Update leave quotas
            $quotasUpdated = LeaveQuota::where('employee_id', null)
                                     ->whereHas('employee', function($query) use ($employee) {
                                         $query->where('nama_lengkap', $employee->nama_lengkap);
                                     })
                                     ->update(['employee_id' => $employee->id]);
            $results['leave_quotas_updated'] = $quotasUpdated;

            // Update leave requests
            $requestsUpdated = LeaveRequest::where('employee_id', null)
                                         ->whereHas('employee', function($query) use ($employee) {
                                             $query->where('nama_lengkap', $employee->nama_lengkap);
                                         })
                                         ->update(['employee_id' => $employee->id]);
            $results['leave_requests_updated'] = $requestsUpdated;

        } catch (\Exception $e) {
            $results['errors'][] = 'Leave sync error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Sync Employee related tables
     */
    private static function syncEmployeeRelatedTables($employee)
    {
        $results = [
            'documents_updated' => 0,
            'employment_histories_updated' => 0,
            'promotion_histories_updated' => 0,
            'benefits_updated' => 0,
            'trainings_updated' => 0,
            'errors' => []
        ];

        try {
            // Update employee documents
            $documentsUpdated = EmployeeDocument::where('employee_id', null)
                                              ->whereHas('employee', function($query) use ($employee) {
                                                  $query->where('nama_lengkap', $employee->nama_lengkap);
                                              })
                                              ->update(['employee_id' => $employee->id]);
            $results['documents_updated'] = $documentsUpdated;

            // Update employment histories
            $historiesUpdated = EmploymentHistory::where('employee_id', null)
                                                ->whereHas('employee', function($query) use ($employee) {
                                                    $query->where('nama_lengkap', $employee->nama_lengkap);
                                                })
                                                ->update(['employee_id' => $employee->id]);
            $results['employment_histories_updated'] = $historiesUpdated;

            // Update promotion histories
            $promotionsUpdated = PromotionHistory::where('employee_id', null)
                                                ->whereHas('employee', function($query) use ($employee) {
                                                    $query->where('nama_lengkap', $employee->nama_lengkap);
                                                })
                                                ->update(['employee_id' => $employee->id]);
            $results['promotion_histories_updated'] = $promotionsUpdated;

            // Update benefits
            $benefitsUpdated = Benefit::where('employee_id', null)
                                    ->whereHas('employee', function($query) use ($employee) {
                                        $query->where('nama_lengkap', $employee->nama_lengkap);
                                    })
                                    ->update(['employee_id' => $employee->id]);
            $results['benefits_updated'] = $benefitsUpdated;

            // Update trainings
            $trainingsUpdated = Training::where('employee_id', null)
                                      ->whereHas('employee', function($query) use ($employee) {
                                          $query->where('nama_lengkap', $employee->nama_lengkap);
                                      })
                                      ->update(['employee_id' => $employee->id]);
            $results['trainings_updated'] = $trainingsUpdated;

        } catch (\Exception $e) {
            $results['errors'][] = 'Employee related sync error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Auto sync when new employee is created
     */
    public static function autoSyncNewEmployee($employee)
    {
        return self::syncEmployeeByName($employee->nama_lengkap, $employee->id);
    }

    /**
     * Auto sync when attendance is recorded
     */
    public static function autoSyncAttendance($userName, $userPin = null)
    {
        // Find employee by name
        $employee = Employee::where('nama_lengkap', $userName)->first();
        
        if ($employee) {
            return self::syncEmployeeByName($employee->nama_lengkap, $employee->id);
        }

        return [
            'success' => false,
            'message' => 'Employee not found for attendance sync',
            'data' => []
        ];
    }

    /**
     * Auto sync when user registers
     */
    public static function autoSyncUserRegistration($userName)
    {
        return self::syncEmployeeByName($userName);
    }

    /**
     * Auto sync when morning reflection attendance is recorded
     */
    public static function autoSyncMorningReflection($employeeName)
    {
        return self::syncEmployeeByName($employeeName);
    }

    /**
     * Bulk sync all employees
     */
    public static function bulkSyncAllEmployees()
    {
        $employees = Employee::all();
        $results = [];

        foreach ($employees as $employee) {
            $result = self::syncEmployeeByName($employee->nama_lengkap, $employee->id);
            $results[$employee->id] = $result;
        }

        return $results;
    }

    /**
     * Sync specific employee by ID
     */
    public static function syncEmployeeById($employeeId)
    {
        $employee = Employee::find($employeeId);
        
        if (!$employee) {
            return [
                'success' => false,
                'message' => 'Employee not found',
                'data' => []
            ];
        }

        return self::syncEmployeeByName($employee->nama_lengkap, $employee->id);
    }
} 