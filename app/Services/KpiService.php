<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\Attendance;
use App\Models\MorningReflectionAttendance;
use App\Models\KpiPointSetting;
use App\Models\KpiQualityScore;
use App\Models\PrEpisode;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\PrEditorWork;
use App\Models\PrEditorPromosiWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrQualityControlWork;
use App\Models\PrBroadcastingWork;
use App\Models\PrPromotionWork;
use App\Models\PrManagerDistribusiQcWork;
use App\Models\PrEpisodeWorkflowProgress;
use App\Models\PrProgramCrew;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiService
{
    /**
     * Get KPI dashboard data for an employee
     */
    public function getEmployeeKpi(int $employeeId, ?int $month = null, ?int $year = null): array
    {
        $employee = Employee::with('user')->findOrFail($employeeId);
        $user = $employee->user;

        if (!$user) {
            return ['error' => 'Employee has no associated user account'];
        }

        $year = $year ?? now()->year;
        $month = $month ?? null; // null = yearly

        return [
            'employee' => [
                'id' => $employee->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'profile_picture' => $user->profile_picture,
                'nik' => $employee->nik ?? $employee->nip ?? null,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'label' => $month ? Carbon::create($year, $month)->translatedFormat('F Y') : "Tahun $year",
            ],
            'work_points' => $this->calculateWorkPoints($user, $month, $year),
            'office_attendance' => $this->calculateOfficeAttendance($employee, $month, $year),
            'reflection_attendance' => $this->calculateReflectionAttendance($employee, $month, $year),
            'kpi_score' => $this->calculateOverallScore($user, $employee, $month, $year),
        ];
    }

    /**
     * Calculate work points from workflow submissions
     */
    public function calculateWorkPoints(User $user, ?int $month, int $year): array
    {
        $userId = $user->id;
        $settings = KpiPointSetting::all()->keyBy(function ($item) {
            return $item->role . '_' . $item->program_type;
        });

        $workItems = [];
        $totalPoints = 0;
        $maxPoints = 0;
        $onTimeCount = 0;
        $lateCount = 0;
        $notDoneCount = 0;

        // Get all program regular episodes this user worked on
        $this->collectPrWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $user);

        // Get all music program episodes this user worked on
        $this->collectMusicWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $user);

        // We calculate maxPoints globally based on expected target per role (Total Aired Episodes * max_point per episode)
        // For backup workloads, they add to totalPoints but DO NOT increase maxPoints, creating possibilities of >100%
        
        $totalTasks = $onTimeCount + $lateCount + $notDoneCount;
        
        // Define base role from user to identify their expected target count
        $normalizedUserRole = strtolower(str_replace([' ', '&', '-'], ['_', '', '_'], $user->role ?? ''));
        
        // Count total music episodes in the queried period
        $targetEpisodeCount = \App\Models\Episode::whereHas('program', function($q) {
                $q->where('category', 'musik');
            })
            ->whereYear('air_date', $year)
            ->when($month, function($q) use($month) {
                $q->whereMonth('air_date', $month);
            })->count();

        // If they have a regular KPI target (5 points per episode)
        // Base points = target_episode_count * 5. Only apply this base maxPoints to core workers. 
        // We will assign a dynamic max based on the expected workflow steps
        $baseSettingPoint = 5; 
        $maxPoints = $targetEpisodeCount * $baseSettingPoint;

        if ($maxPoints == 0 && $totalPoints > 0) {
            // Backup edge case if no target episodes but somehow scored
            $maxPoints = $totalTasks * $baseSettingPoint; 
        }

        $percentage = $maxPoints > 0 ? round(($totalPoints / $maxPoints) * 100, 1) : 0;

        return [
            'total_points' => $totalPoints,
            'max_points' => $maxPoints,
            'percentage' => $percentage,
            'breakdown' => [
                'on_time' => $onTimeCount,
                'late' => $lateCount,
                'not_done' => $notDoneCount,
                'total_tasks' => $totalTasks,
            ],
            'on_time_percentage' => $totalTasks > 0 ? round(($onTimeCount / $totalTasks) * 100, 1) : 0,
            'late_percentage' => $totalTasks > 0 ? round(($lateCount / $totalTasks) * 100, 1) : 0,
            'not_done_percentage' => $totalTasks > 0 ? round(($notDoneCount / $totalTasks) * 100, 1) : 0,
            'items' => $workItems,
        ];
    }

    /**
     * Collect Program Regular work points
     */
    private function collectPrWorkPoints(int $userId, ?int $month, int $year, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, User $user): void
    {
        // Map work models to their role keys and completion tracking fields
        $workModels = [
            'kreatif' => [
                'model' => PrCreativeWork::class,
                'user_field' => 'created_by',
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved', 'completed'],
            ],
            'produksi' => [
                'model' => PrProduksiWork::class,
                'user_field' => 'completed_by',
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'editor' => [
                'model' => PrEditorWork::class,
                'user_field' => 'originally_assigned_to',
                'completed_field' => 'completed_at',
                'status_completed' => ['completed', 'pending_qc'],
            ],
            'editor_promosi' => [
                'model' => PrEditorPromosiWork::class,
                'user_field' => 'assigned_to',
                'completed_field' => 'submitted_at',
                'status_completed' => ['completed', 'pending_qc'],
            ],
            'design_grafis' => [
                'model' => PrDesignGrafisWork::class,
                'user_field' => 'assigned_to',
                'completed_field' => 'submitted_at',
                'status_completed' => ['completed', 'pending_qc'],
            ],
            'quality_control' => [
                'model' => PrQualityControlWork::class,
                'user_field' => 'created_by',
                'completed_field' => 'qc_completed_at',
                'status_completed' => ['completed', 'approved'],
            ],
            'broadcasting' => [
                'model' => PrBroadcastingWork::class,
                'user_field' => 'created_by',
                'completed_field' => 'published_at',
                'status_completed' => ['completed', 'published'],
            ],
            'promotion' => [
                'model' => PrPromotionWork::class,
                'user_field' => 'originally_assigned_to',
                'completed_field' => 'updated_at',
                'status_completed' => ['completed'],
            ],
        ];

        foreach ($workModels as $roleKey => $config) {
            $query = $config['model']::with(['episode.program'])
                ->where($config['user_field'], $userId);

            // Filter by date using the episode's air_date
            $query->whereHas('episode', function ($q) use ($month, $year) {
                $q->whereYear('air_date', $year);
                if ($month) {
                    $q->whereMonth('air_date', $month);
                }
            });

            $works = $query->get();
            $settingKey = $roleKey . '_regular';
            $setting = $settings->get($settingKey);

            if (!$setting) continue;

            foreach ($works as $work) {
                $episode = $work->episode;
                if (!$episode) continue;

                $completedAt = $work->{$config['completed_field']} ?? null;
                $deadline = $this->getDeadlineForWork($work, $episode, $roleKey, 'regular');
                $isCompleted = in_array($work->status, $config['status_completed']);

                // Determine user's actual role vs work role
                $isBackup = $this->isBackupWork($user, $roleKey);

                // Calculate points
                $status = 'not_done';
                $points = $setting->points_not_done;

                if ($isCompleted && $completedAt) {
                    if ($deadline && Carbon::parse($completedAt)->lte(Carbon::parse($deadline))) {
                        $status = 'on_time';
                        $points = $setting->points_on_time;
                        $onTimeCount++;
                    } else {
                        $status = 'late';
                        $points = $setting->points_late;
                        $lateCount++;
                    }
                } else if (!$isCompleted) {
                    if ($deadline && now()->diffInDays(Carbon::parse($deadline)) > 7) {
                        $notDoneCount++;
                    } else {
                        $points = 0;
                    }
                }

                // Get quality score if exists
                $qualityScore = KpiQualityScore::where('employee_id', $userId)
                    ->where('pr_episode_id', $episode->id)
                    ->where('workflow_step', $roleKey)
                    ->first();

                $totalPoints += $qualityScore ? $qualityScore->quality_score : $points;
                $maxPoints += $setting->points_on_time;

                $items[] = [
                    'episode_id' => $episode->id,
                    'program_name' => $episode->program->name ?? 'Unknown',
                    'episode_number' => $episode->episode_number,
                    'role' => $roleKey,
                    'role_label' => $this->getRoleLabel($roleKey),
                    'is_backup' => $isBackup,
                    'backup_note' => $isBackup ? "Backup pekerjaan " . $this->getRoleLabel($roleKey) : null,
                    'deadline' => $deadline ? Carbon::parse($deadline)->toIso8601String() : null,
                    'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
                    'status' => $status,
                    'points' => $qualityScore ? $qualityScore->quality_score : $points,
                    'original_points' => $points,
                    'is_overridden' => $qualityScore !== null,
                    'max_points' => $setting->points_on_time,
                    'quality_score' => $qualityScore ? $qualityScore->quality_score : null,
                    'quality_max' => $setting->quality_max,
                    'program_type' => 'regular',
                ];
            }
        }
    }

    /**
     * Collect Music Program work points
     */
    private function collectMusicWorkPoints(int $userId, ?int $month, int $year, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, User $user): void
    {
        // 1. Get all deadlines assigned to this user for the period
        $deadlineQuery = \App\Models\Deadline::with(['episode.program'])
            ->where('assigned_user_id', $userId)
            ->whereHas('episode', function ($q) use ($month, $year) {
                $q->whereYear('air_date', $year);
                if ($month) {
                    $q->whereMonth('air_date', $month);
                }
            });

        $assignedDeadlines = $deadlineQuery->get();

        // 2. Map Work Models to Roles
        $workModels = [
            'producer' => [
                'model' => \App\Models\CreativeWork::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved'],
            ],
            'kreatif' => [
                'model' => \App\Models\CreativeWork::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved', 'submitted'],
            ],
            'musik_arr' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'submitted_at',
                'status_completed' => ['submitted', 'approved', 'rejected'],
            ],
            'sound_eng' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'sound_engineer_help_at',
                'status_completed' => ['submitted', 'approved', 'rejected'],
            ],
            'tim_setting_coord' => [
                'model' => \App\Models\ProduksiWork::class,
                'completed_field' => 'setting_completed_at',
                'status_completed' => ['in_progress', 'completed'],
            ],
            'tim_syuting_coord' => [
                'model' => \App\Models\ProduksiWork::class,
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'tim_vocal_coord' => [
                'model' => \App\Models\ProduksiWork::class,
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'editor' => [
                'model' => \App\Models\EditorWork::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved', 'completed', 'reviewed'],
            ],
            'design_grafis' => [
                'model' => \App\Models\DesignGrafisWork::class,
                'completed_field' => 'submitted_at',
                'status_completed' => ['approved', 'completed', 'submitted', 'reviewed'],
            ],
            'quality_control' => [
                'model' => \App\Models\QualityControl::class,
                'completed_field' => 'qc_completed_at',
                'status_completed' => ['completed', 'approved'],
            ],
            'manager_distribusi' => [
                'model' => \App\Models\BroadcastingWork::class,
                'completed_field' => 'accepted_at',
                'status_completed' => ['approved', 'scheduled', 'published', 'completed'],
            ],
            'broadcasting' => [
                'model' => \App\Models\BroadcastingWork::class,
                'completed_field' => 'published_at',
                'status_completed' => ['completed', 'published'],
            ],
            'editor_promosi' => [
                'model' => \App\Models\PromotionWork::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['published', 'approved'],
                'filters' => ['work_type' => 'highlight_ig']
            ],
            'promotion' => [
                'model' => \App\Models\PromotionWork::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['published', 'approved'],
                'filters' => ['work_type' => 'bts_photo']
            ],
            'art_set_design' => [
                'model' => \App\Models\EquipmentLoan::class,
                'completed_field' => 'loan_date',
                'status_completed' => ['active', 'returned'],
            ],
            'art_set_design_return' => [
                'model' => \App\Models\EquipmentLoan::class,
                'completed_field' => 'return_date',
                'status_completed' => ['returned'],
            ],
        ];

        // 3. Process each deadline
        foreach ($assignedDeadlines as $deadlineRecord) {
            $roleKey = $deadlineRecord->role;
            $episode = $deadlineRecord->episode;
            if (!$episode) continue;

            $settingKey = $roleKey . '_musik';
            $setting = $settings->get($settingKey);
            if (!$setting) continue;

            $config = $workModels[$roleKey] ?? null;
            $work = null;

            if ($config) {
                $workQuery = $config['model']::where('episode_id', $episode->id);
                if (isset($config['filters'])) {
                    foreach ($config['filters'] as $fKey => $fVal) {
                        $workQuery->where($fKey, $fVal);
                    }
                }
                $work = $workQuery->first();
            }

            $completedAt = $work ? ($work->{$config['completed_field']} ?? null) : null;
            $isCompleted = $work ? in_array($work->status, $config['status_completed']) : false;
            $deadlineDate = $deadlineRecord->deadline_date ?? null;

            // Sound engineer check specifically Needs SE Help
            if ($roleKey === 'sound_eng' && $work && !$work->needs_sound_engineer_help) continue;

            $status = 'not_done';
            $points = $setting->points_not_done;

            if ($isCompleted && $completedAt) {
                if ($deadlineDate && Carbon::parse($completedAt)->lte(Carbon::parse($deadlineDate))) {
                    $status = 'on_time';
                    $points = $setting->points_on_time;
                    $onTimeCount++;
                } else {
                    $status = 'late';
                    $points = $setting->points_late;
                    $lateCount++;
                }
            } else if (!$isCompleted) {
                // Strictly follow the deadline for point deductions
                if ($deadlineDate && Carbon::parse($deadlineDate)->isPast()) {
                    // Deadline has passed and task is NOT completed
                    $points = $setting->points_not_done;
                    $status = 'not_done';
                    $notDoneCount++;
                } else {
                    // Task is not done but deadline hasn't passed yet, points are 0 (not penalized yet)
                    $points = 0;
                    $status = 'pending'; // Change label from not_done to pending for clarity
                }
            }

            $qualityScore = KpiQualityScore::where('employee_id', $userId)
                ->where('music_episode_id', $episode->id)
                ->where('workflow_step', $roleKey)
                ->first();

            $isBackup = $this->isBackupWork($user, $roleKey);
            
            // SPECIAL RULE: Producer Quality is the average of their team's quality points
            $effectivePoints = $points;
            $isQualityOverridden = $qualityScore !== null;
            $displayQuality = $qualityScore ? $qualityScore->quality_score : null;

            if ($roleKey === 'producer' || $roleKey === 'manager_distribusi') {
                $teamAvgQuality = KpiQualityScore::where('music_episode_id', $episode->id)
                    ->whereNotIn('workflow_step', ['producer', 'manager_distribusi', 'program_manager'])
                    ->avg('quality_score');
                
                if ($teamAvgQuality) {
                    $effectivePoints = round($teamAvgQuality, 1);
                    $displayQuality = $effectivePoints;
                    $isQualityOverridden = true;
                }
            } else if ($qualityScore) {
                $effectivePoints = $qualityScore->quality_score;
            }

            $totalPoints += $effectivePoints;
            // $maxPoints is no longer accumulated here per-assignment. We will calculate it globally based on Total Expected Episodes to support > 100% backup logic.

            $items[] = [
                'episode_id' => $episode->id,
                'program_name' => ($episode->program->name ?? 'Unknown') . ' (Music)',
                'episode_number' => $episode->episode_number,
                'role' => $roleKey,
                'role_label' => $this->getRoleLabel($roleKey),
                'is_backup' => $isBackup,
                'backup_note' => $isBackup ? "Backup pekerjaan " . $this->getRoleLabel($roleKey) : null,
                'deadline' => $deadlineDate ? Carbon::parse($deadlineDate)->toIso8601String() : null,
                'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
                'status' => $status,
                'points' => $effectivePoints,
                'original_points' => $points,
                'is_overridden' => $isQualityOverridden,
                'max_points' => $setting->points_on_time,
                'quality_score' => $displayQuality,
                'quality_max' => $setting->quality_max,
                'program_type' => 'musik',
            ];
        }
    }

    /**
     * Check if this is backup work (user role doesn't match work role)
     */
    private function isBackupWork(User $user, string $workRole): bool
    {
        $normalizedUserRole = strtolower(str_replace([' ', '&', '-'], ['_', '', '_'], $user->role ?? ''));

        $roleMapping = [
            'kreatif' => ['creative', 'kreatif'],
            'produksi' => ['production', 'produksi', 'setting'],
            'editor' => ['editor'],
            'editor_promosi' => ['editor_promosi', 'editor_promotion', 'editorpromosi'],
            'design_grafis' => ['design_grafis', 'designgrafis', 'graphic_design'],
            'quality_control' => ['quality_control', 'qualitycontrol', 'qc'],
            'broadcasting' => ['broadcasting'],
            'promotion' => ['promotion', 'promosi'],
            'producer' => ['producer', 'director', 'manager_program'],
            'art_set_design' => ['production_team', 'art_set', 'property'],
            'art_set_design_return' => ['production_team', 'art_set', 'property'],
            'produksi_setting' => ['production_team', 'produksi'],
            'produksi_syuting' => ['production_team', 'produksi'],
        ];

        $expectedRoles = $roleMapping[$workRole] ?? [$workRole];
        return !in_array($normalizedUserRole, $expectedRoles);
    }

    /**
     * Get deadline for a specific work record
     */
    private function getDeadlineForWork($work, $episode, string $roleKey, string $category = 'regular'): ?string
    {
        // First check if work has its own deadline field
        if (isset($work->deadline) && $work->deadline) {
            return $work->deadline;
        }

        // Otherwise calculate from air_date using WorkflowStep constants
        if ($episode && $episode->air_date) {
            $daysBefore = \App\Constants\WorkflowStep::getDeadlineDaysForRole($roleKey, $category);
            return Carbon::parse($episode->air_date)->subDays($daysBefore)->toDateTimeString();
        }

        return null;
    }

    /**
     * Calculate office attendance statistics
     */
    public function calculateOfficeAttendance(Employee $employee, ?int $month, int $year): array
    {
        $query = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        $attendances = $query->get();

        $stats = [
            'present_ontime' => 0,
            'present_late' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'sick_leave' => 0,
        ];

        $totalWorkHours = 0;
        $totalLateMinutes = 0;
        $dailyData = [];

        foreach ($attendances as $att) {
            // Skip weekends as per user request
            if ($att->date) {
                $date = \Carbon\Carbon::parse($att->date);
                if ($date->isSaturday() || $date->isSunday()) {
                    continue;
                }
            }

            $status = $att->status ?? 'absent';
            
            // Map permission to absent as per user request (remove izin)
            $statsStatus = $status;
            if ($status === 'permission') {
                $statsStatus = 'absent';
            }

            if (isset($stats[$statsStatus])) {
                $stats[$statsStatus]++;
            }
            $totalWorkHours += $att->work_hours ?? 0;
            $totalLateMinutes += $att->late_minutes ?? 0;

            $dailyData[] = [
                'date' => \Carbon\Carbon::parse($att->date)->format('Y-m-d'),
                'status' => $status,
                'check_in' => $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i:s') : null,
                'check_out' => $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('H:i:s') : null,
                'work_hours' => $att->work_hours,
                'late_minutes' => $att->late_minutes,
            ];
        }

        $total = array_sum($stats);
        $presentTotal = $stats['present_ontime'] + $stats['present_late'];

        return [
            'summary' => $stats,
            'total_days' => $total,
            'present_total' => $presentTotal,
            'present_percentage' => $total > 0 ? round(($presentTotal / $total) * 100, 1) : 0,
            'on_time_percentage' => $presentTotal > 0 ? round(($stats['present_ontime'] / $presentTotal) * 100, 1) : 0,
            'avg_work_hours' => $presentTotal > 0 ? round($totalWorkHours / $presentTotal, 2) : 0,
            'total_work_hours' => round($totalWorkHours, 2),
            'total_late_minutes' => $totalLateMinutes,
            'daily_data' => $dailyData,
        ];
    }

    /**
     * Calculate morning reflection attendance statistics
     */
    public function calculateReflectionAttendance(Employee $employee, ?int $month, int $year): array
    {
        $query = MorningReflectionAttendance::where('employee_id', $employee->id)
            ->whereYear('date', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        $attendances = $query->get();

        $stats = [
            'Hadir' => 0,
            'Terlambat' => 0,
            'Absen' => 0,
            'Cuti' => 0,
        ];

        $dailyData = [];

        foreach ($attendances as $att) {
            // Skip weekends as per user request
            if ($att->date) {
                $date = \Carbon\Carbon::parse($att->date);
                if ($date->isSaturday() || $date->isSunday()) {
                    continue;
                }
            }

            $status = $att->status ?? 'Absen';
            
            // Map izin to Absen as per user request
            $statsStatus = $status;
            if (strtolower($status) === 'izin') {
                $statsStatus = 'Absen';
            }

            if (isset($stats[$statsStatus])) {
                $stats[$statsStatus]++;
            }

            $dailyData[] = [
                'date' => \Carbon\Carbon::parse($att->date)->format('Y-m-d'),
                'status' => $status,
                'join_time' => $att->join_time ? \Carbon\Carbon::parse($att->join_time)->format('H:i:s') : null,
                'attendance_method' => $att->attendance_method,
            ];
        }

        $total = array_sum($stats);
        $hadirTotal = $stats['Hadir'] + $stats['Terlambat'];

        return [
            'summary' => $stats,
            'total_days' => $total,
            'present_total' => $hadirTotal,
            'present_percentage' => $total > 0 ? round(($hadirTotal / $total) * 100, 1) : 0,
            'on_time_percentage' => $total > 0 ? round(($stats['Hadir'] / $total) * 100, 1) : 0,
            'daily_data' => $dailyData,
        ];
    }

    /**
     * Calculate overall KPI score (0-100+)
     */
    public function calculateOverallScore(User $user, Employee $employee, ?int $month, int $year): array
    {
        $workPoints = $this->calculateWorkPoints($user, $month, $year);
        $workPercentage = $workPoints['percentage'] ?? 0;

        // Overall score is purely based on work performance
        // Attendance data is shown as detailed statistics only (no points)
        $score = min(round($workPercentage), 150); // Cap at 150% (backup work can exceed 100)

        $label = 'Poor';
        $color = '#ef4444';
        if ($score >= 90) { $label = 'Excellent'; $color = '#22c55e'; }
        elseif ($score >= 70) { $label = 'Good'; $color = '#84cc16'; }
        elseif ($score >= 50) { $label = 'Average'; $color = '#eab308'; }
        elseif ($score >= 30) { $label = 'Below Average'; $color = '#f97316'; }

        return [
            'score' => $score,
            'label' => $label,
            'color' => $color,
            'work_percentage' => $workPercentage,
        ];
    }

    /**
     * Get role label for display
     */
    private function getRoleLabel(string $role): string
    {
        $labels = [
            'producer' => 'Producer (Approval)',
            'kreatif' => 'Creative (Script)',
            'tim_setting_coord' => 'Koordinator Setting',
            'tim_vocal_coord' => 'Koordinator Vocal',
            'tim_syuting_coord' => 'Koordinator Syuting',
            'art_set_design' => 'Art & Set (Alat Keluar)',
            'art_set_design_return' => 'Art & Set (Alat Masuk)',
            'editor' => 'Editor Video Program',
            'editor_promosi' => 'Editor Promosi (Highlight IG)',
            'design_grafis' => 'Design Grafis',
            'quality_control' => 'QC',
            'broadcasting' => 'Broadcasting',
            'promotion' => 'Promosi (BTS Photo)',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'promosi_syuting' => 'Promosi Syuting (Highlight IG dsb)',
            'manager_distribusi' => 'Manager Distribusi (QC Editor)',
        ];

        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
