<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Episodes table - 53 episodes per program (auto-generated)
     */
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->integer('episode_number'); // Nomor episode (1-53)
            $table->string('title');
            $table->text('description')->nullable();
            
            // Schedule Information
            $table->datetime('air_date'); // Tanggal & jam tayang
            $table->datetime('production_date')->nullable(); // Tanggal syuting
            $table->enum('format_type', ['weekly', 'quarterly'])->default('weekly');
            $table->integer('kwartal')->nullable(); // 1-4 (jika quarterly)
            $table->integer('pelajaran')->nullable(); // 1-14 (jika quarterly)
            
            // Episode Status
            $table->enum('status', [
                'planning',           // Sedang direncanakan
                'ready_to_produce',   // Siap diproduksi
                'in_production',      // Sedang produksi
                'post_production',    // Post produksi
                'ready_to_air',       // Siap tayang
                'aired',             // Sudah tayang
                'cancelled'          // Dibatalkan
            ])->default('planning');
            
            // Content Information
            $table->text('rundown')->nullable(); // Rundown episode
            $table->text('script')->nullable(); // Script
            $table->json('talent_data')->nullable(); // Data talent (host, narasumber)
            $table->string('location')->nullable(); // Lokasi syuting
            $table->text('notes')->nullable(); // Catatan tambahan
            $table->text('production_notes')->nullable(); // Catatan produksi
            
            // Workflow Information
            $table->enum('current_workflow_state', [
                'program_created',
                'episode_generated',
                'music_arrangement',
                'creative_work',
                'production_planning',
                'equipment_request',
                'shooting_recording',
                'editing',
                'quality_control',
                'broadcasting',
                'promotion',
                'completed'
            ])->default('program_created');
            
            $table->foreignId('assigned_to_role')->nullable(); // Role yang sedang bertanggung jawab
            $table->foreignId('assigned_to_user')->nullable()->constrained('users')->onDelete('set null'); // User yang sedang bertanggung jawab
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('program_id');
            $table->index('episode_number');
            $table->index('status');
            $table->index('air_date');
            $table->index('production_date');
            $table->index('current_workflow_state');
            $table->index('assigned_to_role');
            $table->index('assigned_to_user');
            $table->index(['program_id', 'episode_number']);
            $table->index(['status', 'air_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};














