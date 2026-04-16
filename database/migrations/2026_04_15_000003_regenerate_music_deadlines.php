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
        // Cari program yang kategorinya Musik
        $musicPrograms = Program::whereIn('category', ['musik', 'music'])->pluck('id');

        // Cari episode musik yang belum selesai (bukan aired/completed)
        $episodes = Episode::whereIn('program_id', $musicPrograms)
            ->whereNotIn('status', ['aired', 'completed'])
            ->get();

        foreach ($episodes as $episode) {
            // Panggil fungsi generateDeadlines() yang sudah kita update
            // Ini akan menghapus deadline lama (musik_arr) dan membuat 6 deadline baru
            $episode->generateDeadlines();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert needed
    }
};
