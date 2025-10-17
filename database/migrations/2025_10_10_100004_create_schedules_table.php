<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Music Schedules - Jadwal rekaman vokal & jadwal syuting untuk music production
     * Dibuat oleh Kreatif, dapat di-cancel/reschedule oleh Producer
     */
    public function up(): void
    {
        Schema::create('music_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Schedule Type
            $table->enum('schedule_type', [
                'recording',    // Rekaman vokal
                'shooting'      // Syuting video klip
            ]);
            
            // Schedule Details
            $table->dateTime('scheduled_datetime'); // Tanggal & jam
            $table->string('location'); // Lokasi (Studio, outdoor location, etc.)
            $table->text('location_address')->nullable(); // Alamat lengkap
            $table->text('schedule_notes')->nullable();
            
            // Status
            $table->enum('status', [
                'scheduled',      // Terjadwal
                'confirmed',      // Dikonfirmasi
                'in_progress',    // Sedang berlangsung
                'completed',      // Selesai
                'cancelled',      // Dibatalkan
                'rescheduled'     // Dijadwal ulang
            ])->default('scheduled');
            
            // Cancellation/Rescheduling
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();
            
            $table->dateTime('rescheduled_datetime')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->foreignId('rescheduled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rescheduled_at')->nullable();
            
            // Completion
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            $table->index(['music_submission_id', 'schedule_type', 'status']);
            $table->index(['scheduled_datetime', 'status']);
            $table->index(['schedule_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_schedules');
    }
};

