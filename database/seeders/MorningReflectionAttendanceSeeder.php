<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
use Carbon\Carbon;

class MorningReflectionAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil beberapa employee untuk testing
        $employees = Employee::take(5)->get();
        
        if ($employees->isEmpty()) {
            $this->command->info('No employees found. Please run EmployeeSeeder first.');
            return;
        }

        $today = Carbon::now('Asia/Jakarta');
        
        foreach ($employees as $employee) {
            // Buat data untuk hari ini
            MorningReflectionAttendance::create([
                'employee_id' => $employee->id,
                'date' => $today->toDateString(),
                'status' => 'Hadir',
                'join_time' => $today->copy()->setTime(7, 15),
                'testing_mode' => true
            ]);

            // Buat data untuk kemarin
            MorningReflectionAttendance::create([
                'employee_id' => $employee->id,
                'date' => $today->copy()->subDay()->toDateString(),
                'status' => 'Terlambat',
                'join_time' => $today->copy()->subDay()->setTime(7, 32),
                'testing_mode' => true
            ]);

            // Buat data untuk 2 hari yang lalu
            MorningReflectionAttendance::create([
                'employee_id' => $employee->id,
                'date' => $today->copy()->subDays(2)->toDateString(),
                'status' => 'Hadir',
                'join_time' => $today->copy()->subDays(2)->setTime(7, 10),
                'testing_mode' => true
            ]);
        }

        $this->command->info('Morning reflection attendance data seeded successfully!');
    }
} 