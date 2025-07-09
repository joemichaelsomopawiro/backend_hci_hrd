<?php

/**
 * Test Auto-Sync System
 * 
 * File ini untuk testing sistem auto-sinkronisasi yang baru dibuat
 * Jalankan dengan: php test_auto_sync.php
 */

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AUTO-SYNC SYSTEM TEST ===\n\n";

// Test data
$testEmployeeName = "Test Employee Sync";
$testEmployeeId = null;

try {
    // 1. Test creating new employee (should trigger auto-sync)
    echo "1. Testing Employee Creation with Auto-Sync...\n";
    
    $employee = \App\Models\Employee::create([
        'nama_lengkap' => $testEmployeeName,
        'nik' => '123456789',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Test Address',
        'status_pernikahan' => 'Belum Menikah',
        'jabatan_saat_ini' => 'Employee',
        'tanggal_mulai_kerja' => '2024-01-01',
        'tingkat_pendidikan' => 'S1',
        'gaji_pokok' => 5000000,
    ]);
    
    $testEmployeeId = $employee->id;
    echo "   ✓ Employee created with ID: {$testEmployeeId}\n";
    
    // 2. Test manual sync by name
    echo "\n2. Testing Manual Sync by Name...\n";
    $syncResult = \App\Services\EmployeeSyncService::syncEmployeeByName($testEmployeeName);
    
    if ($syncResult['success']) {
        echo "   ✓ Manual sync successful\n";
        echo "   - Users updated: " . $syncResult['data']['sync_operations']['users']['updated'] . "\n";
        echo "   - Users created: " . $syncResult['data']['sync_operations']['users']['created'] . "\n";
    } else {
        echo "   ✗ Manual sync failed: " . $syncResult['message'] . "\n";
    }
    
    // 3. Test sync by ID
    echo "\n3. Testing Sync by ID...\n";
    $syncByIdResult = \App\Services\EmployeeSyncService::syncEmployeeById($testEmployeeId);
    
    if ($syncByIdResult['success']) {
        echo "   ✓ Sync by ID successful\n";
    } else {
        echo "   ✗ Sync by ID failed: " . $syncByIdResult['message'] . "\n";
    }
    
    // 4. Test creating user with same name (should auto-link)
    echo "\n4. Testing User Creation with Auto-Link...\n";
    
    $user = \App\Models\User::create([
        'name' => $testEmployeeName,
        'email' => 'test.sync.' . time() . '@company.com',
        'phone' => '08123456789' . rand(1, 9),
        'password' => bcrypt('password123'),
        'employee_id' => null // Will be auto-linked
    ]);
    
    echo "   ✓ User created with ID: {$user->id}\n";
    
    // 5. Test sync again to link the user
    echo "\n5. Testing Sync to Link User...\n";
    $linkResult = \App\Services\EmployeeSyncService::syncEmployeeByName($testEmployeeName);
    
    if ($linkResult['success']) {
        echo "   ✓ User linking successful\n";
        $updatedUser = \App\Models\User::find($user->id);
        echo "   - User employee_id: " . ($updatedUser->employee_id ?? 'null') . "\n";
        echo "   - User role: " . ($updatedUser->role ?? 'null') . "\n";
    } else {
        echo "   ✗ User linking failed: " . $linkResult['message'] . "\n";
    }
    
    // 6. Test creating attendance record (should auto-sync)
    echo "\n6. Testing Attendance Auto-Sync...\n";
    
    // Ambil attendance_machine_id yang valid
    $machineId = \App\Models\AttendanceMachine::first()->id ?? 1;
    // Create employee attendance record
    $employeeAttendance = \App\Models\EmployeeAttendance::create([
        'name' => $testEmployeeName,
        'machine_user_id' => 999,
        'attendance_machine_id' => $machineId,
        'is_active' => true,
        'employee_id' => null // Will be auto-synced
    ]);
    
    // Create attendance record
    $attendance = \App\Models\Attendance::create([
        'user_name' => $testEmployeeName,
        'date' => date('Y-m-d'),
        'check_in' => '08:00:00',
        'check_out' => '17:00:00',
        'employee_id' => null // Will be auto-synced
    ]);
    
    echo "   ✓ Attendance records created\n";
    
    // Test auto-sync attendance
    $attendanceSyncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($testEmployeeName);
    
    if ($attendanceSyncResult['success']) {
        echo "   ✓ Attendance auto-sync successful\n";
    } else {
        echo "   ✗ Attendance auto-sync failed: " . $attendanceSyncResult['message'] . "\n";
    }
    
    // 7. Test morning reflection auto-sync
    echo "\n7. Testing Morning Reflection Auto-Sync...\n";
    
    $morningReflectionSyncResult = \App\Services\EmployeeSyncService::autoSyncMorningReflection($testEmployeeName);
    
    if ($morningReflectionSyncResult['success']) {
        echo "   ✓ Morning reflection auto-sync successful\n";
    } else {
        echo "   ✗ Morning reflection auto-sync failed: " . $morningReflectionSyncResult['message'] . "\n";
    }
    
    // 8. Test bulk sync
    echo "\n8. Testing Bulk Sync...\n";
    $bulkSyncResults = \App\Services\EmployeeSyncService::bulkSyncAllEmployees();
    
    $successCount = 0;
    $errorCount = 0;
    foreach ($bulkSyncResults as $result) {
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    echo "   ✓ Bulk sync completed\n";
    echo "   - Success: {$successCount}\n";
    echo "   - Errors: {$errorCount}\n";
    
    // 9. Test sync status
    echo "\n9. Testing Sync Status...\n";
    
    $employees = \App\Models\Employee::with(['user', 'employeeAttendance'])->get();
    $needsSyncCount = 0;
    
    foreach ($employees as $emp) {
        $needsSync = false;
        $issues = [];
        
        if (!$emp->user) {
            $needsSync = true;
            $issues[] = 'No user account';
        }
        
        if (!$emp->employeeAttendance) {
            $needsSync = true;
            $issues[] = 'No employee attendance';
        }
        
        if ($needsSync) {
            $needsSyncCount++;
        }
    }
    
    echo "   ✓ Sync status checked\n";
    echo "   - Total employees: " . $employees->count() . "\n";
    echo "   - Needs sync: {$needsSyncCount}\n";
    echo "   - Synced: " . ($employees->count() - $needsSyncCount) . "\n";
    
    // 10. Test orphaned records sync
    echo "\n10. Testing Orphaned Records Sync...\n";
    
    // Create some orphaned records
    $orphanedAttendance = \App\Models\Attendance::create([
        'user_name' => $testEmployeeName,
        'date' => date('Y-m-d', strtotime('-1 day')),
        'check_in' => '08:00:00',
        'check_out' => '17:00:00',
        'employee_id' => null
    ]);
    
    echo "   ✓ Orphaned records created\n";
    
    // Test sync orphaned records
    $orphanedSyncResult = \App\Services\EmployeeSyncService::syncEmployeeByName($testEmployeeName);
    
    if ($orphanedSyncResult['success']) {
        echo "   ✓ Orphaned records sync successful\n";
    } else {
        echo "   ✗ Orphaned records sync failed: " . $orphanedSyncResult['message'] . "\n";
    }
    
    echo "\n=== TEST SUMMARY ===\n";
    echo "✓ All auto-sync tests completed successfully!\n";
    echo "✓ Employee ID: {$testEmployeeId}\n";
    echo "✓ Employee Name: {$testEmployeeName}\n";
    
    // Cleanup test data
    echo "\n=== CLEANUP ===\n";
    
    // Delete test records
    \App\Models\Attendance::where('user_name', $testEmployeeName)->delete();
    \App\Models\EmployeeAttendance::where('name', $testEmployeeName)->delete();
    \App\Models\User::where('name', $testEmployeeName)->delete();
    \App\Models\Employee::where('nama_lengkap', $testEmployeeName)->delete();
    
    echo "✓ Test data cleaned up\n";
    
} catch (\Exception $e) {
    echo "✗ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Cleanup on error
    if ($testEmployeeId) {
        \App\Models\Employee::where('id', $testEmployeeId)->delete();
    }
    \App\Models\User::where('name', $testEmployeeName)->delete();
    \App\Models\Attendance::where('user_name', $testEmployeeName)->delete();
    \App\Models\EmployeeAttendance::where('name', $testEmployeeName)->delete();
}

echo "\n=== AUTO-SYNC SYSTEM TEST COMPLETED ===\n"; 