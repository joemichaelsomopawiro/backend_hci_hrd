<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_role',
        'to_role',
        'work_type',
        'work_id',
        'status',
        'notes',
        'created_by',
        'from_user_id',
        'to_user_id',
        'transition_data'
    ];

    protected $casts = [
        'transition_data' => 'array'
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create workflow transition
     */
    public static function createTransition($fromRole, $toRole, $workType, $workId, $status = 'pending', $notes = null, $data = [])
    {
        return self::create([
            'from_role' => $fromRole,
            'to_role' => $toRole,
            'work_type' => $workType,
            'work_id' => $workId,
            'status' => $status,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'transition_data' => $data
        ]);
    }

    /**
     * Get transitions for specific work
     */
    public static function getWorkTransitions($workType, $workId)
    {
        return self::where('work_type', $workType)
            ->where('work_id', $workId)
            ->with(['fromUser', 'toUser', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending transitions for user
     */
    public static function getPendingTransitions($userId, $role = null)
    {
        $query = self::where('to_user_id', $userId)
            ->where('status', 'pending');

        if ($role) {
            $query->where('to_role', $role);
        }

        return $query->with(['fromUser', 'createdBy'])->get();
    }
}













