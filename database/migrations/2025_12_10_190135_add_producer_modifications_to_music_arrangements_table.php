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
        Schema::table('music_arrangements', function (Blueprint $table) {
            // Original values from Music Arranger
            $table->string('original_song_title')->nullable()->after('song_title');
            $table->string('original_singer_name')->nullable()->after('singer_name');
            
            // Producer modifications
            $table->string('producer_modified_song_title')->nullable()->after('original_singer_name');
            $table->string('producer_modified_singer_name')->nullable()->after('producer_modified_song_title');
            $table->boolean('producer_modified')->default(false)->after('producer_modified_singer_name');
            $table->timestamp('producer_modified_at')->nullable()->after('producer_modified');
            
            // Optional: Link to songs table
            $table->foreignId('song_id')->nullable()->constrained('songs')->onDelete('set null')->after('episode_id');
            
            // Optional: Link to singers table (if exists) or users table
            $table->foreignId('singer_id')->nullable()->constrained('users')->onDelete('set null')->after('song_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_arrangements', function (Blueprint $table) {
            $table->dropForeign(['song_id']);
            $table->dropForeign(['singer_id']);
            $table->dropColumn([
                'original_song_title',
                'original_singer_name',
                'producer_modified_song_title',
                'producer_modified_singer_name',
                'producer_modified',
                'producer_modified_at',
                'song_id',
                'singer_id'
            ]);
        });
    }
};
