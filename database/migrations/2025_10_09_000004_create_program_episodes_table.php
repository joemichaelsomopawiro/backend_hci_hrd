<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Program Episodes - Setiap program regular fixed 53 episode (1 tahun = 53 minggu)
     * Auto-generated saat program dibuat dengan deadline otomatis per role
     */
    public function up(): void
    {
        Schema::create('program_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_regular_id')->constrained('program_regular')->onDelete('cascade');
            $table->integer('episode_number'); // 1-53
            $table->string('title')->nullable(); // Bisa dikosongkan dulu, nanti diisi saat produksi
            $table->text('description')->nullable();
            
            // Jadwal Tayang & Produksi
            $table->dateTime('air_date'); // Tanggal & jam tayang
            $table->date('production_date')->nullable(); // Tanggal syuting (dijadwalkan oleh Manager Broadcast)
            
            // Format Episode (Mingguan atau Kwartal)
            $table->string('format_type')->default('weekly'); // 'weekly' atau 'quarterly'
            $table->integer('kwartal')->nullable(); // 1-4 (jika format quarterly)
            $table->integer('pelajaran')->nullable(); // 1-14 (jika format quarterly)
            
            // Status Episode
            $table->enum('status', [
                'planning',           // Masih planning
                'ready_to_produce',   // Siap produksi (rundown approved)
                'in_production',      // Sedang syuting
                'post_production',    // Editing
                'ready_to_air',       // Siap tayang
                'aired',              // Sudah tayang
                'cancelled'           // Dibatalkan
            ])->default('planning');
            
            // Rundown & Script
            $table->text('rundown')->nullable(); // Rundown episode
            $table->text('script')->nullable(); // Script detail
            
            // Talent & Location
            $table->json('talent_data')->nullable(); // Host, narasumber, dll
            $table->string('location')->nullable(); // Lokasi syuting
            
            // Notes
            $table->text('notes')->nullable();
            $table->json('production_notes')->nullable();
            
            $table->timestamps();
            
            $table->unique(['program_regular_id', 'episode_number']);
            $table->index(['program_regular_id', 'status']);
            $table->index(['air_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_episodes');
    }
};

