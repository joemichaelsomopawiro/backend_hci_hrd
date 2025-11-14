<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Program Regular adalah program yang tayang mingguan (53 episode per tahun).
     * Setiap program dikerjakan oleh 1 Production Team.
     */
    public function up(): void
    {
        // Check if table already exists
        if (!Schema::hasTable('program_regular')) {
            Schema::create('program_regular', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('production_team_id')->constrained('production_teams')->onDelete('restrict'); // Tidak bisa hapus tim jika ada program aktif
            $table->foreignId('manager_program_id')->constrained('users')->onDelete('cascade'); // Manager Program yang membuat
            
            // Informasi Program
            $table->date('start_date'); // Tanggal mulai episode pertama
            $table->time('air_time'); // Jam tayang (contoh: 19:00)
            $table->integer('duration_minutes')->default(60); // Durasi program dalam menit
            $table->string('broadcast_channel')->nullable(); // Channel broadcast
            
            // Status Program
            $table->enum('status', [
                'draft',              // Baru dibuat, belum submit
                'pending_approval',   // Menunggu approval Manager Broadcasting
                'approved',           // Sudah diapprove, siap produksi
                'in_production',      // Sedang dalam produksi
                'completed',          // Program selesai (53 episode sudah tayang)
                'cancelled',          // Program dibatalkan
                'rejected'            // Ditolak oleh Manager Broadcasting
            ])->default('draft');
            
            // Target Views (Tarik data mingguan dari analitik)
            $table->bigInteger('target_views_per_episode')->nullable();
            
            // Workflow fields
            $table->text('submission_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->text('approval_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->text('rejection_notes')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['production_team_id', 'status']);
            $table->index(['manager_program_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_regular');
    }
};

