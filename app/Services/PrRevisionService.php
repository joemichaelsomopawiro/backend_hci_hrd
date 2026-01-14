<?php

namespace App\Services;

use App\Models\PrProgram;
use App\Models\PrProgramRevision;
use Illuminate\Support\Facades\DB;

class PrRevisionService
{
    /**
     * Request revisi
     */
    public function requestRevision(PrProgram $program, array $data, int $requestedBy): PrProgramRevision
    {
        return DB::transaction(function () use ($program, $data, $requestedBy) {
            // Get current data snapshot
            $beforeData = [
                'status' => $program->status,
                'name' => $program->name,
                'description' => $program->description
            ];

            // Create revision
            $revision = PrProgramRevision::create([
                'program_id' => $program->id,
                'revision_type' => $data['revision_type'],
                'before_data' => $beforeData,
                'after_data' => $data['after_data'] ?? null,
                'revision_reason' => $data['revision_reason'],
                'status' => 'pending',
                'requested_by' => $requestedBy
            ]);

            return $revision;
        });
    }

    /**
     * Approve revisi
     */
    public function approveRevision(PrProgramRevision $revision, int $reviewedBy, ?string $notes = null): PrProgramRevision
    {
        return DB::transaction(function () use ($revision, $reviewedBy, $notes) {
            $revision->update([
                'status' => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'review_notes' => $notes
            ]);

            // Apply revision changes jika ada after_data
            if ($revision->after_data) {
                $program = $revision->program;
                $program->update($revision->after_data);
            }

            return $revision->fresh();
        });
    }

    /**
     * Reject revisi
     */
    public function rejectRevision(PrProgramRevision $revision, int $reviewedBy, string $notes): PrProgramRevision
    {
        $revision->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_notes' => $notes
        ]);

        return $revision->fresh();
    }

    /**
     * Get revision history
     */
    public function getRevisionHistory(PrProgram $program, ?string $revisionType = null)
    {
        $query = $program->revisions()->with(['requester', 'reviewer']);

        if ($revisionType) {
            $query->where('revision_type', $revisionType);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
