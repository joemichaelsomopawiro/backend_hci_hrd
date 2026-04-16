<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Episode;
use App\Models\Program;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Cari semua program yang memiliki data aransemen musik tapi belum ditandai 'musik'
        $programs = Program::whereHas('episodes.musicArrangements')->get();
        foreach ($programs as $program) {
            $program->category = 'musik';
            $program->save();
        }

        // 2. Refresh SEMUA episode yang berkaitan dengan musik
        $musicEpisodes = Episode::whereHas('musicArrangements')
            ->orWhereHas('program', function($q) {
                $q->where('category', 'musik');
            })->get();

        foreach ($musicEpisodes as $episode) {
            // Re-generate menggunakan Engine Smart yang baru saya update di Episode.php
            $episode->deadlines()->delete();
            $episode->generateDeadlines();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
