<?php

namespace App\Services;

use App\Models\{
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
    Program,
    PrProgram
};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskVisibilityService
{
    /**
     * Task type mapping to models
     */
    protected static array $taskTypeMapping = [
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
    ];

    /**
     * Get all tasks with filters
     */
    public static function getAllTasks(array $filters = []): array
    {
        $allTasks = collect();

        foreach (self::$taskTypeMapping as $taskType => $modelClass) {
            $tasks = self::getTasksFromModel($taskType, $modelClass, $filters);
            $allTasks = $allTasks->merge($tasks);
        }

        // Sort by created_at descending
        $allTasks = $allTasks->sortByDesc('created_at')->values();

        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 50;
        $total = $allTasks->count();
        $items = $allTasks->forPage($page, $perPage)->values();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Get tasks from specific model
     */
    protected static function getTasksFromModel(string $taskType, string $modelClass, array $filters): Collection
    {
        if (!class_exists($modelClass)) {
            return collect();
        }

        $query = $modelClass::query();

        // Determine user field based on model
        $userField = self::getUserField($taskType);
        $userRelation = self::getUserRelation($taskType);
        
        // Eager load relationships
        $query->with([$userRelation => function($q) {
            $q->select('id', 'name', 'email', 'role');
        }]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where($userField, $filters['user_id']);
        }

        if (isset($filters['search'])) {
            // Search in program name
            if (str_starts_with($taskType, 'pr_')) {
                $query->whereHas('program', function($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%');
                });
            } else {
                $query->whereHas('episode.program', function($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%');
                });
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Get results and transform
        return $query->get()->map(function($task) use ($taskType, $userField) {
            return self::transformTask($task, $taskType, $userField);
        });
    }

    /**
     * Transform task to unified structure
     */
    protected static function transformTask($task, string $taskType, string $userField): array
    {
        $userRelation = self::getUserRelation($taskType);
        $assignedUser = $task->$userRelation;
        
        // Get program info
        if (str_starts_with($taskType, 'pr_')) {
            $program = $task->program ?? null;
            $episodeNumber = null;
        } else {
            $episode = $task->episode ?? null;
            $program = $episode?->program ?? null;
            $episodeNumber = $episode?->episode_number ?? null;
        }

        return [
            'id' => $task->id,
            'task_type' => $taskType,
            'task_type_label' => self::getTaskTypeLabel($taskType),
            'program_id' => $program?->id,
            'program_name' => $program?->name ?? 'N/A',
            'episode_number' => $episodeNumber,
            'assigned_to' => [
                'id' => $assignedUser?->id,
                'name' => $assignedUser?->name ?? 'Unassigned',
                'role' => $assignedUser?->role ?? 'N/A'
            ],
            'status' => $task->status ?? 'unknown',
            'status_label' => self::getStatusLabel($task->status ?? 'unknown'),
            'deadline' => $task->deadline ?? $task->due_date ?? null,
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
            'was_reassigned' => $task->was_reassigned ?? false,
            'originally_assigned_to' => $task->originally_assigned_to ?? null
        ];
    }

    /**
     * Get user field (column name) for task type
     */
    protected static function getUserField(string $taskType): string
    {
        // Most models use 'created_by' as the assignee
        return 'created_by';
    }

    /**
     * Get user relationship name for task type
     */
    protected static function getUserRelation(string $taskType): string
    {
        // Eloquent relationship name
        return 'createdBy';
    }

    /**
     * Get task type label
     */
    protected static function getTaskTypeLabel(string $taskType): string
    {
        return match($taskType) {
            'music_arrangement' => 'Music Arrangement',
            'editor_work', 'pr_editor_work' => 'Editor Work',
            'creative_work', 'pr_creative_work' => 'Creative Work',
            'promotion_work', 'pr_promotion_work' => 'Promotion Work',
            'quality_control_work', 'pr_quality_control_work' => 'Quality Control',
            'production_work', 'pr_produksi_work' => 'Production Work',
            'broadcasting_work', 'pr_broadcasting_work' => 'Broadcasting Work',
            'design_grafis_work' => 'Design Grafis Work',
            default => ucwords(str_replace('_', ' ', $taskType))
        };
    }

    /**
     * Get status label
     */
    protected static function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'revision' => 'Revision Required',
            default => ucwords(str_replace('_', ' ', $status))
        };
    }

    /**
     * Get task statistics
     */
    public static function getTaskStatistics(array $filters = []): array
    {
        $stats = [
            'total_tasks' => 0,
            'by_status' => [],
            'by_type' => [],
            'by_role' => [],
            'reassigned_tasks' => 0
        ];

        foreach (self::$taskTypeMapping as $taskType => $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $query = $modelClass::query();

            // Apply date filters
            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            $count = $query->count();
            $stats['total_tasks'] += $count;
            $stats['by_type'][$taskType] = $count;

            // Count by status
            $statusCounts = $query->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');
            
            foreach ($statusCounts as $status => $statusCount) {
                $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + $statusCount;
            }

            // Count reassigned tasks
            $reassignedCount = $modelClass::where('was_reassigned', true)->count();
            $stats['reassigned_tasks'] += $reassignedCount;
        }

        return $stats;
    }

    /**
     * Get task detail by type and ID
     */
    public static function getTaskDetail(string $taskType, int $taskId): ?array
    {
        $modelClass = self::$taskTypeMapping[$taskType] ?? null;
        
        if (!$modelClass || !class_exists($modelClass)) {
            return null;
        }

        $task = $modelClass::find($taskId);
        
        if (!$task) {
            return null;
        }

        $userField = self::getUserField($taskType);
        $task->load($userField);

        return self::transformTask($task, $taskType, $userField);
    }
}
