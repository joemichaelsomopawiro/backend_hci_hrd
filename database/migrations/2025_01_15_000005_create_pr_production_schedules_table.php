<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Jadwal Produksi - dibuat oleh Producer
     * Berisi jadwal untuk 53 episode
     */
    public function up(): void
    {
        Schema::create('pr_production_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('pr_episodes')->onDelete('cascade');
            
            // Jadwal Produksi
            $table->date('scheduled_date'); // Tanggal jadwal produksi
            $table->time('scheduled_time')->nullable(); // Jam jadwal produksi
            $table->text('schedule_notes')->nullable(); // Catatan jadwal
            
            // Status Jadwal
            $table->enum('status', [
                'draft',              // Draft
                'confirmed',          // Dikonfirmasi
                'in_progress',        // Sedang berlangsung
                'completed',          // Selesai
                'cancelled'           // Dibatalkan
            ])->default('draft');
            
            // Created by (Producer)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['program_id', 'status']);
            $table->index(['episode_id', 'status']);
            $table->index(['scheduled_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_production_schedules');
    }
};
