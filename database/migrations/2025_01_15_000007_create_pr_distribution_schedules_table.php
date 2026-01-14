<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Jadwal Tayang - dibuat oleh Manager Distribusi
     */
    public function up(): void
    {
        Schema::create('pr_distribution_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('pr_episodes')->onDelete('cascade');
            
            // Jadwal Tayang
            $table->date('schedule_date'); // Tanggal tayang
            $table->time('schedule_time'); // Jam tayang
            $table->string('channel')->nullable(); // Channel/platform tayang
            $table->text('schedule_notes')->nullable(); // Catatan jadwal
            
            // Status Jadwal
            $table->enum('status', [
                'draft',              // Draft
                'confirmed',          // Dikonfirmasi
                'scheduled',          // Terjadwal
                'aired',              // Sudah tayang
                'cancelled'           // Dibatalkan
            ])->default('draft');
            
            // Created by (Manager Distribusi)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['program_id', 'status']);
            $table->index(['episode_id', 'status']);
            $table->index(['schedule_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_distribution_schedules');
    }
};
