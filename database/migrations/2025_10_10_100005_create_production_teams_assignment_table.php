<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Production Teams Assignment - Tim yang ditugaskan untuk shooting/recording
     * Di-assign oleh Producer untuk setiap schedule
     */
    public function up(): void
    {
        Schema::create('production_teams_assignment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->foreignId('schedule_id')->nullable()->constrained('music_schedules')->onDelete('set null');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade'); // Producer
            
            // Team Type
            $table->enum('team_type', [
                'shooting',     // Tim syuting
                'setting',      // Tim setting (art & set)
                'recording'     // Tim rekam vokal
            ]);
            
            // Team Info
            $table->string('team_name')->nullable(); // Nama tim (optional)
            $table->text('team_notes')->nullable();
            
            // Status
            $table->enum('status', [
                'assigned',         // Sudah ditugaskan
                'confirmed',        // Dikonfirmasi
                'in_progress',      // Sedang bertugas
                'completed',        // Selesai tugas
                'cancelled'         // Dibatalkan
            ])->default('assigned');
            
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['music_submission_id', 'team_type', 'status']);
            $table->index(['schedule_id', 'team_type']);
        });
        
        // Members of each team
        Schema::create('production_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('production_teams_assignment')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Member Role in this team
            $table->enum('role', [
                'leader',       // Team leader
                'crew',         // Crew member
                'talent',       // Talent (penyanyi, actor, dll)
                'support'       // Support staff
            ])->default('crew');
            
            $table->text('role_notes')->nullable();
            
            // Status
            $table->enum('status', [
                'assigned',     // Ditugaskan
                'confirmed',    // Konfirmasi hadir
                'present',      // Hadir
                'absent',       // Tidak hadir
                'completed'     // Selesai
            ])->default('assigned');
            
            $table->timestamps();
            
            $table->unique(['assignment_id', 'user_id'], 'unique_assignment_user');
            $table->index(['assignment_id', 'role']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_team_members');
        Schema::dropIfExists('production_teams_assignment');
    }
};

