<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Add manager approval tracking
            $table->foreignId('manager_approved_by')->nullable()->constrained('employees')->onDelete('set null')->after('approved_by');
            $table->enum('manager_status', ['pending', 'approved', 'rejected'])->default('pending')->after('status');
            $table->timestamp('manager_approved_at')->nullable()->after('approved_at');
            $table->text('manager_rejection_reason')->nullable()->after('rejection_reason');
            
            // Add HR approval tracking
            $table->foreignId('hr_approved_by')->nullable()->constrained('employees')->onDelete('set null')->after('manager_approved_by');
            $table->enum('hr_status', ['pending', 'approved', 'rejected'])->default('pending')->after('manager_status');
            $table->timestamp('hr_approved_at')->nullable()->after('manager_approved_at');
            $table->text('hr_rejection_reason')->nullable()->after('manager_rejection_reason');
            
            // Update overall status to reflect the workflow
            $table->enum('overall_status', ['pending_manager', 'pending_hr', 'approved', 'rejected'])->default('pending_manager')->after('hr_status');
        });
    }

    public function down()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['manager_approved_by']);
            $table->dropForeign(['hr_approved_by']);
            $table->dropColumn([
                'manager_approved_by', 'manager_status', 'manager_approved_at', 'manager_rejection_reason',
                'hr_approved_by', 'hr_status', 'hr_approved_at', 'hr_rejection_reason',
                'overall_status'
            ]);
        });
    }
};