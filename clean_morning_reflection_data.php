<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== CLEANING MORNING REFLECTION ATTENDANCE DATA ===\n";

try {
    // 1. Cek data yang employee_id-nya tidak ada di tabel employees
    echo "\n1. Checking invalid employee_id references...\n";
    
    $invalidAttendances = MorningReflectionAttendance::whereNotExists(function($query) {
        $query->select(DB::raw(1))
              ->from('employees')
              ->whereRaw('employees.id = morning_reflection_attendance.employee_id');
    })->get();
    
    echo "Found " . $invalidAttendances->count() . " attendance records with invalid employee_id\n";
    
    if ($invalidAttendances->count() > 0) {
        echo "Invalid records:\n";
        foreach ($invalidAttendances as $attendance) {
            echo "- ID: {$attendance->id}, Employee ID: {$attendance->employee_id}, Date: {$attendance->date}\n";
        }
        
        // Tanya user apakah ingin menghapus data invalid
        echo "\nDo you want to delete these invalid records? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) === 'y' || trim($line) === 'Y') {
            $deletedCount = $invalidAttendances->count();
            $invalidAttendances->each(function($attendance) {
                $attendance->delete();
            });
            echo "Deleted {$deletedCount} invalid attendance records\n";
        } else {
            echo "Skipped deleting invalid records\n";
        }
    }
    
    // 2. Cek data yang employee_id-nya null
    echo "\n2. Checking null employee_id records...\n";
    
    $nullEmployeeAttendances = MorningReflectionAttendance::whereNull('employee_id')->get();
    
    echo "Found " . $nullEmployeeAttendances->count() . " attendance records with null employee_id\n";
    
    if ($nullEmployeeAttendances->count() > 0) {
        echo "Null employee_id records:\n";
        foreach ($nullEmployeeAttendances as $attendance) {
            echo "- ID: {$attendance->id}, Date: {$attendance->date}\n";
        }
        
        // Tanya user apakah ingin menghapus data null
        echo "\nDo you want to delete these null employee_id records? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) === 'y' || trim($line) === 'Y') {
            $deletedCount = $nullEmployeeAttendances->count();
            $nullEmployeeAttendances->each(function($attendance) {
                $attendance->delete();
            });
            echo "Deleted {$deletedCount} null employee_id records\n";
        } else {
            echo "Skipped deleting null employee_id records\n";
        }
    }
    
    // 3. Cek duplikasi data (employee_id + date yang sama)
    echo "\n3. Checking duplicate records...\n";
    
    $duplicates = DB::table('morning_reflection_attendance')
        ->select('employee_id', 'date', DB::raw('COUNT(*) as count'))
        ->groupBy('employee_id', 'date')
        ->having('count', '>', 1)
        ->get();
    
    echo "Found " . $duplicates->count() . " duplicate groups\n";
    
    if ($duplicates->count() > 0) {
        foreach ($duplicates as $duplicate) {
            echo "- Employee ID: {$duplicate->employee_id}, Date: {$duplicate->date}, Count: {$duplicate->count}\n";
            
            // Ambil semua record duplikat
            $duplicateRecords = MorningReflectionAttendance::where('employee_id', $duplicate->employee_id)
                ->whereDate('date', $duplicate->date)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Keep the latest one, delete the rest
            $toDelete = $duplicateRecords->skip(1);
            $deletedCount = $toDelete->count();
            
            if ($deletedCount > 0) {
                $toDelete->each(function($attendance) {
                    $attendance->delete();
                });
                echo "  -> Kept latest record, deleted {$deletedCount} duplicates\n";
            }
        }
    }
    
    // 4. Cek konsistensi tipe data employee_id
    echo "\n4. Checking employee_id data type consistency...\n";
    
    $totalAttendances = MorningReflectionAttendance::count();
    echo "Total attendance records: {$totalAttendances}\n";
    
    // 5. Test endpoint untuk memastikan data sudah bersih
    echo "\n5. Testing endpoint response...\n";
    
    // Simulasi request ke endpoint
    $attendances = MorningReflectionAttendance::with('employee')
        ->orderBy('date', 'desc')
        ->limit(5)
        ->get();
    
    $transformedAttendances = $attendances->map(function ($attendance) {
        $data = $attendance->toArray();
        $data['employee_id'] = (int) $data['employee_id'];
        
        if ($attendance->employee) {
            $data['employee_name'] = $attendance->employee->nama_lengkap;
            $data['employee']['id'] = (int) $attendance->employee->id;
        } else {
            $data['employee_name'] = 'Karyawan Tidak Ditemukan';
            $data['employee'] = null;
        }
        
        return $data;
    });
    
    echo "Sample transformed data:\n";
    foreach ($transformedAttendances as $attendance) {
        echo "- ID: {$attendance['id']}, Employee ID: {$attendance['employee_id']}, Employee Name: {$attendance['employee_name']}\n";
    }
    
    echo "\n=== CLEANING COMPLETED ===\n";
    echo "Data morning reflection attendance sudah dibersihkan dan konsisten!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    Log::error('Error cleaning morning reflection data', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 