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
            $table->boolean('arrangement_started')->default(false)->after('submission_status');
            $table->timestamp('arrangement_started_at')->nullable()->after('arrangement_started');
            $table->timestamp('arrangement_completed_at')->nullable()->after('arrangement_started_at');
            $table->string('arrangement_file_name')->nullable()->after('arrangement_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'arrangement_started',
                'arrangement_started_at',
                'arrangement_completed_at',
                'arrangement_file_name'
            ]);
        });
    }
};