<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('music_arrangements', function (Blueprint $row) {
            if (!Schema::hasColumn('music_arrangements', 'song_approved_at')) {
                $row->timestamp('song_approved_at')->nullable()->after('producer_modified_at');
            }
            if (!Schema::hasColumn('music_arrangements', 'arrangement_submitted_at')) {
                $row->timestamp('arrangement_submitted_at')->nullable()->after('submitted_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_arrangements', function (Blueprint $row) {
            $row->dropColumn(['song_approved_at', 'arrangement_submitted_at']);
        });
    }
};
