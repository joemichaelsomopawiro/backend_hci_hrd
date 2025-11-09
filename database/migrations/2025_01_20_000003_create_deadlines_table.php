<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Deadlines table - Auto-calculate deadlines untuk setiap role di setiap episode
     */
    public function up(): void
    {
        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('role', [
                'kreatif',
                'musik_arr',
                'sound_eng',
                'produksi',
                'editor',
                'art_set_design',
                'design_grafis',
                'promotion',
                'broadcasting',
                'quality_control'
            ]);
            
            // Deadline Information
            $table->datetime('deadline_date'); // Tanggal deadline
            $table->boolean('is_completed')->default(false); // Status completion
            $table->timestamp('completed_at')->nullable(); // Tanggal completion
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null'); // User yang complete
            $table->text('notes')->nullable(); // Catatan deadline
            $table->enum('status', [
                'pending',      // Menunggu
                'in_progress',  // Sedang dikerjakan
                'completed',    // Selesai
                'overdue',      // Terlambat
                'cancelled'     // Dibatalkan
            ])->default('pending');
            
            // Reminder System
            $table->boolean('reminder_sent')->default(false); // Status reminder
            $table->timestamp('reminder_sent_at')->nullable(); // Tanggal reminder terakhir
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('role');
            $table->index('deadline_date');
            $table->index('is_completed');
            $table->index('status');
            $table->index('completed_by');
            $table->index(['episode_id', 'role']);
            $table->index(['deadline_date', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deadlines');
    }
};














