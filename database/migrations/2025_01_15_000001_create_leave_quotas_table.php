<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveQuotasTable extends Migration
{
    public function up()
    {
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->year('year');
            $table->integer('annual_leave_quota')->default(12); // Jatah cuti tahunan
            $table->integer('annual_leave_used')->default(0); // Cuti tahunan yang sudah digunakan
            $table->integer('sick_leave_quota')->default(12); // Jatah cuti sakit
            $table->integer('sick_leave_used')->default(0); // Cuti sakit yang sudah digunakan
            $table->integer('emergency_leave_quota')->default(2); // Jatah cuti darurat
            $table->integer('emergency_leave_used')->default(0); // Cuti darurat yang sudah digunakan
            $table->timestamps();
            
            $table->unique(['employee_id', 'year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_quotas');
    }
}