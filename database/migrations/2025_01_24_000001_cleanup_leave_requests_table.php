<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['manager_approved_by']);
            $table->dropForeign(['hr_approved_by']);
            
            // Drop redundant columns
            $table->dropColumn([
                'manager_approved_by',
                'manager_status', 
                'manager_approved_at',
                'manager_rejection_reason',
                'hr_approved_by',
                'hr_status',
                'hr_approved_at', 
                'hr_rejection_reason',
                'status' // kolom asli yang sudah digantikan overall_status
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Restore columns if needed
            $table->foreignId('manager_approved_by')->nullable()->constrained('employees')->onDelete('set null');
            $table->enum('manager_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_rejection_reason')->nullable();
            $table->foreignId('hr_approved_by')->nullable()->constrained('employees')->onDelete('set null');
            $table->enum('hr_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('hr_approved_at')->nullable();
            $table->text('hr_rejection_reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        });
    }
};