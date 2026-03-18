<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\RoleHierarchyService;
use Illuminate\Support\Facades\Log;

class LeaveNotificationService
{
    /**
     * Notify manager(s) when employee submits a leave request
     */
    public function notifyLeaveSubmitted(LeaveRequest $leaveRequest): void
    {
        try {
            $employee = $leaveRequest->employee;
            $employeeUser = $employee->user;
            
            if (!$employeeUser) {
                Log::warning('LeaveNotificationService: Employee has no user account', ['employee_id' => $employee->id]);
                return;
            }

            $employeeRole = $employeeUser->role;
            $employeeName = $employee->nama_lengkap ?? $employeeUser->name ?? 'Pegawai';
            $leaveTypeName = $this->getLeaveTypeName($leaveRequest->leave_type);
            
            // Find managers who can approve this leave request
            $managersToNotify = $this->getManagersForEmployee($employeeRole);
            
            foreach ($managersToNotify as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'type' => 'leave_request_submitted',
                    'title' => 'Permohonan Cuti Baru',
                    'message' => "{$employeeName} mengajukan {$leaveTypeName} selama {$leaveRequest->total_days} hari ({$this->formatDate($leaveRequest->start_date)} - {$this->formatDate($leaveRequest->end_date)})",
                    'data' => [
                        'leave_request_id' => $leaveRequest->id,
                        'employee_id' => $employee->id,
                        'employee_name' => $employeeName,
                        'leave_type' => $leaveRequest->leave_type,
                        'start_date' => $leaveRequest->start_date,
                        'end_date' => $leaveRequest->end_date,
                        'total_days' => $leaveRequest->total_days,
                        'source' => 'leave_request'
                    ],
                    'related_type' => 'LeaveRequest',
                    'related_id' => $leaveRequest->id,
                    'priority' => 'high',
                    'status' => 'unread'
                ]);
            }
            
