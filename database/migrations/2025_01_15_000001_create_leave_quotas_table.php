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
            $table->integer('sick_leave_quota')->default(3); // Jatah cuti sakit (3 hari)
            $table->integer('sick_leave_used')->default(0); // Cuti sakit yang sudah digunakan
            $table->integer('emergency_leave_quota')->default(1); // Jatah cuti darurat (1 hari)
            $table->integer('emergency_leave_used')->default(0); // Cuti darurat yang sudah digunakan
            $table->integer('maternity_leave_quota')->default(80); // Jatah cuti melahirkan (80 hari)
            $table->integer('maternity_leave_used')->default(0); // Cuti melahirkan yang sudah digunakan
            $table->integer('paternity_leave_quota')->default(3); // Jatah cuti ayah (3 hari)
            $table->integer('paternity_leave_used')->default(0); // Cuti ayah yang sudah digunakan
            $table->integer('marriage_leave_quota')->default(3); // Jatah cuti menikah
            $table->integer('marriage_leave_used')->default(0); // Cuti menikah yang sudah digunakan
            $table->integer('bereavement_leave_quota')->default(3); // Jatah cuti duka
            $table->integer('bereavement_leave_used')->default(0); // Cuti duka yang sudah digunakan
            $table->timestamps();
            
            $table->unique(['employee_id', 'year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_quotas');
    }
}