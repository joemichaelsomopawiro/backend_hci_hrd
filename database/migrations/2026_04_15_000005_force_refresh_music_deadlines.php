<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Episode;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ambil semua episode dari program kategori 'musik'
        $musicEpisodes = Episode::whereHas('program', function($q) {
            $q->where('category', 'musik');
        })->get();

        Log::info("Refresing deadlines for " . $musicEpisodes->count() . " music episodes...");

        foreach ($musicEpisodes as $episode) {
            try {
                // Picu fungsi generateDeadlines untuk membuat role baru (music_arr_song, dsb)
                // dan menghapus role lama (ghost roles)
                $episode->generateDeadlines();
                
                // Pastikan PJ (Assigned User) juga disinkronkan kembali
                $episode->syncTeamAssignments();
            } catch (\Exception $e) {
                Log::error("Failed to refresh episode {$episode->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed for data refresh
    }
};
