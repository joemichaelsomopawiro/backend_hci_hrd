<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel utama Program Regular
     */
    public function up(): void
    {
        Schema::create('pr_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama program
            $table->text('description')->nullable(); // Deskripsi program
            
            // Manager Program yang membuat
            $table->foreignId('manager_program_id')->constrained('users')->onDelete('cascade');
            
            // Status Program
            $table->enum('status', [
                'draft',                    // Draft - baru dibuat
                'concept_pending',          // Konsep menunggu approval
                'concept_approved',         // Konsep disetujui
                'concept_rejected',         // Konsep ditolak
                'production_scheduled',     // Jadwal produksi dibuat
                'in_production',           // Sedang produksi
                'editing',                 // Sedang editing
                'submitted_to_manager',     // Disubmit ke Manager Program
                'manager_approved',         // Disetujui Manager Program
                'manager_rejected',         // Ditolak Manager Program
                'submitted_to_distribusi',  // Disubmit ke Manager Distribusi
                'distribusi_approved',      // Disetujui Manager Distribusi
                'distribusi_rejected',      // Ditolak Manager Distribusi
                'scheduled',               // Jadwal tayang dibuat
                'distributed',             // Sudah didistribusi
                'completed',               // Program selesai
                'cancelled'                // Dibatalkan
            ])->default('draft');
            
            // Informasi Program
            $table->date('start_date'); // Tanggal mulai episode pertama
            $table->time('air_time'); // Jam tayang (contoh: 19:00)
            $table->integer('duration_minutes')->default(60); // Durasi program dalam menit
            $table->string('broadcast_channel')->nullable(); // Channel broadcast
            
            // Tahun program (untuk generate 53 episode per tahun)
            $table->year('program_year'); // Tahun program (2025, 2026, dll)
            
            // Workflow tracking
            $table->foreignId('producer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('manager_distribusi_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['manager_program_id', 'status']);
            $table->index(['producer_id', 'status']);
            $table->index(['program_year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_programs');
    }
};
