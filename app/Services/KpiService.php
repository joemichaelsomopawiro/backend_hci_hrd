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

        // Total points will be calculated by summing points_on_time of all expected assignments
        // to support accurate percentage even for granular roles with different point values
        $maxPoints = 0; 

        // Get all program regular episodes this user worked on
        $this->collectPrWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $user);

        // Get all music program episodes this user worked on
        $this->collectMusicWorkPoints($userId, $month, $year, $settings, $workItems, $totalPoints, $maxPoints, $onTimeCount, $lateCount, $notDoneCount, $user);

        $totalTasks = $onTimeCount + $lateCount + $notDoneCount;
        
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
        $deadlineQuery = \App\Models\Deadline::with(['episode.program' => function($q) {
                $q->withTrashed();
            }])
            ->where('assigned_user_id', $userId)
            ->where(function($q) use ($month, $year) {
                // Include by Air Date
                $q->whereHas('episode', function ($sq) use ($month, $year) {
                    $sq->whereYear('air_date', $year);
                    if ($month) {
                        $sq->whereMonth('air_date', $month);
                    }
                })
                // OR Include by Deadline Date (so items like Ep 18 show up in the month they are due)
                ->orWhere(function($sq) use ($month, $year) {
                    $sq->whereYear('deadline_date', $year);
                    if ($month) {
                        $sq->whereMonth('deadline_date', $month);
                    }
                });
            });

        $assignedDeadlines = $deadlineQuery->get();
        
        // 1.5 WORK-BASED DISCOVERY: Find episodes where the user actually did work (Approvals)
        // even if no deadline was formally assigned to them.
        $activityEpisodeIds = \App\Models\MusicArrangement::where(function($q) use ($userId) {
                $q->where('reviewed_by', $userId)
                  ->orWhere('created_by', $userId);
            })
            ->where(function($q) use ($month, $year) {
                if ($month) {
                    $q->whereMonth('song_approved_at', $month)->orWhereMonth('reviewed_at', $month);
                }
                $q->whereYear('song_approved_at', $year)->orWhereYear('reviewed_at', $year);
            })
            ->pluck('episode_id')
            ->unique()
            ->toArray();
            
        $existingEpIds = $assignedDeadlines->pluck('episode_id')->unique()->toArray();
        $missingEpIds = array_diff($activityEpisodeIds, $existingEpIds);
        
        if (!empty($missingEpIds)) {
            $missingEpisodes = \App\Models\Episode::with(['program' => function($q) { $q->withTrashed(); }])
                ->whereIn('id', $missingEpIds)
                ->get();
            
            foreach ($missingEpisodes as $ep) {
                // Add a virtual base deadline so the expansion logic picks it up
                $assignedDeadlines->push((object)[
                    'role' => 'producer',
                    'episode_id' => $ep->id,
                    'episode' => $ep,
                    'deadline_date' => $ep->air_date ? $ep->air_date->copy()->subDays(10) : now(),
                    'assigned_user_id' => $userId,
                    'is_virtual' => true
                ]);
            }
        }

        // 2. Map Work Models to Roles
        $workModels = [
            'producer' => [
                'model' => \App\Models\CreativeWork::class,
                'fallback_model' => \App\Models\MusicArrangement::class, // Fallback for Music programs
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved', 'arrangement_approved'],
            ],
            'kreatif' => [
                'model' => \App\Models\CreativeWork::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['approved', 'submitted'],
            ],
            'musik_arr' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'arrangement_submitted_at',
                'status_completed' => ['submitted', 'approved', 'rejected'],
            ],
            'musik_arr_song' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'created_at',
                'status_completed' => ['draft', 'song_proposal', 'song_rejected', 'song_approved', 'submitted', 'approved', 'rejected'],
            ],
            'musik_arr_lagu' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'arrangement_submitted_at',
                'status_completed' => ['submitted', 'approved', 'rejected'],
            ],
            'producer_acc_song' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'song_approved_at',
                'status_completed' => ['song_approved', 'approved'], // Removed 'submitted' and 'rejected'
            ],
            'producer_acc_lagu' => [
                'model' => \App\Models\MusicArrangement::class,
                'completed_field' => 'reviewed_at',
                'status_completed' => ['arrangement_approved', 'approved'],
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

        // 3. Process each deadline (Expand for Music Producer roles if missing)
        $processedItems = [];
        $producerExpandedIds = []; // Track to avoid double expansion

        foreach ($assignedDeadlines as $deadlineRecord) {
            $processedItems[] = $deadlineRecord;
            
            // If it's a Producer role for a Music episode, and other roles are missing in the list
            // inject them so they appear in the KPI table automatically
            if ($deadlineRecord->role === 'producer' && !in_array($deadlineRecord->episode_id, $producerExpandedIds)) {
                $episode = $deadlineRecord->episode;
                if ($episode) {
                    $existingRoles = $assignedDeadlines->where('episode_id', $deadlineRecord->episode_id)->pluck('role')->toArray();
                    
                    if (!in_array('producer_acc_song', $existingRoles)) {
                        $processedItems[] = (object)[
                            'role' => 'producer_acc_song',
                            'episode_id' => $deadlineRecord->episode_id,
                            'episode' => $deadlineRecord->episode,
                            'deadline_date' => $deadlineRecord->episode->air_date ? $deadlineRecord->episode->air_date->copy()->subDays(15) : $deadlineRecord->deadline_date,
                            'assigned_user_id' => $deadlineRecord->assigned_user_id
                        ];
                    }
                    if (!in_array('producer_acc_lagu', $existingRoles)) {
                        $processedItems[] = (object)[
                            'role' => 'producer_acc_lagu',
                            'episode_id' => $deadlineRecord->episode_id,
                            'episode' => $deadlineRecord->episode,
                            'deadline_date' => $deadlineRecord->episode->air_date ? $deadlineRecord->episode->air_date->copy()->subDays(11) : $deadlineRecord->deadline_date,
                            'assigned_user_id' => $deadlineRecord->assigned_user_id
                        ];
                    }
                    $producerExpandedIds[] = $deadlineRecord->episode_id;
                }
            }
        }

        foreach ($processedItems as $deadlineRecord) {
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

                // Fallback logic for roles that might use different models (e.g. Producer in Music programs)
                if (!$work && isset($config['fallback_model'])) {
                    $work = $config['fallback_model']::where('episode_id', $episode->id)->first();
                }
            }

            $completedAt = $work ? ($work->{$config['completed_field']} ?? null) : null;
            $isCompleted = $work ? in_array($work->status, $config['status_completed']) : false;
            $deadlineDate = $deadlineRecord->deadline_date ?? null;

            // Sound engineer check specifically Needs SE Help
            if ($roleKey === 'sound_eng' && $work && !$work->needs_sound_engineer_help) continue;

            $status = 'not_done';
            $points = $setting->points_not_done;

            if ($isCompleted && $completedAt) {
                $delayDays = Carbon::parse($deadlineDate)->diffInDays(Carbon::parse($completedAt), false);
                
                if ($deadlineDate && Carbon::parse($completedAt)->lte(Carbon::parse($deadlineDate))) {
                    $status = 'on_time';
                    $points = $setting->points_on_time;
                    $onTimeCount++;
                } else if ($delayDays > 7) {
                    // Penalti 7 Hari: Gagal total jika terlambat lebih dari seminggu
                    $status = 'late_failed';
                    // KHUSUS MUSIK: Gunakan pinalti -5 (points_not_done). SELAIN ITU (Regular): Tetap 0 agar aman.
                    $points = ($setting->program_type === 'musik') ? $setting->points_not_done : 0;
                    $lateCount++;
                } else {
                    $status = 'late';
                    $points = $setting->points_late;
                    $lateCount++;
                }
            } else if (!$isCompleted) {
                // Strictly follow the deadline for point deductions
                if ($deadlineDate && Carbon::parse($deadlineDate)->isPast()) {
                    $overdueDays = Carbon::parse($deadlineDate)->diffInDays(now(), false);
                    
                    if ($overdueDays > 7) {
                        // Gagal total jika melewati deadline lebih dari seminggu tanpa pengerjaan
                        // KHUSUS MUSIK: Gunakan pinalti -5 (points_not_done). SELAIN ITU: Tetap 0.
                        $points = ($setting->program_type === 'musik') ? $setting->points_not_done : 0;
                        $status = 'failed';
                    } else {
                        // Belum sampai 7 hari: Berikan 0 poin (belum pinalti penuh tapi tidak ada poin)
                        $points = 0;
                        $status = 'not_done';
                    }
                    $notDoneCount++;
                } else {
                    // Task is not done but deadline hasn't passed yet, points are 0 (not penalized yet)
                    $points = 0;
                    $status = 'pending';
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

            if (in_array($roleKey, ['producer', 'manager_distribusi', 'program_manager'])) {
                // Sesuai Excel: KPI Manager adalah rata-rata gabungan dari semua tim-nya dia
                // Kita ambil semua perolehan poin dari anggota tim musik lainnya di episode ini (kecuali manager itu sendiri)
                $teamPerformanceIds = \App\Models\User::whereIn('role', [
                        'Musik Arr', 'Sound Engineer', 'Kreatif', 'Editor', 'Quality Control',
                        'Artistika', 'Broadcasting', 'Promotion', 'Tim Setting', 'Tim Syuting'
                    ])->pluck('id');

                // Kita cari rata-rata kualitas tim sebagai dasar poin manager
                $teamAvgPoints = \App\Models\KpiQualityScore::where('music_episode_id', $episode->id)
                    ->whereIn('employee_id', $teamPerformanceIds)
                    ->avg('quality_score');
                
                if ($teamAvgPoints) {
                    $effectivePoints = round($teamAvgPoints, 1);
                    $displayQuality = $effectivePoints;
                    $isQualityOverridden = true;
                }
            } else if ($qualityScore) {
                $effectivePoints = $qualityScore->quality_score;
            }

            $totalPoints += $effectivePoints;
            if (!$isBackup) {
                $maxPoints += $setting->points_on_time;
            }

            $items[] = [
                'episode_id' => $episode->id,
                'program_name' => ($episode->program?->name ?? ($episode->program_id ? (\App\Models\Program::withTrashed()->find($episode->program_id)?->name ?? 'Music Program') : 'Music Program')) . ' (Music)',
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
            'producer_acc_song' => ['producer', 'director', 'manager_program'],
            'producer_acc_lagu' => ['producer', 'director', 'manager_program'],
            'art_set_design' => ['production_team', 'art_set', 'property'],
            'art_set_design_return' => ['production_team', 'art_set', 'property'],
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
            'producer' => 'Producer (Approve Script/Creative)',
            'producer_acc_song' => 'Producer (ACC Lagu Proposal)',
            'producer_acc_lagu' => 'Producer (ACC Hasil Arrangement)',
            'sound_eng' => 'Edit Audio / Mixing',
            'tim_setting_coord' => 'Koordinator Setting',
            'tim_syuting_coord' => 'Koordinator Produksi/Syuting',
            'tim_vocal_coord' => 'Take Rekam Audio - Vocal',
            'art_set_design' => 'Art & Set Properti (Alat Keluar)',
            'art_set_design_return' => 'Art & Set Properti (Alat Masuk)',
            'editor' => 'Editor Video Program',
            'editor_promosi' => 'Editor Promosi (Highlight IG)',
            'design_grafis' => 'Design Grafis',
            'quality_control' => 'QC',
            'broadcasting' => 'Broadcasting (Upload)',
            'promotion' => 'Promosi (Share Konten)',
            'promosi_syuting' => 'Promosi (Highlight IG)',
            'musik_arr' => 'Music Arranger',
            'musik_arr_song' => 'Ajukan Lagu',
            'musik_arr_lagu' => 'Aransemen Lagu',
            'sound_eng' => 'Sound Engineer',
            'promosi_syuting' => 'Promosi Syuting (Highlight IG dsb)',
            'manager_distribusi' => 'Manager Distribusi (QC Editor)',
        ];

        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
