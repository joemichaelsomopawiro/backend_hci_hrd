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
        $waitingCount = 0;

        // Get all program regular episodes this user worked on
        $this->collectPrWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user);

        // Get Art & Set Properti equipment loan points (Alat Keluar & Alat Masuk)
        $this->collectArtSetEquipmentPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user);

        // Get all music program episodes this user worked on
        $this->collectMusicWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user);

        $totalTasks = $onTimeCount + $lateCount + $notDoneCount + $waitingCount;
        $percentage = $maxPoints > 0 ? round(($totalPoints / $maxPoints) * 100, 1) : 0;

        return [
            'total_points' => $totalPoints,
            'max_points' => $maxPoints,
            'percentage' => $percentage,
            'breakdown' => [
                'on_time' => $onTimeCount,
                'late' => $lateCount,
                'not_done' => $notDoneCount,
                'waiting' => $waitingCount,
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
    private function collectPrWorkPoints(int $userId, ?int $month, int $year, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount, User $user): void
    {
        // Map work models to their role keys and completion tracking fields
        $workModels = [
            'kreatif' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [3],
                'role_label' => ['Kreatif', 'Creative', 'kreatif', 'creative'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'production_crew' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [5],
                'role_label' => ['qc', 'quality_control', 'shooting_team', 'setting_team', 'shooting_coordinator', 'setting_coordinator', 'Koordinator Setting', 'Anggota setting', 'Koordinator Syuting', 'Anggota Syuting', 'Setting Team', 'Shooting Team', 'Setting', 'Syuting', 'Shooting', 'Cameraman', 'Lighting', 'Sound Syuting'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'editor' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [6],
                'role_label' => ['Editor', 'editor'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'editor_promosi' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [6],
                'role_label' => ['Editor Promosi', 'Editor Promotion', 'editor_promosi', 'editor_promotion'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'design_grafis' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [6],
                'role_label' => ['Design Grafis', 'Graphic Designer', 'Graphic Design', 'design_grafis'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'quality_control' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [8],
                'role_label' => ['QC', 'Quality Control', 'qc', 'quality_control'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'distribution_manager_qc' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [7],
                'role_label' => ['Distribution Manager', 'distribution_manager', 'Manager Distribusi', 'manager_distribusi'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'broadcasting' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [9],
                'role_label' => ['Broadcasting', 'broadcasting'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'promotion_shooting' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [5],
                'role_label' => ['Promosi', 'Promotion', 'promosi', 'promotion'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'promotion' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [10],
                'role_label' => ['Promosi', 'Promotion', 'promosi', 'promotion'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
            'producer' => [
                'model' => PrEpisodeWorkflowProgress::class,
                'workflow_step' => [2],
                'role_label' => ['Producer', 'producer'],
                'completed_field' => 'completed_at',
                'status_completed' => ['completed'],
            ],
        ];

        $processedProgs = [];

        foreach ($workModels as $roleKey => $config) {
            $model = $config['model'];
            $query = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model))
                ? $model::withTrashed()
                : $model::query();

            $query->with([
                'episode' => fn($q) => $q->withTrashed(), 
                'episode.program' => fn($q) => $q->withTrashed()
            ]);
            
            $query->whereIn('workflow_step', $config['workflow_step'])
                  ->where(function ($q) use ($userId, $roleKey, $config) {
                      // 1. Explicitly assigned to the workflow step
                      $q->where('assigned_user_id', $userId);
                      
                      // 2. Logic for Producer (Sees all episodes in their programs)
                      if ($roleKey === 'producer') {
                          $q->orWhereHas('episode.program', fn($pq) => $pq->where('producer_id', $userId));
                      }

                      // 3. Logic for Creative, Editor, etc. (They usually work on the whole program)
                      $isProgramLevel = in_array($roleKey, ['kreatif', 'editor', 'editor_promosi', 'design_grafis', 'quality_control', 'broadcasting', 'promotion', 'promotion_shooting', 'distribution_manager_qc']);
                      if ($isProgramLevel) {
                        $q->orWhereHas('episode.program.crews', function ($pq) use ($userId, $config) {
                            $roleLabels = (array)$config['role_label'];
                            $pq->where('user_id', $userId)->whereIn('role', $roleLabels);
                        });
                      }

                      // 4. Stricter logic for Production Crew (Step 5): Only assigned or bundled episodes
                      if ($roleKey === 'production_crew') {
                          $q->orWhereHas('episode.crews', fn($ec) => $ec->where('user_id', $userId))
                            ->orWhereHas('episode.productionWork.equipmentLoans.produksiWorks.episode.crews', function ($bc) use ($userId) {
                                $bc->where('user_id', $userId); // Found via bundled episodes
                            });
                      }
                  });

            // Filter by date using the task's deadline_at, or fallback to episode air_date if deadline is missing
            $query->where(function ($q) use ($year, $month, $roleKey) {
                // 1. Primary filter: check the workflow record's deadline
                $q->where(function ($sq) use ($year, $month) {
                    $sq->whereYear('deadline_at', $year);
                    if ($month) $sq->whereMonth('deadline_at', $month);
                })->orWhere(function ($sq) use ($year, $month) {
                    // 2. Secondary filter: check episode air_date if deadline is missing
                    $sq->whereNull('deadline_at')
                       ->whereHas('episode', function ($eq) use ($year, $month) {
                           $eq->whereYear('air_date', $year);
                           if ($month) $eq->whereMonth('air_date', $month);
                       });
                });

                // 3. SPECIAL Case for Step 5 (Shooting/Promotion): Check shooting date
                if (in_array($roleKey, ['promotion_shooting', 'production_crew'])) {
                    $q->orWhereHas('episode', function ($eq) use ($year, $month) {
                        $eq->whereYear('production_date', $year);
                        if ($month) $eq->whereMonth('production_date', $month);
                    });

                    // For promotion, also check their specific record's shooting_date
                    if ($roleKey === 'promotion_shooting') {
                        $q->orWhereHas('episode.promotionWork', function ($pq) use ($year, $month) {
                            $pq->whereYear('shooting_date', $year);
                            if ($month) $pq->whereMonth('shooting_date', $month);
                        });
                    }
                }
            });

            // Sort by deadline to consistently process the earliest episode for per-program grouping
            $works = $query->orderBy('deadline_at', 'asc')->get();
            $settingKeyMap = [
                'production_crew' => 'produksi_regular',
                'promotion_shooting' => 'promotion_regular'
            ];
            $settingKey = $settingKeyMap[$roleKey] ?? ($roleKey . '_regular');
            $setting = $settings->get($settingKey);

            // FALLBACK: Distribution Manager QC use Quality Control points if not explicitly defined
            if (!$setting && $roleKey === 'distribution_manager_qc') {
                $setting = $settings->get('quality_control_regular');
            }

            if (!$setting) continue;

            foreach ($works as $work) {
                $episode = $work->episode;
                if (!$episode) continue;

                // SPECIAL LOGIC: Producer "Receiving Program" (Step 2) is per PROGRAM, not per episode.
                // We only count the first one encountered in the list for this KPI period.
                $isPerProgram = ($roleKey === 'producer' && $work->workflow_step == 2);
                if ($isPerProgram) {
                    $progId = $episode->program_id;
                    if (isset($processedProgs[$progId])) {
                        continue; // Skip subsequent episodes for this program
                    }
                    $processedProgs[$progId] = true;
                }

                $completedAt = $work->{$config['completed_field']} ?? null;
                $deadline = $this->getDeadlineForWork($work, $episode, $roleKey);
                $isCompleted = in_array($work->status, $config['status_completed']);

                // SPECIAL CASES: Track completion via specific models instead of the shared workflow record
                if ($roleKey === 'editor') {
                    $editorWork = \App\Models\PrEditorWork::where('pr_episode_id', $episode->id)->first();
                    if ($editorWork) {
                        $isCompleted = ($editorWork->status === 'completed');
                        $completedAt = $isCompleted ? ($editorWork->completed_at ?? $editorWork->updated_at) : null;
                    } else {
                        $isCompleted = false; // Decoupled: If work record doesn't exist, it's NOT completed for this role
                        $completedAt = null;
                    }
                } else if ($roleKey === 'editor_promosi') {
                    $promoEditorWork = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episode->id)->first();
                    if ($promoEditorWork) {
                        $isCompleted = ($promoEditorWork->status === 'completed');
                        $completedAt = $isCompleted ? ($promoEditorWork->completed_at ?? $promoEditorWork->updated_at) : null;
                    } else {
                        $isCompleted = false;
                        $completedAt = null;
                    }
                } else if ($roleKey === 'design_grafis') {
                    $designWork = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $episode->id)->first();
                    if ($designWork) {
                        $isCompleted = ($designWork->status === 'completed' || $designWork->submitted_at !== null);
                        $completedAt = $designWork->submitted_at ?? ($isCompleted ? ($designWork->updated_at ?? now()) : null);
                    } else {
                        $isCompleted = false;
                        $completedAt = null;
                    }
                } else if ($roleKey === 'production_crew') {
                    $prodWork = \App\Models\PrProduksiWork::where('pr_episode_id', $episode->id)->first();
                    if ($prodWork) {
                        $isCompleted = ($prodWork->status === 'completed');
                        $completedAt = $isCompleted ? ($prodWork->updated_at ?? now()) : null;
                    } else {
                        $isCompleted = false;
                        $completedAt = null;
                    }
                } else if ($roleKey === 'promotion_shooting') {
                    $promoWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episode->id)->first();
                    if ($promoWork) {
                        $isCompleted = ($promoWork->status === 'completed');
                        $completedAt = $isCompleted ? ($promoWork->updated_at ?? now()) : null;
                    } else {
                        $isCompleted = false;
                        $completedAt = null;
                    }
                }

                // Filter for deleted/inactive programs
                $program = $episode->program;
                if ($program) {
                    $isInactive = in_array($program->status, ['inactive', 'deleted', 'concept_pending', 'draft']);
                    $isDeleted = $program->trashed();
                    
                    if (($isInactive || $isDeleted) && !$isCompleted) {
                        // Skip uncompleted tasks for inactive/deleted programs
                        continue;
                    }
                }


                // Determine user's actual role vs work role
                $isBackup = $this->isBackupWork($user, $roleKey);

                // Calculate points
                $status = 'waiting';
                $points = 0;

                if ($isCompleted && $completedAt) {
                    $completedAtTime = Carbon::parse($completedAt);
                    $deadlineTime = $deadline ? Carbon::parse($deadline) : null;
                    
                    if ($deadlineTime && $completedAtTime->lte($deadlineTime)) {
                        $status = 'on_time';
                        $points = $setting->points_on_time;
                        $onTimeCount++;
                    } else {
                        $status = 'late';
                        $points = $setting->points_late;
                        $lateCount++;
                    }
                } else if (!$isCompleted) {
                    $deadlineTime = $deadline ? Carbon::parse($deadline) : null;
                    $isOverdue = $deadlineTime && now()->gt($deadlineTime);
                    $daysOverdue = $isOverdue ? now()->diffInDays($deadlineTime) : 0;

                    if ($isOverdue && $daysOverdue > 7) {
                        $status = 'not_done';
                        $points = $setting->points_not_done;
                        $notDoneCount++;
                    } else {
                        // Still within grace period or not yet reached deadline
                        $status = 'waiting';
                        $points = 0;
                        $waitingCount++;
                    }
                }

                // Get quality score if exists
                $qualityScore = KpiQualityScore::where('employee_id', $userId)
                    ->where('pr_episode_id', $episode->id)
                    ->where('workflow_step', $roleKey)
                    ->first();

                $totalPoints += $qualityScore ? $qualityScore->quality_score : $points;
                
                // Backup work is a bonus and doesn't increase the target (max points)
                if (!$isBackup) {
                    $maxPoints += $setting->points_on_time;
                }

                $items[] = [
                    'episode_id' => $episode->id,
                    'program_name' => $episode->program->name ?? 'Unknown',
                    'episode_number' => $isPerProgram ? null : $episode->episode_number,
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
                    'max_points' => $isBackup ? 0 : $setting->points_on_time,
                    'quality_score' => $qualityScore ? $qualityScore->quality_score : null,
                    'quality_max' => $setting->quality_max,
                    'program_type' => 'regular',
                    'work_description' => $this->getWorkDescription($roleKey, $work),
                    'is_per_program' => $isPerProgram,
                ];
            }
        }
    }

    /**
     * Collect Art & Set Properti equipment loan points (Alat Keluar & Alat Masuk)
     */
    private function collectArtSetEquipmentPoints(int $userId, ?int $month, int $year, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount, User $user): void
    {
        // Only relevant if user is Art & Set Properti staff (the one who approves/checks the gear)
        $loans = \App\Models\EquipmentLoan::where('approver_id', $userId)
            ->with(['produksiWorks.episode.program'])
            ->get();

        if ($loans->isEmpty()) return;

        $keluarSetting = $settings->get('art_set_alat_keluar_regular');
        $masukSetting = $settings->get('art_set_alat_masuk_regular');

        if (!$keluarSetting || !$masukSetting) return;

        // Keep track of processed episode+action to avoid duplicates if an episode has multiple loans
        $processed = [];

        foreach ($loans as $loan) {
            foreach ($loan->produksiWorks as $prodWork) {
                $episode = $prodWork->episode;
                if (!$episode) continue;

                // Filter by date (using production_date as primarily relevant for equipment loans)
                $dateToCheck = $episode->production_date ?? $episode->air_date;
                if (!$dateToCheck) continue;

                $carbonDate = Carbon::parse($dateToCheck);
                if ($carbonDate->year != $year) continue;
                if ($month && $carbonDate->month != $month) continue;

                // 1. Calculate Alat Keluar (Equipment Out)
                $keluarKey = $episode->id . '_keluar';
                if (!isset($processed[$keluarKey])) {
                    // Deadline is the shooting date (production_date)
                    // If not set, fallback to 8 days before air_date
                    $keluarDeadline = $episode->production_date ? 
                        Carbon::parse($episode->production_date)->toDateTimeString() : 
                        ($episode->air_date ? Carbon::parse($episode->air_date)->copy()->subDays(8)->toDateTimeString() : null);
                    
                    $this->processEquipmentAction(
                        $userId, $episode, 'art_set_alat_keluar', 'Alat Keluar', 
                        $loan->loan_date ?? (in_array($loan->status, ['active', 'return_requested', 'completed', 'returned']) ? ($loan->loan_date ?? $loan->updated_at) : null), 
                        $keluarDeadline, $keluarSetting,
                        $items, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount,
                        $loan, $this->isBackupWork($user, 'art_set_alat_keluar')
                    );
                    $processed[$keluarKey] = true;
                }

                // 2. Calculate Alat Masuk (Equipment Return)
                $masukKey = $episode->id . '_masuk';
                if (!isset($processed[$masukKey])) {
                    // Deadline for return is 8 days before air_date as per user request
                    // We use copy() to ensure we don't accidentally modify any shared instance
                    $masukDeadline = $episode->air_date ? Carbon::parse($episode->air_date)->copy()->subDays(8)->toDateTimeString() : null;
                    
                    $this->processEquipmentAction(
                        $userId, $episode, 'art_set_alat_masuk', 'Alat Masuk', 
                        $loan->return_date ?? (in_array($loan->status, ['returned', 'completed']) ? ($loan->return_date ?? $loan->updated_at) : null), $masukDeadline, $masukSetting,
                        $items, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount,
                        $loan, $this->isBackupWork($user, 'art_set_alat_masuk')
                    );
                    $processed[$masukKey] = true;
                }
            }
        }
    }

    /**
     * Helper to process a specific equipment action (borrow/return)
     */
    private function processEquipmentAction(
        int $userId, $episode, string $roleKey, string $actionLabel,
        $completedAt, $deadline, $setting,
        array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount,
        $loan, bool $isBackup
    ): void {
        $status = 'not_done';
        $points = $setting->points_not_done;
        $isCompleted = (bool)$completedAt;

        if ($isCompleted) {
            $completedAtTime = Carbon::parse($completedAt);
            $deadlineTime = $deadline ? Carbon::parse($deadline)->endOfDay() : null;
            
            if (!$deadlineTime || $completedAtTime->lte($deadlineTime)) {
                $status = 'on_time';
                $points = $setting->points_on_time;
                $onTimeCount++;
            } else {
                $status = 'late';
                $points = $setting->points_late;
                $lateCount++;
            }
        } else {
            $deadlineTime = $deadline ? Carbon::parse($deadline)->endOfDay() : null;
            $isOverdue = $deadlineTime && now()->gt($deadlineTime);
            $daysOverdue = $isOverdue ? now()->diffInDays($deadlineTime) : 0;

            if ($isOverdue && $daysOverdue > 7) {
                $status = 'not_done';
                $points = $setting->points_not_done;
                $notDoneCount++;
            } else {
                $status = 'waiting';
                $points = 0;
                $waitingCount++;
            }
        }

        $totalPoints += $points;

        // Backup work is a bonus and doesn't increase the target (max points)
        if (!$isBackup) {
            $maxPoints += $setting->points_on_time;
        }

        $items[] = [
            'episode_id' => $episode->id,
            'program_name' => $episode->program->name ?? 'Unknown',
            'episode_number' => $episode->episode_number,
            'role' => $roleKey,
            'role_label' => $this->getRoleLabel($roleKey),
            'is_backup' => false,
            'deadline' => $deadline ? Carbon::parse($deadline)->toIso8601String() : null,
            'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
            'status' => $status,
            'points' => $points,
            'original_points' => $points,
            'is_overridden' => false,
            'max_points' => $isBackup ? 0 : $setting->points_on_time,
            'program_type' => 'regular',
            'work_description' => $this->getWorkDescription($roleKey, $loan),
        ];
    }

    /**
     * Collect Music Program work points
     */
    private function collectMusicWorkPoints(int $userId, ?int $month, int $year, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount, User $user): void
    {
        $workModels = [
            'musik_arr' => [
                'model' => \App\Models\MusicArrangement::class,
                'user_field' => 'created_by',
                'completed_field' => 'submitted_at',
                'status_completed' => ['submitted', 'approved', 'rejected'],
            ],
            'sound_eng' => [
                'model' => \App\Models\MusicArrangement::class,
                'user_field' => 'sound_engineer_helper_id',
                'completed_field' => 'sound_engineer_help_at',
                'status_completed' => ['submitted', 'approved', 'rejected'],
            ],
            'editor' => [
                'model' => \App\Models\EditorWork::class,
                'user_field' => 'created_by',
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved', 'completed', 'reviewed'],
            ],
            'design_grafis' => [
                'model' => \App\Models\DesignGrafisWork::class,
                'user_field' => 'assigned_to',
                'completed_field' => 'submitted_at',
                'status_completed' => ['approved', 'completed', 'submitted', 'reviewed'],
            ],
            'quality_control' => [
                'model' => \App\Models\QualityControl::class,
                'user_field' => 'qc_by',
                'completed_field' => 'qc_completed_at',
                'status_completed' => ['completed', 'approved'],
            ],
            'broadcasting' => [
                'model' => \App\Models\BroadcastingWork::class,
                'user_field' => 'created_by',
                'completed_field' => 'published_at',
                'status_completed' => ['completed', 'published'],
            ],
            'promotion' => [
                'model' => \App\Models\PromotionWork::class,
                'user_field' => 'originally_assigned_to',
                'completed_field' => 'reviewed_at',
                'status_completed' => ['published', 'approved'],
            ],
        ];

        foreach ($workModels as $roleKey => $config) {
            $model = $config['model'];
            $query = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model))
                ? $model::withTrashed()
                : $model::query();

            $query->with([
                'episode' => fn($q) => $q->withTrashed(), 
                'episode.program' => fn($q) => $q->withTrashed()
            ])
            ->where($config['user_field'], $userId);

            // Filter by date using the calculated deadline (air_date - X days)
            $deadlineDays = \App\Constants\WorkflowStep::getDeadlineDaysForRole($roleKey);
            $query->whereHas('episode', function ($q) use ($month, $year, $deadlineDays) {
                $q->withTrashed()
                  ->whereRaw("YEAR(DATE_SUB(air_date, INTERVAL ? DAY)) = ?", [$deadlineDays, $year]);
                
                if ($month) {
                    $q->whereRaw("MONTH(DATE_SUB(air_date, INTERVAL ? DAY)) = ?", [$deadlineDays, $month]);
                }
            });

            $works = $query->get();
            $settingKey = $roleKey . '_musik';
            $setting = $settings->get($settingKey);

            if (!$setting) continue;

            foreach ($works as $work) {
                $episode = $work->episode;
                if (!$episode) continue;

                $completedAt = $work->{$config['completed_field']} ?? null;
                $deadline = $this->getDeadlineForWork($work, $episode, $roleKey);
                $isCompleted = in_array($work->status, $config['status_completed']);
                
                // Filter for deleted/inactive programs
                $program = $episode->program;
                if ($program) {
                    // For music programs, any status other than approved/active might be considered "not yet ready"
                    // but we focus on explicitly stopped ones.
                    $isInactive = in_array($program->status, ['rejected', 'on_hold', 'draft', 'inactive']);
                    $isDeleted = $program->trashed();

                    if (($isInactive || $isDeleted) && !$isCompleted) {
                        continue;
                    }
                }

                // Sound engineer check specifically Needs SE Help
                if ($roleKey === 'sound_eng' && !$work->needs_sound_engineer_help) continue;

                $qualityScore = KpiQualityScore::where('employee_id', $userId)
                    ->where('music_episode_id', $episode->id)
                    ->where('workflow_step', $roleKey)
                    ->first();

                $isBackup = $this->isBackupWork($user, $roleKey);

                $status = 'not_done';
                $points = $setting->points_not_done;

                if ($isCompleted && $completedAt) {
                    $completedAtTime = Carbon::parse($completedAt);
                    $deadlineTime = $deadline ? Carbon::parse($deadline) : null;

                    if ($deadlineTime && $completedAtTime->lte($deadlineTime)) {
                        $status = 'on_time';
                        $points = $setting->points_on_time;
                        $onTimeCount++;
                    } else {
                        $status = 'late';
                        $points = $setting->points_late;
                        $lateCount++;
                    }
                } else if (!$isCompleted) {
                    $deadlineTime = $deadline ? Carbon::parse($deadline) : null;
                    $isOverdue = $deadlineTime && now()->gt($deadlineTime);
                    $daysOverdue = $isOverdue ? now()->diffInDays($deadlineTime) : 0;

                    if ($isOverdue && $daysOverdue > 7) {
                        $status = 'not_done';
                        $points = $setting->points_not_done;
                        $notDoneCount++;
                    } else {
                        // Still within grace period or not yet reached deadline
                        $status = 'waiting';
                        $points = 0;
                        $waitingCount++;
                    }
                }

                $qualityScore = KpiQualityScore::where('employee_id', $userId)
                    ->where('music_episode_id', $episode->id)
                    ->where('workflow_step', $roleKey)
                    ->first();

                $totalPoints += $qualityScore ? $qualityScore->quality_score : $points;
                
                // Backup work is a bonus and doesn't increase the target (max points)
                if (!$isBackup) {
                    $maxPoints += $setting->points_on_time;
                }

                $items[] = [
                    'episode_id' => $episode->id,
                    'program_name' => ($episode->program->name ?? 'Unknown') . ' (Music)',
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
                    'max_points' => $isBackup ? 0 : $setting->points_on_time,
                    'quality_score' => $qualityScore ? $qualityScore->quality_score : null,
                    'quality_max' => $setting->quality_max,
                    'program_type' => 'musik',
                ];
            }
        }
    }

    /**
     * Check if this is backup work (user role doesn't match work role)
     */
    private function isBackupWork(User $user, string $workRole): bool
    {
        $normalizedUserRole = strtolower(str_replace([' ', '&', '-'], ['_', '', '_'], $user->role ?? ''));

        $roleMapping = [
            'producer' => ['producer'],
            'kreatif' => ['creative', 'kreatif'],
            'production_crew' => ['production', 'produksi', 'setting', 'shooting_team', 'setting_team'],
            'editor' => ['editor'],
            'editor_promosi' => ['editor_promosi', 'editor_promotion', 'editorpromosi'],
            'design_grafis' => ['design_grafis', 'designgrafis', 'graphic_design'],
            'quality_control' => ['quality_control', 'qualitycontrol', 'qc'],
            'broadcasting' => ['broadcasting'],
            'promotion_shooting' => ['promotion', 'promosi'],
            'promotion' => ['promotion', 'promosi'],
            'musik_arr' => ['music_arranger', 'music', 'musik'],
            'sound_eng' => ['sound_engineer', 'sound'],
            'art_set_alat_keluar' => ['art_set_properti', 'art_set', 'art'],
            'art_set_alat_masuk' => ['art_set_properti', 'art_set', 'art'],
            'distribution_manager_qc' => ['distribution_manager', 'manager_distribusi'],
        ];

        $expectedRoles = $roleMapping[$workRole] ?? [$workRole];
        return !in_array($normalizedUserRole, $expectedRoles);
    }

    /**
     * Get deadline for a specific work record
     */
    private function getDeadlineForWork($work, $episode, string $roleKey): ?string
    {
        // First check if work has its own deadline field
        if (isset($work->deadline) && $work->deadline) {
            return $work->deadline;
        }

        if (isset($work->deadline_at) && $work->deadline_at) {
            return $work->deadline_at;
        }

        // SPECIAL CASE: Step 5 (Shooting & Promotion) deadline is the production/shooting date
        if (in_array($roleKey, ['promotion_shooting', 'production_crew'])) {
            // For promotion, prioritize their specific shooting_date field
            if ($roleKey === 'promotion_shooting') {
                $promoWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episode->id)->first();
                if ($promoWork && $promoWork->shooting_date) {
                    return Carbon::parse($promoWork->shooting_date)->toDateTimeString();
                }
            }

            // Fallback to episode's production_date
            if ($episode && $episode->production_date) {
                return Carbon::parse($episode->production_date)->toDateTimeString();
            }
        }

        // Otherwise calculate from air_date using WorkflowStep constants
        if ($episode && $episode->air_date) {
            $daysBefore = \App\Constants\WorkflowStep::getDeadlineDaysForRole($roleKey);
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
            if ($att->date && ($att->date->isSaturday() || $att->date->isSunday())) {
                continue;
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
                'date' => $att->date->format('Y-m-d'),
                'status' => $status,
                'check_in' => $att->check_in ? $att->check_in->format('H:i:s') : null,
                'check_out' => $att->check_out ? $att->check_out->format('H:i:s') : null,
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
            if ($att->date && ($att->date->isSaturday() || $att->date->isSunday())) {
                continue;
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
                'date' => $att->date->format('Y-m-d'),
                'status' => $status,
                'join_time' => $att->join_time ? $att->join_time->format('H:i:s') : null,
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
     * Get detailed English description of what the task involves
     */
    private function getWorkDescription(string $role, $work = null): string
    {
        $descriptions = [
            'producer' => 'Receiving Program', // Default for Step 2
            'kreatif' => 'Scriptwriting & Shooting Arrangements',
            'production_crew' => 'Shooting and upload file links',
            'art_set_alat_keluar' => 'Equipment Loan & Shooting (Art Set - Checkout)',
            'art_set_alat_masuk' => 'Equipment Loan & Shooting (Art Set - Return)',
            'editor' => 'Content Editing (Main Episode)',
            'editor_promosi' => 'Content Editing (Promotion Content)',
            'design_grafis' => 'Content Editing (Graphic Design)',
            'quality_control' => 'Final Quality Check',
            'broadcasting' => 'Content Upload',
            'promotion' => 'Content Sharing',
            'musik_arr' => 'Music composition and arrangement',
            'sound_eng' => 'Audio engineering and sound mixing',
        ];

        // Specific handling for Producer steps & Step 5 roles
        if ($role === 'producer' && $work && isset($work->workflow_step)) {
            if ($work->workflow_step == 2) return 'Receiving Program';
        }

        // Handle the case where promotion is part of step 5 syuting
        if ($role === 'promotion' && $work && isset($work->workflow_step) && $work->workflow_step == 5) {
            return 'Equipment Loan & Shooting (Promotion)';
        }

        return $descriptions[$role] ?? 'General task completion';
    }

    /**
     * Get role label for display
     */
    private function getRoleLabel(string $role): string
    {
        $labels = [
            'producer' => 'Producer',
            'kreatif' => 'Creative',
            'produksi' => 'Production / Setting',
            'art_set_design' => 'Art & Set Properti',
            'art_set_alat_keluar' => 'Art & Set Properti (Alat Keluar)',
            'art_set_alat_masuk' => 'Art & Set Properti (Alat Masuk)',
            'editor' => 'Editor Video',
            'editor_promosi' => 'Editor Promosi',
            'design_grafis' => 'Design Grafis',
            'quality_control' => 'Quality Control',
            'broadcasting' => 'Broadcasting',
            'promotion' => 'Promotion',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
        ];

        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
