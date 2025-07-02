<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // No foreign keys exist, just drop the columns
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('employee_id');
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn('employee_id');
        });
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