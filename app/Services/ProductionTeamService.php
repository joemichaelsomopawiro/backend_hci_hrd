<?php

namespace App\Services;

use App\Models\ProductionTeam;
use App\Models\ProductionTeamMember;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class ProductionTeamService
{
    /**
     * Create production team
     */
    public function createTeam(array $data): ProductionTeam
    {
        return DB::transaction(function () use ($data) {
            $team = ProductionTeam::create($data);
            
            // Send notification to producer
            Notification::create([
                'user_id' => $team->producer_id,
                'type' => 'team_created',
                'title' => 'Production Team Created',
                'message' => "Production team '{$team->name}' has been created successfully.",
                'priority' => 'normal'
            ]);
            
            return $team;
        });
    }

    /**
     * Add member to team
     */
    public function addMember(ProductionTeam $team, int $userId, string $role, ?string $notes = null): ProductionTeamMember
    {
        return DB::transaction(function () use ($team, $userId, $role, $notes) {
            // Check if user already has this role in the team
            $existingMember = $team->members()
                ->where('user_id', $userId)
                ->where('role', $role)
                ->where('is_active', true)
                ->first();
                
            if ($existingMember) {
                throw new \Exception("User already has role {$role} in this team");
            }
            
            $member = $team->addMember($userId, $role, $notes);
            
            // Send notification to user
            Notification::create([
                'user_id' => $userId,
                'type' => 'team_member_added',
                'title' => 'Added to Production Team',
                'message' => "You have been added to production team '{$team->name}' as {$role}.",
                'priority' => 'normal'
            ]);
            
            return $member;
        });
    }

    /**
     * Remove member from team
     */
    public function removeMember(ProductionTeam $team, int $userId, string $role): bool
    {
        return DB::transaction(function () use ($team, $userId, $role) {
            $member = $team->members()
                ->where('user_id', $userId)
                ->where('role', $role)
                ->where('is_active', true)
                ->first();
                
            if (!$member) {
                throw new \Exception("Member not found");
            }
            
            // Check if this is the last member for this role
            $roleCount = $team->members()
                ->where('role', $role)
                ->where('is_active', true)
                ->count();
                
            if ($roleCount <= 1) {
                throw new \Exception("Cannot remove the last member for role: {$role}");
            }
            
            $result = $team->removeMember($userId, $role);
            
            if ($result) {
                // Send notification to user
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'team_member_removed',
                    'title' => 'Removed from Production Team',
                    'message' => "You have been removed from production team '{$team->name}' as {$role}.",
                    'priority' => 'normal'
                ]);
            }
            
            return $result;
        });
    }

    /**
     * Update team member
     */
    public function updateMember(ProductionTeamMember $member, array $data): ProductionTeamMember
    {
        return DB::transaction(function () use ($member, $data) {
            $member->update($data);
            
            // Send notification to user
            Notification::create([
                'user_id' => $member->user_id,
                'type' => 'team_member_updated',
                'title' => 'Team Member Updated',
                'message' => "Your role in production team '{$member->productionTeam->name}' has been updated.",
                'priority' => 'normal'
            ]);
            
            return $member;
        });
    }

    /**
     * Get team members
     */
    public function getTeamMembers(ProductionTeam $team): array
    {
        $members = $team->members()->with('user')->where('is_active', true)->get();
        
        return $members->map(function ($member) {
            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'user_name' => $member->user->name,
                'user_email' => $member->user->email,
                'role' => $member->role,
                'role_label' => $member->role_label,
                'joined_at' => $member->joined_at,
                'notes' => $member->notes
            ];
        })->toArray();
    }

    /**
     * Get team statistics
     */
    public function getTeamStatistics(ProductionTeam $team): array
    {
        $totalMembers = $team->members()->where('is_active', true)->count();
        $requiredRoles = ['creative', 'musik_arr', 'sound_eng', 'production', 'editor', 'art_set_design'];
        $existingRoles = $team->members()->where('is_active', true)->pluck('role')->toArray();
        $missingRoles = array_diff($requiredRoles, $existingRoles);
        $hasAllRoles = count($missingRoles) === 0;
        
        $roleDistribution = [];
        foreach ($requiredRoles as $role) {
            $roleDistribution[$role] = $team->members()
                ->where('role', $role)
                ->where('is_active', true)
                ->count();
        }
        
        return [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'total_members' => $totalMembers,
            'required_roles' => $requiredRoles,
            'existing_roles' => $existingRoles,
            'missing_roles' => $missingRoles,
            'has_all_roles' => $hasAllRoles,
            'is_ready_for_production' => $team->isReadyForProduction(),
            'role_distribution' => $roleDistribution
        ];
    }

    /**
     * Get teams by producer
     */
    public function getTeamsByProducer(int $producerId): array
    {
        $teams = ProductionTeam::where('producer_id', $producerId)
            ->with(['members' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();
            
        return $teams->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'producer_id' => $team->producer_id,
                'is_active' => $team->is_active,
                'created_at' => $team->created_at,
                'member_count' => $team->members()->where('is_active', true)->count(),
                'is_ready_for_production' => $team->isReadyForProduction()
            ];
        })->toArray();
    }

    /**
     * Get available users for role
     * 
     * Maps production team member role to user role:
     * - kreatif -> Creative
     * - musik_arr -> Music Arranger
     * - sound_eng -> Sound Engineer
     * - produksi -> Production
     * - editor -> Editor
     * - art_set_design -> Art & Set Properti
     */
    public function getAvailableUsersForRole(string $role): array
    {
        // Special logic for Tim Syuting (production), Tim Setting (art_set_design), and Tim Vocal (sound_eng)
        // Producer can select ANY user (except Managers) for these roles
        if (in_array($role, ['production', 'art_set_design', 'sound_eng'])) {
             $users = User::whereNotIn('role', ['Manager Program', 'Program Manager', 'General Manager', 'Administrator'])
                ->where('is_active', true)
                ->get();
        } else {
            // Map production team role to user role for other specialized roles
            $roleMapping = [
                'creative' => 'Creative',
                'musik_arr' => 'Music Arranger',
                'sound_eng' => 'Sound Engineer',
                'editor' => 'Editor',
            ];
            
            // Get user role from mapping, fallback to original role if not found
            $userRole = $roleMapping[$role] ?? $role;
            
            $users = User::where('role', $userRole)
                ->where('is_active', true)
                ->get();
        }
            
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];
        })->toArray();
    }

    /**
     * Get team workload
     */
    public function getTeamWorkload(ProductionTeam $team): array
    {
        $workload = [];
        $members = $team->members()->where('is_active', true)->with('user')->get();
        
        foreach ($members as $member) {
            $workload[$member->role] = [
                'user_id' => $member->user_id,
                'user_name' => $member->user->name,
                'role' => $member->role,
                'role_label' => $member->role_label,
                'active_tasks' => $this->getActiveTasksForUser($member->user_id),
                'overdue_tasks' => $this->getOverdueTasksForUser($member->user_id),
                'completed_tasks' => $this->getCompletedTasksForUser($member->user_id)
            ];
        }
        
        return $workload;
    }

    /**
     * Get active tasks for user
     */
    private function getActiveTasksForUser(int $userId): int
    {
        // Implement logic to get active tasks for user
        // This would depend on your specific task tracking system
        return 0;
    }

    /**
     * Get overdue tasks for user
     */
    private function getOverdueTasksForUser(int $userId): int
    {
        // Implement logic to get overdue tasks for user
        // This would depend on your specific task tracking system
        return 0;
    }

    /**
     * Get completed tasks for user
     */
    private function getCompletedTasksForUser(int $userId): int
    {
        // Implement logic to get completed tasks for user
        // This would depend on your specific task tracking system
        return 0;
    }

    /**
     * Deactivate team
     */
    public function deactivateTeam(ProductionTeam $team): bool
    {
        return DB::transaction(function () use ($team) {
            $team->update(['is_active' => false]);
            
            // Send notification to all team members
            $teamMembers = $team->members()->where('is_active', true)->get();
            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'team_deactivated',
                    'title' => 'Team Deactivated',
                    'message' => "Production team '{$team->name}' has been deactivated.",
                    'priority' => 'normal'
                ]);
            }
            
            return true;
        });
    }

    /**
     * Reactivate team
     */
    public function reactivateTeam(ProductionTeam $team): bool
    {
        return DB::transaction(function () use ($team) {
            $team->update(['is_active' => true]);
            
            // Send notification to all team members
            $teamMembers = $team->members()->where('is_active', true)->get();
            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'team_reactivated',
                    'title' => 'Team Reactivated',
                    'message' => "Production team '{$team->name}' has been reactivated.",
                    'priority' => 'normal'
                ]);
            }
            
            return true;
        });
    }
}
