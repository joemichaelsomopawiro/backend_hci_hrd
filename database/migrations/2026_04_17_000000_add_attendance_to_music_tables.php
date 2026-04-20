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
        if (Schema::hasTable('sound_engineer_recordings')) {
            Schema::table('sound_engineer_recordings', function (Blueprint $table) {
                if (!Schema::hasColumn('sound_engineer_recordings', 'crew_attendances')) {
                    $table->json('crew_attendances')->nullable()->after('review_notes');
                }
            });
        }

        if (Schema::hasTable('promotion_works')) {
            Schema::table('promotion_works', function (Blueprint $table) {
                if (!Schema::hasColumn('promotion_works', 'crew_attendances')) {
                    $table->json('crew_attendances')->nullable()->after('review_notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sound_engineer_recordings')) {
            Schema::table('sound_engineer_recordings', function (Blueprint $table) {
                $table->dropColumn('crew_attendances');
            });
        }

        if (Schema::hasTable('promotion_works')) {
            Schema::table('promotion_works', function (Blueprint $table) {
                $table->dropColumn('crew_attendances');
            });
        }
    }
};
