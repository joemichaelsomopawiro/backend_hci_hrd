<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('employee_signature_path')->nullable()->after('rejection_reason');
            $table->string('approver_signature_path')->nullable()->after('employee_signature_path');
            $table->string('leave_location')->nullable()->after('approver_signature_path');
            $table->string('contact_phone')->nullable()->after('leave_location');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'employee_signature_path',
                'approver_signature_path',
                'leave_location',
                'contact_phone',
            ]);
        });
    }
};


