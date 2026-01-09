<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if table already exists
        if (!Schema::hasTable('attendance_sync_logs')) {
            Schema::create('attendance_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_machine_id')->constrained('attendance_machines')->onDelete('cascade');
            $table->enum('operation', [
                'pull_data',           // Tarik data absensi
                'pull_user_data',      // Tarik data user dari mesin
                'push_user',          // Upload user ke mesin
                'delete_user',        // Hapus user dari mesin
                'clear_data',         // Clear data mesin
                'sync_time',          // Sinkronisasi waktu
                'restart_machine',    // Restart mesin
                'test_connection'     // Test koneksi
            ]);
            $table->enum('status', ['success', 'failed', 'partial'])->default('failed');
            $table->text('message')->nullable()->comment('Pesan hasil operasi');
            $table->json('details')->nullable()->comment('Detail operasi');
            $table->integer('records_processed')->default(0)->comment('Jumlah record yang diproses');
            $table->timestamp('started_at')->comment('Waktu mulai operasi');
            $table->timestamp('completed_at')->nullable()->comment('Waktu selesai operasi');
            $table->decimal('duration', 8, 3)->nullable()->comment('Durasi operasi (detik)');
            $table->timestamps();
            
            $table->index(['attendance_machine_id', 'operation']);
            $table->index(['status', 'started_at']);
            $table->index('started_at');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('attendance_sync_logs');
    }
}; 