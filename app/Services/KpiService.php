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

        // Hardcoded overrides for Music Art Set Properti as requested by user
        $musicArtSetKeluar = (object)[
            'points_on_time' => 2,
            'points_late' => 1,
            'points_not_done' => -5,
            'quality_max' => 0
        ];
        $musicArtSetMasuk = (object)[
            'points_on_time' => 2, // Assuming return is also 2/1 if requested or keep default
            'points_late' => 1,
            'points_not_done' => -5,
            'quality_max' => 0
        ];

        $workItems = [];
        $totalPoints = 0;
        $maxPoints = 0;
        $onTimeCount = 0;
        $lateCount = 0;
        $notDoneCount = 0;
        $waitingCount = 0;

        // Total points will be calculated by summing points_on_time of all expected assignments
        // to support accurate percentage even for granular roles with different point values
        $maxPoints = 0; 

        // Get all program regular episodes this user worked on
        $this->collectPrWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user);

        // Get Art & Set Properti equipment loan points (Alat Keluar & Alat Masuk)
        $this->collectArtSetEquipmentPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user);

        // Get all music program episodes this user worked on
        $this->collectMusicWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user);

        // We calculate maxPoints globally based on expected target per role (Total Aired Episodes * max_point per episode)
        // For backup workloads, they add to totalPoints but DO NOT increase maxPoints, creating possibilities of >100%
        
        $totalTasks = $onTimeCount + $lateCount + $notDoneCount + $waitingCount;
        
        // Final safeguard if no tasks but somehow scored (should not normally happen with the above logic)
        if ($maxPoints == 0 && $totalPoints > 0) {
            $maxPoints = $totalPoints; 
        }
        $percentage = $maxPoints > 0 ? round(($totalPoints / $maxPoints) * 100, 1) : 0;

        // NEW: Calculate Yearly Target Points based on 52 episodes (as per User Excel)
        // Strictly focus ONLY on Program Musik roles as requested
        $yearlyTargetPoints = 0;
        $uniqueRoles = collect($workItems)->where('program_type', 'musik')->pluck('role')->unique();

        foreach ($uniqueRoles as $role) {
            $setting = $settings->get($role . '_musik');
            if ($setting) {
                // For yearly view, the target for Music Program is (Points per Episode * 52)
                $yearlyTargetPoints += ($setting->points_on_time * 52);
            }
        }

        return [
            'total_points' => $totalPoints,
            'max_points' => $maxPoints,
            'percentage' => $percentage,
            'yearly_target_points' => $yearlyTargetPoints, 
            'yearly_target_percentage' => $yearlyTargetPoints > 0 ? round(($totalPoints / $yearlyTargetPoints) * 100, 1) : 0,
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

            // NEW: Use Music settings if episode belongs to a music program
            $musicSetting = $settings->get($roleKey . '_musik');
            
            foreach ($works as $work) {
                $episode = $work->episode;
                if (!$episode) continue;

                $program = $episode->program;
                $isMusic = ($program && $program->category === 'musik');
                $currentSetting = ($isMusic && $musicSetting) ? $musicSetting : $setting;

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
                $deadline = $this->getDeadlineForWork($work, $episode, $roleKey, $isMusic ? 'musik' : 'regular');
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
                        $points = $currentSetting->points_on_time;
                        $onTimeCount++;
                    } else {
                        $status = 'late';
                        $points = $currentSetting->points_late;
                        $lateCount++;
                    }
                } else if (!$isCompleted) {
                    $deadlineTime = $deadline ? Carbon::parse($deadline) : null;
                    $isOverdue = $deadlineTime && now()->gt($deadlineTime);
                    $daysOverdue = $isOverdue ? now()->diffInDays($deadlineTime) : 0;

                    if ($isOverdue && $daysOverdue > 7) {
                        // NEW: Some roles don't have penalty if specifically requested (QC/Dist Manager/Setting/Syuting)
                        $noPenaltyRoles = ['quality_control', 'distribution_manager_qc', 'production_crew'];
                        if (in_array($roleKey, $noPenaltyRoles) && $isMusic) {
                            $status = 'waiting_late';
                            $points = 0;
                            $waitingCount++;
                        } else {
                            $status = 'not_done';
                            $points = $currentSetting->points_not_done;
                            $notDoneCount++;
                        }
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

                // Integration of Quality Score (1-5 point) in addition to Speed Point (3/1/-5)
                // If qualityScore exists, we add it to the base speed points
                $finalPoints = $points;
                if ($qualityScore) {
                    // Safety check: if speed points are negative (Abandoned), should we still add quality?
                    // Usually yes, even if late/abandoned, the work quality itself can be evaluated.
                    $finalPoints += $qualityScore->quality_score;
                }

                $totalPoints += $finalPoints;
                
                // Backup work is a bonus and doesn't increase the target (max points)
                if (!$isBackup) {
                    $maxPoints += $currentSetting->points_on_time;
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
                    'max_points' => $isBackup ? 0 : $currentSetting->points_on_time,
                    'quality_score' => $qualityScore ? $qualityScore->quality_score : null,
                    'quality_max' => $currentSetting->quality_max,
                    'program_type' => $isMusic ? 'musik' : 'regular',
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
        // Part 1: Regular Program (EquipmentLoan)
        $loans = \App\Models\EquipmentLoan::where('approver_id', $userId)
            ->with(['produksiWorks.episode.program'])
            ->get();
        
        // Part 2: Music Program (ProductionEquipment)
        $musicLoans = \App\Models\ProductionEquipment::where('approved_by', $userId)
            ->with(['episode.program'])
            ->get();

        $keluarSetting = $settings->get('art_set_alat_keluar_regular');
        $masukSetting = $settings->get('art_set_alat_masuk_regular');

        // Note: Regular loans might be missing settings, but that shouldn't block Music loans
        if ($loans->isNotEmpty() && $keluarSetting && $masukSetting) {
            foreach ($loans as $loan) {
                foreach ($loan->produksiWorks as $prodWork) {
                    $this->processArtSetLoan($loan, $prodWork->episode, $userId, $year, $month, $settings, $items, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user, $processed);
                }
            }
        }

        // Processing Music Loans (New)
        // Keep track of processed episode+action to avoid duplicates
        $processed = $processed ?? [];
        foreach ($musicLoans as $loan) {
            // Find all episodes linked to this equipment request (support multi-episode loans)
            $episodesToScan = [];
            if ($loan->episode_id) $episodesToScan[] = $loan->episode_id;
            
            if ($loan->request_group_id) {
                $groupEpisodes = \App\Models\ProductionEquipment::where('request_group_id', $loan->request_group_id)
                   ->pluck('episode_id')->filter()->unique()->toArray();
                $episodesToScan = array_unique(array_merge($episodesToScan, $groupEpisodes));
            }

            foreach ($episodesToScan as $scanId) {
                $episode = \App\Models\Episode::find($scanId);
                if (!$episode) continue;
                $this->processArtSetLoan($loan, $episode, $userId, $year, $month, $settings, $items, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount, $user, $processed, 'musik');
            }
        }
    }

    /**
     * Helper to process Art Set Loan and award points
     */
    private function processArtSetLoan($loan, $episode, $userId, $year, $month, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount, User $user, array &$processed, string $programType = 'regular'): void
    {
        // Filter by date (using production_date or air_date)
        $dateToCheck = $episode->production_date ?? $episode->air_date;
        if (!$dateToCheck) return;

        $carbonDate = Carbon::parse($dateToCheck);
        if ($carbonDate->year != $year) return;
        if ($month && $carbonDate->month != $month) return;

        $isMusic = ($programType === 'musik');
        
        // Use custom points for Music Program Art Set as requested (2 for on time, 1 for late)
        $keluarSetting = $isMusic ? (object)['points_on_time' => 2, 'points_late' => 1, 'points_not_done' => -5] : $settings->get('art_set_alat_keluar_regular');
        $masukSetting = $isMusic ? (object)['points_on_time' => 2, 'points_late' => 1, 'points_not_done' => -5] : $settings->get('art_set_alat_masuk_regular');

        if (!$keluarSetting || !$masukSetting) return;

        // 1. Calculate Alat Keluar (Equipment Out)
        $keluarKey = $episode->id . '_keluar_' . $programType;
        if (!isset($processed[$keluarKey])) {
            $keluarDeadline = $episode->production_date ? 
                Carbon::parse($episode->production_date)->toDateTimeString() : 
                ($episode->air_date ? Carbon::parse($episode->air_date)->copy()->subDays(8)->toDateTimeString() : null);
            
            // Completed At based on approved_at or loan_date
            $completedAt = $loan->approved_at ?? $loan->loan_date ?? (in_array($loan->status, ['active', 'approved', 'in_use', 'returned']) ? $loan->updated_at : null);

            $this->processEquipmentAction(
                $userId, $episode, 'art_set_alat_keluar', 'Alat Keluar', 
                $completedAt, $keluarDeadline, $keluarSetting,
                $items, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount,
                $loan, $this->isBackupWork($user, 'art_set_alat_keluar'), $programType
            );
            $processed[$keluarKey] = true;
        }

        // 2. Calculate Alat Masuk (Equipment Return)
        $masukKey = $episode->id . '_masuk_' . $programType;
        if (!isset($processed[$masukKey])) {
            $masukDeadline = $episode->air_date ? Carbon::parse($episode->air_date)->copy()->subDays(8)->toDateTimeString() : null;
            
            // For ProductionEquipment, use returned_at. For EquipmentLoan, use return_date.
            $completedAt = $loan->returned_at ?? $loan->return_date ?? (in_array($loan->status, ['returned', 'completed']) ? $loan->updated_at : null);

            $this->processEquipmentAction(
                $userId, $episode, 'art_set_alat_masuk', 'Alat Masuk', 
                $completedAt, $masukDeadline, $masukSetting,
                $items, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $waitingCount,
                $loan, $this->isBackupWork($user, 'art_set_alat_masuk'), $programType
            );
            $processed[$masukKey] = true;
        }
    }

    /**
     * Helper to process a specific equipment action (borrow/return)
     */
    private function processEquipmentAction(
        int $userId, $episode, string $roleKey, string $actionLabel,
        $completedAt, $deadline, $setting,
        array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount,
        $loan, bool $isBackup, string $programType = 'regular'
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
            'program_name' => ($episode->program->name ?? 'Unknown') . ($programType === 'musik' ? ' (Music)' : ''),
            'episode_number' => $episode->episode_number,
            'role' => $roleKey,
            'role_label' => $this->getDynamicRoleLabel($roleKey, $loan),
            'is_backup' => false,
            'deadline' => $deadline ? Carbon::parse($deadline)->toIso8601String() : null,
            'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
            'status' => $status,
            'points' => (float)$points,
            'original_points' => $points,
            'is_overridden' => false,
            'max_points' => $isBackup ? 0 : $setting->points_on_time,
            'program_type' => $programType,
            'work_description' => $this->getWorkDescription($roleKey, $loan),
        ];
    }

    /**
     * Get dynamic role label including team details if available
     */
    private function getDynamicRoleLabel(string $role, $loan = null): string
    {
        $base = $this->getRoleLabel($role);
        
        if (($role === 'art_set_alat_keluar' || $role === 'art_set_alat_masuk') && $loan) {
            $team = 'Team';
            if (isset($loan->team_type)) {
                $team = $loan->team_type === 'recording' ? 'Vokal' : ucfirst($loan->team_type);
            } elseif (isset($loan->crewLeader?->name)) {
                $team = $loan->crewLeader->name;
            }
            return "{$base} (Pinjam: {$team})";
        }
        
        return $base;
    }

    /**
     * Collect Music Program work points using the refined discovery engine and user-defined scoring rules
     */
    private function collectMusicWorkPoints($userId, $month, $year, $settings, array &$items, int &$totalPoints, int &$maxPoints, int &$onTimeCount, int &$lateCount, int &$notDoneCount, int &$waitingCount, User $user): void
    {
        // 0. PRE-DETECTION: Normalize role once for the entire discovery phase
        $normUserRole = str_replace([' ', '&', '-'], '_', strtolower($user->role ?? ''));
        $isArranger = str_contains($normUserRole, 'music') || str_contains($normUserRole, 'musik');
        $isCreative = str_contains($normUserRole, 'creative') || str_contains($normUserRole, 'kreatif');
        $isSoundEng = str_contains($normUserRole, 'sound') || str_contains($normUserRole, 'vocal');

        // 1. DISCOVERY ENGINE: Find all episodes where this user has any footprint
        $episodeScan = [];

        // Track A: Direct Music Arrangements (Created or Reviewed)
        $arrangements = \App\Models\MusicArrangement::where('created_by', $userId)
            ->orWhere('reviewed_by', $userId)
            ->get();
        
        foreach ($arrangements as $m) {
            $hasActivity = false;
            $dates = [$m->created_at, $m->song_approved_at, $m->arrangement_submitted_at, $m->reviewed_at, $m->submitted_at];
            foreach ($dates as $d) {
                if ($d && $d->year == $year && (!$month || $d->month == $month)) {
                    $hasActivity = true;
                    break;
                }
            }
            if ($hasActivity) {
                $episodeScan[$m->episode_id]['arrangement'] = $m;
            }
        }

        // Track B: Defined Deadlines in the period
        $deadlines = \App\Models\Deadline::with(['episode.program' => function($q) { $q->withTrashed(); }])
            ->where('assigned_user_id', $userId)
            ->whereYear('deadline_date', $year)
            ->when($month, fn($q) => $q->whereMonth('deadline_date', $month))
            ->get();
        
        foreach ($deadlines as $d) {
            $episodeScan[$d->episode_id]['deadlines'][] = $d;
        }

        // Track C: Team-Based Participation (Program Air Date)
        $teamParticipation = \App\Models\ProductionTeamMember::with(['assignment.episode.program' => function($q) { $q->withTrashed(); }])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();
            
        foreach ($teamParticipation as $member) {
            $ep = $member->assignment?->episode;
            if (!$ep) continue;
            
            // DISCOVERY FIX: Allow discovery by Air Date OR by Production Date OR by Current Month activity
            $isTargetMonth = ($ep->air_date && $ep->air_date->year == $year && (!$month || $ep->air_date->month == $month));
            $isProductionMonth = ($ep->production_date && $ep->production_date->year == $year && (!$month || $ep->production_date->month == $month));
            
            // ACTIVITY FALLBACK: If user has work on this episode that was updated/submitted in THE TARGET period, include it
            $hasActivityInPeriod = false;
            $editingCheck = \App\Models\SoundEngineerEditing::where('episode_id', $ep->id)->where('sound_engineer_id', $userId)->first();
            if ($editingCheck) {
                $checkDate = $editingCheck->submitted_at ?? $editingCheck->updated_at;
                if ($checkDate && $checkDate->year == $year && (!$month || $checkDate->month == $month)) {
                    $hasActivityInPeriod = true;
                }
            }

            if (!$isTargetMonth && !$isProductionMonth && !$hasActivityInPeriod) {
                continue;
            }
            $type = $member->assignment->team_type; // shooting, setting, recording
            $isCoord = $member->is_coordinator;
            
            $assignedRole = null;
            if ($type === 'recording') {
                $assignedRole = $isCoord ? 'tim_vocal_coord' : 'tim_vocal_member';
            } elseif ($type === 'setting') {
                $assignedRole = $isCoord ? 'tim_setting_coord' : 'tim_setting_member';
            } elseif ($type === 'shooting') {
                $assignedRole = $isCoord ? 'tim_syuting_coord' : 'tim_syuting_member';
            }

            if ($assignedRole) {
                $deadline = $ep->air_date->copy();
                if ($assignedRole === 'tim_vocal_coord' || $assignedRole === 'tim_vocal_member') {
                    $deadline->subDays(10);
                } else {
                    $deadline->subDays(8);
                }
                
                $episodeScan[$ep->id]['team_roles'][] = (object)[
                    'role' => $assignedRole, 
                    'deadline' => $deadline
                ];
            }
        }

        // Track D: Creative Work Activity (New)
        $creativeWorks = \App\Models\CreativeWork::where('created_by', $userId)->get();
        foreach ($creativeWorks as $cw) {
            $episodeScan[$cw->episode_id]['creative_work'] = $cw;
        }

        // Track E: Sound Engineer Editing Activity (PURE ACTIVITY BASED)
        $editingWorks = \App\Models\SoundEngineerEditing::where(function($q) use ($userId, $isSoundEng) {
                // Own tasks
                $q->where('sound_engineer_id', $userId)
                  ->orWhere('created_by', $userId);
                  
                // BROAD DISCOVERY: If user is a Sound Engineer, include tasks from programs they are in
                if ($isSoundEng) {
                    $q->orWhereNull('sound_engineer_id') // Unassigned
                      // OR assigned to a Producer (auto-assignment fallback)
                      ->orWhereHas('soundEngineer', function($sq) {
                          $sq->whereIn('role', ['producer', 'program_manager', 'produksi']);
                      })
                      ->orWhereHas('episode.program.teams.members', function($sub) use ($userId) {
                          $sub->where('team_members.user_id', $userId)
                              ->where('team_members.is_active', true);
                      });
                }

            })->get();
            
        foreach ($editingWorks as $ew) {
             if (!$ew->sound_engineer_id && $ew->created_by != $userId) {
                 // Check if user is the one who updated it (activity) or is in the team
                 $isSubmitter = ($ew->status === 'submitted' || $ew->status === 'approved'); // If they submitted it, they own it for KPI
             
                 // Check if user is in the production team for this program as an SE
                 $inTeam = \App\Models\Program::whereHas('teams.members', function($q) use ($userId) {
                     $q->where('team_members.user_id', $userId)->where('team_members.is_active', true);

                 })->whereHas('episodes', function($q) use ($ew) {
                     $q->where('id', $ew->episode_id);
                 })->exists();

                 if (!$isSubmitter && !$inTeam) continue;
             }
             
             // NEW: If assigned to someone else but CURRENT USER submitted it, include it in discovery
             if ($ew->sound_engineer_id != $userId && ($ew->status === 'submitted' || $ew->status === 'approved')) {
                  // Only if we can determine this user was the one who did the work
                  // For now, let's trust the activity if they were the one who updated it last
                  // (Usually sound_engineer_id would be updated by acceptWork but let's be safe)
             }
             $episodeScan[$ew->episode_id]['vocal_edit'] = $ew;

             
             // ROLE DISCOVERY: If work found, user must be evaluated for this role
             if ($isSoundEng) {
                 $episodeScan[$ew->episode_id]['discovered_roles'][] = (object)[
                    'role' => 'musik_vocal_edit', 
                    'deadline' => $ew->submitted_at ?? ($ew->episode?->air_date ? $ew->episode->air_date->copy()->subDays(6) : now())
                 ];
             }
        }

        // Track G: Music Arranger Help Activity (Sound Engineer helping Arranger)
        $arrHelpWorks = \App\Models\MusicArrangement::where('sound_engineer_helper_id', $userId)->get();

        foreach ($arrHelpWorks as $ahw) {
             // Discovery: Add ALL episodes to scan
             $episodeScan[$ahw->episode_id]['arranger_help'] = $ahw;
             
             // ROLE DISCOVERY
             if ($ahw->sound_engineer_helper_id == $userId) {
                 $episodeScan[$ahw->episode_id]['discovered_roles'][] = (object)[
                    'role' => 'musik_arr_help', 
                    'deadline' => $ahw->sound_engineer_help_at ?? ($ahw->episode?->air_date ? $ahw->episode->air_date->copy()->subDays(11) : now())
                 ];
             }
        }

        // Track H: Sound Engineer Recording Activity (Vocal Recording)
        $recordingWorks = \App\Models\SoundEngineerRecording::where(function($q) use ($userId, $isSoundEng) {
                $q->where('created_by', $userId)
                  ->orWhere('reviewed_by', $userId);
                  
                if ($isSoundEng) {
                    $q->orWhereHas('episode.program.teams.members', function($sub) use ($userId) {
                        $sub->where('team_members.user_id', $userId)
                            ->where('team_members.is_active', true);
                    });
                }
            })->get();
        foreach ($recordingWorks as $rw) {
             $episodeScan[$rw->episode_id]['vocal_recording'] = $rw;
             
             // ROLE DISCOVERY
             if ($rw->created_by == $userId) {
                 $episodeScan[$rw->episode_id]['discovered_roles'][] = (object)[
                    'role' => 'tim_vocal_member',                     'deadline' => $rw->recording_completed_at ?? ($rw->episode?->air_date ? $rw->episode->air_date->copy()->subDays(10) : now())
                  ];
              }
         }

        // Track I: Design Grafis (Music) - Capped at 1 point unit per episode
        $designWorks = \App\Models\DesignGrafisWork::where('assigned_to', $userId)
            ->where(function($q) use ($year, $month) {
                // Discover by episode date OR by activity date
                $q->whereHas('episode', function($eq) use ($year, $month) {
                    $eq->whereYear('air_date', $year)
                       ->when($month, fn($mq) => $mq->whereMonth('air_date', $month));
                })->orWhere(function($aq) use ($year, $month) {
                    $aq->whereYear('updated_at', $year)
                       ->when($month, fn($mq) => $mq->whereMonth('updated_at', $month));
                });
            })->get();
            
        foreach ($designWorks as $dw) {
            $episodeScan[$dw->episode_id]['design_grafis_list'][] = $dw;
            
            // Only add discovered role once per episode
            $alreadyAdded = collect($episodeScan[$dw->episode_id]['discovered_roles'] ?? [])->contains('role', 'design_grafis');
            if (!$alreadyAdded) {
                $episodeScan[$dw->episode_id]['discovered_roles'][] = (object)[
                    'role' => 'design_grafis',
                    'deadline' => $dw->deadline ?? ($dw->episode?->air_date ? $dw->episode->air_date->copy()->subDays(5) : now())
                ];
            }
        }

        // Track J: Editor (Music)
        $editorWorks = \App\Models\EditorWork::where('created_by', $userId)->get();
        foreach ($editorWorks as $ew) {
            $episodeScan[$ew->episode_id]['editor_work'] = $ew;
            $episodeScan[$ew->episode_id]['discovered_roles'][] = (object)[
                'role' => 'editor',
                'deadline' => $ew->deadline ?? ($ew->episode?->air_date ? $ew->episode->air_date->copy()->subDays(7) : now())
            ];
        }

        // Track K: Editor Promosi Work Model (Music) - Capped at 1 point unit per episode
        $editorPromoWorks = \App\Models\EditorPromosiWork::where(function($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhere('originally_assigned_to', $userId);
            })
            ->where(function($q) use ($year, $month) {
                $q->whereHas('episode', function($eq) use ($year, $month) {
                    $eq->whereYear('air_date', $year)
                       ->when($month, fn($mq) => $mq->whereMonth('air_date', $month));
                })->orWhere(function($aq) use ($year, $month) {
                    $aq->whereYear('updated_at', $year)
                       ->when($month, fn($mq) => $mq->whereMonth('updated_at', $month));
                });
            })->get();
            
        foreach ($editorPromoWorks as $epw) {
            $episodeScan[$epw->episode_id]['editor_promosi_list'][] = $epw;
            
            // Only add discovered role once per episode
            $alreadyAdded = collect($episodeScan[$epw->episode_id]['discovered_roles'] ?? [])->contains('role', 'editor_promosi');
            if (!$alreadyAdded) {
                $episodeScan[$epw->episode_id]['discovered_roles'][] = (object)[
                    'role' => 'editor_promosi',
                    'deadline' => $epw->deadline ?? ($epw->episode?->air_date ? $epw->episode->air_date->copy()->subDays(6) : now())
                ];
            }
        }

        // Track L: Broadcasting (Music)
        $broadcastingWorks = \App\Models\BroadcastingWork::where(function($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhere('submitted_by', $userId)
                  ->orWhere('accepted_by', $userId)
                  ->orWhere('approved_by', $userId)
                  ->orWhere('rated_by', $userId)
                  // Discover if user is in the program crew and has broadcasting role
                  ->orWhereHas('episode.program.productionTeam.members', function($sub) use ($userId) {
                      $sub->where('user_id', $userId)->where('is_active', true);
                  });
            })->get();
        foreach ($broadcastingWorks as $bw) {
            $episodeScan[$bw->episode_id]['broadcasting_work'] = $bw;
            
            // If user is Distribution Manager, this is Step 7 (Editor Approval / QC)
            // Otherwise it's standard Broadcasting task
            $bwRole = ($normUserRole === 'distribution_manager') ? 'distribution_manager_qc' : 'broadcasting';
            
            $episodeScan[$bw->episode_id]['discovered_roles'][] = (object)[
                'role' => $bwRole,
                'deadline' => $bw->episode?->air_date ? $bw->episode->air_date->copy()->subDays(4) : now()
            ];
        }

        // Track M: Promotion (Music)
        $promotionWorksQuery = \App\Models\PromotionWork::query();
        
        // If user is a Promotion role, allow discovery of ALL music promotion works 
        // that match the period, so they get points for tasks they are assigned to
        // If they are not a promotion role (backup), only discover if they created it.
        $isPromotionRole = str_contains($normUserRole, 'promotion') || str_contains($normUserRole, 'promosi');
        $isEditorPromotion = str_contains($normUserRole, 'editor');
        
        if (!$isPromotionRole) {
            $promotionWorksQuery->where('created_by', $userId);
        } else {
            // Even if promotion role, only discover if they are the creator or assigned
            $promotionWorksQuery->where('created_by', $userId);
        }

        $promotionWorks = $promotionWorksQuery->where(function($q) use ($year, $month) {
                $q->whereHas('episode', function($eq) use ($year, $month) {
                    $eq->whereYear('air_date', $year)
                      ->when($month, fn($mq) => $mq->whereMonth('air_date', $month))
                      ->orWhereYear('production_date', $year)
                      ->when($month, fn($mq) => $mq->whereMonth('production_date', $month));
                })->orWhere(function($aq) use ($year, $month) {
                    $aq->whereYear('updated_at', $year)
                       ->when($month, fn($mq) => $mq->whereMonth('updated_at', $month));
                });
            })->get();

        foreach ($promotionWorks as $pw) {
            $episodeScan[$pw->episode_id]['promotion_work_list'][] = $pw;
            
            // Discovery based on work_type
            $isEditorType = in_array($pw->work_type, ['bts_video', 'highlight_ig', 'highlight_facebook', 'highlight_tv', 'iklan_episode_tv']);
            $roleKey = $isEditorType ? 'editor_promosi' : 'promotion';
            
            // Only add discovered role once per episode
            $alreadyAdded = collect($episodeScan[$pw->episode_id]['discovered_roles'] ?? [])->contains('role', $roleKey);
            if (!$alreadyAdded) {
                if ($isEditorType) {
                    $deadline = $pw->episode?->air_date ? $pw->episode->air_date->copy()->subDays(6) : now();
                    $episodeScan[$pw->episode_id]['discovered_roles'][] = (object)[
                        'role' => 'editor_promosi',
                        'deadline' => $deadline
                    ];
                } else {
                    $episodeScan[$pw->episode_id]['discovered_roles'][] = (object)[
                        'role' => 'promotion',
                        'deadline' => $pw->episode?->air_date ?? now()
                    ];
                }
            }
        }

        // Track N: Quality Control (Music)
        $qcWorks = \App\Models\QualityControlWork::where(function($q) use ($userId) {
                // Discover ONLY for the person who actually performs the review (QC person/Manager)
                // Creators (Editors) should not be evaluated for the QC role itself
                $q->where('reviewed_by', $userId);
            })->get();
        foreach ($qcWorks as $qw) {
            $episodeScan[$qw->episode_id]['qc_work'] = $qw;
            $role = ($qw->qc_type === 'manager_distribusi') ? 'distribution_manager_qc' : 'quality_control';
            $deadline = $qw->episode?->air_date ? $qw->episode->air_date->copy()->subDays(6) : now();
            $episodeScan[$qw->episode_id]['discovered_roles'][] = (object)[
                'role' => $role,
                'deadline' => $deadline
            ];
        }

        // Track O: Task Reassignments (Backup/Substitution Discovery)
        $reassignedTasks = \App\Models\TaskReassignment::where('new_user_id', $userId)
            ->whereNotNull('episode_id')
            ->get();
        foreach ($reassignedTasks as $rt) {
             $role = $rt->role_key ?: $rt->task_type;
             $epId = (int) $rt->episode_id;
             
             // Avoid double counting if already discovered via work record
             $alreadyExists = collect($episodeScan[$epId]['discovered_roles'] ?? [])->contains('role', $role);
             
             if (!$alreadyExists) {
                 // Fetch episode for air_date
                 $ep = \App\Models\Episode::find($epId);
                 if ($ep) {
                     $daysBefore = \App\Constants\WorkflowStep::getDeadlineDaysForRole($role, 'musik');
                     $deadline = $ep->air_date ? $ep->air_date->copy()->subDays($daysBefore) : now();
                     
                     $episodeScan[$epId]['discovered_roles'][] = (object)[
                        'role' => $role, 
                        'deadline' => $deadline
                     ];
                 }
             }
        }

        // Track Q: Tasks Reassigned AWAY from this user (Handed over to someone else)
        $reassignedAway = \App\Models\TaskReassignment::where('original_user_id', $userId)
            ->whereNotNull('episode_id')
            ->get();
        $reassignedAwayMap = [];
        foreach ($reassignedAway as $ra) {
            $epId = (int)$ra->episode_id;
            $role = $ra->role_key ?: $ra->task_type;
            $reassignedAwayMap[$epId][$role] = true;
        }

        // Track F: Equipment Loan Participation (Tim Setting/Syuting/Art Set Discovery)
        $equipmentTasks = \App\Models\ProductionEquipment::where('crew_leader_id', $userId)
            ->orWhereJsonContains('crew_member_ids', (string)$userId)
            ->orWhereJsonContains('crew_member_ids', (int)$userId)
            ->orWhere('approved_by', $userId) // Include Art Set Approver
            ->get();

        foreach ($equipmentTasks as $eq) {
             // Find all episodes linked to this equipment request (support multi-episode loans)
             $episodesToScan = [];
             if ($eq->episode_id) {
                 $episodesToScan[] = $eq->episode_id;
             }
             
             if ($eq->request_group_id) {
                 $groupEpisodes = \App\Models\ProductionEquipment::where('request_group_id', $eq->request_group_id)
                    ->pluck('episode_id')
                    ->filter()
                    ->unique()
                    ->toArray();
                 $episodesToScan = array_unique(array_merge($episodesToScan, $groupEpisodes));
             }

             foreach ($episodesToScan as $scanId) {
                 // DISCOVERY FIX: Activity-based discovery (if loan is scheduled for this month)
                 if ($eq->scheduled_date && $eq->scheduled_date->year == $year && (!$month || $eq->scheduled_date->month == $month)) {
                     
                     // ROLE COMPATIBILITY CHECK
                     // Only assign Setting/Shooting roles if they match user's role OR if they are the explicit Leader
                     $isLeader = ($eq->crew_leader_id == $userId);
                     $targetRole = null;
                     
                     if ($eq->team_type === 'setting') {
                         $targetRole = $isLeader ? 'tim_setting_coord' : 'tim_setting_member';
                     } elseif ($eq->team_type === 'shooting') {
                         $targetRole = $isLeader ? 'tim_syuting_coord' : 'tim_syuting_member';
                     } elseif ($eq->team_type === 'recording') {
                         $targetRole = $isLeader ? 'tim_vocal_coord' : 'tim_vocal_member';
                     }

                     if ($targetRole) {
                         $roleKeyBase = explode('_', $targetRole)[1]; // e.g., 'setting', 'syuting', 'vocal'
                         $isCompatible = str_contains($normUserRole, $roleKeyBase) || 
                                        ($roleKeyBase === 'syuting' && str_contains($normUserRole, 'shooting')) ||
                                        ($roleKeyBase === 'vocal' && (str_contains($normUserRole, 'sound') || str_contains($normUserRole, 'music')));

                         if ($isLeader || $isCompatible) {
                             $episodeScan[$scanId]['equipment_loan'] = $eq;
                             $episodeScan[$scanId]['team_roles'][] = (object)[
                                 'role' => $targetRole,
                                 'deadline' => $eq->scheduled_date
                             ];
                         }
                     }
                 }
                 
                 // Also inject if the episode itself belongs to this month
                 $scanEpisode = \App\Models\Episode::find($scanId);
                 if ($scanEpisode && $scanEpisode->air_date && $scanEpisode->air_date->year == $year && (!$month || $scanEpisode->air_date->month == $month)) {
                    if (!isset($episodeScan[$scanId]['equipment_loan'])) {
                        // Check compatibility before broad discovery
                        $isCompatibleRole = str_contains($normUserRole, $eq->team_type) || ($eq->crew_leader_id == $userId);
                        if ($isCompatibleRole) {
                            $episodeScan[$scanId]['equipment_loan'] = $eq;
                        }
                    }
                 }

                 // If person is the Approver, they are Art Set Properti
                 if ($eq->approved_by == $userId) {
                     $episodeScan[$scanId]['team_roles'][] = (object)[
                         'role' => 'art_set_alat_keluar',
                         'deadline' => $eq->scheduled_date
                     ];
                     $episodeScan[$scanId]['team_roles'][] = (object)[
                         'role' => 'art_set_alat_masuk',
                         'deadline' => $eq->scheduled_date ? $eq->scheduled_date->copy()->addDays(7) : null 
                     ];
                 }
             }
         }

        // Track P: Program-Based Discovery (Music Programs)
        // Ensure episodes are discovered if the user is assigned to the program's production team
        $involvedMusicProgramIds = \App\Models\Program::where('category', 'musik')
            ->where(function($q) use ($userId) {
                $q->whereHas('productionTeam.members', fn($sq) => $sq->where('user_id', $userId)->where('is_active', true))
                  ->orWhere('manager_program_id', $userId);
            })->pluck('id', 'id')->keys();

        if ($involvedMusicProgramIds->isNotEmpty()) {
            $episodesOfProgs = \App\Models\Episode::whereIn('program_id', $involvedMusicProgramIds)
                ->where(function($q) use ($year, $month) {
                    $q->where(function($aq) use ($year, $month) {
                        $aq->whereYear('air_date', $year);
                        if ($month) $aq->whereMonth('air_date', $month);
                    })->orWhere(function($pq) use ($year, $month) {
                        $pq->whereYear('production_date', $year);
                        if ($month) $pq->whereMonth('production_date', $month);
                    });
                })->get();

            foreach ($episodesOfProgs as $ep) {
                if (!isset($episodeScan[$ep->id])) {
                    $episodeScan[$ep->id] = [];
                }
            }
        }

        // 2. PROCESSING ENGINE: Iterate through discovered episodes and map to roles
        foreach ($episodeScan as $episodeId => $data) {
            $episode = \App\Models\Episode::with(['program' => function($q) { $q->withTrashed(); }])->find($episodeId);
            if (!$episode) continue;

            // PERIOD FILTER: Only process if:
            // 1. It belongs to this month's Air Date
            // 2. OR has activity in this month
            // 3. OR is in this month's Production/Shooting period
            $isAirDateMonth = ($episode->air_date && $episode->air_date->year == $year && (!$month || $episode->air_date->month == $month));
            $isProdDateMonth = ($episode->production_date && $episode->production_date->year == $year && (!$month || $episode->production_date->month == $month));
            
            $hasWorkActivity = false;
            $workRecords = [
                $data['vocal_edit'] ?? null,
                $data['arranger_help'] ?? null,
                $data['creative_work'] ?? null,
                $data['arrangement'] ?? null,
                $data['equipment_loan'] ?? null,
                $data['qc_work'] ?? null,
                $data['broadcasting_work'] ?? null,
                ...($data['design_grafis_list'] ?? []),
                ...($data['editor_promosi_list'] ?? []),
                ...($data['promotion_work_list'] ?? []) // Include all for activity check
            ];
            foreach ($workRecords as $wr) {
                if ($wr) {
                    // Check all possible activity dates across all music work models
                    $dates = [
                        $wr->submitted_at, 
                        $wr->updated_at, 
                        $wr->loan_date, 
                        $wr->reviewed_at, 
                        $wr->created_at,
                        $wr->arrangement_submitted_at,
                        $wr->sound_engineer_help_at,
                        $wr->returned_at,
                        $wr->published_time,
                        $wr->accepted_at,
                        $wr->approved_at
                    ];
                    foreach ($dates as $d) {
                        if ($d && $d->year == $year && (!$month || $d->month == $month)) {
                            $hasWorkActivity = true;
                            break 2; // Break both loops
                        }
                    }
                }
            }

            // 4. OR has a task deadline in this month (CRITICAL for tasks with air_date in next month)
            $hasTaskDeadlineInMonth = false;
            
            // Check discovered roles
            $rawRoles = array_merge(
                $data['discovered_roles'] ?? [],
                $data['team_roles'] ?? [],
                $data['deadlines'] ?? []
            );
            
            foreach ($rawRoles as $r) {
                $dDate = $r->deadline ?? $r->deadline_date ?? null;
                if ($dDate) {
                    $carbonD = Carbon::parse($dDate);
                    if ($carbonD->year == $year && (!$month || $carbonD->month == $month)) {
                        $hasTaskDeadlineInMonth = true;
                        break;
                    }
                }
            }

            if (!$isAirDateMonth && !$isProdDateMonth && !$hasWorkActivity && !$hasTaskDeadlineInMonth) {
                continue;
            }
             // Determine which roles are assigned to this user for this episode
            $rolesToProcess = [];
            
            // If it's a direct deadline, use it
            if (isset($data['deadlines'])) {
                foreach ($data['deadlines'] as $d) {
                    $rolesToProcess[] = (object)['role' => $d->role, 'deadline' => $d->deadline_date];
                }
            }

            // If discovered via team membership
            if (isset($data['team_roles'])) {
                foreach ($data['team_roles'] as $r) {
                    if (!collect($rolesToProcess)->contains('role', $r->role)) {
                        $rolesToProcess[] = $r;
                    }
                }
            }

            // If user is Sound Engineer and has an editing record or help record, ensure roles exist
            if (($isSoundEng || isset($data['vocal_edit'])) && !collect($rolesToProcess)->contains('role', 'musik_vocal_edit')) {
                $rolesToProcess[] = (object)['role' => 'musik_vocal_edit', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(8) : now()->clone()->subMonth()];
            }

            if (($isSoundEng || isset($data['arranger_help'])) && !collect($rolesToProcess)->contains('role', 'musik_arr_help')) {
                $rolesToProcess[] = (object)['role' => 'musik_arr_help', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(11) : now()->clone()->subMonth()];
            }
            // If user is Arranger and has an arrangement record (or discovered via team), ensure Music roles exist
            if ($isArranger) {
                $hasSong = collect($rolesToProcess)->contains('role', 'musik_arr_song');
                $hasLagu = collect($rolesToProcess)->contains('role', 'musik_arr_lagu');
                
                if (!$hasSong) {
                    $rolesToProcess[] = (object)['role' => 'musik_arr_song', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(15) : now()->clone()->subMonth()];
                }
                if (!$hasLagu) {
                    $rolesToProcess[] = (object)['role' => 'musik_arr_lagu', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(11) : now()->clone()->subMonth()];
                }
            }

            // If user is Creative, ensure 'kreatif' role exists
            if ($isCreative && !collect($rolesToProcess)->contains('role', 'kreatif')) {
                $rolesToProcess[] = (object)['role' => 'kreatif', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(10) : now()->clone()->subMonth()];
            }

            // MUSIC ROLE FALLBACKS: If user has these roles, ensure they are processed for this episode
            $musicRoleMap = [
                'broadcasting' => 4,
                'distribution_manager_qc' => 6,
                'editor' => 7,
                'editor_promosi' => 6,
                'design_grafis' => 5,
                'promotion' => 0,
                'quality_control' => 6,
                'promotion_shooting' => 8
            ];
            
            foreach ($musicRoleMap as $mRole => $daysBefore) {
                if ($normUserRole === $mRole && !collect($rolesToProcess)->contains('role', $mRole)) {
                    // Check if this role was reassigned away for THIS episode
                    if (isset($reassignedAwayMap[$episodeId][$mRole])) {
                        continue; // Skip penalization, someone else is doing it
                    }

                    // Only add fallback if there is at least some activity or explicit assignment indicator
                    $hasActivity = $hasWorkActivity || $hasTaskDeadlineInMonth;
                    
                    // Specific check for work records of this role
                    $roleHasWorkRecord = false;
                    if ($mRole === 'promotion') $roleHasWorkRecord = !empty($data['promotion_work_list']);
                    if ($mRole === 'editor_promosi') $roleHasWorkRecord = !empty($data['editor_promosi_list']);
                    if ($mRole === 'design_grafis') $roleHasWorkRecord = !empty($data['design_grafis_list']);
                    if ($mRole === 'broadcasting') $roleHasWorkRecord = isset($data['broadcasting_work']);
                    if ($mRole === 'editor') $roleHasWorkRecord = isset($data['editor_work']);
                    if ($mRole === 'quality_control') $roleHasWorkRecord = isset($data['qc_work']);

                    if ($hasActivity || $roleHasWorkRecord) {
                        $rolesToProcess[] = (object)[
                            'role' => $mRole, 
                            'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays($daysBefore) : now()
                        ];
                    }
                }
            }

            // ADD DISCOVERED ROLES (Activity-based)
            if (isset($data['discovered_roles'])) {
                foreach ($data['discovered_roles'] as $dr) {
                    if (!collect($rolesToProcess)->contains('role', $dr->role)) {
                        $rolesToProcess[] = $dr;
                    }
                }
            }

            // Specific Music Program Approvals for Producers
            if (str_contains($normUserRole, 'producer')) {
                 if (isset($data['arrangement'])) {
                     // 1. Acc Song Proposal
                     $rolesToProcess[] = (object)['role' => 'musik_arr_approval', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(11) : now()];
                     
                     // 2. Acc Arrangement Music
                     $rolesToProcess[] = (object)['role' => 'musik_producer_acc_arr', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(11) : now()];
                 }
                 
                 // 3. Creative Approval & Script Check (Mapping to musik_rec_approval logic)
                 if (isset($data['vocal_recording']) || isset($data['creative_work'])) {
                     $rolesToProcess[] = (object)['role' => 'musik_rec_approval', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(10) : now()];
                 }
                 
                 if (isset($data['vocal_edit'])) {
                     $rolesToProcess[] = (object)['role' => 'musik_edit_approval', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(6) : now()];
                 }
            }

            // Fallback for Producer roles if discovered but not in deadlines
            if (str_contains($normUserRole, 'producer') && !collect($rolesToProcess)->contains(fn($r) => str_contains($r->role, 'producer'))) {
                 // Check if already covered by team coordinator roles
                 $hasCoordRole = collect($rolesToProcess)->contains(fn($r) => str_contains($r->role, '_coord'));
                 if (!$hasCoordRole) {
                    $rolesToProcess[] = (object)['role' => 'producer', 'deadline' => $episode->air_date ? $episode->air_date->copy()->subDays(1) : now()];
                 }
            }

            // 3. CALCULATION ENGINE: Process each role
            foreach ($rolesToProcess as $r) {
                $roleKey = $r->role;
                $deadlineDate = $r->deadline;

                // Load Setting with Hardcoded Fail-Safes
                $setting = $settings->get($roleKey . '_musik');
                if (!$setting) {
                    $defaults = [
                        'musik_arr_song' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'musik_arr_lagu' => ['on' => 10, 'late' => 2, 'none' => -5],
                        'kreatif' => ['on' => 5, 'late' => 2, 'none' => -5],
                        'producer' => ['on' => 5, 'late' => 1, 'none' => -5],
                        'tim_vocal_coord' => ['on' => 5, 'late' => 2, 'none' => -5],
                        'tim_vocal_member' => ['on' => 5, 'late' => 2, 'none' => -5],
                        'musik_vocal_edit' => ['on' => 10, 'late' => 2, 'none' => -5],
                        'musik_arr_help' => ['on' => 10, 'late' => 2, 'none' => -5],
                        'tim_setting_coord' => ['on' => 5, 'late' => 2, 'none' => 0],
                        'tim_setting_member' => ['on' => 5, 'late' => 2, 'none' => 0],
                        'tim_syuting_coord' => ['on' => 5, 'late' => 2, 'none' => 0],
                        'tim_syuting_member' => ['on' => 5, 'late' => 2, 'none' => 0],
                        'art_set_alat_keluar' => ['on' => 2, 'late' => 1, 'none' => -5],
                        'art_set_alat_masuk' => ['on' => 2, 'late' => 1, 'none' => -5],
                        'musik_arr_approval' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'musik_producer_acc_arr' => ['on' => 5, 'late' => 2, 'none' => -5],
                        'musik_rec_approval' => ['on' => 5, 'late' => 2, 'none' => -5],
                        'musik_edit_approval' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'promotion_shooting' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'distribution_manager_qc' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'design_grafis' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'editor' => ['on' => 5, 'late' => 2, 'none' => -5],
                        'editor_promosi' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'broadcasting' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'promotion' => ['on' => 3, 'late' => 1, 'none' => -5],
                        'quality_control' => ['on' => 3, 'late' => 1, 'none' => 0],
                    ];
                    $def = $defaults[$roleKey] ?? ['on' => 3, 'late' => 1, 'none' => -5];
                    $setting = (object)[
                        'points_on_time' => $def['on'],
                        'points_late' => $def['late'],
                        'points_not_done' => $def['none'],
                        'quality_max' => 5
                    ];
                }

                // Check Work Completion
                $work = null;
                $completedAt = null;
                $isCompleted = false;

                if (str_contains($roleKey, 'musik_arr')) {
                    $work = $data['arrangement'] ?? \App\Models\MusicArrangement::where('episode_id', $episodeId)->first();
                    if ($work) {
                        // Song Proposal is completed if status reached any stage beyond proposal, including rejected (rejection is at arrangement stage)
                        $songSuccess = ['song_proposal', 'song_approved', 'arrangement_submitted', 'arrangement_approved', 'approved', 'submitted', 'rejected'];
                        $arrSuccess = ['arrangement_submitted', 'arrangement_approved', 'approved', 'submitted'];
                        
                        if ($roleKey === 'musik_arr_song') {
                            $isCompleted = (in_array($work->status, $songSuccess) || $work->song_approved_at !== null || $work->submitted_at !== null);
                            $completedAt = $work->submitted_at ?? $work->created_at; 
                        } else {
                            $isCompleted = (in_array($work->status, $arrSuccess) || $work->arrangement_submitted_at !== null);
                            $completedAt = $work->arrangement_submitted_at ?? $work->reviewed_at;
                        }
                    }
                } elseif ($roleKey === 'kreatif') {
                    $work = $data['creative_work'] ?? \App\Models\CreativeWork::where('episode_id', $episodeId)->where('created_by', $userId)->first();
                    if ($work) {
                        $isCompleted = in_array($work->status, ['submitted', 'approved']);
                        $completedAt = $work->status === 'approved' ? $work->reviewed_at : $work->updated_at;
                    }
                } elseif (str_contains($roleKey, 'tim_vocal')) {
                    $work = \App\Models\SoundEngineerRecording::where('episode_id', $episodeId)->first();
                    if ($work) {
                        $isCompleted = in_array($work->status, ['completed', 'reviewed']);
                        $completedAt = $work->recording_completed_at ?? $work->reviewed_at;
                    } else {
                        // FALLBACK: If "pinjam alat" for recording is returned, it counts as completion for Vocal Team
                        $eq = $data['equipment_loan'] ?? \App\Models\ProductionEquipment::where('episode_id', $episodeId)->where('team_type', 'recording')->first();
                        
                        // GROUP CHECK: If part of a multi-episode loan, any return in the group counts
                        if ($eq && $eq->request_group_id && $eq->status !== 'returned') {
                            $eq = \App\Models\ProductionEquipment::where('request_group_id', $eq->request_group_id)
                                ->where('status', 'returned')->first() ?: $eq;
                        }

                        if ($eq && $eq->status === 'returned') {
                            $isCompleted = true;
                            $completedAt = $eq->returned_at ?? $eq->updated_at;
                        }
                    }
                } elseif ($roleKey === 'musik_vocal_edit') {
                    $work = $data['vocal_edit'] ?? \App\Models\SoundEngineerEditing::where('episode_id', $episodeId)->where('sound_engineer_id', $userId)->first();
                    if ($work) {
                        $isCompleted = in_array($work->status, ['submitted', 'approved']);
                        $completedAt = $work->status === 'approved' ? ($work->approved_at ?? $work->updated_at) : ($work->submitted_at ?? $work->updated_at);
                    }
                } elseif ($roleKey === 'musik_arr_help') {
                    $work = $data['arranger_help'] ?? \App\Models\MusicArrangement::where('episode_id', $episodeId)->where('sound_engineer_helper_id', $userId)->first();
                    if ($work) {
                        // Help is ONLY completed if Sound Engineer actually provided a file link or performed help action
                        // AND it was actually requested/rejected (to ensure points are only for corrective help)
                        $wasRejected = ($work->rejection_reason !== null || $work->needs_sound_engineer_help);
                        $isCompleted = $wasRejected && ($work->sound_engineer_help_file_link !== null || $work->sound_engineer_help_at !== null); 
                        $completedAt = $work->sound_engineer_help_at ?? $work->updated_at;
                    }

                } elseif (str_contains($roleKey, 'tim_setting')) {
                    $work = \App\Models\ProduksiWork::where('episode_id', $episodeId)->first();
                    if ($work && $work->setting_completed_at) {
                        $isCompleted = true;
                        $completedAt = $work->setting_completed_at;
                    } else {
                        // FALLBACK: If "pinjam alat" is approved/in_use, it counts for Setting Team
                        $eq = $data['equipment_loan'] ?? \App\Models\ProductionEquipment::where('episode_id', $episodeId)->where('team_type', 'setting')->first();
                        
                        // GROUP CHECK: If part of a multi-episode loan, any approve/return in the group counts
                        if ($eq && $eq->request_group_id && !in_array($eq->status, ['approved', 'in_use', 'returned'])) {
                             $eq = \App\Models\ProductionEquipment::where('request_group_id', $eq->request_group_id)
                                ->whereIn('status', ['approved', 'in_use', 'returned'])->first() ?: $eq;
                        }

                        if ($eq && in_array($eq->status, ['approved', 'in_use', 'returned'])) {
                            $isCompleted = true;
                            $completedAt = $eq->approved_at ?? $eq->updated_at;
                        }
                    }
                } elseif (str_contains($roleKey, 'tim_syuting')) {
                    $work = \App\Models\ProduksiWork::where('episode_id', $episodeId)->first();
                    if ($work && $work->status === 'completed') {
                        $isCompleted = true;
                        $completedAt = $work->completed_at;
                    } else {
                        // FALLBACK: If shooting files exist OR equipment is returned, it counts for Syuting Team
                        $eq = $data['equipment_loan'] ?? \App\Models\ProductionEquipment::where('episode_id', $episodeId)->where('team_type', 'shooting')->first();
                        
                        // GROUP CHECK: If part of a multi-episode loan, any return in the group counts
                        if ($eq && $eq->request_group_id && $eq->status !== 'returned') {
                             $eq = \App\Models\ProductionEquipment::where('request_group_id', $eq->request_group_id)
                                ->where('status', 'returned')->first() ?: $eq;
                        }

                        if ($eq && $eq->status === 'returned') {
                            $isCompleted = true;
                            $completedAt = $eq->returned_at ?? $eq->updated_at;
                        }
                    }
                } elseif (str_contains($roleKey, 'art_set_alat')) {
                    $eq = $data['equipment_loan'] ?? \App\Models\ProductionEquipment::where('episode_id', $episodeId)->first();
                    if ($eq) {
                        $isCompleted = ($roleKey === 'art_set_alat_keluar') ? 
                            in_array($eq->status, ['approved', 'in_use', 'returned']) : 
                            ($eq->status === 'returned');
                        $completedAt = ($roleKey === 'art_set_alat_keluar') ? 
                            ($eq->approved_at ?? $eq->updated_at) : 
                            ($eq->returned_at ?? $eq->updated_at);
                    }
                } elseif (str_contains($roleKey, 'musik_arr_approval')) {
                    $work = $data['arrangement'] ?? \App\Models\MusicArrangement::where('episode_id', $episodeId)->first();
                    if ($work && (in_array($work->status, ['submitted', 'approved', 'arrangement_approved']) || $work->song_approved_at !== null)) {
                         $isCompleted = true;
                         $completedAt = $work->song_approved_at ?? $work->reviewed_at ?? $work->updated_at;
                    }
                } elseif (str_contains($roleKey, 'musik_producer_acc_arr')) {
                    $work = $data['arrangement'] ?? \App\Models\MusicArrangement::where('episode_id', $episodeId)->first();
                    if ($work && (in_array($work->status, ['approved', 'arrangement_approved']) || $work->reviewed_at !== null)) {
                         $isCompleted = true;
                         $completedAt = $work->reviewed_at ?? $work->updated_at;
                    }
                } elseif (str_contains($roleKey, 'musik_rec_approval')) {
                    // This role is used for Producer Creative & Script Approval
                    $work = $data['creative_work'] ?? \App\Models\CreativeWork::where('episode_id', $episodeId)->first();
                    if ($work && in_array($work->status, ['approved'])) {
                         $isCompleted = true;
                         $completedAt = $work->reviewed_at ?? $work->updated_at;
                    }
                } elseif (str_contains($roleKey, 'musik_edit_approval')) {
                    $work = $data['vocal_edit'] ?? \App\Models\SoundEngineerEditing::where('episode_id', $episodeId)->first();
                    if ($work && $work->status === 'approved') {
                         $isCompleted = true;
                         $completedAt = $work->approved_at ?? $work->updated_at;
                    }
                } elseif ($roleKey === 'design_grafis') {
                    $works = $data['design_grafis_list'] ?? [];
                    if (empty($works)) {
                         $works = \App\Models\DesignGrafisWork::where('episode_id', $episodeId)->where('assigned_to', $userId)->get();
                    }

                    foreach ($works as $work) {
                        $completed = in_array($work->status, ['submitted', 'approved', 'completed', 'pending_qc']) || $work->file_link || $work->file_path || !empty($work->file_paths);
                        if ($completed) {
                            $isCompleted = true;
                            // Take the earliest completion time for the episode record
                            $cTime = $work->status === 'approved' ? $work->reviewed_at : $work->updated_at;
                            if (!$completedAt || Carbon::parse($cTime)->lt(Carbon::parse($completedAt))) {
                                $completedAt = $cTime;
                            }
                        }
                    }
                } elseif ($roleKey === 'editor') {
                    $work = $data['editor_work'] ?? \App\Models\EditorWork::where('episode_id', $episodeId)->where('created_by', $userId)->first();
                    if ($work) {
                        $isCompleted = in_array($work->status, ['submitted', 'approved', 'completed', 'pending_qc']);
                        $completedAt = $work->status === 'approved' ? $work->reviewed_at : $work->updated_at;
                    }
                } elseif ($roleKey === 'editor_promosi') {
                    // Combine EditorPromosiWork AND PromotionWorks of editor types
                    $works = array_merge(
                        $data['editor_promosi_list'] ?? [], 
                        collect($data['promotion_work_list'] ?? [])->filter(fn($p) => in_array($p->work_type, ['bts_video', 'highlight_ig', 'highlight_facebook', 'highlight_tv', 'iklan_episode_tv']))->all()
                    );
                    
                    if (empty($works)) {
                         $works = \App\Models\EditorPromosiWork::where('episode_id', $episodeId)
                            ->where(function($q) use ($userId) {
                                $q->where('created_by', $userId)
                                  ->orWhere('originally_assigned_to', $userId);
                            })->get();
                    }

                    foreach ($works as $work) {
                        $completed = in_array($work->status, ['submitted', 'approved', 'completed', 'pending_qc', 'published', 'review']) || !empty($work->file_link) || !empty($work->file_path) || (!empty($work->file_paths) && count($work->file_paths) > 0) || (!empty($work->file_links) && count($work->file_links) > 0);
                        if ($completed) {
                            $isCompleted = true;
                            // Take the earliest completion time for the episode record
                            $cTime = $work->status === 'approved' ? $work->reviewed_at : $work->updated_at;
                            if (!$completedAt || (Carbon::parse($cTime)->lt(Carbon::parse($completedAt)))) {
                                $completedAt = $cTime;
                            }
                        }
                    }
                } elseif ($roleKey === 'promotion') {
                    $works = $data['promotion_work_list'] ?? [];
                    if (!empty($works)) {
                        $allTasksDone = true;
                        $latestTime = null;
                        foreach ($works as $work) {
                            $isTaskDone = ($work->status === 'published' || $work->status === 'completed' || !empty($work->social_media_links) || !empty($work->social_media_proof));
                            if (!$isTaskDone) {
                                $allTasksDone = false;
                                break;
                            }
                            $cTime = $work->updated_at;
                            if (!$latestTime || Carbon::parse($cTime)->gt(Carbon::parse($latestTime))) {
                                $latestTime = $cTime;
                            }
                        }
                        if ($allTasksDone) {
                            $isCompleted = true;
                            $completedAt = $latestTime;
                        }
                    }
                } elseif ($roleKey === 'broadcasting' || $roleKey === 'distribution_manager_qc') {
                    $work = $data['broadcasting_work'] ?? \App\Models\BroadcastingWork::where('episode_id', $episodeId)->first();
                    if ($work) {
                        // Completion for Distribution Manager / Broadcasting team: If it is accepted or approved for broadcast
                        $isCompleted = ($work->status === 'published' || $work->published_time !== null || $work->accepted_at !== null || $work->approved_at !== null);
                        $completedAt = $work->published_time ?? ($work->accepted_at ?? ($work->approved_at ?? $work->updated_at));
                    }
                } elseif ($roleKey === 'promotion_shooting') {
                    $work = $data['promotion_work'] ?? \App\Models\PromotionWork::where('episode_id', $episodeId)->first();
                    if ($work) {
                        // REFINED: For music promotion shooting (BTS/Photos), 
                        // it is only completed if they have uploaded the files (links/paths not empty)
                        $hasFiles = !empty($work->file_links) || !empty($work->file_paths);
                        
                        $isMusic = ($episode->program?->category === 'Music' || $episode->program?->category === 'Musik');
                        
                        if ($isMusic) {
                            // Strictly require files for music promotion shooting to count as completed
                            $isCompleted = $hasFiles;
                        } else {
                            // Status 'done' or 'published' counts as completed, or if still in progress/editing but files are uploaded
                            $isCompleted = (in_array($work->status, ['published', 'done']) || (in_array($work->status, ['shooting', 'editing']) && $hasFiles));
                        }
                        
                        $completedAt = $work->updated_at;
                    }
                } elseif ($roleKey === 'promotion') {
                    $work = $data['promotion_work'] ?? \App\Models\PromotionWork::where('episode_id', $episodeId)->first();
                    if ($work) {
                        $isCompleted = ($work->status === 'published' || !empty($work->social_media_proof));
                        $completedAt = $work->updated_at;
                    }
                } elseif ($roleKey === 'quality_control') {
                    $work = $data['qc_work'] ?? \App\Models\QualityControlWork::where('episode_id', $episodeId)->first();
                    if ($work) {
                        $isCompleted = in_array($work->status, ['passed', 'approved']);
                        $completedAt = $work->reviewed_at ?? $work->updated_at;
                    }
                } else {
                    // Generic work model check
                    $modelClass = (str_contains($roleKey, 'producer') && !str_contains($roleKey, 'musik_')) ? \App\Models\CreativeWork::class : null;
                    if ($modelClass) {
                        $work = $modelClass::where('episode_id', $episodeId)->first();
                        if ($work) {
                            $isCompleted = in_array($work->status, ['approved', 'submitted']);
                            $completedAt = $work->reviewed_at;
                        }
                    }
                }

                // Determine Status and Points
                $status = 'not_done';
                
                // Hardcoded point rules for Promotion roles in Music Program (Modernization)
                $isMusicRole = in_array($roleKey, ['editor', 'promotion_shooting', 'editor_promosi', 'design_grafis', 'quality_control', 'distribution_manager_qc', 'promotion', 'broadcasting']);
                
                if ($isMusicRole) {
                    $pointsOnTime = 3;
                    $pointsLate = 1;
                    $pointsNotDone = -5;
                    
                    // Specific deadline rules for Music
                    if (in_array($roleKey, ['promotion_shooting', 'production_crew'])) {
                        // Use Production Date for Syuting/Crew
                        if ($episode->production_date) {
                            $deadlineDate = $episode->production_date;
                        }
                    } else if ($episode->air_date) {
                        // Use Air Date minus offset (e.g. 6 days) for QC/Promosi/Design
                        $daysBefore = \App\Constants\WorkflowStep::getDeadlineDaysForRole($roleKey, 'musik');
                        $deadlineDate = $episode->air_date->copy()->subDays($daysBefore);
                    }
                } else {
                    $pointsOnTime = $setting->points_on_time;
                    $pointsLate = $setting->points_late;
                    $pointsNotDone = $setting->points_not_done;
                }
                
                $points = $pointsNotDone;

                if ($isCompleted && $completedAt) {
                    $cAt = Carbon::parse($completedAt);
                    $dDate = $deadlineDate ? Carbon::parse($deadlineDate) : null;
                    
                    if (!$dDate || $cAt->lte($dDate)) {
                        $status = 'on_time';
                        $points = $pointsOnTime;
                        $onTimeCount++;
                    } else {
                        // Music Modernization: If completion is more than 7 days late, it is a FAILURE (-5)
                        $delayDays = $dDate ? $cAt->diffInDays($dDate) : 0;
                        if ($isMusicRole && $delayDays > 7) {
                            $status = 'failed';
                            $points = $pointsNotDone;
                            $notDoneCount++;
                        } else {
                            $status = 'late';
                            $points = $pointsLate;
                            $lateCount++;
                        }
                    }
                } else {
                    // Not completed yet
                    if ($deadlineDate && Carbon::parse($deadlineDate)->isFuture()) {
                        $status = 'waiting';
                        $points = 0;
                        $waitingCount++;
                    } else if ($deadlineDate) {
                        // Deadline is past
                        $diff = Carbon::parse($deadlineDate)->diffInDays(now(), false);
                        if ($diff > 7) {
                             // NEW: Check if this role does NOT have a penalty for non-completion after 7 days
                             $noPenaltyRoles = ['tim_setting_coord', 'tim_setting_member', 'tim_syuting_coord', 'tim_syuting_member', 'quality_control', 'manager_distribusi'];
                             $isNoPenalty = in_array($roleKey, $noPenaltyRoles);
                             
                             // BUT for Music Program, we enforce the penalty regardless of role (as per user request)
                             if ($isNoPenalty && !$isMusicRole) {
                                  $status = 'waiting_late';
                                  $points = 0;
                                  $waitingCount++;
                             } else {
                                  $status = 'failed';
                                  $points = $pointsNotDone;
                                  $notDoneCount++;
                             }
                        } else {
                             $status = 'waiting_late';
                             $points = 0;
                             $waitingCount++;
                        }
                    } else {
                        // FALLBACK: No air date and not completed yet? Keep waiting
                        $status = 'waiting';
                        $points = 0;
                        $waitingCount++;
                    }
                }
                // Add to items
                $items[] = [
                    'episode_id' => $episodeId,
                    'program_name' => ($episode->program?->name ?? 'Music Program') . ' (Music)',
                    'episode_number' => $episode->episode_number,
                    'role' => $roleKey,
                    'role_label' => $this->getDynamicRoleLabel($roleKey, $data['equipment_loan'] ?? null),
                    'deadline' => $deadlineDate ? Carbon::parse($deadlineDate)->toIso8601String() : null,
                    'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
                    'status' => $status,
                    'points' => (float)$points,
                    'max_points' => (int)$setting->points_on_time,
                    'program_type' => 'musik',
                ];
                $totalPoints += $points;
                $maxPoints += $setting->points_on_time;
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
            'musik_arr_song' => ['music_arranger', 'music', 'musik'],
            'musik_arr_lagu' => ['music_arranger', 'music', 'musik'],
            'musik_arr_help' => ['sound_engineer', 'sound', 'music_arranger', 'music', 'musik'],
            'sound_eng' => ['sound_engineer', 'sound'],
            'musik_vocal_edit' => ['sound_engineer', 'sound', 'vocal'],
            'art_set_alat_keluar' => ['art_set_properti', 'art_set', 'art', 'production_team', 'property'],
            'art_set_alat_masuk' => ['art_set_properti', 'art_set', 'art', 'production_team', 'property'],
            'producer_acc_song' => ['producer', 'director', 'manager_program'],
            'producer_acc_lagu' => ['producer', 'director', 'manager_program'],
            'art_set_design' => ['production_team', 'art_set', 'property'],
            'art_set_design_return' => ['production_team', 'art_set', 'property'],
            'distribution_manager_qc' => ['distribution_manager', 'manager_distribusi'],
            'producer' => ['producer', 'director', 'manager_program'],
            'produksi_setting' => ['production_team', 'produksi'],
            'produksi_syuting' => ['production_team', 'produksi'],
        ];

        $expectedRoles = $roleMapping[$workRole] ?? [$workRole];
        
        // Final definitive fix for Producer roles
        if (in_array($workRole, ['producer', 'producer_acc_song', 'producer_acc_lagu']) && 
            str_contains($normalizedUserRole, 'producer')) {
            return false;
        }

        // Match if user's normalized role contains any of the expected roles (e.g. 'producer_program' contains 'producer')
        foreach ($expectedRoles as $expected) {
            if (str_contains($normalizedUserRole, $expected)) {
                return false;
            }
        }

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

        // Add team details for Art & Set Properti so it's easier to understand
        if (($role === 'art_set_alat_keluar' || $role === 'art_set_alat_masuk') && $work) {
            $team = 'Team';
            if (isset($work->team_type)) {
                $team = ucfirst($work->team_type);
            } elseif (isset($work->borrower?->name)) {
                $team = $work->borrower->name;
            }
            
            $action = ($role === 'art_set_alat_keluar') ? 'Checkout' : 'Return';
            return "Equipment {$action} - Borrowed by: {$team}";
        }

        return $descriptions[$role] ?? 'General task completion';
    }

    /**
     * Get role label for display
     */
    private function getRoleLabel(string $role): string
    {
        $labels = [
            'producer' => 'Producer (Approve Script/Creative)',
            'producer_acc_song' => 'Producer (ACC Lagu Proposal)',
            'producer_acc_lagu' => 'Producer (ACC Hasil Arrangement)',
            'kreatif' => 'Creative (Script)',
            'produksi' => 'Production / Setting',
            'tim_setting_coord' => 'Koordinator Setting',
            'tim_setting_member' => 'Anggota Tim Setting',
            'tim_syuting_coord' => 'Koordinator Produksi/Syuting',
            'tim_syuting_member' => 'Anggota Tim Produksi/Syuting',
            'tim_vocal_coord' => 'Koordinator Rekam Vokal',
            'tim_vocal_member' => 'Anggota Tim Rekam Vokal',
            'art_set_design' => 'Art & Set Properti (Alat Keluar)',
            'art_set_design_return' => 'Art & Set Properti (Alat Masuk)',
            'art_set_alat_keluar' => 'Art & Set Properti (Alat Keluar)',
            'art_set_alat_masuk' => 'Art & Set Properti (Alat Masuk)',
            'editor' => 'Editor Video Program',
            'editor_promosi' => 'Editor Promosi (Materi Promosi)',
            'design_grafis' => 'Design Grafis',
            'quality_control' => 'QC',
            'broadcasting' => 'Broadcasting (Upload)',
            'promotion' => 'Promosi (Share Konten)',
            'promosi_syuting' => 'Promosi (Highlight IG)',
            'musik_arr' => 'Music Arranger',
            'musik_arr_song' => 'Ajukan Lagu',
            'musik_arr_lagu' => 'Aransemen Lagu',
            'sound_eng' => 'Sound Engineer',
            'promosi_syuting' => 'Promosi Syuting (Materi Promo)',
            'promotion_shooting' => 'Promosi Syuting (Materi Promo)',
            'manager_distribusi' => 'Manager Distribusi (QC Editor)',
            'distribution_manager_qc' => 'Manager Distribusi (QC Editor)',
        ];

        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
