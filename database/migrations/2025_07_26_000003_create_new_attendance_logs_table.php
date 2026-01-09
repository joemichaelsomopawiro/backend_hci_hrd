<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if table already exists
        if (!Schema::hasTable('attendance_logs')) {
            Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_machine_id')->constrained('attendance_machines')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade');
            $table->string('user_pin', 20)->comment('PIN/UserID dari mesin (bisa NIK atau NumCard)');
            $table->timestamp('datetime')->comment('Tanggal dan waktu tap dari mesin');
            $table->enum('verified_method', ['card', 'fingerprint', 'face', 'password'])->default('card')->comment('Metode verifikasi');
            $table->integer('verified_code')->comment('Kode verifikasi dari mesin');
            $table->enum('status_code', ['check_in', 'check_out', 'break_out', 'break_in', 'overtime_in', 'overtime_out'])->default('check_in')->comment('Status dari mesin');
            $table->boolean('is_processed')->default(false)->comment('Apakah sudah diproses menjadi attendance');
            $table->text('raw_data')->nullable()->comment('Data mentah dari mesin');
            $table->timestamps();
            
            $table->index(['employee_id', 'datetime']);
            $table->index(['user_pin', 'datetime']);
            $table->index(['datetime', 'is_processed']);
            $table->index('is_processed');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('attendance_logs');
    }
}; 