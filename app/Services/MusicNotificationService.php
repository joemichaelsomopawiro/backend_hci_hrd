<?php

namespace App\Services;

use App\Models\MusicNotification;
use App\Models\MusicRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MusicNotificationService
{
    /**
     * Send notification when music request is submitted
     */
    public function notifyRequestSubmitted(MusicRequest $musicRequest)
    {
        try {
            // Notify all Producers
            $producers = User::where('role', 'Producer')->get();
            
            foreach ($producers as $producer) {
                MusicNotification::create([
                    'user_id' => $producer->id,
                    'music_request_id' => $musicRequest->id,
                    'type' => 'request_submitted',
                    'title' => 'Music Request Baru',
                    'message' => "{$musicRequest->musicArranger->name} mengajukan request lagu '{$musicRequest->song->title}' untuk ditinjau.",
                ]);
            }

            Log::info('Music request submitted notification sent', [
                'music_request_id' => $musicRequest->id,
                'producers_notified' => $producers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send request submitted notification', [
                'music_request_id' => $musicRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification when music request is taken by producer
     */
    public function notifyRequestTaken(MusicRequest $musicRequest)
    {
        try {
            // Notify the music arranger
            MusicNotification::create([
                'user_id' => $musicRequest->music_arranger_id,
                'music_request_id' => $musicRequest->id,
                'type' => 'request_taken',
                'title' => 'Request Diambil',
                'message' => "Request lagu '{$musicRequest->song->title}' telah diambil oleh {$musicRequest->producer->name} untuk ditinjau.",
            ]);

            Log::info('Music request taken notification sent', [
                'music_request_id' => $musicRequest->id,
                'music_arranger_id' => $musicRequest->music_arranger_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send request taken notification', [
                'music_request_id' => $musicRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification when music request is approved
     */
    public function notifyRequestApproved(MusicRequest $musicRequest)
    {
        try {
            // Notify the music arranger
            MusicNotification::create([
                'user_id' => $musicRequest->music_arranger_id,
                'music_request_id' => $musicRequest->id,
                'type' => 'request_approved',
                'title' => 'Request Disetujui',
                'message' => "Request lagu '{$musicRequest->song->title}' telah disetujui oleh {$musicRequest->producer->name}.",
            ]);

            Log::info('Music request approved notification sent', [
                'music_request_id' => $musicRequest->id,
                'music_arranger_id' => $musicRequest->music_arranger_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send request approved notification', [
                'music_request_id' => $musicRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification when music request is rejected
     */
    public function notifyRequestRejected(MusicRequest $musicRequest)
    {
        try {
            // Notify the music arranger
            MusicNotification::create([
                'user_id' => $musicRequest->music_arranger_id,
                'music_request_id' => $musicRequest->id,
                'type' => 'request_rejected',
                'title' => 'Request Ditolak',
                'message' => "Request lagu '{$musicRequest->song->title}' ditolak oleh {$musicRequest->producer->name}. Catatan: {$musicRequest->producer_notes}",
            ]);

            Log::info('Music request rejected notification sent', [
                'music_request_id' => $musicRequest->id,
                'music_arranger_id' => $musicRequest->music_arranger_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send request rejected notification', [
                'music_request_id' => $musicRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get notifications for a user
     */
    public function getUserNotifications($userId, $limit = 10)
    {
        return MusicNotification::forUser($userId)
            ->with(['musicRequest.song', 'musicRequest.musicArranger', 'musicRequest.producer'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount($userId)
    {
        return MusicNotification::forUser($userId)->unread()->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId)
    {
        $notification = MusicNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId)
    {
        return MusicNotification::forUser($userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Get read status of a notification
     */
    public function getReadStatus($notificationId, $userId)
    {
        $notification = MusicNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        return $notification ? $notification->is_read : false;
    }
}


