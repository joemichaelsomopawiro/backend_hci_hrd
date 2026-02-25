<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrProduksiWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_episode_id',
        'pr_creative_work_id',
        'equipment_list',
        'equipment_requests',
        'needs_list',
        'needs_requests',
        'run_sheet_id',
        'shooting_files',
        'shooting_file_links',
        'shooting_notes',
        'status',
        'created_by',
        'completed_by',
        'completion_notes',
        'completed_at',
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'equipment_requests' => 'array',
        'needs_list' => 'array',
        'needs_requests' => 'array',
        'shooting_files' => 'array',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function episode()
    {
        return $this->belongsTo(PrEpisode::class, 'pr_episode_id');
    }

    public function creativeWork()
    {
        return $this->belongsTo(PrCreativeWork::class, 'pr_creative_work_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function runSheet()
    {
        return $this->belongsTo(ShootingRunSheet::class, 'run_sheet_id');
    }

    public function equipmentLoan()
    {
        return $this->hasOne(EquipmentLoan::class, 'pr_produksi_work_id');
    }

    public function editorWork()
    {
        return $this->hasOne(PrEditorWork::class, 'pr_production_work_id'); // Note: PrEditorWork has pr_production_work_id
        // or cleaner: return $this->hasOne(PrEditorWork::class, 'pr_episode_id', 'pr_episode_id');
        // Let's check PrEditorWork definition. It has pr_production_work_id.
        // So this->hasOne(PrEditorWork::class, 'pr_production_work_id') is correct if PrEditorWork belongs to PrProduksiWork.
        // But PrEditorWork belongsTo PrProduksiWork via pr_production_work_id?
        // Let's check PrEditorWork.php again.
        // It says: public function productionWork() { return $this->belongsTo(PrProduksiWork::class, 'pr_production_work_id'); }
        // So yes, PrProduksiWork hasOne PrEditorWork.
        return $this->hasOne(PrEditorWork::class, 'pr_production_work_id');
    }

    // Helper Methods
    public function acceptWork(int $userId): void
    {
        $this->update([
            'status' => 'in_progress',
            'created_by' => $userId
        ]);
    }

    public function completeWork(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_by' => $userId,
            'completion_notes' => $notes,
            'completed_at' => now()
        ]);
    }
}
