<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Program Schedule Options - Opsi jadwal tayang dari Manager Program ke Manager Broadcasting
     */
    public function up(): void
    {
        Schema::create('program_schedule_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->onDelete('cascade'); // Optional: untuk opsi per episode
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade'); // Manager Program
            
            // Schedule Options (bisa multiple options)
            $table->json('schedule_options'); // Array of options: [{date: '2025-01-30 20:00', notes: 'Prime time'}, ...]
            
            // Platform untuk jadwal
            $table->enum('platform', [
                'tv',
                'youtube',
                'website',
                'all' // Semua platform
            ])->default('all');
            
            // Status
            $table->enum('status', [
                'pending',      // Menunggu review Manager Broadcasting
                'reviewing',    // Sedang direview
                'approved',     // Diterima (Manager Broadcasting pilih salah satu opsi)
                'rejected',     // Ditolak
                'expired'       // Kadaluarsa
            ])->default('pending');
            
            // Selected option (jika sudah dipilih oleh Manager Broadcasting)
            $table->integer('selected_option_index')->nullable(); // Index dari schedule_options yang dipilih
            $table->datetime('selected_schedule_date')->nullable(); // Jadwal yang dipilih
            
            // Review info
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null'); // Manager Broadcasting
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            // Notes
            $table->text('submission_notes')->nullable(); // Catatan dari Manager Program
            $table->text('rejection_reason')->nullable(); // Alasan jika ditolak
            
            $table->timestamps();
            
            // Indexes
            $table->index(['program_id', 'status']);
            $table->index(['episode_id', 'status']);
            $table->index('submitted_by');
            $table->index('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_schedule_options');
    }
};























