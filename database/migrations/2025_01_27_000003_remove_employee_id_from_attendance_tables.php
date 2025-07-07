<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Use DB queries with error handling to drop columns if they exist
        try {
            // Check if employee_id column exists in attendances table
            $columns = DB::select("DESCRIBE attendances");
            $hasEmployeeId = collect($columns)->contains('Field', 'employee_id');
            
            if ($hasEmployeeId) {
                DB::statement("ALTER TABLE attendances DROP COLUMN employee_id");
            }
        } catch (\Exception $e) {
            // Column doesn't exist or already dropped, continue
        }

        try {
            // Check if employee_id column exists in attendance_logs table
            $columns = DB::select("DESCRIBE attendance_logs");
            $hasEmployeeId = collect($columns)->contains('Field', 'employee_id');
            
            if ($hasEmployeeId) {
                DB::statement("ALTER TABLE attendance_logs DROP COLUMN employee_id");
            }
        } catch (\Exception $e) {
            // Column doesn't exist or already dropped, continue
        }
    }

    public function down()
    {
        // Add employee_id back to attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade')->after('id');
        });

        // Add employee_id back to attendance_logs table
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade')->after('attendance_machine_id');
        });
    }
}; 