<?php

/**
 * Test script untuk memverifikasi sistem auto-sync employee_id di attendance
 * 
 * Script ini akan:
 * 1. Cek status sync saat ini
 * 2. Test manual bulk sync
 * 3. Verifikasi hasil sync
 * 4. Test upload TXT dengan auto-sync
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Employee;
use App\Models\Attendance;
use App\Services\TxtAttendanceUploadService;

echo "=== TEST EMPLOYEE SYNC SYSTEM ===\n\n";

// Test 1: Cek status sync saat ini
echo "1. Checking current sync status...\n";
$totalAttendance = Attendance::count();
$syncedAttendance = Attendance::whereNotNull('employee_id')->count();
$unsyncedAttendance = $totalAttendance - $syncedAttendance;
$syncPercentage = $totalAttendance > 0 ? round(($syncedAttendance / $totalAttendance) * 100, 2) : 0;

echo "   Total Attendance: $totalAttendance\n";
echo "   Synced: $syncedAttendance\n";
echo "   Unsynced: $unsyncedAttendance\n";
echo "   Sync Percentage: $syncPercentage%\n\n";

// Test 2: Tampilkan sample data yang belum sync
echo "2. Sample unsynced data:\n";
$unsyncedSamples = Attendance::whereNull('employee_id')
                            ->select('user_name', 'card_number', 'date')
                            ->limit(5)
                            ->get();

foreach ($unsyncedSamples as $sample) {
    echo "   Name: {$sample->user_name}, Card: {$sample->card_number}, Date: {$sample->date}\n";
    
    // Cek apakah ada employee dengan nama yang sama
    $employee = Employee::where('nama_lengkap', $sample->user_name)->first();
    if ($employee) {
        echo "     -> Employee found: ID {$employee->id}\n";
    } else {
        echo "     -> Employee NOT found\n";
        
        // Coba cari dengan case insensitive
        $employees = Employee::all();
        foreach ($employees as $emp) {
            if (strtolower(trim($emp->nama_lengkap)) === strtolower(trim($sample->user_name))) {
                echo "     -> Employee found (case insensitive): ID {$emp->id} - {$emp->nama_lengkap}\n";
                break;
            }
        }
    }
}
echo "\n";

// Test 3: Test manual bulk sync
echo "3. Testing manual bulk sync...\n";
$txtService = new TxtAttendanceUploadService();
$syncResult = $txtService->manualBulkSyncAttendance();

echo "   Sync Result: " . ($syncResult['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "   Message: {$syncResult['message']}\n";
echo "   Data: " . json_encode($syncResult['data'], JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Cek status setelah sync
echo "4. Checking sync status after manual sync...\n";
$totalAttendanceAfter = Attendance::count();
$syncedAttendanceAfter = Attendance::whereNotNull('employee_id')->count();
$unsyncedAttendanceAfter = $totalAttendanceAfter - $syncedAttendanceAfter;
$syncPercentageAfter = $totalAttendanceAfter > 0 ? round(($syncedAttendanceAfter / $totalAttendanceAfter) * 100, 2) : 0;

echo "   Total Attendance: $totalAttendanceAfter\n";
echo "   Synced: $syncedAttendanceAfter (+" . ($syncedAttendanceAfter - $syncedAttendance) . ")\n";
echo "   Unsynced: $unsyncedAttendanceAfter\n";
echo "   Sync Percentage: $syncPercentageAfter%\n\n";

// Test 5: Sample synced data
echo "5. Sample synced data:\n";
$syncedSamples = Attendance::whereNotNull('employee_id')
                          ->with('employee:id,nama_lengkap')
                          ->select('user_name', 'card_number', 'date', 'employee_id')
                          ->limit(5)
                          ->get();

foreach ($syncedSamples as $sample) {
    $employeeName = $sample->employee ? $sample->employee->nama_lengkap : 'N/A';
    echo "   Name: {$sample->user_name}, Card: {$sample->card_number}\n";
    echo "     -> Linked to Employee ID {$sample->employee_id}: {$employeeName}\n";
}
echo "\n";

// Test 6: Verifikasi employee yang ada
echo "6. Employee database info:\n";
$totalEmployees = Employee::count();
echo "   Total Employees: $totalEmployees\n";

$employeeSamples = Employee::select('id', 'nama_lengkap', 'NumCard')->limit(5)->get();
echo "   Sample employees:\n";
foreach ($employeeSamples as $emp) {
    echo "     ID {$emp->id}: {$emp->nama_lengkap} (Card: {$emp->NumCard})\n";
}
echo "\n";

// Test 7: Cek notes untuk debug mapping
echo "7. Checking mapping info from notes...\n";
$attendanceWithNotes = Attendance::whereNotNull('notes')
                                 ->whereNotNull('employee_id')
                                 ->limit(3)
                                 ->get();

foreach ($attendanceWithNotes as $att) {
    $notes = json_decode($att->notes, true);
    if (isset($notes['mapped_by'])) {
        echo "   {$att->user_name} -> Employee ID {$att->employee_id}\n";
        echo "     Mapped by: {$notes['mapped_by']}\n";
        echo "     Sync status: {$notes['sync_status']}\n";
        if (isset($notes['sync_timestamp'])) {
            echo "     Sync time: {$notes['sync_timestamp']}\n";
        }
    }
}

echo "\n=== TEST COMPLETED ===\n";

if ($syncPercentageAfter > $syncPercentage) {
    echo "✅ SUCCESS: Sync percentage improved from $syncPercentage% to $syncPercentageAfter%\n";
} else {
    echo "⚠️  WARNING: No improvement in sync percentage\n";
}

if ($syncPercentageAfter >= 90) {
    echo "🎉 EXCELLENT: Sync percentage is very good ($syncPercentageAfter%)\n";
} elseif ($syncPercentageAfter >= 70) {
    echo "👍 GOOD: Sync percentage is acceptable ($syncPercentageAfter%)\n";
} else {
    echo "❌ NEEDS ATTENTION: Low sync percentage ($syncPercentageAfter%)\n";
} 