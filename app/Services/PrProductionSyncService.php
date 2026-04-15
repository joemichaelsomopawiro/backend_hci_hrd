<?php

namespace App\Services;

use App\Models\PrProduksiWork;
use App\Models\PrEpisodeCrew;
use App\Models\EquipmentLoan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrProductionSyncService
{
    /**
     * Synchronize all production data from a primary work to its bundled siblings.
     */
    public function syncBundledWorks(PrProduksiWork $primaryWork): void
    {
        $loan = $primaryWork->equipmentLoans()
            ->whereIn('status', ['pending', 'approved', 'active', 'return_requested', 'returned', 'completed'])
            ->first();

        if (!$loan) {
            return;
        }

        // Find all sibling works tied to the same loan
        $siblingWorks = $loan->produksiWorks()
            ->where('pr_produksi_works.id', '!=', $primaryWork->id)
            ->get();

        foreach ($siblingWorks as $sibling) {
            $this->syncWorkAttributes($primaryWork, $sibling);
            $this->syncCrews($primaryWork, $sibling);
        }
    }

    /**
     * Sync specific work attributes (Files, Notes, Status).
     */
    private function syncWorkAttributes(PrProduksiWork $primary, PrProduksiWork $target): void
    {
        $target->update([
            'shooting_file_links' => $primary->shooting_file_links,
            'shooting_files' => $primary->shooting_files,
            'shooting_notes' => $primary->shooting_notes,
            'status' => $primary->status,
            'completed_at' => $primary->completed_at,
            'completed_by' => $primary->completed_by,
            'crew_attendances' => $primary->crew_attendances,
        ]);
        
        // Also ensure next-step (Step 6 Editor Work) is ready for siblings if primary is completed
        if ($primary->status === 'completed') {
            app(\App\Http\Controllers\Api\Pr\PrProduksiController::class)
                ->ensureEditorWorkReady($target->pr_episode_id, $primary->completed_by);
            
            // Sync workflow step completion
            $workflowStep = \App\Models\PrWorkflowStep::where('pr_episode_id', $target->pr_episode_id)
                ->where('step_number', 5)
                ->first();

            if ($workflowStep && !$workflowStep->is_completed) {
                $workflowStep->markAsCompleted($primary->completed_by, 'Auto-completed via bundled sync');
            }

            // Also sync the KPI-relevant PrEpisodeWorkflowProgress model
            $workflowProgress = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $target->pr_episode_id)
                ->where('workflow_step', 5)
                ->first();

            if ($workflowProgress && $workflowProgress->status !== 'completed') {
                $workflowProgress->update([
                    'status' => 'completed',
                    'completed_at' => $primary->completed_at ?? now(),
                    'assigned_user_id' => $primary->completed_by
                ]);
            }
        }
    }

    /**
     * Sync crew assignments between episodes.
     */
    public function syncCrews(PrProduksiWork $primary, PrProduksiWork $target): void
    {
        $primaryEpisodeId = $primary->pr_episode_id;
        $targetEpisodeId = $target->pr_episode_id;

        $primaryCrews = PrEpisodeCrew::where('episode_id', $primaryEpisodeId)->get();

        // Standardize: Delete target crews and replace with primary crews
        // Use a transaction for safety
        DB::transaction(function () use ($primaryCrews, $targetEpisodeId) {
            PrEpisodeCrew::where('episode_id', $targetEpisodeId)->delete();

            foreach ($primaryCrews as $crew) {
                PrEpisodeCrew::create([
                    'episode_id' => $targetEpisodeId,
                    'user_id' => $crew->user_id,
                    'role' => $crew->role,
                    'is_coordinator' => $crew->is_coordinator,
                ]);
            }
        });
    }
}
