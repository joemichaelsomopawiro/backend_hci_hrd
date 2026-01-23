<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReassignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_type',
        'task_id',
        'program_id',
        'original_user_id',
        'new_user_id',
        'reassigned_by_user_id',
        'reason'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship dengan original user
     */
    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    /**
     * Relationship dengan new user
     */
    public function newUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }

    /**
     * Relationship dengan reassigner
     */
    public function reassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_by_user_id');
    }

    /**
     * Relationship dengan program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * Scope untuk filter by task type
     */
    public function scopeByTaskType($query, string $taskType)
    {
        return $query->where('task_type', $taskType);
    }

    /**
     * Scope untuk filter by task
     */
    public function scopeByTask($query, string $taskType, int $taskId)
    {
        return $query->where('task_type', $taskType)
                     ->where('task_id', $taskId);
    }

    /**
     * Scope untuk filter by program
     */
    public function scopeByProgram($query, int $programId)
    {
        return $query->where('program_id', $programId);
    }

    /**
     * Scope untuk filter by reassigner
     */
    public function scopeByReassigner($query, int $userId)
    {
        return $query->where('reassigned_by_user_id', $userId);
    }

    /**
     * Get history for specific task
     */
    public static function getTaskHistory(string $taskType, int $taskId)
    {
        return self::byTask($taskType, $taskId)
            ->with(['originalUser', 'newUser', 'reassignedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get statistics
     */
    public static function getStatistics(array $filters = [])
    {
        $query = self::query();

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['program_id'])) {
            $query->byProgram($filters['program_id']);
        }

        return [
            'total_reassignments' => $query->count(),
            'by_task_type' => $query->select('task_type', \DB::raw('count(*) as count'))
                                   ->groupBy('task_type')
                                   ->pluck('count', 'task_type'),
            'recent_reassignments' => $query->with(['originalUser', 'newUser', 'reassignedBy', 'program'])
                                           ->orderBy('created_at', 'desc')
                                           ->limit(10)
                                           ->get()
        ];
    }
}
