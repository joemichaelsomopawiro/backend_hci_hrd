<?php

namespace App\Services;

use App\Models\ProgramEpisode;
use App\Models\User;
use App\Models\ProgramNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Workflow Notification Service
 * 
 * Centralized service untuk mengirim notifikasi workflow antar divisi
 * Supports: In-app notifications, Email (future), SMS (future), Push (future)
 */
class WorkflowNotificationService
{
    /**
     * Send notification when script submitted (Creative → Producer)
     */
    public function notifyScriptSubmitted(ProgramEpisode $episode): bool
    {
        try {
            $producer = $episode->programRegular->productionTeam->producer;
            
            return $this->createNotification([
                'user_id' => $producer->id,
                'program_id' => $episode->program_regular_id,
                'episode_id' => $episode->id,
                'type' => 'script_submitted',
                'title' => 'New Script Submitted',
                'message' => "Script untuk episode {$episode->episode_number} telah disubmit oleh Creative dan menunggu review Anda.",
                'action_url' => "/workflow/producer/episodes/{$episode->id}/review",
                'priority' => 'normal'
            ]);
        } catch (\Exception $e) {
            Log::error('Error notifying script submitted: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when rundown approved (Producer → Produksi)
     */
    public function notifyRundownApproved(ProgramEpisode $episode): bool
    {
        try {
            // Notify all production team members
            $productionMembers = $episode->programRegular->productionTeam->members()
                ->where('role', 'production')
                ->where('is_active', true)
                ->get();

            foreach ($productionMembers as $member) {
                $this->createNotification([
                    'user_id' => $member->user_id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'rundown_approved',
                    'title' => 'Rundown Approved - Ready for Production',
                    'message' => "Rundown episode {$episode->episode_number} telah diapprove. Silakan prepare untuk shooting.",
                    'action_url' => "/workflow/produksi/episodes/{$episode->id}",
                    'priority' => 'high'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying rundown approved: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when rundown rejected (Producer → Creative)
     */
    public function notifyRundownRejected(ProgramEpisode $episode): bool
    {
        try {
            $creativeMembers = $episode->programRegular->productionTeam->members()
                ->where('role', 'creative')
                ->where('is_active', true)
                ->get();

            foreach ($creativeMembers as $member) {
                $this->createNotification([
                    'user_id' => $member->user_id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'rundown_rejected',
                    'title' => 'Rundown Needs Revision',
                    'message' => "Rundown episode {$episode->episode_number} membutuhkan revisi. Silakan check feedback dari Producer.",
                    'action_url' => "/workflow/creative/episodes/{$episode->id}",
                    'priority' => 'high'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying rundown rejected: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when shooting completed (Produksi → Editor)
     */
    public function notifyShootingCompleted(ProgramEpisode $episode): bool
    {
        try {
            $editorMembers = $episode->programRegular->productionTeam->members()
                ->where('role', 'editor')
                ->where('is_active', true)
                ->get();

            foreach ($editorMembers as $member) {
                $this->createNotification([
                    'user_id' => $member->user_id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'shooting_completed',
                    'title' => 'New Editing Task',
                    'message' => "Shooting episode {$episode->episode_number} selesai. Raw files sudah tersedia untuk editing.",
                    'action_url' => "/editor/episodes/{$episode->id}",
                    'priority' => 'normal',
                    'deadline' => $episode->deadlines()->where('role', 'editor')->first()->deadline_date ?? null
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying shooting completed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when editing completed (Editor → QC)
     */
    public function notifyEditingCompleted(ProgramEpisode $episode): bool
    {
        try {
            // Notify QC team (usually Producer)
            $producer = $episode->programRegular->productionTeam->producer;
            
            return $this->createNotification([
                'user_id' => $producer->id,
                'program_id' => $episode->program_regular_id,
                'episode_id' => $episode->id,
                'type' => 'editing_completed',
                'title' => 'Episode Ready for QC',
                'message' => "Editing episode {$episode->episode_number} selesai. Final file sudah tersedia untuk QC review.",
                'action_url' => "/qc/episodes/{$episode->id}",
                'priority' => 'high'
            ]);
        } catch (\Exception $e) {
            Log::error('Error notifying editing completed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when QC approved (QC → Broadcasting)
     */
    public function notifyQCApproved(ProgramEpisode $episode): bool
    {
        try {
            // Notify Broadcasting team (specific role or all broadcasting staff)
            $broadcastingUsers = User::where('role', 'Broadcasting')->where('is_active', true)->get();

            foreach ($broadcastingUsers as $user) {
                $this->createNotification([
                    'user_id' => $user->id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'qc_approved',
                    'title' => 'Episode Ready for Broadcasting',
                    'message' => "Episode {$episode->episode_number} passed QC. Silakan lanjutkan ke upload YouTube & Website.",
                    'action_url' => "/broadcasting/episodes/{$episode->id}",
                    'priority' => 'high',
                    'deadline' => $episode->air_date
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying QC approved: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when QC needs revision (QC → Editor)
     */
    public function notifyQCRevisionNeeded(ProgramEpisode $episode): bool
    {
        try {
            // Notify the editor who worked on this episode
            $editorId = $episode->editing_completed_by;
            
            if ($editorId) {
                return $this->createNotification([
                    'user_id' => $editorId,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'qc_revision_needed',
                    'title' => 'QC Revision Required',
                    'message' => "Episode {$episode->episode_number} membutuhkan revisi. Silakan check feedback dari QC.",
                    'action_url' => "/editor/episodes/{$episode->id}/revision",
                    'priority' => 'urgent'
                ]);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error notifying QC revision: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when episode aired (Broadcasting → Manager Distribusi)
     */
    public function notifyEpisodeAired(ProgramEpisode $episode): bool
    {
        try {
            $distribusiUsers = User::where('role', 'Manager Distribusi')->where('is_active', true)->get();

            foreach ($distribusiUsers as $user) {
                $this->createNotification([
                    'user_id' => $user->id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'episode_aired',
                    'title' => 'New Episode Aired',
                    'message' => "Episode {$episode->episode_number} telah tayang. Link: {$episode->youtube_url}",
                    'action_url' => "/distribusi/episodes/{$episode->id}/performance",
                    'priority' => 'normal'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying episode aired: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification for deadline reminder (1 day before)
     */
    public function notifyDeadlineReminder(ProgramEpisode $episode, string $role): bool
    {
        try {
            $members = $episode->programRegular->productionTeam->members()
                ->where('role', $role)
                ->where('is_active', true)
                ->get();

            $deadline = $episode->deadlines()->where('role', $role)->first();

            foreach ($members as $member) {
                $this->createNotification([
                    'user_id' => $member->user_id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'deadline_reminder',
                    'title' => 'Deadline Reminder',
                    'message' => "Deadline untuk episode {$episode->episode_number} ({$role}) adalah besok!",
                    'action_url' => "/workflow/episodes/{$episode->id}",
                    'priority' => 'high',
                    'deadline' => $deadline->deadline_date ?? null
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying deadline reminder: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification for overdue deadline
     */
    public function notifyDeadlineOverdue(ProgramEpisode $episode, string $role): bool
    {
        try {
            $members = $episode->programRegular->productionTeam->members()
                ->where('role', $role)
                ->where('is_active', true)
                ->get();

            // Also notify Manager Program
            $manager = $episode->programRegular->managerProgram;

            foreach ($members as $member) {
                $this->createNotification([
                    'user_id' => $member->user_id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'deadline_overdue',
                    'title' => 'URGENT: Deadline Overdue',
                    'message' => "Deadline untuk episode {$episode->episode_number} ({$role}) sudah terlewat!",
                    'action_url' => "/workflow/episodes/{$episode->id}",
                    'priority' => 'urgent'
                ]);
            }

            // Notify manager
            if ($manager) {
                $this->createNotification([
                    'user_id' => $manager->id,
                    'program_id' => $episode->program_regular_id,
                    'episode_id' => $episode->id,
                    'type' => 'deadline_overdue_alert',
                    'title' => 'Team Deadline Overdue',
                    'message' => "Deadline {$role} untuk episode {$episode->episode_number} telah terlewat.",
                    'action_url' => "/manager/programs/{$episode->program_regular_id}/dashboard",
                    'priority' => 'urgent'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notifying deadline overdue: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create notification record in database
     */
    private function createNotification(array $data): bool
    {
        try {
            ProgramNotification::create([
                'user_id' => $data['user_id'],
                'program_id' => $data['program_id'] ?? null,
                'episode_id' => $data['episode_id'] ?? null,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'action_url' => $data['action_url'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'deadline' => $data['deadline'] ?? null,
                'is_read' => false,
                'created_at' => now()
            ]);

            // TODO: Send email notification if user has email notifications enabled
            // TODO: Send push notification if user has push notifications enabled
            // TODO: Send SMS if urgent and user has SMS notifications enabled

            return true;
        } catch (\Exception $e) {
            Log::error('Error creating notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications(int $userId, ?string $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = ProgramNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): bool
    {
        try {
            $notification = ProgramNotification::find($notificationId);
            if ($notification) {
                $notification->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): bool
    {
        try {
            ProgramNotification::where('user_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return false;
        }
    }
}

