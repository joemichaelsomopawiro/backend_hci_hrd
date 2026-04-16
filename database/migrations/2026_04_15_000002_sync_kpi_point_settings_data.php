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
        $defaults = KpiPointSetting::getDefaults();
        
        foreach ($defaults as $setting) {
            KpiPointSetting::updateOrCreate(
                [
                    'role' => $setting['role'],
                    'program_type' => $setting['program_type']
                ],
                [
                    'points_on_time' => $setting['points_on_time'],
                    'points_late' => $setting['points_late'],
                    'points_not_done' => $setting['points_not_done'],
                    'quality_min' => $setting['quality_min'],
                    'quality_max' => $setting['quality_max']
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to revert without knowing previous values
    }
};
