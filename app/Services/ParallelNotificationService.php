<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\ProductionTeamMember;
use Illuminate\Support\Facades\DB;

class ParallelNotificationService
{
    /**
     * Send parallel notifications to multiple recipients
     * Bulk insert untuk performance optimization
     *
     * @param array $recipients Array of ['user_id' => int]
     * @param array $notificationData ['type', 'title', 'message', 'data']
     * @return int Number of notifications created
     */
    public static function notifyMultiple(array $recipients, array $notificationData): int
    {
        if (empty($recipients)) {
            return 0;
        }

        $notifications = [];
        $now = now();
        
        foreach ($recipients as $recipient) {
            if (!isset($recipient['user_id'])) {
                continue;
            }

            $notifications[] = [
                'user_id' => $recipient['user_id'],
                'type' => $notificationData['type'] ?? 'general',
                'title' => $notificationData['title'] ?? 'Notification',
                'message' => $notificationData['message'] ?? '',
                'data' => json_encode($notificationData['data'] ?? []),
                'read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        
        if (empty($notifications)) {
            return 0;
        }

        // Bulk insert for performance (lebih cepat untuk banyak notifikasi)
        DB::table('notifications')->insert($notifications);
        
        return count($notifications);
    }
    
    /**
     * Notify specific user roles
     * Filter by program/episode if provided
     *
     * @param array $roles Array of role names (e.g., ['Producer', 'Editor'])
     * @param array $notificationData Notification content
     * @param int|null $programId Optional program filter
     * @param int|null $episodeId Optional episode filter
     * @return int Number of notifications created
     */
    public static function notifyRoles(
        array $roles, 
        array $notificationData, 
        ?int $programId = null,
        ?int $episodeId = null
    ): int {
        $recipients = [];
        
        foreach ($roles as $role) {
            $query = User::where('role', $role);
            
            // Filter by program production team if provided
            if ($programId) {
                $query->whereHas('productionTeamMembers', function($q) use ($programId) {
                    $q->where('is_active', true)
                      ->whereHas('productionTeam', function($q2) use ($programId) {
                          $q2->whereHas('programs', function($q3) use ($programId) {
                              $q3->where('id', $programId);
                          });
                      });
                });
            }
            
            foreach ($query->get() as $user) {
                $recipients[] = ['user_id' => $user->id];
            }
        }
        
        return self::notifyMultiple($recipients, $notificationData);
    }
    
    /**
     * Notify production team members by their role in team
     * 
     * @param int $productionTeamId
     * @param array $teamRoles Array of team roles (e.g., ['kreatif', 'musik_arr'])
     * @param array $notificationData
     * @return int
     */
    public static function notifyTeamMembers(
        int $productionTeamId,
        array $teamRoles,
        array $notificationData
    ): int {
        $recipients = [];
        
        $members = ProductionTeamMember::where('production_team_id', $productionTeamId)
            ->where('is_active', true)
            ->whereIn('role', $teamRoles)
            ->with('user')
            ->get();
        
        foreach ($members as $member) {
            if ($member->user) {
                $recipients[] = ['user_id' => $member->user_id];
            }
        }
        
        return self::notifyMultiple($recipients, $notificationData);
    }
    
    /**
     * Notify all team members of a production team
     * 
     * @param int $productionTeamId
     * @param array $notificationData
     * @return int
     */
    public static function notifyEntireTeam(
        int $productionTeamId,
        array $notificationData
    ): int {
        $recipients = [];
        
        $members = ProductionTeamMember::where('production_team_id', $productionTeamId)
            ->where('is_active', true)
            ->get();
        
        foreach ($members as $member) {
            $recipients[] = ['user_id' => $member->user_id];
        }
        
        return self::notifyMultiple($recipients, $notificationData);
    }
    
    /**
     * Notify specific users by their IDs
     * 
     * @param array $userIds Array of user IDs
     * @param array $notificationData
     * @return int
     */
    public static function notifyUsers(
        array $userIds,
        array $notificationData
    ): int {
        $recipients = array_map(function($userId) {
            return ['user_id' => $userId];
        }, $userIds);
        
        return self::notifyMultiple($recipients, $notificationData);
    }
}
