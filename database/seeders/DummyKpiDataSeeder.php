<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DummyKpiDataSeeder extends Seeder
{
    public function run()
    {
        $employeeId = 42; // Creative User
        $userPin = '12345'; // Assumption or I should check if it's needed for machines

        // 1. Generate Office Attendance for May 2026
        // Working days (Mon-Fri)
        $date = Carbon::create(2026, 5, 1);
        $daysInMonth = $date->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = Carbon::create(2026, 5, $day);
            
            // Skip weekends for mostly on-time/late data
            if ($currentDate->isWeekend()) continue;

            // Random status distribution
            $rand = rand(1, 100);
            $status = 'present_ontime';
            $checkIn = null;
            $checkOut = null;
            $lateMinutes = 0;
            $workHours = 0;

            if ($rand <= 70) {
                $status = 'present_ontime';
                $checkIn = $currentDate->copy()->setHour(7)->setMinute(rand(0, 25))->setSecond(rand(0, 59));
                $checkOut = $currentDate->copy()->setHour(16)->setMinute(rand(30, 59))->setSecond(rand(0, 59));
                $lateMinutes = 0;
            } elseif ($rand <= 85) {
                $status = 'present_late';
                $checkIn = $currentDate->copy()->setHour(7)->setMinute(rand(31, 60))->setSecond(rand(0, 59));
                $checkOut = $currentDate->copy()->setHour(16)->setMinute(rand(30, 59))->setSecond(rand(0, 59));
                $lateMinutes = $checkIn->diffInMinutes($currentDate->copy()->setHour(7)->setMinute(30));
            } elseif ($rand <= 90) {
                $status = 'sick_leave';
            } elseif ($rand <= 95) {
                $status = 'on_leave';
            } else {
                $status = 'absent';
            }

            if ($checkIn && $checkOut) {
                $workHours = $checkOut->diffInMinutes($checkIn) / 60;
            }

            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $currentDate->format('Y-m-d')],
                [
                    'user_pin' => $userPin,
                    'user_name' => 'Creative User',
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'status' => $status,
                    'work_hours' => $workHours,
                    'late_minutes' => $lateMinutes,
                    'early_leave_minutes' => 0,
                    'total_taps' => 2,
                ]
            );

            // 2. Generate Morning Reflection for the same date
            $reflectionStatus = 'Hadir';
            $joinTime = null;

            if ($status === 'present_ontime') {
                $reflectionStatus = 'Hadir';
                $joinTime = $currentDate->copy()->setHour(7)->setMinute(rand(50, 59))->setSecond(rand(0, 59));
            } elseif ($status === 'present_late') {
                $reflectionStatus = 'Terlambat';
                $joinTime = $currentDate->copy()->setHour(8)->setMinute(rand(0, 15))->setSecond(rand(0, 59));
            } elseif ($status === 'sick_leave' || $status === 'on_leave') {
                 $reflectionStatus = 'Cuti';
            } else {
                 $reflectionStatus = 'Absen';
            }

            MorningReflectionAttendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $currentDate->format('Y-m-d')],
                [
                    'status' => $reflectionStatus,
                    'join_time' => $joinTime,
                    'attendance_method' => 'online',
                    'attendance_source' => 'zoom'
                ]
            );
        }

        // --- 3. Work Performance Dummy Data ---
        $episodes = \App\Models\PrEpisode::whereYear('air_date', 2026)->whereMonth('air_date', 5)->get();
        foreach ($episodes as $index => $ep) {
            $status = ($index % 3 === 0) ? 'approved' : (($index % 3 === 1) ? 'submitted' : 'draft');
            $isLate = ($index % 5 === 0);
            
            $deadline = \Carbon\Carbon::parse($ep->air_date)->subDays(7);
            $completedAt = $isLate ? $deadline->copy()->addDays(2) : $deadline->copy()->subDays(1);

            \App\Models\PrCreativeWork::updateOrCreate(
                ['pr_episode_id' => $ep->id, 'created_by' => 51],
                [
                    'status' => $status,
                    'reviewed_at' => ($status === 'draft') ? null : $completedAt,
                    'script_content' => 'Dummy script for KPI testing',
                ]
            );
        }
    }
}
