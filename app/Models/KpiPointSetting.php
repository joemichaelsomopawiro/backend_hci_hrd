<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiPointSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'program_type',
        'points_on_time',
        'points_late',
        'points_not_done',
        'quality_min',
        'quality_max',
        'updated_by',
    ];

    protected $casts = [
        'points_on_time' => 'integer',
        'points_late' => 'integer',
        'points_not_done' => 'integer',
        'quality_min' => 'integer',
        'quality_max' => 'integer',
    ];

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get default point settings from KPI spreadsheet
     */
    public static function getDefaults(): array
    {
        return [
            // Program Regular
            ['role' => 'producer', 'program_type' => 'regular', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'kreatif', 'program_type' => 'regular', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'produksi', 'program_type' => 'regular', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'art_set_design', 'program_type' => 'regular', 'points_on_time' => 2, 'points_late' => 1, 'points_not_done' => 0, 'quality_min' => 0, 'quality_max' => 0],
            ['role' => 'editor', 'program_type' => 'regular', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'quality_control', 'program_type' => 'regular', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'editor_promosi', 'program_type' => 'regular', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'design_grafis', 'program_type' => 'regular', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'broadcasting', 'program_type' => 'regular', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'promotion', 'program_type' => 'regular', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            // Program Musik (Based on User Matrix - Updated)
            ['role' => 'producer', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'producer_acc_song', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'producer_acc_lagu', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'kreatif', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'tim_setting_coord', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'tim_vocal_coord', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'tim_syuting_coord', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'musik_arr', 'program_type' => 'musik', 'points_on_time' => 10, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'musik_arr_song', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'musik_arr_lagu', 'program_type' => 'musik', 'points_on_time' => 10, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'sound_eng', 'program_type' => 'musik', 'points_on_time' => 10, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'art_set_design', 'program_type' => 'musik', 'points_on_time' => 2, 'points_late' => 1, 'points_not_done' => 0, 'quality_min' => 0, 'quality_max' => 0],
            ['role' => 'art_set_design_return', 'program_type' => 'musik', 'points_on_time' => 2, 'points_late' => 1, 'points_not_done' => 0, 'quality_min' => 0, 'quality_max' => 0],
            ['role' => 'editor', 'program_type' => 'musik', 'points_on_time' => 5, 'points_late' => 2, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'quality_control', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'manager_distribusi', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'promosi_syuting', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'editor_promosi', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'design_grafis', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'broadcasting', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
            ['role' => 'promotion', 'program_type' => 'musik', 'points_on_time' => 3, 'points_late' => 1, 'points_not_done' => -5, 'quality_min' => 1, 'quality_max' => 5],
        ];
    }
}
