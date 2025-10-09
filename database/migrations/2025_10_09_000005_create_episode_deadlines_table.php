<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Episode Deadlines - Tracking deadline untuk setiap role di setiap episode
     * 
     * Aturan deadline:
     * - Editor: 7 hari sebelum tayang
     * - Kreatif & Produksi: 9 hari sebelum tayang
     * 
     * Contoh: Episode 1 tayang tanggal 10, maka:
     * - Episode 1 deadline Editor: 10 - 7 = tanggal 3
     * - Episode 1 deadline Kreatif/Produksi: 10 - 9 = tanggal 1
     * 
     * Episode 2 (seminggu setelah episode 1):
     * - Episode 2 deadline Editor: 17 - 7 = tanggal 10
     * - Episode 2 deadline Kreatif/Produksi: 17 - 9 = tanggal 8
     */
    public function up(): void
    {
        Schema::create('episode_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_episode_id')->constrained('program_episodes')->onDelete('cascade');
            $table->enum('role', [
                'kreatif',
                'musik_arr',
                'sound_eng',
                'produksi',
                'editor',
                'art_set_design'
            ]);
            
            // Deadline
            $table->dateTime('deadline_date'); // Deadline untuk role ini
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Notes & Status
            $table->text('notes')->nullable();
            $table->enum('status', [
                'pending',      // Belum dikerjakan
                'in_progress',  // Sedang dikerjakan
                'completed',    // Selesai
                'overdue',      // Terlambat
                'cancelled'     // Dibatalkan (karena program cancelled)
            ])->default('pending');
            
            // Notifikasi sudah dikirim atau belum
            $table->boolean('reminder_sent')->default(false);
            $table->dateTime('reminder_sent_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['program_episode_id', 'role']);
            $table->index(['deadline_date', 'status']);
            $table->index(['is_completed', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episode_deadlines');
    }
};

