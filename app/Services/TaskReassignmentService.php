<?php

namespace App\Services;

use App\Models\{
    MusicArrangement,
    EditorWork,
    CreativeWork,
    PromotionWork,
    QualityControlWork,
    ProduksiWork,
    BroadcastingWork,
    DesignGrafisWork,
    PrEditorWork,
    PrCreativeWork,
    PrPromotionWork,
    PrQualityControlWork,
    PrProduksiWork,
    PrBroadcastingWork,
    TaskReassignment,
    User,
    Program,
    PrProgram
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskReassignmentService
{
    /**
     * Task type to model mapping
     */
    protected static array $taskTypeMapping = [
        'music_arrangement' => MusicArrangement::class,
        'editor_work' => EditorWork::class,
        'creative_work' => CreativeWork::class,
        'promotion_work' => PromotionWork::class,
        'quality_control_work' => QualityControlWork::class,
        'production_work' => ProduksiWork::class,
        'broadcasting_work' => BroadcastingWork::class,
        'design_grafis_work' => DesignGrafisWork::class,
        'pr_editor_work' => PrEditorWork::class,
        'pr_creative_work' => PrCreativeWork::class,
        'pr_promotion_work' => PrPromotionWork::class,
        'pr_quality_control_work' => PrQualityControlWork::class,
        'pr_produksi_work' => PrProduksiWork::class,
        'pr_broadcasting_work' => PrBroadcastingWork::class,
        'deadline' => \App\Models\Deadline::class,
    ];

    /**
     * Reassign task to new user
     */
    public static function reassignTask(
        string $taskType,
        int $taskId,
        int $newUserId,
        int $reassignedByUserId,
        ?string $reason = null,
        bool $bypassRoleCheck = false
    ): array {
        try {
            DB::beginTransaction();

            // Validate reassignment
            $validation = self::validateReassignment($taskType, $taskId, $newUserId, $reassignedByUserId, $bypassRoleCheck);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }

            $task = $validation['task'];
            $newUser = $validation['new_user'];
            $reassigner = $validation['reassigner'];
            $userField = self::getUserField($taskType);

            // Get original user ID
            $originalUserId = $task->$userField;

            // Update task (defensively check for audit columns)
            $attributes = $task->getAttributes();
            
            if (array_key_exists('originally_assigned_to', $attributes) || $task->getConnection()->getSchemaBuilder()->hasColumn($task->getTable(), 'originally_assigned_to')) {
                if ($task->originally_assigned_to === null) {
                    $task->originally_assigned_to = $originalUserId;
                }
            }
            
            if (array_key_exists('was_reassigned', $attributes) || $task->getConnection()->getSchemaBuilder()->hasColumn($task->getTable(), 'was_reassigned')) {
                $task->was_reassigned = true;
            }

            $task->$userField = $newUserId;
            $task->save();

            // Get program ID
            $programId = self::getProgramId($task, $taskType);

            // Create audit log
            $reassignment = TaskReassignment::create([
                'task_type' => $taskType,
                'task_id' => $taskId,
                'program_id' => $programId,
                'original_user_id' => $originalUserId,
                'new_user_id' => $newUserId,
                'reassigned_by_user_id' => $reassignedByUserId,
                'reason' => $reason
            ]);

            // Send notifications
            $notificationResult = self::sendReassignmentNotifications(
                $task,
                $taskType,
                $originalUserId,
                $newUser,
                $reassigner,
                $programId,
                $reason
            );

            // SPECIAL LOGIC: If a Deadline is reassigned, keep the Work record in sync
            if ($taskType === 'deadline') {
                self::syncWorkRecordWithDeadline($task, $newUserId);
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'task' => $task,
                    'reassignment' => $reassignment,
                    'notifications_sent' => $notificationResult['count']
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task reassignment failed', [
                'task_type' => $taskType,
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to reassign task: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate reassignment
     */
    public static function validateReassignment(
        string $taskType,
        int $taskId,
        int $newUserId,
        int $reassignedByUserId,
        bool $bypassRoleCheck = false
    ): array {
        // Check task type valid
        $modelClass = self::$taskTypeMapping[$taskType] ?? null;
        if (!$modelClass || !class_exists($modelClass)) {
            return [
                'valid' => false,
                'error' => 'Invalid task type'
            ];
        }

        // Check task exists
        $task = $modelClass::find($taskId);
        if (!$task) {
            return [
                'valid' => false,
                'error' => 'Task not found'
            ];
        }

        // Check task not completed
        if ($taskType === 'deadline') {
            if ($task->is_completed) {
                return [
                    'valid' => false,
                    'error' => 'Cannot reassign completed deadline'
                ];
            }
        } elseif (in_array($task->status, ['completed', 'approved'])) {
            return [
                'valid' => false,
                'error' => 'Cannot reassign completed task'
            ];
        }

        // Check new user exists
        $newUser = User::find($newUserId);
        if (!$newUser) {
            return [
                'valid' => false,
                'error' => 'New user not found'
            ];
        }

        // Check reassigner exists and has permission
        $reassigner = User::find($reassignedByUserId);
        if (!$reassigner) {
            return [
                'valid' => false,
                'error' => 'Reassigner not found'
            ];
        }

        if (!in_array($reassigner->role, ['Program Manager', 'Producer', 'Manager', 'Manager Program'])) {
            return [
                'valid' => false,
                'error' => 'Only Program Manager and Producer can reassign tasks'
            ];
        }

        // Check program still active (optional - can be warning instead)
        $program = self::getProgram($task, $taskType);
        if ($program && isset($program->status) && $program->status === 'closed') {
            return [
                'valid' => false,
                'error' => 'Cannot reassign task for closed program'
            ];
        }

        return [
            'valid' => true,
            'task' => $task,
            'new_user' => $newUser,
            'reassigner' => $reassigner
        ];
    }

    /**
     * Send reassignment notifications
     */
    protected static function sendReassignmentNotifications(
        $task,
        string $taskType,
        int $originalUserId,
        $newUser,
        $reassigner,
        ?int $programId,
        ?string $reason
    ): array {
        $taskLabel = TaskVisibilityService::getTaskTypeLabel($taskType);
        $programName = self::getProgram($task, $taskType)?->name ?? 'N/A';

        $baseMessage = "Task {$taskLabel} untuk program '{$programName}'";
        $reasonText = $reason ? " Alasan: {$reason}" : "";

        // Notification data
        $notifications = [];

        // 1. Notify new user
        $notifications[] = [
            'user_id' => $newUser->id,
            'type' => 'task_assigned',
            'title' => 'Task Baru Dialihkan Kepada Anda',
            'message' => "{$baseMessage} telah dialihkan kepada Anda oleh {$reassigner->name}.{$reasonText}",
            'program_id' => $programId,
            'related_id' => $task->id,
            'related_type' => $taskType,
            'created_at' => now(),
            'updated_at' => now()
        ];

        // 2. Notify original user
        if ($originalUserId && $originalUserId != $newUser->id) {
            $notifications[] = [
                'user_id' => $originalUserId,
                'type' => 'task_reassigned',
                'title' => 'Task Anda Dialihkan',
                'message' => "{$baseMessage} yang sebelumnya Anda kerjakan telah dialihkan kepada {$newUser->name} oleh {$reassigner->name}.{$reasonText}",
                'program_id' => $programId,
                'related_id' => $task->id,
                'related_type' => $taskType,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // 3. Notify Program Manager (if reassigner is Producer)
        if ($reassigner->role === 'Producer') {
            $managerProgram = User::whereIn('role', ['Program Manager', 'Manager Program'])->first();
            if ($managerProgram && $managerProgram->id != $reassigner->id) {
                $notifications[] = [
                    'user_id' => $managerProgram->id,
                    'type' => 'task_reassigned',
                    'title' => 'Task Dialihkan oleh Producer',
                    'message' => "Producer {$reassigner->name} mengalihkan {$baseMessage} dari user sebelumnya kepada {$newUser->name}.{$reasonText}",
                    'program_id' => $programId,
                    'related_id' => $task->id,
                    'related_type' => $taskType,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // 4. Notify Producer (if reassigner is Program Manager)
        if (in_array($reassigner->role, ['Program Manager', 'Manager Program'])) {
            $producer = User::where('role', 'Producer')->first();
            if ($producer && $producer->id != $reassigner->id) {
                $notifications[] = [
                    'user_id' => $producer->id,
                    'type' => 'task_reassigned',
                    'title' => 'Task Dialihkan oleh Program Manager',
                    'message' => "Program Manager {$reassigner->name} mengalihkan {$baseMessage} dari user sebelumnya kepada {$newUser->name}.{$reasonText}",
                    'program_id' => $programId,
                    'related_id' => $task->id,
                    'related_type' => $taskType,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // Send using parallel notification service
        if (!empty($notifications)) {
            DB::table('notifications')->insert($notifications);
        }

        return [
            'count' => count($notifications),
            'recipients' => array_column($notifications, 'user_id')
        ];
    }

    /**
     * Get reassignment history for task
     */
    public static function getReassignmentHistory(string $taskType, int $taskId): array
    {
        return TaskReassignment::byTask($taskType, $taskId)
            ->with(['originalUser', 'newUser', 'reassignedBy'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($reassignment) {
                return [
                    'id' => $reassignment->id,
                    'from_user' => [
                        'id' => $reassignment->originalUser->id,
                        'name' => $reassignment->originalUser->name
                    ],
                    'to_user' => [
                        'id' => $reassignment->newUser->id,
                        'name' => $reassignment->newUser->name
                    ],
                    'reassigned_by' => [
                        'id' => $reassignment->reassignedBy->id,
                        'name' => $reassignment->reassignedBy->name,
                        'role' => $reassignment->reassignedBy->role
                    ],
                    'reason' => $reassignment->reason,
                    'created_at' => $reassignment->created_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Get user field for task type
     */
    protected static function getUserField(string $taskType): string
    {
        // Based on model inspection, most models use 'created_by'
        // Some older or specific PR models might use 'user_id' but we found PrEditorWork uses created_by
        
        return match($taskType) {
            'music_arrangement' => 'created_by',
            'creative_work' => 'created_by', // Verified
            'editor_work' => 'created_by',   // Verified
            'pr_editor_work' => 'created_by', // Verified
            'deadline' => 'assigned_user_id',
            // Safe default for others based on pattern
            default => 'created_by'
        };
    }

    /**
     * Get program from task
     */
    protected static function getProgram($task, string $taskType)
    {
        if (str_starts_with($taskType, 'pr_')) {
            return $task->program ?? null;
        }
        
        $episode = $task->episode ?? null;
        return $episode?->program ?? null;
    }

    /**
     * Get program ID from task
     */
    protected static function getProgramId($task, string $taskType): ?int
    {
        $program = self::getProgram($task, $taskType);
        return $program?->id;
    }

    /**
     * Get available users for task type (Global Selection enabled)
     */
    public static function getAvailableUsers(string $taskType, ?int $taskId = null, bool $allUsers = false): array
    {
        // Music Program request: Allow global selection for ALL active users
        // regardless of their primary role.
        return User::where('is_active', true)
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => "{$user->name} ({$user->role})",
                    'email' => $user->email,
                    'role' => $user->role
                ];
            })
            ->toArray();
    }

    /**
     * Sync Work record with Deadline assignment
     */
    protected static function syncWorkRecordWithDeadline($deadline, int $newUserId): void
    {
        $episodeId = $deadline->episode_id;
        $role = $deadline->role;

        try {
            switch ($role) {
                case 'kreatif':
                    CreativeWork::where('episode_id', $episodeId)->update(['created_by' => $newUserId]);
                    break;
                case 'editor':
                    EditorWork::where('episode_id', $episodeId)->update(['created_by' => $newUserId]);
                    break;
                case 'musik_arr':
                    MusicArrangement::where('episode_id', $episodeId)->update(['created_by' => $newUserId]);
                    break;
                case 'promotion':
                    PromotionWork::where(['episode_id' => $episodeId, 'work_type' => 'bts_photo'])->update(['created_by' => $newUserId]);
                    break;
                case 'editor_promosi':
                    PromotionWork::where(['episode_id' => $episodeId, 'work_type' => 'highlight_ig'])->update(['created_by' => $newUserId]);
                    break;
                case 'design_grafis':
                    DesignGrafisWork::where('episode_id', $episodeId)->update(['created_by' => $newUserId]);
                    break;
                case 'broadcasting':
                    BroadcastingWork::where('episode_id', $episodeId)->update(['created_by' => $newUserId]);
                    break;
                case 'quality_control':
                    QualityControlWork::where('episode_id', $episodeId)->update(['created_by' => $newUserId]);
                    break;
                case 'tim_vocal_coord':
                case 'tim_setting_coord':
                case 'tim_syuting_coord':
                    ProduksiWork::where(['episode_id' => $episodeId, 'role' => $role])->update(['created_by' => $newUserId]);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync work record with deadline', [
                'deadline_id' => $deadline->id,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
        }
    }
}
