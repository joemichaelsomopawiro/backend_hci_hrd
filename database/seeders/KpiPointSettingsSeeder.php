<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KpiPointSetting;

class KpiPointSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = KpiPointSetting::getDefaults();

        foreach ($defaults as $setting) {
            KpiPointSetting::updateOrCreate(
                ['role' => $setting['role'], 'program_type' => $setting['program_type']],
                $setting
            );
        }

        $this->command->info('KPI point settings seeded successfully (' . count($defaults) . ' records).');
    }
}