            Log::info('LeaveNotificationService: Notified managers about leave submission', [
                'leave_request_id' => $leaveRequest->id,
                'managers_notified' => $managersToNotify->count()
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveNotificationService: Error notifying leave submission', [
                'error' => $e->getMessage(),
                'leave_request_id' => $leaveRequest->id ?? null
            ]);
        }
    }

    /**
     * Notify employee when their leave request is approved
     */
    public function notifyLeaveApproved(LeaveRequest $leaveRequest): void
    {
        try {
            $employee = $leaveRequest->employee;
            $employeeUser = $employee->user;
            
            if (!$employeeUser) {
                Log::warning('LeaveNotificationService: Employee has no user account for approval notification', ['employee_id' => $employee->id]);
                return;
            }

            $approver = $leaveRequest->approvedBy;
            $approverName = $approver->nama_lengkap ?? $approver->user->name ?? 'Atasan';
            $leaveTypeName = $this->getLeaveTypeName($leaveRequest->leave_type);
            
            Notification::create([
                'user_id' => $employeeUser->id,
                'type' => 'leave_request_approved',
                'title' => 'Cuti Disetujui',
                'message' => "Permohonan {$leaveTypeName} Anda ({$this->formatDate($leaveRequest->start_date)} - {$this->formatDate($leaveRequest->end_date)}) telah disetujui oleh {$approverName}",
                'data' => [
                    'leave_request_id' => $leaveRequest->id,
                    'leave_type' => $leaveRequest->leave_type,
                    'start_date' => $leaveRequest->start_date,
                    'end_date' => $leaveRequest->end_date,
                    'total_days' => $leaveRequest->total_days,
                    'approved_by' => $approverName,
                    'source' => 'leave_request'
                ],
                'related_type' => 'LeaveRequest',
                'related_id' => $leaveRequest->id,
                'priority' => 'normal',
                'status' => 'unread'
            ]);
            
            Log::info('LeaveNotificationService: Notified employee about leave approval', [
                'leave_request_id' => $leaveRequest->id,
                'employee_user_id' => $employeeUser->id
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveNotificationService: Error notifying leave approval', [
                'error' => $e->getMessage(),
                'leave_request_id' => $leaveRequest->id ?? null
            ]);
        }
    }

    /**
     * Notify employee when their leave request is rejected
     */
    public function notifyLeaveRejected(LeaveRequest $leaveRequest): void
    {
        try {
            $employee = $leaveRequest->employee;
            $employeeUser = $employee->user;
            
            if (!$employeeUser) {
                Log::warning('LeaveNotificationService: Employee has no user account for rejection notification', ['employee_id' => $employee->id]);
                return;
            }

            $approver = $leaveRequest->approvedBy;
            $approverName = $approver->nama_lengkap ?? $approver->user->name ?? 'Atasan';
            $leaveTypeName = $this->getLeaveTypeName($leaveRequest->leave_type);
            $rejectionReason = $leaveRequest->rejection_reason ?? 'Tidak ada alasan yang diberikan';
            
            Notification::create([
                'user_id' => $employeeUser->id,
                'type' => 'leave_request_rejected',
                'title' => 'Cuti Ditolak',
                'message' => "Permohonan {$leaveTypeName} Anda ({$this->formatDate($leaveRequest->start_date)} - {$this->formatDate($leaveRequest->end_date)}) ditolak oleh {$approverName}. Alasan: {$rejectionReason}",
                'data' => [
                    'leave_request_id' => $leaveRequest->id,
                    'leave_type' => $leaveRequest->leave_type,
                    'start_date' => $leaveRequest->start_date,
                    'end_date' => $leaveRequest->end_date,
                    'total_days' => $leaveRequest->total_days,
                    'rejected_by' => $approverName,
                    'rejection_reason' => $rejectionReason,
                    'source' => 'leave_request'
                ],
                'related_type' => 'LeaveRequest',
                'related_id' => $leaveRequest->id,
                'priority' => 'high',
                'status' => 'unread'
            ]);
            
            Log::info('LeaveNotificationService: Notified employee about leave rejection', [
                'leave_request_id' => $leaveRequest->id,
                'employee_user_id' => $employeeUser->id
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveNotificationService: Error notifying leave rejection', [
                'error' => $e->getMessage(),
                'leave_request_id' => $leaveRequest->id ?? null
            ]);
        }
    }

    /**
     * Get managers who can approve leave for a given employee role
     */
    private function getManagersForEmployee(string $employeeRole)
    {
        $managerRoles = [];
        
        // Determine which managers can approve based on employee role
        if (RoleHierarchyService::isInProgramDepartment($employeeRole)) {
            $managerRoles[] = 'Program Manager';
        }
        
        if (RoleHierarchyService::isInDistributionDepartment($employeeRole)) {
            $managerRoles[] = 'Distribution Manager';
        }
        
        if (RoleHierarchyService::isInHRDepartment($employeeRole)) {
            $managerRoles[] = 'HR Manager';
            $managerRoles[] = 'HR';
        }
        
        // If no specific department found, notify HR as fallback
        if (empty($managerRoles)) {
            $managerRoles = ['HR Manager', 'HR', 'Program Manager', 'Distribution Manager'];
        }
        
        return User::whereIn('role', $managerRoles)->get();
    }

    /**
     * Get leave type name in Indonesian
     */
    private function getLeaveTypeName(string $leaveType): string
    {
        $types = [
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'paternity' => 'Cuti Ayah',
            'marriage' => 'Cuti Menikah',
            'bereavement' => 'Cuti Duka'
        ];
        
        return $types[$leaveType] ?? ucfirst($leaveType);
    }

    /**
     * Format date for display
     */
    private function formatDate($date): string
    {
        if (!$date) return '-';
        
        try {
            $carbonDate = \Carbon\Carbon::parse($date);
            return $carbonDate->format('d M Y');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }
}
