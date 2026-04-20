<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\KpiPointSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Force update point settings for Music Arranger roles to match user requirement
        KpiPointSetting::updateOrCreate(
            ['role' => 'musik_arr_lagu', 'program_type' => 'musik'],
            [
                'points_on_time' => 10,
                'points_late' => 2,
                'points_not_done' => -5,
                'quality_min' => 1,
                'quality_max' => 5
            ]
        );

        KpiPointSetting::updateOrCreate(
            ['role' => 'musik_arr', 'program_type' => 'musik'],
            [
                'points_on_time' => 10,
                'points_late' => 2,
                'points_not_done' => -5,
                'quality_min' => 1,
                'quality_max' => 5
            ]
        );

        KpiPointSetting::updateOrCreate(
            ['role' => 'sound_eng', 'program_type' => 'musik'],
            [
                'points_on_time' => 10,
                'points_late' => 2,
                'points_not_done' => -5,
                'quality_min' => 1,
                'quality_max' => 5
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
