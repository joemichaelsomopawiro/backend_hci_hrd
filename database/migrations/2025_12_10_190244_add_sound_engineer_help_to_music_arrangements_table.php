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
            // Sound Engineer help fields
            $table->foreignId('sound_engineer_helper_id')->nullable()->constrained('users')->onDelete('set null')->after('reviewed_by');
            $table->text('sound_engineer_help_notes')->nullable()->after('sound_engineer_helper_id');
            $table->timestamp('sound_engineer_help_at')->nullable()->after('sound_engineer_help_notes');
            $table->boolean('needs_sound_engineer_help')->default(false)->after('sound_engineer_help_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_arrangements', function (Blueprint $table) {
            $table->dropForeign(['sound_engineer_helper_id']);
            $table->dropColumn([
                'sound_engineer_helper_id',
                'sound_engineer_help_notes',
                'sound_engineer_help_at',
                'needs_sound_engineer_help'
            ]);
        });
    }
};
