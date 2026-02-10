<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PrProgram;
use App\Models\PrProgramConcept;
use App\Models\PrEpisode;
use App\Models\User;

class PrNotificationService
{
    /**
     * Notify Producer tentang konsep baru
     */
    public function notifyConceptCreated(PrProgramConcept $concept): void
    {
        $producers = User::where('role', 'Producer')->get();

        foreach ($producers as $producer) {
            Notification::create([
                'user_id' => $producer->id,
                'type' => 'program_regular_concept_created',
                'title' => 'Konsep Program Baru',
                'message' => "Konsep program '{$concept->program->name}' menunggu approval Anda",
                'data' => [
                    'program_id' => $concept->program_id,
                    'concept_id' => $concept->id,
                    'program_name' => $concept->program->name
                ],
                'related_type' => 'PrProgramConcept',
                'related_id' => $concept->id,
                'priority' => 'high',
                'status' => 'unread'
            ]);
        }
    }

    /**
     * Notify Manager Program tentang approval/rejection konsep
     */
    public function notifyConceptReviewed(PrProgramConcept $concept, string $action): void
    {
        $manager = $concept->program->managerProgram;

        Notification::create([
            'user_id' => $manager->id,
            'type' => 'program_regular_concept_reviewed',
            'title' => "Konsep Program {$action}",
            'message' => "Konsep program '{$concept->program->name}' telah {$action}",
            'data' => [
                'program_id' => $concept->program_id,
                'concept_id' => $concept->id,
                'action' => $action
            ],
            'related_type' => 'PrProgramConcept',
            'related_id' => $concept->id,
            'priority' => 'normal',
            'status' => 'unread'
        ]);
    }

    /**
     * Notify Manager Program tentang program yang disubmit
     */
    public function notifyProgramSubmitted(PrProgram $program): void
    {
        $manager = $program->managerProgram;

        Notification::create([
            'user_id' => $manager->id,
            'type' => 'program_regular_submitted',
            'title' => 'Program Menunggu Approval',
            'message' => "Program '{$program->name}' telah disubmit dan menunggu approval Anda",
            'data' => [
                'program_id' => $program->id,
                'program_name' => $program->name
            ],
            'related_type' => 'PrProgram',
            'related_id' => $program->id,
            'priority' => 'high',
            'status' => 'unread'
        ]);
    }

    /**
     * Notify Producer tentang approval/rejection program
     */
    public function notifyProgramReviewed(PrProgram $program, string $action): void
    {
        if ($program->producer) {
            Notification::create([
                'user_id' => $program->producer->id,
                'type' => 'program_regular_reviewed',
                'title' => "Program {$action}",
                'message' => "Program '{$program->name}' telah {$action} oleh Manager Program",
                'data' => [
                    'program_id' => $program->id,
                    'action' => $action
                ],
                'related_type' => 'PrProgram',
                'related_id' => $program->id,
                'priority' => 'normal',
                'status' => 'unread'
            ]);
        }
    }

    /**
     * Notify Manager Distribusi tentang program yang disubmit
     */
    public function notifyProgramSubmittedToDistribusi(PrProgram $program): void
    {
        $distribusiManagers = User::where('role', 'Manager Distribusi')->get();

        foreach ($distribusiManagers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'program_regular_submitted_distribusi',
                'title' => 'Program Menunggu Distribusi',
                'message' => "Program '{$program->name}' telah disubmit dan menunggu verifikasi distribusi",
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name
                ],
                'related_type' => 'PrProgram',
                'related_id' => $program->id,
                'priority' => 'high',
                'status' => 'unread'
            ]);
        }
    }

    /**
     * Notify tentang revisi
     */
    public function notifyRevisionRequested(PrProgram $program, string $revisionType, int $requestedBy): void
    {
        // Notify Manager Program
        $manager = $program->managerProgram;

        Notification::create([
            'user_id' => $manager->id,
            'type' => 'program_regular_revision_requested',
            'title' => 'Revisi Program Diminta',
            'message' => "Revisi {$revisionType} untuk program '{$program->name}' telah diminta",
            'data' => [
                'program_id' => $program->id,
                'revision_type' => $revisionType
            ],
            'related_type' => 'PrProgram',
            'related_id' => $program->id,
            'priority' => 'normal',
            'status' => 'unread'
        ]);
    }

    /**
     * Notify Producer tentang Creative Work yang disubmit
     */
    public function notifyCreativeWorkSubmitted(\App\Models\PrCreativeWork $work): void
    {
        $program = $work->episode->program;

        // 1. Notify Assigned Producer
        $producersToNotify = collect();

        if ($program->producer) {
            $producersToNotify->push($program->producer);
        }

        // 2. Notify other crew members with Producer role
        $crewProducers = \App\Models\PrProgramCrew::where('program_id', $program->id)
            ->where('role', 'Producer')
            ->with('user')
            ->get()
            ->pluck('user');

        $producersToNotify = $producersToNotify->merge($crewProducers)->unique('id');

        foreach ($producersToNotify as $producer) {
            Notification::create([
                'user_id' => $producer->id,
                'type' => 'pr_creative_work_submitted',
                'title' => 'Creative Work Submitted',
                'message' => "Creative work for PR Episode {$work->episode->episode_number} has been submitted for review.",
                'data' => [
                    'creative_work_id' => $work->id,
                    'pr_episode_id' => $work->pr_episode_id,
                    'pr_program_id' => $program->id,
                    'program_name' => $program->name,
                    'episode_number' => $work->episode->episode_number
                ],
                'related_type' => 'PrCreativeWork',
                'related_id' => $work->id,
                'priority' => 'high',
                'status' => 'unread'
            ]);
        }
    }
}
