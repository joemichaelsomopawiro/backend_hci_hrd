<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Programs table - Main program management
     */
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', [
                'draft',              // Baru dibuat, belum submit
                'pending_approval',   // Menunggu approval Manager Broadcasting
                'approved',           // Sudah diapprove, siap produksi
                'in_production',      // Sedang dalam produksi
                'completed',          // Program selesai (53 episode sudah tayang)
                'cancelled',          // Program dibatalkan
                'rejected'            // Ditolak oleh Manager Broadcasting
            ])->default('draft');
            
            // Program Information
            $table->foreignId('manager_program_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('production_team_id')->nullable();
            $table->date('start_date'); // Tanggal mulai episode pertama
            $table->time('air_time'); // Jam tayang (contoh: 19:00)
            $table->integer('duration_minutes')->default(60); // Durasi program dalam menit
            $table->string('broadcast_channel')->nullable(); // Channel broadcast
            $table->bigInteger('target_views_per_episode')->nullable(); // Target views per episode
            
            // Workflow fields
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('manager_program_id');
            $table->index('production_team_id');
            $table->index('start_date');
            $table->index(['status', 'manager_program_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
