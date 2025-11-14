<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists
        if (!Schema::hasTable('employee_attendance')) {
            return;
        }
        
        // Only add column if it doesn't exist
        if (!Schema::hasColumn('employee_attendance', 'employee_id')) {
            Schema::table('employee_attendance', function (Blueprint $table) {
                // Add employee_id column
                $table->unsignedBigInteger('employee_id')->nullable()->after('attendance_machine_id');
                
                // Add foreign key constraint
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
                
                // Add index for better performance
                $table->index('employee_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendance', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['employee_id']);
            
            // Drop index
            $table->dropIndex(['employee_id']);
            
            // Drop column
            $table->dropColumn('employee_id');
        });
    }
};
