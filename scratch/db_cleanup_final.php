<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Identify what to KEEP
$keptUsers = DB::table('users')->where('email', 'LIKE', '%@joe.com')->get();
$keptUserIds = $keptUsers->pluck('id')->toArray();
$keptEmployeeIds = $keptUsers->whereNotNull('employee_id')->pluck('employee_id')->unique()->toArray();

echo "Keeping " . count($keptUserIds) . " users and " . count($keptEmployeeIds) . " employees.\n";

// 2. Tables with employee_id to clean
$relatedTables = [
    'attendances',
    'attendance_logs',
    'benefits',
    'employee_attendance',
    'employee_documents',
    'employment_histories',
    'kpi_quality_scores',
    'leave_quotas',
    'leave_requests',
    'morning_reflection_attendance',
    'promotion_histories',
    'trainings'
];

try {
    DB::beginTransaction();
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    // Cleanup related tables
    foreach ($relatedTables as $table) {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->whereNotIn('employee_id', $keptEmployeeIds)->delete();
            echo "Deleted $count records from $table.\n";
        }
    }

    // Cleanup Users
    $userCount = DB::table('users')->whereNotIn('id', $keptUserIds)->delete();
    echo "Deleted $userCount records from users.\n";

    // Cleanup Employees
    $empCount = DB::table('employees')->whereNotIn('id', $keptEmployeeIds)->delete();
    echo "Deleted $empCount records from employees.\n";

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    DB::commit();
    echo "\nCleanup COMPLETED successfully.\n";

} catch (\Exception $e) {
    DB::rollBack();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "\nERROR during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
