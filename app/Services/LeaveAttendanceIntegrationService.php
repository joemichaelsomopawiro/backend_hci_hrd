<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Attendance;
use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeaveAttendanceIntegrationService
{
    /**
     * Sinkronisasi status cuti ke tabel attendance untuk tanggal tertentu
     */
    public function syncLeaveStatusToAttendance($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        // Ambil semua leave request yang approved untuk tanggal tersebut
        $approvedLeaves = LeaveRequest::where('overall_status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with('employee')
            ->get();

        foreach ($approvedLeaves as $leave) {
            $this->updateAttendanceForLeave($leave, $date);
            $this->updateMorningReflectionForLeave($leave, $date);
        }

        Log::info("Leave status synced to attendance for date: {$date->format('Y-m-d')}", [
            'total_leaves' => $approvedLeaves->count()
        ]);

        return $approvedLeaves->count();
    }

    /**
     * Update attendance record untuk employee yang sedang cuti
     */
    private function updateAttendanceForLeave(LeaveRequest $leave, Carbon $date)
    {
        // Cari attendance record berdasarkan employee_id
        // Karena tabel attendance menggunakan user_pin, kita perlu mapping dari employee
        $employee = $leave->employee;
        
        if (!$employee) {
            Log::warning("Employee not found for leave request ID: {$leave->id}");
            return;
        }

        // Cari attendance berdasarkan user_pin dari employee
        $attendance = Attendance::where('user_pin', $employee->user_pin)
            ->where('date', $date->format('Y-m-d'))
            ->first();

        if ($attendance) {
            // Update existing attendance record
            $attendance->update([
                'status' => $this->mapLeaveTypeToAttendanceStatus($leave->leave_type),
                'notes' => "Cuti {$leave->leave_type} - {$leave->reason}"
            ]);
        } else {
            // Buat attendance record baru untuk employee yang cuti
            Attendance::create([
                'user_pin' => $employee->user_pin,
                'user_name' => $employee->nama_lengkap,
                'card_number' => $employee->card_number ?? null,
                'date' => $date->format('Y-m-d'),
                'check_in' => null,
                'check_out' => null,
                'status' => $this->mapLeaveTypeToAttendanceStatus($leave->leave_type),
                'work_hours' => 0,
                'overtime_hours' => 0,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'total_taps' => 0,
                'notes' => "Cuti {$leave->leave_type} - {$leave->reason}"
            ]);
        }
    }

    /**
     * Update morning reflection attendance untuk employee yang sedang cuti
     */
    private function updateMorningReflectionForLeave(LeaveRequest $leave, Carbon $date)
    {
        $employee = $leave->employee;
        
        if (!$employee) {
            return;
        }

        // Cari atau buat morning reflection attendance record
        $morningReflection = MorningReflectionAttendance::where('employee_id', $employee->id)
            ->where('date', $date->format('Y-m-d'))
            ->first();

        if ($morningReflection) {
            // Update existing record
            $morningReflection->update([
                'status' => 'Cuti',
                'join_time' => null
            ]);
        } else {
            // Buat record baru
            MorningReflectionAttendance::create([
                'employee_id' => $employee->id,
                'date' => $date->format('Y-m-d'),
                'status' => 'Cuti',
                'join_time' => null,
                'testing_mode' => false
            ]);
        }
    }

    /**
     * Mapping jenis cuti ke status attendance
     */
    private function mapLeaveTypeToAttendanceStatus($leaveType)
    {
        return match($leaveType) {
            'sick' => 'sick_leave',
            'annual', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement' => 'on_leave',
            default => 'on_leave'
        };
    }

    /**
     * Cek apakah employee sedang cuti pada tanggal tertentu
     */
    public function isEmployeeOnLeave($employeeId, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        return LeaveRequest::where('employee_id', $employeeId)
            ->where('overall_status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Ambil detail cuti employee untuk tanggal tertentu
     */
    public function getEmployeeLeaveDetails($employeeId, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        return LeaveRequest::where('employee_id', $employeeId)
            ->where('overall_status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Sinkronisasi untuk rentang tanggal
     */
    public function syncLeaveStatusForDateRange($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $totalSynced = 0;

        while ($start->lte($end)) {
            $totalSynced += $this->syncLeaveStatusToAttendance($start->copy());
            $start->addDay();
        }

        Log::info("Leave status synced for date range: {$startDate} to {$endDate}", [
            'total_records' => $totalSynced
        ]);

        return $totalSynced;
    }

    /**
     * Update status attendance ketika leave request disetujui
     */
    public function handleLeaveApproval(LeaveRequest $leaveRequest)
    {
        $startDate = Carbon::parse($leaveRequest->start_date);
        $endDate = Carbon::parse($leaveRequest->end_date);
        
        // Sinkronisasi untuk semua tanggal dalam rentang cuti
        while ($startDate->lte($endDate)) {
            $this->updateAttendanceForLeave($leaveRequest, $startDate->copy());
            $this->updateMorningReflectionForLeave($leaveRequest, $startDate->copy());
            $startDate->addDay();
        }

        Log::info("Attendance updated for approved leave request ID: {$leaveRequest->id}");
    }

    /**
     * Hapus status cuti dari attendance ketika leave request dibatalkan/ditolak
     */
    public function handleLeaveRejection(LeaveRequest $leaveRequest)
    {
        $startDate = Carbon::parse($leaveRequest->start_date);
        $endDate = Carbon::parse($leaveRequest->end_date);
        $employee = $leaveRequest->employee;
        
        if (!$employee) {
            return;
        }

        while ($startDate->lte($endDate)) {
            // Reset attendance status jika tidak ada tap
            $attendance = Attendance::where('user_pin', $employee->user_pin)
                ->where('date', $startDate->format('Y-m-d'))
                ->first();

            if ($attendance && !$attendance->check_in && !$attendance->check_out) {
                // Jika tidak ada tap sama sekali, ubah status ke absent
                $attendance->update([
                    'status' => 'absent',
                    'notes' => null
                ]);
            } elseif ($attendance) {
                // Jika ada tap, recalculate status
                $attendance->updateCalculations();
            }

            // Reset morning reflection attendance
            $morningReflection = MorningReflectionAttendance::where('employee_id', $employee->id)
                ->where('date', $startDate->format('Y-m-d'))
                ->first();

            if ($morningReflection && $morningReflection->status === 'Cuti') {
                $morningReflection->update([
                    'status' => 'Absen'
                ]);
            }

            $startDate->addDay();
        }

        Log::info("Attendance status reset for rejected leave request ID: {$leaveRequest->id}");
    }
}