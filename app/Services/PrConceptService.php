<?php

namespace App\Services;

use App\Models\PrProgram;
use App\Models\PrProgramConcept;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PrConceptService
{
    /**
     * Create konsep program
     */
    public function createConcept(PrProgram $program, array $data, int $createdBy): PrProgramConcept
    {
        return DB::transaction(function () use ($program, $data, $createdBy) {
            // Update program status
            $program->update(['status' => 'concept_pending']);

            // Create concept
            $concept = PrProgramConcept::create([
                'program_id' => $program->id,
                'concept' => $data['concept'],
                'objectives' => $data['objectives'] ?? null,
                'target_audience' => $data['target_audience'] ?? null,
                'content_outline' => $data['content_outline'] ?? null,
                'format_description' => $data['format_description'] ?? null,
                'status' => 'pending_approval',
                'created_by' => $createdBy
            ]);

            return $concept;
        });
    }

    /**
     * Approve konsep
     */
    public function approveConcept(PrProgramConcept $concept, int $approvedBy, ?string $notes = null): PrProgramConcept
    {
        return DB::transaction(function () use ($concept, $approvedBy, $notes) {
            $concept->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'approval_notes' => $notes
            ]);

            // Update program status
            $concept->program->update(['status' => 'concept_approved']);

            return $concept->fresh();
        });
    }

    /**
     * Reject konsep
     */
    public function rejectConcept(PrProgramConcept $concept, int $rejectedBy, string $notes): PrProgramConcept
    {
        return DB::transaction(function () use ($concept, $rejectedBy, $notes) {
            $concept->update([
                'status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'rejection_notes' => $notes
            ]);

            // Update program status
            $concept->program->update(['status' => 'concept_rejected']);

            return $concept->fresh();
        });
    }

    /**
     * Get concepts untuk approval
     */
    public function getConceptsForApproval(?int $producerId = null)
    {
        $query = PrProgramConcept::with(['program', 'creator'])
            ->where('status', 'pending_approval');

        // TEAM-BASED FILTERING: Show concepts where user is assigned as Producer (either via producer_id OR crews)
        if ($producerId) {
            $query->whereHas('program', function ($q) use ($producerId) {
                $q->where('producer_id', $producerId)
                    ->orWhereHas('crews', function ($subQ) use ($producerId) {
                        $subQ->where('user_id', $producerId)
                            ->where('role', 'Producer');
                    });
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Update konsep (for Manager Program edit)
     */
    public function updateConcept(PrProgramConcept $concept, array $data): PrProgramConcept
    {
        $concept->update([
            'concept' => $data['concept'],
            'objectives' => $data['objectives'] ?? null,
            'target_audience' => $data['target_audience'] ?? null,
            'content_outline' => $data['content_outline'] ?? null,
            'format_description' => $data['format_description'] ?? null,
        ]);

        return $concept->fresh();
    }

    /**
     * Mark konsep as read by Producer
     */
    public function markAsRead(PrProgramConcept $concept, int $userId): PrProgramConcept
    {
        $concept->update([
            'read_by' => $userId,
            'read_at' => now()
        ]);

        return $concept->fresh();
    }
}
