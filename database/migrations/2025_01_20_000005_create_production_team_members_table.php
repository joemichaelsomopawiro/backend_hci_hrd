<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Production Team Members table - Anggota tim dengan 6 role wajib
     */
    public function up(): void
    {
        Schema::create('production_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_team_id')->constrained('production_teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', [
                'kreatif',           // Kreatif
                'musik_arr',         // Musik Arranger
                'sound_eng',         // Sound Engineer
                'produksi',          // Produksi
                'editor',            // Editor
                'art_set_design'     // Art & Set Design
            ]);
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Unique constraint: satu user tidak boleh duplikat role dalam tim yang sama
            $table->unique(['production_team_id', 'user_id', 'role']);
            
            // Indexes
            $table->index('production_team_id');
            $table->index('user_id');
            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_team_members');
    }
};














