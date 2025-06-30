<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\LeaveQuota;

class UpdateLeaveQuotasSeeder extends Seeder
{
    public function run()
    {
        $currentYear = date('Y');
        
        // Get all employees
        $employees = Employee::all();
        
        foreach ($employees as $employee) {
            // Check if leave quota exists for current year
            $existingQuota = LeaveQuota::where('employee_id', $employee->id)
                                     ->where('year', $currentYear)
                                     ->first();
            
            if ($existingQuota) {
                // Update existing quota with all leave types, ensuring no null values
                $existingQuota->update([
                    'annual_leave_quota' => $existingQuota->annual_leave_quota ?? 12,
                    'annual_leave_used' => $existingQuota->annual_leave_used ?? 0,
                    'sick_leave_quota' => $existingQuota->sick_leave_quota ?? 12,
                    'sick_leave_used' => $existingQuota->sick_leave_used ?? 0,
                    'emergency_leave_quota' => $existingQuota->emergency_leave_quota ?? 2,
                    'emergency_leave_used' => $existingQuota->emergency_leave_used ?? 0,
                    'maternity_leave_quota' => $existingQuota->maternity_leave_quota ?? 90,
                    'maternity_leave_used' => $existingQuota->maternity_leave_used ?? 0,
                    'paternity_leave_quota' => $existingQuota->paternity_leave_quota ?? 7,
                    'paternity_leave_used' => $existingQuota->paternity_leave_used ?? 0,
                    'marriage_leave_quota' => $existingQuota->marriage_leave_quota ?? 3,
                    'marriage_leave_used' => $existingQuota->marriage_leave_used ?? 0,
                    'bereavement_leave_quota' => $existingQuota->bereavement_leave_quota ?? 3,
                    'bereavement_leave_used' => $existingQuota->bereavement_leave_used ?? 0,
                ]);
            } else {
                // Create new quota for employee
                LeaveQuota::create([
                    'employee_id' => $employee->id,
                    'year' => $currentYear,
                    'annual_leave_quota' => 12,
                    'annual_leave_used' => 0,
                    'sick_leave_quota' => 12,
                    'sick_leave_used' => 0,
                    'emergency_leave_quota' => 2,
                    'emergency_leave_used' => 0,
                    'maternity_leave_quota' => 90,
                    'maternity_leave_used' => 0,
                    'paternity_leave_quota' => 7,
                    'paternity_leave_used' => 0,
                    'marriage_leave_quota' => 3,
                    'marriage_leave_used' => 0,
                    'bereavement_leave_quota' => 3,
                    'bereavement_leave_used' => 0,
                ]);
            }
        }
        
        $this->command->info('Leave quotas updated successfully for all employees!');
    }
}