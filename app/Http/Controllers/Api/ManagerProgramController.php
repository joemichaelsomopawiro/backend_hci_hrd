<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Program;
use App\Models\ProductionTeam;
use App\Models\Deadline;
use App\Models\MusicSchedule;
use App\Models\BroadcastingSchedule;
use App\Models\ProgramApproval;
use App\Models\QualityControl;
use App\Models\QualityControlWork;
use App\Models\ProductionTeamAssignment;
use App\Models\Notification;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\SoundEngineerRecording;
use App\Models\ProduksiWork;
use App\Models\EditorWork;
use App\Models\DesignGrafisWork;
use App\Models\PromotionWork;
use App\Models\BroadcastingWork;
use App\Models\ProductionEquipment;
use Carbon\Carbon;
use App\Services\ProgramPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ManagerProgramController extends Controller
{
    /**
     * Get Dashboard Statistics
     * GET /api/live-tv/manager-program/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
             return response()->json(['success' => false, 'message' => 'Authentication required'], 401);
        }

        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Stats basics - Optimized with simple counts
            $activePrograms = Program::where('status', 'active')->count();
            $totalEpisodes = Episode::count();
            $pendingApprovals = ProgramApproval::where('status', 'pending')->count();
            
            // Upcoming deadlines (next 7 days)
            $upcomingDeadlines = Deadline::where('deadline_date', '>=', now())
                ->where('deadline_date', '<=', now()->addDays(7))
                ->count();
                
            // Recent activities - Optimized with proper eager loading for polymorphic relation
            $recentActivities = ProgramApproval::with(['approvable' => function($query) {
                    // This handles multiple types of approvables if needed
                }])
                ->latest()
                ->take(5)
                ->get()
                ->map(function($approval) {
                    // Try to get program name through whichever relation exists
                    $programName = 'Unknown';
                    if (isset($approval->approvable->program)) {
                        $programName = $approval->approvable->program->name;
                    } elseif (isset($approval->approvable->episode->program)) {
                        $programName = $approval->approvable->episode->program->name;
                    }

                    return [
                        'id' => $approval->id,
                        'type' => $approval->approval_type,
                        'program_name' => $programName,
                        'status' => $approval->status,
                        'created_at' => $approval->created_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'active_programs' => $activePrograms,
                    'total_episodes' => $totalEpisodes,
                    'pending_approvals' => $pendingApprovals,
                    'upcoming_deadlines' => $upcomingDeadlines,
                    'recent_activities' => $recentActivities,
                    'budget_requests' => ProgramApproval::where('approval_type', 'special_budget')->where('status', 'pending')->count()
                ],
                'message' => 'Dashboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Assign/Change production team untuk episode tertentu (with program context)
     */
    public function assignTeam(Request $request, int $programId, int $episodeId): JsonResponse
    {
        return $this->assignTeamToEpisode($request, $episodeId);
    }
    
    /**
     * Assign/Change production team untuk episode tertentu
     */
    public function assignTeamToEpisode(Request $request, int $episodeId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can assign teams'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'production_team_id' => 'required|exists:production_teams,id',
            'notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode = Episode::findOrFail($episodeId);
            $team = ProductionTeam::findOrFail($request->production_team_id);
            
            // Save old team for notification
            $oldTeamId = $episode->production_team_id;
            
            // Update episode team
            $episode->update([
                'production_team_id' => $request->production_team_id,
                'team_assignment_notes' => $request->notes,
                'team_assigned_by' => $user->id,
                'team_assigned_at' => now()
            ]);
            
            // Clear cache setelah assign team (episodes dan programs cache perlu di-clear)
            \App\Helpers\QueryOptimizer::clearAllIndexCaches();
            
            // Create notification for new team members
            $teamMembers = $team->members()->where('is_active', true)->get();
            foreach ($teamMembers as $member) {
                \App\Models\Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'team_assigned',
                    'title' => 'Ditugaskan ke Episode Baru',
                    'message' => "Anda ditugaskan untuk Episode {$episode->episode_number} - {$episode->title}",
                    'data' => [
                        'episode_id' => $episode->id,
                        'program_id' => $episode->program_id,
                        'notes' => $request->notes
                    ]
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'production_team_id' => $episode->production_team_id,
                    'team_assigned_at' => $episode->team_assigned_at,
                    'team_assigned_by' => $episode->team_assigned_by,
                    'team_assignment_notes' => $episode->team_assignment_notes
                ],
                'message' => 'Team assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign team',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Edit deadline untuk episode (with program & episode context)
     */
    public function editDeadline(Request $request, int $programId, int $episodeId, int $deadlineId): JsonResponse
    {
        return $this->editDeadlineById($request, $deadlineId);
    }
    
    /**
     * Edit deadline untuk episode
     */
    public function editDeadlineById(Request $request, int $deadlineId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can edit deadlines'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'deadline_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'description' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $deadline = Deadline::findOrFail($deadlineId);
            
            // Save old deadline for history
            $oldDeadline = $deadline->deadline_date;
            
            // Update deadline
            $deadline->update([
                'deadline_date' => $request->deadline_date,
                'description' => $request->description ?? $deadline->description,
                'change_reason' => $request->reason,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'auto_generated' => false // Mark as manually edited
            ]);
            
            // Notify role yang terkait
            $episode = $deadline->episode;
            if ($episode && $episode->productionTeam) {
                $teamMembers = $episode->productionTeam->members()
                    ->where('role', $deadline->role)
                    ->where('is_active', true)
                    ->get();
                    
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'deadline_changed',
                        'title' => 'Deadline Diubah',
                        'message' => "Deadline untuk Episode {$episode->episode_number} telah diubah",
                        'data' => [
                            'episode_id' => $episode->id,
                            'old_deadline' => $oldDeadline,
                            'new_deadline' => $request->deadline_date,
                            'reason' => $request->reason
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $deadline->id,
                    'episode_id' => $deadline->episode_id,
                    'role' => $deadline->role,
                    'deadline_date' => $deadline->deadline_date,
                    'changed_deadline_date' => $deadline->deadline_date,
                    'change_reason' => $deadline->change_reason,
                    'changed_by' => $deadline->changed_by,
                    'changed_at' => $deadline->changed_at
                ],
                'message' => 'Deadline updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate episodes untuk program (manual trigger)
     */
    public function generateEpisodes(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can generate episodes'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'number_of_episodes' => 'required|integer|min:1|max:100',
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    // Validasi: start_date untuk planning Episode 1
                    // Boleh di masa lalu (program yang sudah berjalan) atau masa depan (planning)
                    // Tapi tahun harus masuk akal (antara tahun sekarang - 1 sampai tahun sekarang + 5)
                    $startDate = Carbon::parse($value);
                    $currentYear = Carbon::now()->year;
                    $year = $startDate->year;
                    
                    if ($year < ($currentYear - 1)) {
                        $fail('The start_date year cannot be more than 1 year in the past. Start date is used for planning Episode 1.');
                    }
                    
                    if ($year > ($currentYear + 5)) {
                        $fail('The start_date year cannot be more than 5 years in the future. Please select a reasonable planning date.');
                    }
                },
            ],
            'interval_days' => 'required|integer|min:1|max:30',
            'regenerate' => 'nullable|boolean' // Opsi untuk regenerate episode yang sudah ada
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $validated = $validator->validated();
        
        try {
            $program = Program::findOrFail($programId);
            
            // Check if episodes already generated (exclude soft deleted)
            $existingEpisodes = $program->episodes()->whereNull('deleted_at')->count();
            
            if ($existingEpisodes > 0) {
                // Jika regenerate = true, hapus episode yang lama dulu
                if ($request->get('regenerate', false)) {
                    // Soft delete semua episode yang ada
                    $program->episodes()->whereNull('deleted_at')->each(function($episode) {
                        $episode->delete(); // Soft delete
                    });
                } else {
                    // Episode sudah ada, return info tanpa error
                    // Biarkan user tahu episode sudah ada, tapi tetap bisa generate dengan regenerate=true
                    return response()->json([
                        'success' => false,
                        'message' => 'Episodes already generated for this program. Use regenerate=true to regenerate episodes.',
                        'existing_episodes_count' => $existingEpisodes,
                        'can_regenerate' => true
                    ], 400);
                }
            }
            
            // Generate episodes with custom parameters
            $numberOfEpisodes = $validated['number_of_episodes'];
            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $intervalDays = $validated['interval_days'];
            
            $episodes = [];
            
            for ($i = 1; $i <= $numberOfEpisodes; $i++) {
                $airDate = $startDate->copy()->addDays(($i - 1) * $intervalDays);
                
                $episode = Episode::create([
                    'program_id' => $program->id,
                    'episode_number' => $i,
                    'title' => "Episode $i",
                    'description' => "Episode $i dari program {$program->name}",
                    'air_date' => $airDate->format('Y-m-d'),
                    'production_date' => $airDate->copy()->subDays(7), // 7 hari sebelum tayang
                    'status' => 'draft',
                    'current_workflow_state' => 'episode_generated'
                ]);
                
                // Generate deadlines automatically
                $episode->generateDeadlines();
                
                // Create initial workflow state
                \App\Models\WorkflowState::create([
                    'episode_id' => $episode->id,
                    'current_state' => 'episode_generated',
                    'assigned_to_role' => 'manager_program',
                    'assigned_to_user_id' => $program->manager_program_id,
                    'notes' => 'Episode generated by Manager Program'
                ]);
                
                $episodes[] = $episode->load(['deadlines', 'workflowStates']);
            }
            
            // Reload dengan episodes
            $program->load('episodes.deadlines');
            
            // ✅ OPTIMIZED: Notify production team if exists (bulk insert to avoid N+1)
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
                
                if ($teamMembers->isNotEmpty()) {
                    $notifications = $teamMembers->map(function($member) use ($numberOfEpisodes, $program) {
                        return [
                            'user_id' => $member->user_id,
                            'type' => 'episodes_generated',
                            'title' => 'Episode Program Dibuat',
                            'message' => "$numberOfEpisodes episode untuk program '{$program->name}' telah dibuat dengan deadline otomatis",
                            'data' => json_encode([
                                'program_id' => $program->id,
                                'total_episodes' => $numberOfEpisodes
                            ]),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    })->toArray();
                    
                    \DB::table('notifications')->insert($notifications);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "$numberOfEpisodes episodes generated successfully with deadlines",
                'data' => [
                    'episodes' => $episodes,
                    'program_id' => $program->id,
                    'total_episodes' => count($episodes)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate episodes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate episodes untuk tahun berikutnya (auto-detect tahun)
     * Sistem akan otomatis detect tahun berikutnya dan generate 52 episode
     * Episode number akan continue dari episode terakhir
     */
    public function generateNextYearEpisodes(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($programId);
        
        $isManager = in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram']);
        $isProducer = ($user->role === 'Producer' && $program->producer_id == $user->id);
        
        if (!$isManager && !$isProducer) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or assigned Producer can generate next year episodes'
            ], 403);
        }
        
        try {
            $program = Program::findOrFail($programId);
            
            // Check status program
            if (!in_array($program->status, ['active', 'approved', 'in_production'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program harus dalam status active, approved, atau in_production untuk generate episode tahun berikutnya'
                ], 400);
            }
            
            // Check apakah perlu generate
            $checkResult = $program->checkNextYearEpisodes();
            
            if (!$checkResult['needs_generation']) {
                return response()->json([
                    'success' => false,
                    'message' => $checkResult['message'],
                    'data' => $checkResult
                ], 400);
            }
            
            // Generate episode untuk tahun berikutnya
            $result = $program->generateNextYearEpisodes();
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result
                ], 400);
            }
            
            // ✅ OPTIMIZED: Notifikasi ke production team (bulk insert to avoid N+1)
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
                
                if ($teamMembers->isNotEmpty()) {
                    $notifications = $teamMembers->map(function($member) use ($program, $result) {
                        return [
                            'user_id' => $member->user_id,
                            'type' => 'episodes_generated',
                            'title' => 'Episode Tahun Berikutnya Dibuat',
                            'message' => "52 episode untuk tahun {$result['year']} dari program '{$program->name}' telah dibuat otomatis",
                            'data' => json_encode([
                                'program_id' => $program->id,
                                'year' => $result['year'],
                                'total_episodes' => $result['generated_count'],
                                'start_episode_number' => $result['start_episode_number'],
                                'end_episode_number' => $result['end_episode_number']
                            ]),
                            'program_id' => $program->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    })->toArray();
                    
                    \DB::table('notifications')->insert($notifications);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil generate 52 episode untuk tahun {$result['year']}",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate next year episodes: ' . $e->getMessage(), [
                'program_id' => $programId,
                'error' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate next year episodes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate episodes untuk tahun tertentu
     * @param int $year Tahun yang akan di-generate (opsional, default: tahun berikutnya)
     */
    public function generateEpisodesForYear(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can generate episodes for specific year'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = Program::findOrFail($programId);
            $year = $request->input('year');
            
            // Check status program
            if (!in_array($program->status, ['active', 'approved', 'in_production'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program harus dalam status active, approved, atau in_production'
                ], 400);
            }
            
            // Generate episode untuk tahun tertentu
            $result = $program->generateEpisodesForYear($year);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result
                ], 400);
            }
            
            // Notifikasi ke production team
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
                foreach ($teamMembers as $member) {
                    Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'episodes_generated',
                        'title' => 'Episode Tahun Dibuat',
                        'message' => "52 episode untuk tahun {$year} dari program '{$program->name}' telah dibuat",
                        'data' => [
                            'program_id' => $program->id,
                            'year' => $year,
                            'total_episodes' => $result['generated_count'],
                            'start_episode_number' => $result['start_episode_number'],
                            'end_episode_number' => $result['end_episode_number']
                        ],
                        'program_id' => $program->id
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil generate 52 episode untuk tahun {$year}",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate episodes for year: ' . $e->getMessage(), [
                'program_id' => $programId,
                'year' => $request->input('year'),
                'error' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate episodes for year',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check status episode untuk tahun berikutnya
     * Endpoint untuk cek apakah perlu generate episode tahun berikutnya
     */
    public function checkNextYearEpisodes(int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can check next year episodes status'
            ], 403);
        }
        
        try {
            $program = Program::findOrFail($programId);
            $result = $program->checkNextYearEpisodes();
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $result['needs_generation'] 
                    ? 'Perlu generate episode untuk tahun berikutnya' 
                    : 'Tidak perlu generate episode untuk tahun berikutnya'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check next year episodes status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get list tahun yang tersedia untuk program (untuk dropdown filter)
     * Data episode tahun sebelumnya tetap tersimpan, tidak dihapus
     */
    public function getProgramYears(int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view program years'
            ], 403);
        }
        
        try {
            $program = Program::findOrFail($programId);
        
            // Get semua episode untuk program ini (eager load required data)
            // Optimize: Fetch ALL once and group in PHP to avoid N+1 in loops
            $allEpisodes = Episode::where('program_id', $programId)
                ->whereNull('deleted_at')
                ->whereNotNull('air_date')
                ->get();

            // Get unique years
            $years = $allEpisodes->map(function ($episode) {
                return \Carbon\Carbon::parse($episode->air_date)->year;
            })->unique()->sortDesc()->values()->map(function ($year) use ($allEpisodes) {
                // Filter episodes for this year from the already fetched collection
                $yearEpisodes = $allEpisodes->filter(function ($episode) use ($year) {
                    return \Carbon\Carbon::parse($episode->air_date)->year == $year;
                });
                
                return [
                    'year' => (int)$year,
                    'episode_count' => $yearEpisodes->count(),
                    'first_episode_number' => $yearEpisodes->min('episode_number'),
                    'last_episode_number' => $yearEpisodes->max('episode_number'),
                    'first_air_date' => $yearEpisodes->min('air_date') ? \Carbon\Carbon::parse($yearEpisodes->min('air_date'))->format('Y-m-d') : null,
                    'last_air_date' => $yearEpisodes->max('air_date') ? \Carbon\Carbon::parse($yearEpisodes->max('air_date'))->format('Y-m-d') : null
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program_id' => $programId,
                    'program_name' => $program->name,
                    'years' => $years,
                    'total_years' => $years->count(),
                    'current_year' => \Carbon\Carbon::now()->year
                ],
                'message' => 'Program years retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get program years',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get episodes grouped by year untuk program tertentu
     * Berguna untuk dropdown filter per tahun
     */
    public function getEpisodesByYear(int $programId, Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view episodes by year'
            ], 403);
        }
        
        try {
            $program = Program::findOrFail($programId);
            
            // Get semua episode untuk program ini
            $episodes = Episode::where('program_id', $programId)
                ->whereNull('deleted_at')
                ->with(['deadlines', 'workflowStates'])
                ->orderBy('air_date', 'asc')
                ->orderBy('episode_number', 'asc')
                ->get();
            
            // Group by year
            $groupedByYear = $episodes->groupBy(function ($episode) {
                return \Carbon\Carbon::parse($episode->air_date)->year;
            })->map(function ($yearEpisodes, $year) {
                return [
                    'year' => (int)$year,
                    'episodes' => $yearEpisodes->values(),
                    'count' => $yearEpisodes->count(),
                    'first_episode_number' => $yearEpisodes->min('episode_number'),
                    'last_episode_number' => $yearEpisodes->max('episode_number'),
                    'first_air_date' => $yearEpisodes->min('air_date') ? \Carbon\Carbon::parse($yearEpisodes->min('air_date'))->format('Y-m-d') : null,
                    'last_air_date' => $yearEpisodes->max('air_date') ? \Carbon\Carbon::parse($yearEpisodes->max('air_date'))->format('Y-m-d') : null
                ];
            })->sortByDesc('year')->values();
            
            // Filter by year jika ada parameter
            if ($request->has('year')) {
                $selectedYear = $request->get('year');
                $groupedByYear = $groupedByYear->filter(function ($yearData) use ($selectedYear) {
                    return $yearData['year'] == $selectedYear;
                })->values();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program_id' => $programId,
                    'program_name' => $program->name,
                    'episodes_by_year' => $groupedByYear,
                    'total_episodes' => $episodes->count(),
                    'total_years' => $groupedByYear->count()
                ],
                'message' => 'Episodes grouped by year retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get episodes by year',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Manager Program Dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or Distribution Manager can access this dashboard'
            ], 403);
        }
        
        try {
            // Programs managed by this user
            $programs = Program::where('manager_program_id', $user->id)
                ->with(['productionTeam'])
                ->withCount('episodes')
                ->get();
            
            // Statistics
            $stats = [
                'total_programs' => $programs->count(),
                'active_programs' => $programs->where('status', 'active')->count(),
                'draft_programs' => $programs->where('status', 'draft')->count(),
                'total_episodes' => Episode::whereIn('program_id', $programs->pluck('id'))
                    ->whereNull('deleted_at')
                    ->whereHas('program', function($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->count(),
                'pending_approvals' => ProgramApproval::where('status', 'pending')->count(),
                'budget_requests' => Program::where('budget_approved', false)
                    ->whereNotNull('budget_amount')
                    ->count()
            ];
            
            // Upcoming deadlines
            $upcomingDeadlines = Deadline::whereHas('episode', function($q) use ($programs) {
                $q->whereIn('program_id', $programs->pluck('id'));
            })
            ->where('deadline_date', '>=', now())
            ->where('deadline_date', '<=', now()->addDays(7))
            ->where('is_completed', false)
            ->orderBy('deadline_date')
            ->with('episode.program')
            ->get();
            
            // Recent activities
            $recentPrograms = $programs->sortByDesc('updated_at')->take(5)->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'active_programs' => $stats['active_programs'], // Flat for compatibility
                    'total_episodes' => $stats['total_episodes'], // Flat for compatibility
                    'pending_approvals' => $stats['pending_approvals'], // Flat for compatibility
                    'upcoming_deadlines_count' => $upcomingDeadlines->count(), // Added
                    'budget_requests' => $stats['budget_requests'], // Added
                    'programs' => $programs,
                    'upcoming_deadlines' => $upcomingDeadlines,
                    'recent_activities' => $recentPrograms
                ],
                'message' => 'Dashboard data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update episode views manually
     */
    public function updateEpisodeViews(Request $request, int $episodeId): JsonResponse
    {
        $user = auth()->user();
        $episode = Episode::with('program')->findOrFail($episodeId);
        $program = $episode->program;
        
        $isManager = in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram']);
        $isProducer = ($user->role === 'Producer' && $program && $program->producer_id == $user->id);
        
        if (!$isManager && !$isProducer) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or assigned Producer can update views'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'actual_views' => 'required|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode = Episode::with('program')->findOrFail($episodeId);
            
            // Calculate previous views for growth rate
            $previousViews = $episode->actual_views ?? 0;
            
            // Update views
            $episode->update([
                'actual_views' => $request->actual_views,
                'views_last_updated' => now()
            ]);
            
            // Calculate growth rate
            if ($previousViews > 0) {
                $growthRate = (($request->actual_views - $previousViews) / $previousViews) * 100;
                $episode->update(['views_growth_rate' => $growthRate]);
            }
            
            // Check performance
            $targetViews = $episode->program->target_views_per_episode ?? 0;
            $performance = 'below_target';
            if ($request->actual_views >= $targetViews) {
                $performance = 'above_target';
            }
            
            $episode->refresh();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'actual_views' => $episode->actual_views,
                    'target_views' => $targetViews,
                    'performance' => $performance,
                    'growth_rate' => $episode->views_growth_rate ?? 0,
                    'views_last_updated' => $episode->views_last_updated
                ],
                'message' => 'Views updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update views',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get program performance
     */
    public function getProgramPerformance(int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or Distribution Manager can view performance'
            ], 403);
        }
        
        try {
            $program = Program::findOrFail($programId);
            
            $stats = $program->episodes()
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) as total_episodes,
                    SUM(CASE WHEN actual_views > 0 THEN 1 ELSE 0 END) as episodes_with_views,
                    AVG(CASE WHEN actual_views > 0 THEN actual_views ELSE NULL END) as average_views
                ')
                ->first();

            $totalEpisodes = $stats->total_episodes ?? 0;
            $episodesWithViews = $stats->episodes_with_views ?? 0;
            $averageViews = $stats->average_views ?? 0;
            $targetViews = $program->target_views_per_episode ?? 0;
            
            // Calculate performance
            $performancePercentage = $targetViews > 0 ? ($averageViews / $targetViews) * 100 : 0;
            $performanceStatus = 'pending';
            
            if ($performancePercentage >= 150) {
                $performanceStatus = 'excellent';
            } elseif ($performancePercentage >= 100) {
                $performanceStatus = 'good';
            } elseif ($performancePercentage >= 75) {
                $performanceStatus = 'fair';
            } elseif ($episodesWithViews > 0) {
                $performanceStatus = 'poor';
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'total_episodes' => $totalEpisodes,
                    'episodes_with_views' => $episodesWithViews,
                    'average_views' => round($averageViews, 2),
                    'target_views' => $targetViews,
                    'performance_status' => $performanceStatus,
                    'performance_percentage' => round($performancePercentage, 2),
                    'recommendation' => $this->getRecommendation($performanceStatus)
                ],
                'message' => 'Program performance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get recommendation based on performance
     */
    private function getRecommendation(string $status): string
    {
        return match($status) {
            'excellent' => 'Program performing excellently, consider increasing production',
            'good' => 'Program performing well, continue production',
            'fair' => 'Program needs improvement, review content strategy',
            'poor' => 'Program underperforming, consider major changes or closure',
            default => 'Need more data to evaluate'
        };
    }
    
    /**
     * Get weekly performance report
     */
    public function getWeeklyPerformance(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or Distribution Manager can view performance'
            ], 403);
        }
        
        try {
            $performanceService = app(ProgramPerformanceService::class);
            $report = $performanceService->getWeeklyPerformanceReport($programId, $request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Weekly performance report retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Set/Update target views per episode untuk program
     */
    public function setTargetViews(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($programId);
        
        $isManager = in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager']);
        $isProducer = ($user->role === 'Producer' && $program->producer_id == $user->id);
        
        if (!$isManager && !$isProducer) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program, Distribution Manager, or assigned Producer can set target views'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'target_views_per_episode' => 'required|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = Program::findOrFail($programId);
            $oldTarget = $program->target_views_per_episode;
            
            // Update target views
            $program->update([
                'target_views_per_episode' => $request->target_views_per_episode
            ]);
            
            // Re-evaluate performance setelah update target
            $performanceService = app(ProgramPerformanceService::class);
            $performanceService->evaluateProgramPerformance($program);
            
            $program->refresh();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'old_target_views' => $oldTarget,
                    'new_target_views' => $program->target_views_per_episode,
                    'average_views_per_episode' => $program->average_views_per_episode,
                    'performance_status' => $program->performance_status
                ],
                'message' => 'Target views updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update target views',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Monitoring semua pekerjaan episode hingga penayangan
     * Menampilkan status semua tahap workflow dari awal sampai tayang
     */
    public function monitorEpisodeWorkflow(int $episodeId): JsonResponse
    {
        try {
            $episode = Episode::with([
                'program',
                'deadlines',
                'workflowStates.assignedToUser',
                'musicArrangements.createdBy',
                'musicArrangements.reviewedBy',
                'creativeWorks.createdBy',
                'creativeWorks.reviewedBy',
                'soundEngineerRecordings.createdBy',
                'soundEngineerRecordings.reviewedBy',
                'soundEngineerEditings.createdBy',
                'soundEngineerEditings.approvedBy',
                'soundEngineerEditings.rejectedBy',
                'produksiWorks.createdBy',
                'produksiWorks.completedBy',
                'produksiWorks.settingCompletedBy',
                'editorWorks.createdBy',
                'editorWorks.reviewedBy',
                'designGrafisWorks.createdBy',
                'designGrafisWorks.reviewedBy',
                'qualityControls.createdBy',
                'qualityControls.qcBy',
                'broadcastingSchedules',
                'broadcastingWorks.createdBy',
                'broadcastingWorks.acceptedBy',
                'promotionWorks.createdBy',
                'designGrafisWorks.createdBy',
                'productionTeam.members.user'
            ])->findOrFail($episodeId);

            $buildStep = function ($key, $name, $completed, $status, $reason, $data = null, $deadlineRow = null, $completedAt = null, $completedBy = null, $reviewNotes = null, $rejectionReason = null) {
                $isDeadlineMet = true;
                if ($completed && $completedAt && $deadlineRow && $deadlineRow->deadline_date) {
                    $isDeadlineMet = Carbon::parse($completedAt)->startOfDay() <= Carbon::parse($deadlineRow->deadline_date)->startOfDay();
                }

                $step = [
                    'step_key' => $key,
                    'step_name' => $name,
                    'completed' => (bool) $completed,
                    'status' => $status,
                    'reason_if_not_completed' => $reason,
                    'review_notes' => $reviewNotes,
                    'rejection_reason' => $rejectionReason,
                    'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
                    'completed_by_name' => $completedBy,
                    'is_deadline_met' => $isDeadlineMet,
                    'data' => $data,
                ];

                if ($deadlineRow !== null) {
                    $step['deadline'] = $deadlineRow->deadline_date;
                }

                return $step;
            };

            // Workflow Tracking Logic
            $workflowSteps = [];
            $deadlines = $episode->deadlines;

            $workflowOrder = [];
            if ($episode->program && $episode->program->category === 'musik') {
                $workflowOrder = [
                    'program_active', 'song_proposal', 'song_proposal_approval',
                    'music_arrangement_link', 'arrangement_approval', 'creative_concept',
                    'producer_creative_approval', 'set_property', 'vocal_recording',
                    'promotion_content_start', 'shooting_production', 'return_equipment_production',
                    'return_equipment_vocal', 'vocal_editing', 'video_editing',
                    'design_promo_editing', 'producer_final_review', 'pm_review',
                    'quality_control', 'dm_schedule', 'broadcasting_publishing', 'promotion_sharing'
                ];
            }

            // --- STEP HISTORY COLLECTION ---
            $stepHistory = [];
            
            // 1. Base Workflow States (Immutable Logs)
            foreach($episode->workflowStates as $state) {
                $meta = $state->metadata ?? [];
                $action = $meta['action'] ?? null;
                $targetSteps = [$state->current_state];
                
                // Smart mapping of actions to dashboard step keys
                if ($action === 'song_proposal_submitted') $targetSteps = ['song_proposal'];
                if ($action === 'song_proposal_rejected' || $action === 'song_proposal_approved') $targetSteps = ['song_proposal', 'song_proposal_approval'];
                
                if ($action === 'music_arrangement_submitted') $targetSteps = ['music_arrangement_link'];
                if ($action === 'music_arrangement_rejected' || $action === 'music_arrangement_approved') $targetSteps = ['music_arrangement_link', 'arrangement_approval'];
                
                if ($action === 'creative_work_submitted') $targetSteps = ['creative_concept'];
                if ($action === 'creative_work_rejected' || $action === 'creative_work_approved') $targetSteps = ['creative_concept', 'producer_creative_approval'];
                
                if ($action === 'video_editing_submitted') $targetSteps = ['video_editing', 'producer_final_review'];
                if ($action === 'video_editing_rejected' || $action === 'video_editing_approved') $targetSteps = ['video_editing', 'producer_final_review'];
                
                if ($action === 'vocal_editing_submitted') $targetSteps = ['vocal_editing'];
                if ($action === 'vocal_editing_rejected' || $action === 'vocal_editing_approved') $targetSteps = ['vocal_editing'];

                if ($action === 'shooting_production_completed') $targetSteps = ['shooting_production'];

                $type = 'activity';
                if (strpos($action ?? '', 'reject') !== false || strpos($state->notes ?? '', 'Reject') !== false) {
                    $type = 'rejection';
                } elseif (strpos($action ?? '', 'approve') !== false || strpos($action ?? '', 'completed') !== false) {
                    $type = 'approval';
                }

                $logEntry = [
                    'type' => $type,
                    'status' => $state->current_state,
                    'action' => $action,
                    'notes' => $state->notes,
                    'reason' => $meta['rejection_reason'] ?? $meta['rejection_notes'] ?? null,
                    'user' => $state->assignedToUser->name ?? ($state->performingUser->name ?? 'System'),
                    'created_at' => $state->created_at->toIso8601String()
                ];

                foreach($targetSteps as $tStep) {
                   if (!isset($stepHistory[$tStep])) $stepHistory[$tStep] = [];
                   $stepHistory[$tStep][] = $logEntry;
                }
            }

            // 2. Music-Specific History (Fallback from model if WorkflowState is missing)
            foreach($episode->musicArrangements as $ma) {
                // Arrangement logs from model as a fallback (will be duplicates but aggregated)
                if (in_array($ma->status, ['arrangement_rejected', 'arrangement_approved', 'approved'])) {
                    $arrLog = [
                        'type' => $ma->status === 'arrangement_rejected' ? 'rejection' : 'approval',
                        'status' => $ma->status,
                        'reason' => $ma->rejection_reason,
                        'notes' => $ma->review_notes,
                        'user' => $ma->reviewedBy->name ?? 'Producer',
                        'created_at' => $ma->reviewed_at ? $ma->reviewed_at->toIso8601String() : $ma->updated_at->toIso8601String()
                    ];
                    
                    if (!isset($stepHistory['music_arrangement_link'])) $stepHistory['music_arrangement_link'] = [];
                    if (!isset($stepHistory['arrangement_approval'])) $stepHistory['arrangement_approval'] = [];
                    $stepHistory['music_arrangement_link'][] = $arrLog;
                    $stepHistory['arrangement_approval'][] = $arrLog;
                }
            }
            
            // --- END HISTORY COLLECTION ---
            
            // --- ALL DEADLINES ---
            $isMusik = $episode->program && $episode->program->category === 'musik';
            
            $dlProducer = $deadlines->where('role', 'producer')->first();
            $dlKreatif = $deadlines->where('role', 'kreatif')->first();
            $dlMusik = $deadlines->where('role', 'musik_arr')->first();
            
            // Music Specific Deadlines (Prioritize these for Music Programs)
            $dlMusicSong = $isMusik ? $deadlines->where('role', 'musik_arr_song')->first() : $dlMusik;
            $dlProdSong = $isMusik ? $deadlines->where('role', 'producer_acc_song')->first() : $dlProducer;
            $dlMusicArr = $isMusik ? $deadlines->where('role', 'musik_arr_lagu')->first() : $dlMusik;
            $dlProdArr = $isMusik ? $deadlines->where('role', 'producer_acc_lagu')->first() : $dlProducer;
            $dlProdCreative = $isMusik ? $deadlines->where('role', 'producer_creative')->first() : $dlProducer;

            $dlSound = $deadlines->where('role', 'sound_eng')->first();
            $dlTimVocal = $deadlines->where('role', 'tim_vocal_coord')->first();
            $dlSetting = $deadlines->where('role', 'tim_setting_coord')->first();
            $dlShooting = $deadlines->where('role', 'tim_syuting_coord')->first();
            $dlEditor = $deadlines->where('role', 'editor')->first();
            $dlDesign = $deadlines->where('role', 'design_grafis')->first();
            $dlPromo = $deadlines->where('role', 'promotion')->first();
            $dlQC = $deadlines->where('role', 'quality_control')->first();
            $dlBC = $deadlines->where('role', 'broadcasting')->first();
            $dlReturn = $deadlines->where('role', 'art_set_design_return')->first();

            // --- PHASE 1: MUSIC ---
            $music = $episode->musicArrangements->sortByDesc('created_at')->first();

            // 1. Program Aktif
            $programOk = $episode->program && (in_array($episode->program->status, ['active', 'approved', 'in_production']) || $episode->program->producer_accepted);
            $workflowSteps[] = $buildStep(
                'program_active', 'Program Aktif', $programOk, $programOk ? 'completed' : 'pending',
                $programOk ? null : 'Program belum aktif.',
                ['program_status' => $episode->program->status ?? null],
                null, 
                $episode->program->producer_accepted_at, 
                $episode->program->producerAcceptedBy->name ?? 'System'
            );

            // 2. Song Proposal (Music Arranger)
            // Proposal is "done" if it's been submitted (status is not draft)
            $proposalSubmitted = $music && !in_array($music->status, ['draft']);
            $workflowSteps[] = $buildStep(
                'song_proposal', 'Song Proposal (Music Arranger)', $proposalSubmitted, 
                $proposalSubmitted ? 'completed' : ($music ? $music->status : 'pending'),
                $music && $music->status === 'song_rejected' ? 'Ditolak: ' . $music->rejection_reason : (!$music ? 'Belum diajukan.' : null),
                $music ? ['song_title' => $music->song_title, 'singer_name' => $music->singer_name] : null,
                $dlMusicSong, $music?->created_at, $music?->createdBy?->name,
                $music?->review_notes,
                $music?->status === 'song_rejected' ? $music?->rejection_reason : null
            );

            // 3. Producer (Approve Song Proposal)
            // Approved if status is beyond song_approved
            $songAppr = $music && in_array($music->status, ['song_approved', 'arrangement_in_progress', 'arrangement_submitted', 'arrangement_rejected', 'arrangement_approved', 'approved']);
            $workflowSteps[] = $buildStep(
                'song_proposal_approval', 'Producer (Approve Song Proposal)', $songAppr,
                $songAppr ? 'completed' : ($music && $music->status === 'song_rejected' ? 'rejected' : 'pending'),
                $music && $music->status === 'song_rejected' ? 'Ditolak: ' . $music->rejection_reason : (!$music ? 'Menunggu usulan.' : 'Menunggu review Producer.'),
                null, $dlProdSong, $music?->reviewed_at, $music?->reviewedBy?->name,
                $music?->review_notes,
                $music?->status === 'song_rejected' ? $music?->rejection_reason : null
            );

            // 4. Music Arrangement Link
            // Done if arrangement has been submitted at least once
            $arrSubmitted = $music && in_array($music->status, ['arrangement_submitted', 'arrangement_approved', 'approved']);
            $workflowSteps[] = $buildStep(
                'music_arrangement_link', 'Music Arrangement Link', $arrSubmitted,
                $arrSubmitted ? 'completed' : ($music && $music->status === 'arrangement_rejected' ? 'rejected' : ($songAppr ? 'pending' : 'blocked')),
                $music && $music->status === 'arrangement_rejected' ? 'Ditolak: ' . $music->rejection_reason : (!$songAppr ? 'Menunggu approval lagu.' : 'Belum disubmit.'),
                $music ? ['file_link' => $music->file_link] : null,
                $dlMusicArr, $music?->submitted_at, $music?->createdBy?->name,
                $music?->status === 'arrangement_approved' ? $music?->review_notes : null,
                $music?->status === 'arrangement_rejected' ? $music?->rejection_reason : null
            );

            // 5. Producer (Approve Arrangement)
            $arrAppr = $music && in_array($music->status, ['arrangement_approved', 'approved']);
            $workflowSteps[] = $buildStep(
                'arrangement_approval', 'Producer (Approve Arrangement)', $arrAppr,
                $arrAppr ? 'completed' : ($music && in_array($music->status, ['arrangement_rejected', 'rejected']) ? 'rejected' : 'pending'),
                $music && in_array($music->status, ['arrangement_rejected', 'rejected']) ? 'Ditolak: ' . $music->rejection_reason : (!$arrSubmitted ? 'Menunggu link.' : 'Menunggu review Producer.'),
                null, $dlProdArr, $music?->reviewed_at, $music?->reviewedBy?->name,
                $music?->review_notes,
                $music?->status === 'arrangement_rejected' ? $music?->rejection_reason : null
            );

            // --- PHASE 2: CREATIVE (Depends on Music Approved) ---
            $creative = $episode->creativeWorks->sortByDesc('created_at')->first();
            $creativeDone = $creative && $creative->script_approved && $creative->storyboard_approved;

            // 6. Creative (Script & Storyboard)
            $creativeActive = $arrAppr;
            $workflowSteps[] = $buildStep(
                'creative_concept', 'Creative (Script & Storyboard)', $creativeDone,
                $creativeDone ? 'completed' : ($creativeActive ? ($creative ? ($creative->status === 'rejected' ? 'rejected' : 'in_progress') : 'pending') : 'blocked'),
                !$creativeActive ? 'Menunggu aransemen musik di-approve.' : ($creative && $creative->status === 'rejected' ? 'Ditolak: ' . $creative->rejection_reason : (!$creative ? 'Belum diajukan.' : 'Proses script/storyboard.')),
                null, $dlKreatif, $creative?->created_at, $creative?->createdBy?->name,
                $creative?->status === 'approved' ? $creative?->review_notes : null,
                $creative?->status === 'rejected' ? $creative?->rejection_reason : null
            );

            // 7. Producer (Approve Creative)
            $workflowSteps[] = $buildStep(
                'producer_creative_approval', 'Producer (Approve Creative)', $creativeDone,
                $creativeDone ? 'completed' : ($creative ? ($creative->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                $creative && $creative->status === 'rejected' ? 'Ditolak: ' . $creative->rejection_reason : (!$creative ? 'Menunggu output kreatif.' : 'Menunggu review Producer.'),
                null, $dlProdCreative, $creative?->reviewed_at, $creative?->reviewedBy?->name,
                $creative?->review_notes,
                $creative?->status === 'rejected' ? $creative?->rejection_reason : null
            );

            // --- PHASE 3: PRODUCTION & VOCAL & PROMOTION (Parallel Start after Creative Approved) ---
            $produksi = $episode->produksiWorks->sortByDesc('created_at')->first();
            $dlProduksi = $deadlines->where('role', 'produksi')->first();
            $sound = $episode->soundEngineerRecordings->sortByDesc('created_at')->first();
            $promo = $episode->promotionWorks ?? collect();
            $btsPromo = $promo->where('work_type', 'bts_video')->first();

            $prodActive = $creativeDone;

            // 8. Tim Setting (Set & Property)
            $settingDone = $produksi && $produksi->setting_completed_at !== null;
            $workflowSteps[] = $buildStep(
                'set_property', 'Tim Setting (Set & Property)', $settingDone,
                $settingDone ? 'completed' : ($prodActive ? 'pending' : 'blocked'),
                !$prodActive ? 'Menunggu script kreatif.' : 'Menyiapkan set lokasi.',
                null, $dlSetting, $produksi?->setting_completed_at, $produksi?->settingCompletedBy?->name,
                $produksi?->setting_notes
            );

            // 9. Tim Rekam Vokal (Proses Recording)
            $soundRec = $episode->soundEngineerRecordings->sortByDesc('created_at')->first();
            $soundRecDone = $soundRec && $soundRec->status === 'completed';
            $workflowSteps[] = $buildStep(
                'vocal_recording', 'Tim Rekam Vokal (Proses Recording)', $soundRecDone,
                $soundRecDone ? 'completed' : ($prodActive ? ($soundRec && $soundRec->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                !$prodActive ? 'Menunggu script kreatif.' : ($soundRec && $soundRec->status === 'rejected' ? 'Ditolak: ' . $soundRec->review_notes : 'Proses recording vokal.'),
                null, $dlTimVocal, $soundRec?->recording_completed_at, $soundRec?->createdBy?->name,
                $soundRec?->status === 'reviewed' ? $soundRec?->review_notes : null,
                $soundRec?->status === 'rejected' ? $soundRec?->review_notes : null
            );

            // 10. Promotion (BTS & Initial Tasks)
            $promoStarted = $btsPromo !== null;
            $workflowSteps[] = $buildStep(
                'promotion_content_start', 'Promotion (BTS & Initial Tasks)', $promoStarted,
                $promoStarted ? 'completed' : ($prodActive ? 'pending' : 'blocked'),
                !$prodActive ? 'Menunggu script kreatif.' : 'Memulai konten promosi.',
                null, $dlPromo, $btsPromo?->created_at, $btsPromo?->createdBy?->name
            );

            // 11. Tim Shooting (Production)
            $shootingDone = $produksi && $produksi->status === 'completed';
            $workflowSteps[] = $buildStep(
                'shooting_production', 'Tim Shooting (Production)', $shootingDone,
                $shootingDone ? 'completed' : ($settingDone ? ($produksi && $produksi->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                !$settingDone ? 'Menunggu set lokasi siap.' : ($produksi && $produksi->status === 'rejected' ? 'Ditolak: ' . $produksi->rejection_notes : 'Proses pengambilan gambar.'),
                null, $dlShooting, $produksi?->completed_at, $produksi?->completedBy?->name,
                $produksi?->approval_notes,
                $produksi?->status === 'rejected' ? $produksi?->rejection_notes : null
            );

            // 12. Return Equipment (Setting & Shooting)
            $workflowSteps[] = $buildStep(
                'return_equipment_production', 'Kembalikan Barang (Setting & Shooting)', $shootingDone,
                $shootingDone ? 'completed' : ($shootingDone ? 'pending' : 'blocked'),
                !$shootingDone ? 'Menunggu syuting selesai.' : 'Menyiapkan verifikasi barang.',
                null, $dlReturn, $produksi?->completed_at, $produksi?->completedBy?->name,
                $produksi?->approval_notes
            );

            // 13. Return Equipment (Tim Rekam Vokal)
            $workflowSteps[] = $buildStep(
                'return_equipment_vocal', 'Kembalikan Barang (Tim Rekam Vokal)', $soundRecDone,
                $soundRecDone ? 'completed' : ($soundRecDone ? 'pending' : 'blocked'),
                !$soundRecDone ? 'Menunggu recording selesai.' : 'Menyiapkan verifikasi barang.',
                null, $dlReturn, $soundRec?->recording_completed_at, $soundRec?->createdBy?->name,
                $soundRec?->review_notes
            );

            // 14. Sound Engineer (Vocal Editing)
            $soundEdit = $episode->soundEngineerEditings->sortByDesc('created_at')->first();
            $soundEditDone = $soundEdit && in_array($soundEdit->status, ['completed', 'approved']);
            $workflowSteps[] = $buildStep(
                'vocal_editing', 'Sound Engineer (Vocal Editing)', $soundEditDone,
                $soundEditDone ? 'completed' : ($soundRecDone ? ($soundEdit && $soundEdit->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                !$soundRecDone ? 'Menunggu recording selesai.' : ($soundEdit && $soundEdit->status === 'rejected' ? 'Ditolak: ' . $soundEdit->rejection_reason : 'Proses editing vokal.'),
                [
                    'rejection_reason' => $soundEdit?->rejection_reason,
                    'file_link' => $soundEdit?->final_file_link ?? $soundEdit?->vocal_file_link
                ], 
                $dlSound, $soundEdit?->approved_at ?? $soundEdit?->submitted_at, $soundEdit?->createdBy?->name,
                $soundEdit?->status === 'approved' ? $soundEdit?->approval_notes : null,
                $soundEdit?->status === 'rejected' ? $soundEdit?->rejection_reason : null
            );

            // --- PHASE 4: POST-PRODUCTION ---
            $editor = $episode->editorWorks->sortByDesc('created_at')->first();
            $editorDone = $editor && $editor->status === 'completed';

            // 15. Editor (Main Episode)
            $editorActive = $shootingDone && $soundEditDone;
            $workflowSteps[] = $buildStep(
                'video_editing', 'Editor (Main Episode)', $editorDone,
                $editorDone ? 'completed' : ($editorActive ? ($editor && $editor->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                !$editorActive ? 'Menunggu Shooting & Vocal Editing selesai.' : ($editor && $editor->status === 'rejected' ? 'Ditolak: ' . $editor->rejection_notes : 'Proses editing video utama.'),
                null, $dlEditor, $editor?->updated_at, $editor?->createdBy?->name,
                $editor?->status === 'approved' ? $editor?->approval_notes : null,
                $editor?->status === 'rejected' ? $editor?->rejection_notes : null
            );

            // 16. Design Grafis & Editor Promotion
            $design = $episode->designGrafisWorks->sortByDesc('created_at')->first();
            $designDone = $design && in_array($design->status, ['completed', 'approved']);
            $promoActive = $promoStarted;
            $workflowSteps[] = $buildStep(
                'design_promo_editing', 'Design Grafis & Editor Promotion', $designDone,
                $designDone ? 'completed' : ($promoActive ? ($design && $design->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                !$promoActive ? 'Menunggu task promosi dimulai.' : ($design && $design->status === 'rejected' ? 'Ditolak: ' . $design->rejection_notes : 'Membuat design & promo tools.'),
                null, $dlDesign, $design?->updated_at, $design?->createdBy?->name,
                $design?->status === 'approved' ? $design?->approval_notes : null,
                $design?->status === 'rejected' ? $design?->rejection_notes : null
            );

            // --- PHASE 5: REVIEW & QC ---
            
            // 17. Producer Final Review (Cek File Kurang)
            $finalReviewDone = $editorDone && $designDone;
            $workflowSteps[] = $buildStep(
                'producer_final_review', 'Producer Final Review', $finalReviewDone,
                $finalReviewDone ? 'completed' : ($editorDone ? 'pending' : 'blocked'),
                !$editorDone ? 'Menunggu Editor selesai.' : 'Final review oleh Producer (Cek file/ulang).',
                null, $dlProducer, $editor?->updated_at, $editor?->reviewedBy?->name,
                $editor?->approval_notes
            );

            // 18. Program Manager Review
            $workflowSteps[] = $buildStep(
                'pm_review', 'Program Manager Review', $finalReviewDone,
                $finalReviewDone ? 'completed' : 'pending',
                null, null, null, $episode->updated_at, 'System'
            );

            // 19. Quality Control (Role QC)
            $qc = $episode->qualityControls->sortByDesc('created_at')->first();
            $qcDone = $qc && $qc->status === 'approved';
            $workflowSteps[] = $buildStep(
                'quality_control', 'Quality Control', $qcDone,
                $qcDone ? 'completed' : ($finalReviewDone ? ($qc && $qc->status === 'rejected' ? 'rejected' : 'pending') : 'blocked'),
                !$finalReviewDone ? 'Menunggu review Producer & PM.' : ($qc && $qc->status === 'rejected' ? 'Ditolak: ' . $qc->qc_notes : 'Pengecekan kualitas teknis aset promo dan desain.'),
                ['qc_notes' => $qc?->qc_notes], $dlQC, $qc?->updated_at, $qc?->createdBy?->name,
                $qc?->status === 'approved' ? $qc?->qc_notes : null,
                $qc?->status === 'rejected' ? $qc?->qc_notes : null
            );

            // --- PHASE 6: FINAL & BROADCASTING ---

            // 20. Distribution Manager Accept (QC & Schedule)
            $bc = $episode->broadcastingWorks->sortByDesc('created_at')->first();
            $dmDone = $bc && in_array($bc->status, ['approved', 'scheduled', 'published', 'completed']);
            $dlDM = $deadlines->where('role', 'manager_distribusi')->first();
            $workflowSteps[] = $buildStep(
                'dm_schedule', 'Distribution Manager QC & Schedule', $dmDone,
                $dmDone ? 'completed' : ($qcDone ? 'pending' : 'blocked'),
                !$qcDone ? 'Menunggu lolos QC.' : 'Plotting jadwal tayang konten.',
                null, $dlDM, $bc?->accepted_at, $bc?->acceptedBy?->name,
                $bc?->notes
            );

            // 21. Broadcasting (Published)
            $bcDone = $bc && in_array($bc->status, ['published', 'completed']);
            $workflowSteps[] = $buildStep(
                'broadcasting_publishing', 'Broadcasting (Published)', $bcDone,
                $bcDone ? 'completed' : ($dmDone ? 'pending' : 'blocked'),
                !$dmDone ? 'Menunggu jadwal dari DM.' : 'Konten online/tayang di YouTube/TV.',
                ['youtube_url' => $bc?->youtube_url], $dlBC, $bc?->published_time, $bc?->createdBy?->name,
                $bc?->notes
            );

            // 22. Promotion Sharing (Socmed Final)
            $sharing = $promo->whereIn('work_type', ['share_facebook', 'share_wa_group', 'story_ig', 'reels_facebook'])->where('status', 'published')->first();
            $workflowSteps[] = $buildStep(
                'promotion_sharing', 'Promotion Sharing (Socmed Final)', $sharing !== null,
                $sharing ? 'completed' : ($bcDone ? 'pending' : 'blocked'),
                !$bcDone ? 'Menunggu konten tayang.' : 'Sharing link tayang ke media sosial.',
                null, $dlPromo, $sharing?->updated_at, $sharing?->createdBy?->name
            );

            // Check reached end
            $reachedEnd = $episode->status === 'aired' || $bcDone;
            foreach ($workflowSteps as $key => &$st) {
                $st['history'] = $stepHistory[$st['step_key']] ?? [];
                
                // Ensure rejection_reason is available even if step is completed
                if (empty($st['rejection_reason']) && !empty($st['history'])) {
                    $lastRej = collect($st['history'])->where('type', 'rejection')->sortByDesc('created_at')->first();
                    if ($lastRej) {
                        $st['rejection_reason'] = ($lastRej['reason'] ?? $lastRej['notes'] ?? 'Rejected');
                    }
                }
            }
            unset($st);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title,
                        'air_date' => $episode->air_date,
                        'status' => $episode->status,
                        'current_workflow_state' => $episode->current_workflow_state,
                        'production_team' => $episode->productionTeam ? [
                            'id' => $episode->productionTeam->id,
                            'name' => $episode->productionTeam->name,
                            'members' => $episode->productionTeam->members->map(function($m) {
                                return [
                                    'id' => $m->id,
                                    'user_id' => $m->user_id,
                                    'name' => $m->user->name ?? 'Unknown',
                                    'role' => $m->role,
                                    'is_active' => $m->is_active
                                ];
                            })
                        ] : null
                    ],
                    'workflow_steps' => $workflowSteps,
                    'workflow_order' => $workflowOrder,
                    'summary' => [
                        'total_steps' => count($workflowSteps),
                        'completed_steps' => collect($workflowSteps)->where('completed', true)->count(),
                        'percentage' => round((collect($workflowSteps)->where('completed', true)->count() / count($workflowSteps)) * 100)
                    ]
                ],
                'message' => 'Workflow tracking data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Workflow Monitoring Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load workflow data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Evaluate all active programs performance
     */
    public function evaluateAllPrograms(): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can evaluate programs'
            ], 403);
        }
        
        try {
            $performanceService = app(ProgramPerformanceService::class);
            $results = $performanceService->evaluateAllActivePrograms();
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Programs evaluated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to evaluate programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get programs dengan performa buruk (tidak berkembang)
     * Untuk monitoring program yang perlu dipertimbangkan untuk ditutup
     */
    public function getUnderperformingPrograms(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or Distribution Manager can view underperforming programs'
            ], 403);
        }
        
        try {
            $query = Program::with(['managerProgram', 'productionTeam'])
                ->withCount(['episodes', 'episodes as aired_episodes_count' => function ($q) {
                    $q->where('status', 'aired');
                }])
                ->whereIn('status', ['active', 'in_production', 'approved'])
                ->whereNotNull('target_views_per_episode')
                ->where('target_views_per_episode', '>', 0);
            
            // Filter by program_id if provided
            if ($request->has('program_id') && $request->program_id > 0) {
                $query->where('id', $request->program_id);
            }
            
            // Filter hanya program dengan poor performance
            $performanceStatus = $request->get('performance_status', 'poor'); // default: poor
            if ($performanceStatus === 'poor') {
                $query->where('performance_status', 'poor');
            } elseif ($performanceStatus === 'warning') {
                $query->whereIn('performance_status', ['poor', 'warning']);
            }
            
            // Filter min episodes aired akan dilakukan di filtering setelah get data
            $minEpisodes = $request->get('min_episodes', 4);
            
            // Calculate achievement percentage untuk sorting
            $programs = $query->get()->map(function($program) {
                $targetViews = $program->target_views_per_episode ?? 1;
                $averageViews = $program->average_views_per_episode ?? 0;
                $achievement = $targetViews > 0 ? ($averageViews / $targetViews) * 100 : 0;
                $airedEpisodes = $program->aired_episodes_count;
                
                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'status' => $program->status,
                    'performance_status' => $program->performance_status,
                    'target_views_per_episode' => $program->target_views_per_episode,
                    'average_views_per_episode' => round($averageViews, 2),
                    'total_actual_views' => $program->total_actual_views ?? 0,
                    'achievement_percentage' => round($achievement, 2),
                    'aired_episodes' => $airedEpisodes,
                    'total_episodes' => $program->episodes()->count(),
                    'auto_close_enabled' => $program->auto_close_enabled,
                    'last_performance_check' => $program->last_performance_check,
                    'created_at' => $program->created_at,
                    'manager_program' => $program->managerProgram ? [
                        'id' => $program->managerProgram->id,
                        'name' => $program->managerProgram->name
                    ] : null
                ];
            })->filter(function($program) use ($minEpisodes) {
                // Filter berdasarkan min episodes aired
                return $program['aired_episodes'] >= $minEpisodes;
            });
            
            // Sort by achievement percentage (terendah dulu)
            $programs = $programs->sortBy('achievement_percentage')->values();
            
            // Pagination manual
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);
            $offset = ($page - 1) * $perPage;
            $paginatedPrograms = $programs->slice($offset, $perPage)->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'programs' => $paginatedPrograms,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $programs->count(),
                        'last_page' => ceil($programs->count() / $perPage)
                    ],
                    'summary' => [
                        'total_underperforming' => $programs->count(),
                        'total_poor' => $programs->where('performance_status', 'poor')->count(),
                        'total_warning' => $programs->where('performance_status', 'warning')->count(),
                        'average_achievement' => $programs->count() > 0 
                            ? round($programs->avg('achievement_percentage'), 2) 
                            : 0
                    ]
                ],
                'message' => 'Underperforming programs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get underperforming programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    


    /**
     * Manually close program
     */
    public function closeProgram(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program or Distribution Manager can close programs'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = Program::findOrFail($programId);
            
            $program->update([
                'status' => 'cancelled',
                'rejection_notes' => $request->reason,
                'rejected_by' => $user->id,
                'rejected_at' => now()
            ]);
            
            // Notifikasi ke production team
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()->where('is_active', true)->get();
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'program_closed',
                        'title' => 'Program Ditutup',
                        'message' => "Program '{$program->name}' telah ditutup: {$request->reason}",
                        'data' => [
                            'program_id' => $program->id,
                            'reason' => $request->reason
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program closed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ubah hari tayang dari episode tertentu ke depan (contoh: Jumat jadi Minggu dari Ep 4).
     * PUT /api/live-tv/manager-program/programs/{programId}/broadcast-day
     * Body: from_episode_number (int), new_day_of_week (0=Minggu, 1=Senin, ..., 6=Sabtu)
     */
    public function updateBroadcastDayFromEpisode(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json(['success' => false, 'message' => 'Only Manager Program can update broadcast day'], 403);
        }

        $validator = Validator::make($request->all(), [
            'from_episode_number' => 'required|integer|min:1|max:52',
            'new_day_of_week' => 'required|integer|min:0|max:6', // 0=Minggu, 6=Sabtu
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $program = Program::findOrFail($programId);
        $fromNum = (int) $request->from_episode_number;
        $newDay = (int) $request->new_day_of_week;

        $refEpisode = $program->episodes()->where('episode_number', $fromNum)->first();
        if (!$refEpisode || !$refEpisode->air_date) {
            return response()->json([
                'success' => false,
                'message' => "Episode {$fromNum} not found or has no air_date",
            ], 404);
        }

        $refDate = Carbon::parse($refEpisode->air_date);
        $currentDow = $refDate->dayOfWeek; // 0=Sun, 6=Sat
        $daysToAdd = ($newDay - $currentDow + 7) % 7;
        if ($daysToAdd === 0 && $currentDow !== $newDay) {
            $daysToAdd = 7;
        }
        $firstNewDate = $refDate->copy()->addDays($daysToAdd)->startOfDay();

        $episodes = $program->episodes()
            ->where('episode_number', '>=', $fromNum)
            ->orderBy('episode_number')
            ->get();

        $updated = 0;
        foreach ($episodes as $ep) {
            $weeksOffset = $ep->episode_number - $fromNum;
            $newAirDate = $firstNewDate->copy()->addWeeks($weeksOffset);
            $ep->update(['air_date' => $newAirDate->format('Y-m-d H:i:s')]);
            $updated++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'program_id' => $programId,
                'from_episode_number' => $fromNum,
                'new_day_of_week' => $newDay,
                'first_new_air_date' => $firstNewDate->format('Y-m-d'),
                'episodes_updated' => $updated,
            ],
            'message' => "Jadwal tayang diubah: {$updated} episode dari Ep {$fromNum} ke depan sekarang hari " . ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$newDay] . '.',
        ]);
    }

    /**
     * Submit opsi jadwal tayang ke Manager Broadcasting
     * User: "Manager Program dapat mengirim opsi jadwal tayang ke Manager Broadcasting"
     */
    public function submitScheduleOptions(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can submit schedule options'
            ], 403);
        }

        // LOGGING & NORMALIZATION
        \Illuminate\Support\Facades\Log::info('SubmitScheduleOptions Request:', $request->all());

        // Backward compatibility: older frontend doesn't send apply_to/episode_ids
        if (!$request->has('apply_to') || empty($request->input('apply_to'))) {
            $request->merge(['apply_to' => 'all']);
        }

        $episodeIds = $request->input('episode_ids');
        if (is_array($episodeIds) && !empty($episodeIds)) {
            $firstItem = reset($episodeIds);
            if (is_array($firstItem) || is_object($firstItem)) {
                $ids = [];
                foreach ($episodeIds as $item) {
                     if (is_array($item)) $ids[] = $item['id'] ?? null;
                     elseif (is_object($item)) $ids[] = $item->id ?? null;
                }
                $ids = array_values(array_filter($ids));
                $request->merge(['episode_ids' => $ids]);
                \Illuminate\Support\Facades\Log::info('Normalized episode_ids:', $ids);
            }
        }

        $validator = Validator::make($request->all(), [
            'schedule_options' => 'required|array|min:1',
            'schedule_options.*.date' => 'required|date',
            'schedule_options.*.time' => 'required|date_format:H:i',
            'schedule_options.*.notes' => 'nullable|string|max:500',
            'platform' => 'nullable|in:tv,youtube,website,all',
            'submission_notes' => 'nullable|string|max:1000',
            'apply_to' => 'nullable|in:all,select',
            'episode_ids' => 'required_if:apply_to,select|array',
            'episode_ids.*' => 'exists:episodes,id'
        ]);

        // Validate option datetime (date + time) must be in the future.
        $validator->after(function ($validator) use ($request) {
            $options = $request->input('schedule_options', []);
            if (!is_array($options)) return;

            foreach ($options as $i => $opt) {
                if (!is_array($opt)) continue;
                $date = $opt['date'] ?? null;
                $time = $opt['time'] ?? null;
                if (!$date || !$time) continue;

                try {
                    $dt = \Carbon\Carbon::parse($date . ' ' . $time);
                    if ($dt->lte(now())) {
                        $validator->errors()->add("schedule_options.$i.date", "The schedule_options.$i.date field must be a date/time after now.");
                    }
                } catch (\Exception $e) {
                    // If parse fails, base rules will report invalid date/time.
                }
            }
        });

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('SubmitScheduleValidation Failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $program = Program::findOrFail($programId);

            // Build schedule options array
            $scheduleOptions = [];
            foreach ($request->schedule_options as $index => $option) {
                $scheduleDate = \Carbon\Carbon::parse($option['date'])->setTimeFromTimeString($option['time']);
                
                $scheduleOptions[] = [
                    'index' => $index,
                    'date' => $scheduleDate->format('Y-m-d'),
                    'time' => $option['time'],
                    'datetime' => $scheduleDate->format('Y-m-d H:i:s'),
                    'notes' => $option['notes'] ?? null,
                    'formatted' => $scheduleDate->format('d M Y, H:i')
                ];
            }

            // Create schedule option
            $scheduleOption = \App\Models\ProgramScheduleOption::create([
                'program_id' => $programId,
                'submitted_by' => $user->id,
                'schedule_options' => $scheduleOptions,
                'platform' => $request->platform ?? 'all',
                'status' => 'pending',
                'submission_notes' => $request->submission_notes,
                'apply_to' => $request->apply_to,
                'target_episode_ids' => $request->apply_to === 'select' ? $request->episode_ids : null,
                'episode_id' => ($request->apply_to === 'select' && !empty($request->episode_ids)) ? $request->episode_ids[0] : null
            ]);

            // Notify Manager Broadcasting
            $this->notifyManagerBroadcasting($scheduleOption, $program);

            return response()->json([
                'success' => true,
                'data' => $scheduleOption->load(['program', 'episode', 'submittedBy']),
                'message' => 'Schedule options submitted successfully. Manager Broadcasting has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit schedule options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get submitted schedule options
     */
    public function getScheduleOptions(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view schedule options'
            ], 403);
        }

        try {
            $query = \App\Models\ProgramScheduleOption::with(['program', 'episode', 'submittedBy', 'reviewedBy'])
                ->where('program_id', $programId);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            $options = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $options,
                'message' => 'Schedule options retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedule options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Manager Broadcasting about schedule options
     * OPTIMIZED: Bulk insert to avoid N+1 query problem
     */
    private function notifyManagerBroadcasting($scheduleOption, $program): void
    {
        $managerBroadcastingUsers = \App\Models\User::where('role', 'Distribution Manager')->get();
        
        if ($managerBroadcastingUsers->isEmpty()) {
            return; // No Distribution Managers to notify
        }
        
        $episodeInfo = $scheduleOption->episode 
            ? "Episode {$scheduleOption->episode->episode_number}" 
            : "Program";

        $optionsText = collect($scheduleOption->schedule_options)
            ->map(function($option, $index) {
                return ($index + 1) . ". " . $option['formatted'] . ($option['notes'] ? " ({$option['notes']})" : '');
            })
            ->implode("\n");

        $optionsCount = is_array($scheduleOption->schedule_options) ? count($scheduleOption->schedule_options) : 0;
        
        // ✅ OPTIMIZED: Bulk insert notifications instead of loop
        $notifications = $managerBroadcastingUsers->map(function($managerUser) use ($optionsCount, $program, $episodeInfo, $optionsText, $scheduleOption) {
            return [
                'user_id' => $managerUser->id,
                'title' => 'Opsi Jadwal Tayang Baru',
                'message' => "Manager Program mengirim {$optionsCount} opsi jadwal tayang untuk program '{$program->name}' - {$episodeInfo}.\n\nOpsi:\n{$optionsText}",
                'type' => 'schedule_options_submitted',
                'data' => json_encode([
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $program->id,
                    'episode_id' => $scheduleOption->episode_id,
                    'platform' => $scheduleOption->platform,
                    'options_count' => $optionsCount
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ];
        })->toArray();
        
        \DB::table('notifications')->insert($notifications);
    }

    /**
     * Cancel shooting/recording schedule (Override authority)
     * Manager Program dapat mengcancel jadwal syuting dengan alasan
     */
    public function cancelSchedule(Request $request, int $scheduleId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can cancel schedules'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'notify_team' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schedule = MusicSchedule::findOrFail($scheduleId);
            
            // Check if schedule can be cancelled
            if (in_array($schedule->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel schedule that is already completed or cancelled'
                ], 400);
            }

            // Save old status for history
            $oldStatus = $schedule->status;

            // Cancel schedule
            $schedule->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason . ' (Cancelled by Manager Program)',
                'cancelled_by' => $user->id,
                'cancelled_at' => now()
            ]);

            // Notify team members if requested
            if ($request->get('notify_team', true)) {
                $this->notifyScheduleCancellation($schedule, $request->reason);
            }

            // Create approval record for audit trail
            ProgramApproval::create([
                'approvable_type' => MusicSchedule::class,
                'approvable_id' => $schedule->id,
                'approval_type' => 'schedule_cancellation',
                'requested_by' => $user->id,
                'request_notes' => $request->reason,
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => 'Cancelled by Manager Program (Override authority)',
                'priority' => 'high'
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['musicSubmission', 'canceller']),
                'message' => 'Schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel shooting schedule from Creative Work (Override authority)
     * Manager Program dapat cancel jadwal syuting dari Creative Work
     */
    public function cancelCreativeWorkShooting(Request $request, int $creativeWorkId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can cancel shooting schedules'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'notify_team' => 'nullable|boolean',
            'new_shooting_schedule' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $creativeWork = CreativeWork::with(['episode.program.productionTeam'])->findOrFail($creativeWorkId);
            
            // Check if shooting schedule exists and can be cancelled
            if (!$creativeWork->shooting_schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'No shooting schedule found for this creative work'
                ], 400);
            }

            if ($creativeWork->shooting_schedule_cancelled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shooting schedule already cancelled'
                ], 400);
            }

            // Cancel shooting schedule
            $creativeWork->update([
                'shooting_schedule_cancelled' => true,
                'shooting_cancellation_reason' => $request->reason . ' (Cancelled by Manager Program)',
                'shooting_schedule_new' => $request->new_shooting_schedule
            ]);

            // Cancel shooting team assignments
            $shootingAssignments = \App\Models\ProductionTeamAssignment::where('episode_id', $creativeWork->episode_id)
                ->where('team_type', 'shooting')
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($shootingAssignments as $assignment) {
                $assignment->update(['status' => 'cancelled']);
            }

            // Notify team members if requested
            if ($request->get('notify_team', true)) {
                // Notify production team members
                if ($creativeWork->episode->program->productionTeam) {
                    $teamMembers = $creativeWork->episode->program->productionTeam->members()
                        ->where('is_active', true)
                        ->get();
                    
                    foreach ($teamMembers as $member) {
                        \App\Models\Notification::create([
                            'user_id' => $member->user_id,
                            'type' => 'shooting_cancelled',
                            'title' => 'Jadwal Syuting Dibatalkan',
                            'message' => "Jadwal syuting untuk Episode {$creativeWork->episode->episode_number} telah dibatalkan oleh Manager Program. Alasan: {$request->reason}",
                            'data' => [
                                'creative_work_id' => $creativeWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'cancellation_reason' => $request->reason,
                                'new_schedule' => $request->new_shooting_schedule
                            ]
                        ]);
                    }
                }

                // Notify Creative
                \App\Models\Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'shooting_cancelled',
                    'title' => 'Jadwal Syuting Dibatalkan',
                    'message' => "Jadwal syuting untuk Episode {$creativeWork->episode->episode_number} telah dibatalkan oleh Manager Program. Alasan: {$request->reason}",
                    'data' => [
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id
                    ]
                ]);
            }

            // Create approval record for audit trail
            ProgramApproval::create([
                'approvable_type' => CreativeWork::class,
                'approvable_id' => $creativeWork->id,
                'approval_type' => 'shooting_schedule_cancellation',
                'requested_by' => $user->id,
                'request_notes' => $request->reason,
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => 'Cancelled by Manager Program (Override authority)',
                'priority' => 'high'
            ]);

            return response()->json([
                'success' => true,
                'data' => $creativeWork->fresh(['episode.program']),
                'message' => 'Shooting schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling shooting schedule: ' . $e->getMessage(), [
                'creative_work_id' => $creativeWorkId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel shooting schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule shooting/recording schedule (Override authority)
     * Manager Program dapat merubah jadwal syuting dengan alasan
     */
    public function reschedule(Request $request, int $scheduleId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can reschedule'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_datetime' => 'required|date|after:now',
            'reason' => 'required|string|max:1000',
            'location' => 'nullable|string|max:255',
            'location_address' => 'nullable|string|max:500',
            'schedule_notes' => 'nullable|string|max:1000',
            'notify_team' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schedule = MusicSchedule::findOrFail($scheduleId);
            
            // Check if schedule can be rescheduled
            if (in_array($schedule->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reschedule schedule that is already completed or cancelled'
                ], 400);
            }

            // Save old data for history
            $oldDatetime = $schedule->scheduled_datetime;
            $oldLocation = $schedule->location;
            
            // Parse new datetime
            $newDatetime = Carbon::parse($request->new_datetime);

            // Reschedule
            $schedule->update([
                'status' => 'rescheduled',
                'rescheduled_datetime' => $newDatetime,
                'reschedule_reason' => $request->reason . ' (Rescheduled by Manager Program)',
                'rescheduled_by' => $user->id,
                'rescheduled_at' => now(),
                'location' => $request->location ?? $schedule->location,
                'location_address' => $request->location_address ?? $schedule->location_address,
                'schedule_notes' => ($schedule->schedule_notes ? $schedule->schedule_notes . "\n\n" : '') . 
                                  "RESCHEDULED: {$request->reason}" . 
                                  ($request->schedule_notes ? "\n{$request->schedule_notes}" : '')
            ]);

            // Notify team members if requested
            if ($request->get('notify_team', true)) {
                $this->notifyScheduleReschedule($schedule, $oldDatetime, $newDatetime, $request->reason);
            }

            // Create approval record for audit trail
            ProgramApproval::create([
                'approvable_type' => MusicSchedule::class,
                'approvable_id' => $schedule->id,
                'approval_type' => 'schedule_change',
                'requested_by' => $user->id,
                'request_notes' => $request->reason,
                'request_data' => [
                    'new_datetime' => $newDatetime->format('Y-m-d H:i:s'),
                    'new_location' => $request->location ?? $schedule->location,
                    'new_location_address' => $request->location_address ?? $schedule->location_address
                ],
                'current_data' => [
                    'old_datetime' => $oldDatetime,
                    'old_location' => $oldLocation
                ],
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => 'Rescheduled by Manager Program (Override authority)',
                'priority' => 'high'
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['musicSubmission', 'rescheduler']),
                'message' => 'Schedule rescheduled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Override approval (Override authority untuk semua bidang)
     * Manager Program dapat override approval di semua bidang
     */
    public function overrideApproval(Request $request, int $approvalId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can override approvals'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approval = ProgramApproval::findOrFail($approvalId);
            
            // Check if approval can be overridden
            if (in_array($approval->status, ['approved', 'rejected', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot override approval that is already processed'
                ], 400);
            }

            $action = $request->action;
            $reason = $request->reason . ' (Overridden by Manager Program)';

            if ($action === 'approve') {
                $approval->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'approval_notes' => $reason . ($request->notes ? "\n{$request->notes}" : '')
                ]);

                // Apply the approval to the related model
                $this->applyApproval($approval);
            } else {
                $approval->update([
                    'status' => 'rejected',
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'rejection_notes' => $reason . ($request->notes ? "\n{$request->notes}" : '')
                ]);
            }

            // Notify relevant parties
            $this->notifyApprovalOverride($approval, $action, $reason);

            return response()->json([
                'success' => true,
                'data' => $approval->load(['approvable', 'requestedBy']),
                'message' => "Approval {$action}d successfully (Override authority)"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to override approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending rundown edit requests
     * Manager Program dapat melihat permintaan edit rundown dari Producer
     */
    public function getRundownEditRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view rundown edit requests'
            ], 403);
        }

        try {
            $query = ProgramApproval::where('approval_type', 'episode_rundown')
                ->where('status', 'pending')
                ->with(['approvable', 'requestedBy']);

            // Filter by program
            if ($request->has('program_id')) {
                $query->whereHas('approvable', function($q) use ($request) {
                    $q->where('program_id', $request->program_id);
                });
            }

            $requests = $query->orderBy('requested_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Rundown edit requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rundown edit requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve rundown edit request dari Producer
     */
    public function approveRundownEdit(Request $request, int $approvalId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can approve rundown edits'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approval = ProgramApproval::findOrFail($approvalId);

            if ($approval->approval_type !== 'episode_rundown') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid approval type'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval request is not pending'
                ], 400);
            }

            $episode = Episode::findOrFail($approval->approvable_id);
            $requestData = $approval->request_data;

            // Update approval status
            $approval->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes
            ]);

            // Apply the rundown edit
            $episode->update([
                'rundown' => $requestData['new_rundown'],
                'updated_at' => now()
            ]);

            // Notify Producer
            Notification::create([
                'user_id' => $approval->requested_by,
                'type' => 'rundown_edit_approved',
                'title' => 'Edit Rundown Disetujui',
                'message' => "Permintaan edit rundown untuk Episode {$episode->episode_number}: {$episode->title} telah disetujui oleh Manager Program.",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->load(['approvable', 'requestedBy', 'approvedBy']),
                    'episode' => $episode
                ],
                'message' => 'Rundown edit approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving rundown edit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject rundown edit request dari Producer
     */
    public function rejectRundownEdit(Request $request, int $approvalId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can reject rundown edits'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approval = ProgramApproval::findOrFail($approvalId);

            if ($approval->approval_type !== 'episode_rundown') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid approval type'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval request is not pending'
                ], 400);
            }

            $episode = Episode::findOrFail($approval->approvable_id);

            // Update approval status
            $approval->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_notes' => $request->rejection_reason
            ]);

            // Notify Producer
            Notification::create([
                'user_id' => $approval->requested_by,
                'type' => 'rundown_edit_rejected',
                'title' => 'Edit Rundown Ditolak',
                'message' => "Permintaan edit rundown untuk Episode {$episode->episode_number}: {$episode->title} telah ditolak oleh Manager Program. Alasan: {$request->rejection_reason}",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'rejection_reason' => $request->rejection_reason
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $approval->load(['approvable', 'requestedBy', 'rejectedBy']),
                'message' => 'Rundown edit rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting rundown edit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quality controls for monitoring (GET only)
     * Manager Program dapat monitoring status QC
     */
    public function getQualityControls(Request $request, int $programId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view quality controls'
            ], 403);
        }

        try {
            $program = Program::findOrFail($programId);
            
            $query = QualityControl::whereHas('episode', function($q) use ($programId) {
                $q->where('program_id', $programId);
            })->with(['episode', 'createdBy', 'qcBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by QC type
            if ($request->has('qc_type')) {
                $query->where('qc_type', $request->qc_type);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            $qualityControls = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $qualityControls,
                'message' => 'Quality controls retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quality controls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quality control by episode
     */
    public function getEpisodeQualityControls(Request $request, int $episodeId): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can view quality controls'
            ], 403);
        }

        try {
            $episode = Episode::findOrFail($episodeId);
            
            $qualityControls = QualityControl::where('episode_id', $episodeId)
                ->with(['createdBy', 'qcBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'quality_controls' => $qualityControls
                ],
                'message' => 'Episode quality controls retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quality controls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify team about schedule cancellation
     */
    private function notifyScheduleCancellation($schedule, $reason): void
    {
        // Get episode from production_teams_assignment if available
        $episode = null;
        $assignment = ProductionTeamAssignment::where('schedule_id', $schedule->id)->first();
        
        if ($assignment && $assignment->episode_id) {
            $episode = Episode::find($assignment->episode_id);
        }
        
        // If no episode found, try to get from team assignments
        if (!$episode && $schedule->teamAssignments()->count() > 0) {
            $firstAssignment = $schedule->teamAssignments()->first();
            if ($firstAssignment && $firstAssignment->episode_id) {
                $episode = Episode::find($firstAssignment->episode_id);
            }
        }
        
        $episodeInfo = $episode ? "Episode {$episode->episode_number}" : "Schedule";
        
        // Notify production team members if episode exists
        if ($episode && $episode->productionTeam) {
            $teamMembers = $episode->productionTeam->members()->where('is_active', true)->get();
            
            foreach ($teamMembers as $member) {
                \App\Models\Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'schedule_cancelled',
                    'title' => 'Jadwal Dibatalkan oleh Manager Program',
                    'message' => "Jadwal {$schedule->getScheduleTypeLabel()} untuk {$episodeInfo} telah dibatalkan: {$reason}",
                    'data' => [
                        'schedule_id' => $schedule->id,
                        'episode_id' => $episode->id,
                        'schedule_type' => $schedule->schedule_type,
                        'reason' => $reason
                    ],
                    'priority' => 'high'
                ]);
            }
        }
        
        // Also notify users assigned to this schedule via team assignments
        $teamAssignments = $schedule->teamAssignments()->with('members.user')->get();
        $notifiedUserIds = [];
        
        foreach ($teamAssignments as $teamAssignment) {
            $members = $teamAssignment->members;
            foreach ($members as $member) {
                if ($member->user_id && !in_array($member->user_id, $notifiedUserIds)) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'schedule_cancelled',
                        'title' => 'Jadwal Dibatalkan oleh Manager Program',
                        'message' => "Jadwal {$schedule->getScheduleTypeLabel()} telah dibatalkan: {$reason}",
                        'data' => [
                            'schedule_id' => $schedule->id,
                            'episode_id' => $episode ? $episode->id : null,
                            'schedule_type' => $schedule->schedule_type,
                            'reason' => $reason
                        ],
                        'priority' => 'high'
                    ]);
                    $notifiedUserIds[] = $member->user_id;
                }
            }
        }
    }

    /**
     * Notify team about schedule reschedule
     */
    private function notifyScheduleReschedule($schedule, $oldDatetime, $newDatetime, $reason): void
    {
        // Get episode from production_teams_assignment if available
        $episode = null;
        $assignment = ProductionTeamAssignment::where('schedule_id', $schedule->id)->first();
        
        if ($assignment && $assignment->episode_id) {
            $episode = Episode::find($assignment->episode_id);
        }
        
        // If no episode found, try to get from team assignments
        if (!$episode && $schedule->teamAssignments()->count() > 0) {
            $firstAssignment = $schedule->teamAssignments()->first();
            if ($firstAssignment && $firstAssignment->episode_id) {
                $episode = Episode::find($firstAssignment->episode_id);
            }
        }
        
        $oldDatetimeFormatted = $oldDatetime instanceof Carbon ? $oldDatetime->format('d M Y H:i') : (is_string($oldDatetime) ? Carbon::parse($oldDatetime)->format('d M Y H:i') : $oldDatetime);
        $newDatetimeFormatted = $newDatetime instanceof Carbon ? $newDatetime->format('d M Y H:i') : (is_string($newDatetime) ? Carbon::parse($newDatetime)->format('d M Y H:i') : $newDatetime);
        
        $episodeInfo = $episode ? "Episode {$episode->episode_number}" : "Schedule";
        
        // Notify production team members if episode exists
        if ($episode && $episode->productionTeam) {
            $teamMembers = $episode->productionTeam->members()->where('is_active', true)->get();
            
            foreach ($teamMembers as $member) {
                \App\Models\Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'schedule_rescheduled',
                    'title' => 'Jadwal Diubah oleh Manager Program',
                    'message' => "Jadwal {$schedule->getScheduleTypeLabel()} untuk {$episodeInfo} telah diubah dari {$oldDatetimeFormatted} ke {$newDatetimeFormatted}: {$reason}",
                    'data' => [
                        'schedule_id' => $schedule->id,
                        'episode_id' => $episode->id,
                        'schedule_type' => $schedule->schedule_type,
                        'old_datetime' => $oldDatetime,
                        'new_datetime' => $newDatetime,
                        'reason' => $reason
                    ],
                    'priority' => 'high'
                ]);
            }
        }
        
        // Also notify users assigned to this schedule via team assignments
        $teamAssignments = $schedule->teamAssignments()->with('members.user')->get();
        $notifiedUserIds = [];
        
        foreach ($teamAssignments as $teamAssignment) {
            $members = $teamAssignment->members;
            foreach ($members as $member) {
                if ($member->user_id && !in_array($member->user_id, $notifiedUserIds)) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'schedule_rescheduled',
                        'title' => 'Jadwal Diubah oleh Manager Program',
                        'message' => "Jadwal {$schedule->getScheduleTypeLabel()} telah diubah dari {$oldDatetimeFormatted} ke {$newDatetimeFormatted}: {$reason}",
                        'data' => [
                            'schedule_id' => $schedule->id,
                            'episode_id' => $episode ? $episode->id : null,
                            'schedule_type' => $schedule->schedule_type,
                            'old_datetime' => $oldDatetime,
                            'new_datetime' => $newDatetime,
                            'reason' => $reason
                        ],
                        'priority' => 'high'
                    ]);
                    $notifiedUserIds[] = $member->user_id;
                }
            }
        }
    }

    /**
     * Notify about approval override
     */
    private function notifyApprovalOverride($approval, $action, $reason): void
    {
        $approvable = $approval->approvable;
        
        if ($approvable instanceof Episode) {
            $episode = $approvable;
            $program = $episode->program;
            
            // Notify Manager Program (self)
            \App\Models\Notification::create([
                'user_id' => $program->manager_program_id,
                'type' => 'approval_overridden',
                'title' => 'Approval Di-override',
                'message' => "Approval untuk Episode {$episode->episode_number} telah di-{$action} oleh Manager Program: {$reason}",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'action' => $action,
                    'reason' => $reason
                ],
                'priority' => 'normal'
            ]);

            // Notify Production Team
            if ($episode->productionTeam) {
                $teamMembers = $episode->productionTeam->members()->where('is_active', true)->get();
                foreach ($teamMembers as $member) {
                    \App\Models\Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'approval_overridden',
                        'title' => 'Approval Di-override oleh Manager Program',
                        'message' => "Approval untuk Episode {$episode->episode_number} telah di-{$action}: {$reason}",
                        'data' => [
                            'approval_id' => $approval->id,
                            'episode_id' => $episode->id,
                            'action' => $action
                        ],
                        'priority' => 'normal'
                    ]);
                }
            }
        }
    }

    /**
     * Apply approval to related model
     */
    private function applyApproval($approval): void
    {
        $approvable = $approval->approvable;
        
        if ($approvable instanceof MusicSchedule) {
            // If approving schedule cancellation, ensure it's cancelled
            if ($approval->approval_type === 'schedule_cancellation') {
                $approvable->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $approval->approved_by,
                    'cancelled_at' => now()
                ]);
            }
            // If approving schedule change, apply the changes
            elseif ($approval->approval_type === 'schedule_change' && $approval->request_data) {
                $newDatetime = isset($approval->request_data['new_datetime']) 
                    ? Carbon::parse($approval->request_data['new_datetime']) 
                    : null;
                    
                $approvable->update([
                    'status' => 'rescheduled',
                    'rescheduled_datetime' => $newDatetime,
                    'location' => $approval->request_data['new_location'] ?? $approvable->location,
                    'rescheduled_by' => $approval->approved_by,
                    'rescheduled_at' => now()
                ]);
            }
        }
    }

    /**
     * Get Special Budget Approval Requests
     * GET /api/live-tv/manager-program/special-budget-approvals
     */
    public function getSpecialBudgetApprovals(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Flexible Role Check (Case-insensitive)
            $userRole = strtolower($user->role);
            $allowedRoles = ['manager program', 'program manager', 'managerprogram'];
            
            if (!in_array($userRole, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Your role: ' . $user->role
                ], 403);
            }

            // Always clear cache to ensure real-time data for approvals
            \App\Helpers\QueryOptimizer::clearAllIndexCaches();

            // Query lebih robust: Ambil semua approval dulu, lalu filter manual
            // Ini lebih aman untuk polymorphic relationship
            $allApprovals = ProgramApproval::where('approval_type', 'special_budget')
                ->whereIn('status', ['pending', 'reviewed'])
                ->where('approvable_type', 'App\Models\CreativeWork') // Hanya CreativeWork
                ->with([
                    'approvable' => function($query) {
                        $query->with(['episode.program']);
                    },
                    'requestedBy'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Filter berdasarkan program yang dikelola oleh Program Manager ini
            // Special budget approval hanya bisa dilihat dan di-approve oleh Program Manager
            // yang membuat/mengelola program tersebut (berdasarkan manager_program_id)
            // Ini untuk accountability dan organisasi yang lebih baik
            $filteredApprovals = $allApprovals->filter(function($approval) use ($user) {
                try {
                    $approvable = $approval->approvable;
                    
                    // Pastikan approvable adalah CreativeWork dan ada
                    if (!$approvable) {
                        Log::warning('Approval has no approvable', ['approval_id' => $approval->id]);
                        return false;
                    }
                    
                    // Pastikan ada episode
                    $episode = $approvable->episode;
                    if (!$episode) {
                        Log::warning('CreativeWork has no episode', [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $approvable->id
                        ]);
                        return false;
                    }
                    
                    // Pastikan ada program
                    $program = $episode->program;
                    if (!$program) {
                        Log::warning('Episode has no program', [
                            'approval_id' => $approval->id,
                            'episode_id' => $episode->id
                        ]);
                        return false;
                    }
                    
                    // Filter: tampilkan jika program belum punya manager ATAU program dikelola oleh Program Manager ini
                    // Jika manager_program_id null, semua Program Manager bisa lihat (agar request tidak hilang)
                    $isManaged = $program->manager_program_id === null || $program->manager_program_id == $user->id;
                    
                    if (!$isManaged) {
                        Log::info('Approval filtered out - not managed by this Program Manager', [
                            'approval_id' => $approval->id,
                            'program_id' => $program->id,
                            'program_name' => $program->name,
                            'program_manager_id' => $program->manager_program_id,
                            'current_user_id' => $user->id,
                            'current_user_name' => $user->name,
                            'reason' => 'Special budget approval hanya bisa di-manage oleh Program Manager yang membuat program tersebut'
                        ]);
                    } else {
                        Log::info('Approval included - managed by this Program Manager or program has no manager', [
                            'approval_id' => $approval->id,
                            'program_id' => $program->id,
                            'program_name' => $program->name,
                            'program_manager_id' => $program->manager_program_id,
                            'current_user_id' => $user->id
                        ]);
                    }
                    
                    return $isManaged;
                } catch (\Exception $e) {
                    Log::error('Error filtering approval: ' . $e->getMessage(), [
                        'approval_id' => $approval->id,
                        'approvable_type' => $approval->approvable_type,
                        'trace' => $e->getTraceAsString()
                    ]);
                    return false;
                }
            });

            // Convert ke collection dan paginate manual
            $page = $request->get('page', 1);
            $perPage = 15;
            $total = $filteredApprovals->count();
            $items = $filteredApprovals->slice(($page - 1) * $perPage, $perPage)->values();

            // Format data untuk frontend - pastikan semua field yang diperlukan sudah ada
            $formattedItems = $items->map(function($approval) {
                $approvable = $approval->approvable; // CreativeWork
                $episode = $approvable ? $approvable->episode : null;
                $program = $episode ? $episode->program : null;
                
                // Ambil data dari request_data (sudah di-cast sebagai array)
                $requestData = $approval->request_data ?? [];
                $specialBudgetAmount = isset($requestData['special_budget_amount']) 
                    ? (float) $requestData['special_budget_amount'] 
                    : 0;
                $episodeId = $requestData['episode_id'] ?? ($episode ? $episode->id : null);
                
                // Reason = alasan Producer (special_budget_reason). Sumber: request_notes, request_data.special_budget_reason, atau CreativeWork.special_budget_reason
                $reason = null;
                if (!empty($approval->request_notes) && trim((string) $approval->request_notes) !== '') {
                    $reason = trim((string) $approval->request_notes);
                }
                if (($reason === null || $reason === '') && !empty($requestData['special_budget_reason']) && trim((string) $requestData['special_budget_reason']) !== '') {
                    $reason = trim((string) $requestData['special_budget_reason']);
                }
                if (($reason === null || $reason === '') && $approvable && !empty($approvable->special_budget_reason) && trim((string) $approvable->special_budget_reason) !== '') {
                    $reason = trim((string) $approvable->special_budget_reason);
                }
                $reason = ($reason !== null && $reason !== '') ? $reason : null; // Jangan pakai '-' agar frontend bisa bedakan "kosong" vs "tidak diisi"
                
                // Pastikan creative_work_id ada
                $creativeWorkId = $approvable ? (int) $approvable->id : null;
                
                // Log untuk debugging jika ada masalah
                if (!$creativeWorkId) {
                    Log::warning('Special budget approval missing CreativeWork', [
                        'approval_id' => $approval->id,
                        'approvable_id' => $approval->approvable_id,
                        'approvable_type' => $approval->approvable_type
                    ]);
                }
                
                return [
                    'id' => $approval->id,
                    'approval_type' => $approval->approval_type,
                    'status' => $approval->status,
                    'priority' => $approval->priority ?? 'normal',
                    'requested_at' => $approval->requested_at ? $approval->requested_at->toDateTimeString() : null,
                    'request_notes' => $reason ?? '-',
                    'request_data' => $requestData,
                    // Agar Program Manager dapat data persis seperti input Producer:
                    'special_budget_reason' => $reason,
                    'reason' => $reason ?? '-',
                    
                    // Data dari request_data (untuk frontend)
                    'special_budget_amount' => $specialBudgetAmount,
                    'requested_amount' => $specialBudgetAmount, // Alias untuk frontend compatibility
                    
                    // Creative Work data
                    'creative_work' => $approvable ? [
                        'id' => $approvable->id,
                        'episode_id' => $approvable->episode_id,
                        'status' => $approvable->status,
                        'special_budget_reason' => $approvable->special_budget_reason ?? null,
                    ] : null,
                    
                    // Episode data
                    'episode' => $episode ? [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title ?? "Episode {$episode->episode_number}",
                        'program_id' => $episode->program_id,
                    ] : null,
                    'episode_id' => $episode ? $episode->id : null,
                    'episode_number' => $episode ? $episode->episode_number : null,
                    
                    // Program data
                    'program' => $program ? [
                        'id' => $program->id,
                        'name' => $program->name,
                        'manager_program_id' => $program->manager_program_id,
                    ] : null,
                    'program_id' => $program ? $program->id : null,
                    'program_name' => $program ? $program->name : null,
                    
                    // Requested By
                    'requested_by' => $approval->requestedBy ? [
                        'id' => $approval->requestedBy->id,
                        'name' => $approval->requestedBy->name,
                        'role' => $approval->requestedBy->role,
                    ] : null,
                    'requested_by_id' => $approval->requested_by,
                    'requested_by_name' => $approval->requestedBy ? $approval->requestedBy->name : null,
                    
                    // Additional formatted fields untuk frontend (untuk kemudahan display)
                    'formatted_amount' => 'Rp ' . number_format($specialBudgetAmount, 0, ',', '.'),
                    'episode_display' => $episode ? "Episode {$episode->episode_number}" : 'Episode #-',
                    'creative_work_display' => $creativeWorkId ? "Creative Work #{$creativeWorkId}" : 'Creative Work #undefined',
                    
                    // Pastikan creative_work_id selalu ada di root level untuk frontend
                    'creative_work_id' => $creativeWorkId,
                ];
            });

            // Buat pagination response manual dengan data yang sudah di-format
            $approvals = new \Illuminate\Pagination\LengthAwarePaginator(
                $formattedItems,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            // Log untuk debugging - cek data pertama jika ada
            if ($formattedItems->count() > 0) {
                $firstItem = $formattedItems->first();
                Log::info('Special budget approval response sample', [
                    'approval_id' => $firstItem['id'] ?? null,
                    'creative_work_id' => $firstItem['creative_work_id'] ?? null,
                    'creative_work_display' => $firstItem['creative_work_display'] ?? null,
                    'reason' => $firstItem['reason'] ?? null,
                    'request_notes' => $firstItem['request_notes'] ?? null,
                    'requested_amount' => $firstItem['requested_amount'] ?? null,
                    'episode_number' => $firstItem['episode_number'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'message' => 'Special budget approvals retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getSpecialBudgetApprovals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve special budget approvals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Special Budget Approval History (approved & rejected)
     * GET /api/live-tv/manager-program/special-budget-approvals/history
     */
    public function getSpecialBudgetApprovalsHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $userRole = strtolower($user->role ?? '');
            $allowedRoles = ['manager program', 'program manager', 'managerprogram'];
            if (!in_array($userRole, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Your role: ' . ($user->role ?? 'unknown')
                ], 403);
            }

            $allApprovals = ProgramApproval::where('approval_type', 'special_budget')
                ->whereIn('status', ['approved', 'rejected'])
                ->where('approvable_type', 'App\Models\CreativeWork')
                ->with([
                    'approvable' => fn($q) => $q->with(['episode.program']),
                    'requestedBy',
                    'approvedBy'
                ])
                ->orderBy('updated_at', 'desc')
                ->limit(100)
                ->get();

            $filteredApprovals = $allApprovals->filter(function ($approval) use ($user) {
                try {
                    $approvable = $approval->approvable;
                    if (!$approvable) return false;
                    $episode = $approvable->episode;
                    if (!$episode) return false;
                    $program = $episode->program;
                    if (!$program) return false;
                    return $program->manager_program_id === null || $program->manager_program_id == $user->id;
                } catch (\Throwable $e) {
                    return false;
                }
            });

            $requestData = null;
            $formattedItems = $filteredApprovals->map(function ($approval) {
                $approvable = $approval->approvable;
                $episode = $approvable?->episode;
                $program = $episode?->program;
                $requestData = $approval->request_data ?? [];
                $specialBudgetAmount = isset($requestData['special_budget_amount']) ? (float) $requestData['special_budget_amount'] : 0;
                $approvedAmount = $requestData['approved_amount'] ?? $specialBudgetAmount;

                $reason = null;
                if (!empty($approval->request_notes) && trim((string) $approval->request_notes) !== '') {
                    $reason = trim((string) $approval->request_notes);
                }
                if (($reason === null || $reason === '') && !empty($requestData['special_budget_reason'])) {
                    $reason = trim((string) $requestData['special_budget_reason']);
                }
                if (($reason === null || $reason === '') && $approvable && !empty($approvable->special_budget_reason)) {
                    $reason = trim((string) $approvable->special_budget_reason);
                }

                return [
                    'id' => $approval->id,
                    'approval_type' => $approval->approval_type,
                    'status' => $approval->status,
                    'priority' => $approval->priority ?? 'normal',
                    'requested_at' => $approval->requested_at ? $approval->requested_at->toDateTimeString() : null,
                    'approved_at' => $approval->approved_at ? $approval->approved_at->toDateTimeString() : null,
                    'request_notes' => $reason ?? '-',
                    'special_budget_reason' => $reason,
                    'reason' => $reason ?? '-',
                    'approval_notes' => $approval->approval_notes,
                    'special_budget_amount' => $specialBudgetAmount,
                    'requested_amount' => $specialBudgetAmount,
                    'approved_amount' => $approvedAmount,
                    'request_data' => $requestData,
                    'creative_work' => $approvable ? ['id' => $approvable->id, 'episode_id' => $approvable->episode_id, 'status' => $approvable->status] : null,
                    'episode' => $episode ? ['id' => $episode->id, 'episode_number' => $episode->episode_number, 'title' => $episode->title ?? "Episode {$episode->episode_number}", 'program_id' => $episode->program_id] : null,
                    'episode_id' => $episode?->id,
                    'episode_number' => $episode?->episode_number,
                    'program' => $program ? ['id' => $program->id, 'name' => $program->name] : null,
                    'program_id' => $program?->id,
                    'program_name' => $program?->name,
                    'requested_by' => $approval->requestedBy ? ['id' => $approval->requestedBy->id, 'name' => $approval->requestedBy->name, 'role' => $approval->requestedBy->role] : null,
                    'requested_by_name' => $approval->requestedBy?->name,
                    'approved_by' => $approval->approvedBy ? ['id' => $approval->approvedBy->id, 'name' => $approval->approvedBy->name] : null,
                    'approved_by_name' => $approval->approvedBy?->name,
                    'formatted_amount' => 'Rp ' . number_format($specialBudgetAmount, 0, ',', '.'),
                    'formatted_approved_amount' => 'Rp ' . number_format($approvedAmount, 0, ',', '.'),
                    'episode_display' => $episode ? "Episode {$episode->episode_number}" : 'Episode #-',
                    'creative_work_id' => $approvable ? (int) $approvable->id : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedItems->values()->toArray(),
                'message' => 'Special budget history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getSpecialBudgetApprovalsHistory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve special budget history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revised schedules for Manager Program
     * Schedules that have been revised by Manager Broadcasting
     */
    public function getRevisedSchedules(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Manager Program can access revised schedules'
                ], 403);
            }

            $status = $request->get('status', 'revised,approved');
            $statusArray = explode(',', $status);

            // Get schedules that have been revised (have upload_notes containing "REVISED")
            $schedules = BroadcastingSchedule::whereHas('episode.program', function ($query) use ($user) {
                // Filter by programs managed by this Manager Program
                $query->where('manager_program_id', $user->id);
            })
            ->where(function ($query) use ($statusArray) {
                $query->whereIn('status', $statusArray)
                      ->orWhere('upload_notes', 'like', '%REVISED%');
            })
            ->with([
                'episode.program.managerProgram',
                'episode.program.productionTeam'
            ])
            ->orderBy('schedule_date', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Revised schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving revised schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all approvals for Manager Program
     * Includes rundown edit requests and special budget approvals
     */
    public function getAllApprovals(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array(strtolower($user->role), ['manager program', 'program manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Manager Program can access this'
                ], 403);
            }

            $includeCompleted = $request->boolean('include_completed', false);

            // Get programs managed by this user
            $programIds = Program::where('manager_program_id', $user->id)->pluck('id');

            // Get rundown edit requests (episode_rundown atau rundown_edit)
            $rundownEdits = ProgramApproval::whereIn('approval_type', ['episode_rundown', 'rundown_edit'])
                ->when(!$includeCompleted, function($q) {
                    $q->whereIn('status', ['pending', 'reviewed']);
                })
                ->whereHasMorph('approvable', [Episode::class], function ($q) use ($programIds) {
                    $q->whereIn('program_id', $programIds);
                })
                ->with(['approvable', 'requestedBy'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            // Get special budget approvals
            $specialBudgets = ProgramApproval::where('approval_type', 'special_budget')
                ->when(!$includeCompleted, function($q) {
                    $q->whereIn('status', ['pending', 'reviewed']);
                })
                ->whereHasMorph('approvable', ['App\Models\CreativeWork'], function ($q) use ($programIds) {
                    // CreativeWork -> Episode -> Program
                    $q->whereHas('episode', function ($q2) use ($programIds) {
                        $q2->whereIn('program_id', $programIds);
                    });
                })
                ->with(['approvable.episode.program', 'requestedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'rundown_edits' => $rundownEdits->values(),
                    'special_budgets' => $specialBudgets->values(),
                    'total_pending' => $rundownEdits->where('status', 'pending')->count() + 
                                      $specialBudgets->where('status', 'pending')->count(),
                    'total_all' => $rundownEdits->count() + $specialBudgets->count()
                ],
                'message' => 'Approvals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving approvals: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all schedules for Manager Program
     * Includes shooting schedules and broadcasting schedules
     */
    public function getAllSchedules(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array(strtolower($user->role), ['manager program', 'program manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Manager Program can access this'
                ], 403);
            }

            // Get programs managed by this user
            $programIds = Program::where('manager_program_id', $user->id)
                ->pluck('id');

            if ($programIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'schedules' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => 15,
                            'total' => 0,
                            'last_page' => 1
                        ]
                    ],
                    'message' => 'No schedules found'
                ]);
            }

            // Get broadcasting schedules
            $query = BroadcastingSchedule::whereHas('episode', function($q) use ($programIds) {
                $q->whereIn('program_id', $programIds);
            })
            ->with([
                'episode.program.managerProgram',
                'episode.program.productionTeam'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $statuses = explode(',', $request->status);
                $query->whereIn('status', $statuses);
            }

            // Filter cancelled
            if (!$request->boolean('include_cancelled', false)) {
                $query->where('status', '!=', 'cancelled');
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('schedule_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('schedule_date', '<=', $request->end_date);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $schedules = $query->orderBy('schedule_date', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving schedules: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedules: ' . $e->getMessage()
            ], 500);
        }
    }






    /**
     * Approve Special Budget (dengan atau tanpa edit amount)
     * POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve
     */
    public function approveSpecialBudget(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $userRole = is_string($user->role) ? strtolower(trim($user->role)) : '';
            if (!in_array($userRole, ['manager program', 'program manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'approved_amount' => 'nullable|numeric|min:0', // Jika tidak diisi, approve dengan amount yang diminta
                'approval_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $approval = ProgramApproval::with(['approvable.episode.program.productionTeam'])->findOrFail($id);

            if ($approval->approval_type !== 'special_budget') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a special budget approval'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This approval has already been processed'
                ], 400);
            }

            $requestedAmount = $approval->request_data['special_budget_amount'] ?? 0;
            $approvedAmount = $request->approved_amount ?? $requestedAmount;

            // Update approval dengan approved_amount di approval_notes dan request_data
            $approvalNotes = $request->approval_notes ?? "Budget khusus disetujui sebesar Rp " . number_format($approvedAmount, 0, ',', '.');
            
            // Update request_data dengan approved_amount untuk tracking
            $requestData = $approval->request_data ?? [];
            $requestData['approved_amount'] = $approvedAmount;
            $requestData['is_revised'] = $approvedAmount != $requestedAmount;
            
            // Approve dengan amount yang disetujui
            $approval->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $approvalNotes,
                'request_data' => $requestData // Update request_data dengan approved_amount
            ]);

            // Update Creative Work
            $creativeWork = $approval->approvable; // CreativeWork
            if ($creativeWork) {
                // Update budget_data dengan menambahkan atau mengupdate special budget
                $budgetData = $creativeWork->budget_data ?? [];
                
                // Cek apakah sudah ada special budget di budget_data (jika sudah pernah di-approve sebelumnya)
                $specialBudgetIndex = null;
                foreach ($budgetData as $index => $item) {
                    if (is_array($item) && isset($item['category']) && $item['category'] === 'Special Budget') {
                        $specialBudgetIndex = $index;
                        break;
                    }
                }
                
                // Jika sudah ada, update yang sudah ada. Jika belum, tambahkan yang baru
                $specialBudgetItem = [
                    'category' => 'Special Budget',
                    'description' => $creativeWork->special_budget_reason ?? 'Budget khusus yang disetujui Manager Program',
                    'amount' => $approvedAmount, // Gunakan approved_amount yang sudah di-edit
                    'raw_amount' => $approvedAmount, // Untuk perhitungan di frontend
                    'currency' => 'IDR',
                    'is_special_budget' => true, // Flag penting untuk frontend bisa memisahkan
                    'approved_by_manager' => true,
                    'approved_amount' => $approvedAmount,
                    'requested_amount' => $requestedAmount,
                    'approved_at' => now()->toDateTimeString(),
                    'is_revised' => $approvedAmount != $requestedAmount,
                    'approved_by' => $user->name ?? 'Manager Program'
                ];
                
                if ($specialBudgetIndex !== null) {
                    // Update special budget yang sudah ada
                    $budgetData[$specialBudgetIndex] = $specialBudgetItem;
                } else {
                    // Tambahkan special budget baru
                    $budgetData[] = $specialBudgetItem;
                }

                $creativeWork->update([
                    'budget_data' => $budgetData,
                    'requires_special_budget_approval' => false, // Sudah disetujui
                    'budget_approved' => true, // Budget sudah disetujui
                    'special_budget_reason' => $creativeWork->special_budget_reason, // Keep existing reason
                ]);
                
                Log::info('CreativeWork budget updated after special budget approval', [
                    'creative_work_id' => $creativeWork->id,
                    'requested_amount' => $requestedAmount,
                    'approved_amount' => $approvedAmount,
                    'is_revised' => $approvedAmount != $requestedAmount,
                    'total_budget' => $creativeWork->total_budget
                ]);

                $message = $approvedAmount != $requestedAmount
                    ? "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah disetujui dengan revisi. Diminta: Rp " . number_format($requestedAmount, 0, ',', '.') . ", Disetujui: Rp " . number_format($approvedAmount, 0, ',', '.')
                    : "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah disetujui sebesar Rp " . number_format($approvedAmount, 0, ',', '.');

                // Notify Producer
                $producer = $creativeWork->episode->program->productionTeam->producer ?? null;
                if ($producer) {
                    Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'special_budget_approved',
                        'title' => 'Budget Khusus Disetujui',
                        'message' => $message,
                        'data' => [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'requested_amount' => $requestedAmount,
                            'approved_amount' => $approvedAmount,
                            'is_revised' => $approvedAmount != $requestedAmount
                        ]
                    ]);
                }

                // Notify Creative
                if ($creativeWork->created_by) {
                    Notification::create([
                        'user_id' => $creativeWork->created_by,
                        'type' => 'special_budget_approved',
                        'title' => 'Budget Khusus Disetujui',
                        'message' => $message,
                        'data' => [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'approved_amount' => $approvedAmount
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => true, 
                'data' => $approval, 
                'message' => 'Special budget approved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error approving special budget: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve special budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject Special Budget
     * POST /api/live-tv/manager-program/special-budget-approvals/{id}/reject
     */
    public function rejectSpecialBudget(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $userRole = is_string($user->role) ? strtolower(trim($user->role)) : '';
            if (!in_array($userRole, ['manager program', 'program manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $approval = ProgramApproval::with(['approvable.episode.program.productionTeam'])->findOrFail($id);

            if ($approval->approval_type !== 'special_budget') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a special budget approval'
                ], 400);
            }

            if ($approval->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This approval has already been processed'
                ], 400);
            }

            // Reject approval (ProgramApproval tidak punya method reject, update langsung)
            $approval->update([
                'status' => 'rejected',
                'approval_notes' => $request->rejection_notes,
            ]);

            // Update Creative Work
            $creativeWork = $approval->approvable; // CreativeWork
            if ($creativeWork) {
                // ✅ PENTING: Hapus special budget item dari budget_data jika ada
                $budgetData = $creativeWork->budget_data ?? [];
                
                // Filter: hapus item dengan category 'Special Budget' atau is_special_budget = true
                $budgetData = array_values(array_filter($budgetData, function($item) {
                    if (is_array($item)) {
                        // Hapus jika category adalah 'Special Budget' atau is_special_budget = true
                        return !(
                            (isset($item['category']) && $item['category'] === 'Special Budget') ||
                            (isset($item['is_special_budget']) && $item['is_special_budget'] === true)
                        );
                    }
                    return true; // Keep non-array items
                }));
                
                $creativeWork->update([
                    'budget_data' => $budgetData, // Update budget_data tanpa special budget
                    'requires_special_budget_approval' => false, // Sudah diproses
                    'budget_approved' => false, // Budget ditolak
                    'special_budget_reason' => null // Reset reason
                ]);
                
                Log::info('CreativeWork budget updated after special budget rejection', [
                    'creative_work_id' => $creativeWork->id,
                    'budget_data_count' => count($budgetData),
                    'total_budget' => $creativeWork->total_budget
                ]);

                // Notify Producer
                $producer = $creativeWork->episode->program->productionTeam->producer;
                if ($producer) {
                    Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'special_budget_rejected',
                        'title' => 'Budget Khusus Ditolak',
                        'message' => "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah ditolak oleh Manager Program. Alasan: {$request->rejection_notes}. Anda dapat mengedit creative work untuk perbaikan.",
                        'data' => [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'rejection_notes' => $request->rejection_notes
                        ]
                    ]);
                }

                // Notify Creative
                Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'special_budget_rejected',
                    'title' => 'Budget Khusus Ditolak',
                    'message' => "Budget khusus untuk Episode {$creativeWork->episode->episode_number} telah ditolak oleh Manager Program. Alasan: {$request->rejection_notes}. Silakan perbaiki budget dan ajukan kembali.",
                    'data' => [
                        'approval_id' => $approval->id,
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'rejection_notes' => $request->rejection_notes
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->fresh(['approvable']),
                    'creative_work' => $creativeWork->fresh(['episode', 'specialBudgetApproval'])
                ],
                'message' => 'Special budget rejected. Producer and Creative have been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject special budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

