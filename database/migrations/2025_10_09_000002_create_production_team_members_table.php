<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Production Team Members menyimpan anggota tim dengan role spesifik.
     * 6 Role bawahan yang WAJIB ada di setiap tim:
     * - kreatif
     * - musik_arr (Musik Arranger)
     * - sound_eng (Sound Engineer)
     * - produksi
     * - editor
     * - art_set_design (Art & Set Design)
     */
    public function up(): void
    {
        // Check if table already exists
        if (!Schema::hasTable('production_team_members')) {
            Schema::create('production_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_team_id')->constrained('production_teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', [
                'kreatif',
                'musik_arr',
                'sound_eng',
                'produksi',
                'editor',
                'art_set_design'
            ]);
            $table->boolean('is_active')->default(true);
            $table->date('joined_at')->default(now());
            $table->date('left_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Satu user bisa punya multiple roles dalam tim yang sama, tapi tidak boleh duplikat role
            $table->unique(['production_team_id', 'user_id', 'role'], 'unique_team_user_role');
            
            $table->index(['production_team_id', 'role', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_team_members');
    }
};

