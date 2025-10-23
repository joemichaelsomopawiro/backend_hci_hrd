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
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('modified_by_producer')->nullable()->after('approved_at');
            $table->timestamp('modified_at')->nullable()->after('modified_by_producer');
            
            $table->foreign('modified_by_producer')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropForeign(['modified_by_producer']);
            $table->dropColumn(['modified_by_producer', 'modified_at']);
        });
    }
};
