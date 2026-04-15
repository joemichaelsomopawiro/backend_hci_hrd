<?php

namespace App\Observers;

use App\Models\Episode;
use App\Models\Deadline;
use App\Constants\WorkflowStep;
use Carbon\Carbon;

class EpisodeObserver
{
    /**
     * Handle the Episode "updated" event.
     * Otomatis mengupdate semua deadline jika air_date berubah.
     */
    public function updated(Episode $episode): void
    {
        // Hanya proses jika air_date berubah
        if ($episode->wasChanged('air_date')) {
            $newAirDate = $episode->air_date;
            $programCategory = $episode->program->category ?? 'regular';

            // Ambil semua deadline yang digenerate otomatis untuk episode ini
            $deadlines = Deadline::where('episode_id', $episode->id)
                ->where('auto_generated', true)
                ->get();

            foreach ($deadlines as $deadline) {
                $role = $deadline->role;
                
                // Hitung ulang tanggal deadline berdasarkan air_date baru dan kategori program
                $daysBefore = WorkflowStep::getDeadlineDaysForRole($role, $programCategory);
                
                $newDeadlineDate = Carbon::parse($newAirDate)->subDays($daysBefore);
                
                // Update deadline_date
                $deadline->update([
                    'deadline_date' => $newDeadlineDate,
                    'description' => $deadline->description . ' (Auto-adjusted due to air_date change)'
                ]);
            }
        }
    }
}
