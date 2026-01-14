<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Episode Program Regular - 53 episode per tahun
     * Auto-generate setiap tahun baru
     */
    public function up(): void
    {
        Schema::create('pr_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            
            // Episode Information
            $table->integer('episode_number'); // 1-53
            $table->string('title')->nullable(); // Judul episode (bisa dikosongkan dulu)
            $table->text('description')->nullable(); // Deskripsi episode
            
            // Jadwal
            $table->date('air_date'); // Tanggal tayang
            $table->time('air_time'); // Jam tayang
            $table->date('production_date')->nullable(); // Tanggal produksi (dijadwalkan oleh Producer/Kreatif)
            
            // Status Episode
            $table->enum('status', [
                'scheduled',         // Terjadwal (baru dibuat)
                'production',        // Sedang produksi
                'editing',           // Sedang editing
                'ready_for_review',  // Siap untuk review Manager Program
                'manager_approved',   // Disetujui Manager Program
                'manager_rejected',   // Ditolak Manager Program
                'ready_for_distribusi', // Siap untuk Manager Distribusi
                'distribusi_approved',  // Disetujui Manager Distribusi
                'scheduled_to_air',    // Terjadwal untuk tayang
                'aired',               // Sudah tayang
                'cancelled'            // Dibatalkan
            ])->default('scheduled');
            
            // Production Information
            $table->text('production_notes')->nullable(); // Catatan produksi
            $table->text('editing_notes')->nullable(); // Catatan editing
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint: satu program tidak boleh punya episode number yang sama
            $table->unique(['program_id', 'episode_number']);
            $table->index(['program_id', 'status']);
            $table->index(['air_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_episodes');
    }
};
