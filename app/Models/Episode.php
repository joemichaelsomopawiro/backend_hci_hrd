<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\User;

class Episode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id',
        'episode_number',
        'title',
        'description',
        'air_date',
        'production_date',
        'format_type',
        'kwartal',
        'pelajaran',
        'status',
        'rundown',
        'script',
        'talent_data',
        'location',
        'notes',
        'production_notes',
        'current_workflow_state',
        'assigned_to_role',
        'assigned_to_user',
        'production_team_id',
        'team_assignment_notes',
        'team_assigned_by',
        'team_assigned_at',
        'actual_views',
        'views_last_updated',
        'views_growth_rate',
        'production_deadline',
        'script_deadline',
        'production_started_at',
        'production_completed_at',
        'submission_notes',
        'submitted_at',
        'submitted_by',
        'approval_notes',
        'approved_by',
        'approved_at',
        'rejection_notes',
        'rejected_by',
        'rejected_at'
    ];

    protected $casts = [
        'air_date' => 'datetime',
        'production_date' => 'datetime',
        'talent_data' => 'array',
        'production_deadline' => 'datetime',
        'script_deadline' => 'datetime',
        'production_started_at' => 'datetime',
        'production_completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'views_last_updated' => 'datetime'
    ];

    /**
     * Prepare a date for array / JSON serialization.
     * Memastikan timezone UTC untuk air_date dan production_date
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Relationship dengan Program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
    
    /**
     * Scope untuk exclude episodes dari program yang sudah dihapus
     */
    public function scopeActiveProgram($query)
    {
        return $query->whereHas('program', function($q) {
            $q->whereNull('deleted_at');
        });
    }

    /**
     * Episode belongs to production team
     */
    public function productionTeam(): BelongsTo
    {
        return $this->belongsTo(ProductionTeam::class);
    }

    /**
     * Relationship dengan Deadlines
     */
    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    /**
     * Relationship dengan Workflow States
     */
    public function workflowStates(): HasMany
    {
        return $this->hasMany(WorkflowState::class);
    }

    /**
     * Generate auto deadlines for episode
     */
    public function generateDeadlines()
    {
        $airDate = Carbon::parse($this->air_date);
        $programCategory = strtolower($this->program->category ?? 'regular');
        
        // Smart Detection: Check if it's explicitly marked OR has music arrangement data
        $hasMusicArrangements = $this->musicArrangements()->exists();
        $isMusic = $programCategory === 'musik' || $programCategory === 'music' || $hasMusicArrangements;
        
        $deadlineDays = $isMusic 
            ? \App\Constants\WorkflowStep::MUSIC_ROLE_DEADLINE_DAYS 
            : \App\Constants\WorkflowStep::ROLE_DEADLINE_DAYS;

        // Roles to generate
            $rolesToGenerate = $isMusic 
                ? [
                    'musik_arr_song' => 'Pengajuan Lagu',
                    'producer_acc_song' => 'ACC Lagu (Proposal)',
                    'musik_arr_lagu' => 'Aransemen Lagu',
                    'producer_acc_lagu' => 'ACC Aransemen',
                    'tim_vocal_coord' => 'Take Rekam Audio (Vocal)',
                    'sound_eng' => 'Edit Audio (Mixing)',
                    'kreatif' => 'Creative & Script',
                    'producer_creative' => 'Producer Approve Creative',
                    'tim_setting_coord' => 'Setting & Property',
                    'tim_syuting_coord' => 'Shooting & Produksi',
                    'editor' => 'Editing Video',
                    'quality_control' => 'QC Final',
                    'manager_distribusi' => 'Distribution Manager QC',
                    'broadcasting' => 'Broadcasting',
                    'promotion' => 'Promotion Material',
                    'design_grafis' => 'Design Grafis',
                    'editor_promosi' => 'Social Media Highlight'
                  ]
            : [
                'editor' => 'Editing',
                'kreatif' => 'Creative',
                'produksi' => 'Production',
                'musik_arr' => 'Music Arrangement',
                'sound_eng' => 'Sound Engineering',
                'quality_control' => 'QC',
                'promotion' => 'Promotion',
                'broadcasting' => 'Broadcasting'
              ];

        // Clean up legacy deadlines specifically for Music Programs to avoid "ghost steps"
        if ($isMusic) {
            $legacyRoles = [
                'musik_arr', 'produksi', 'sound_recording', 'shooting_recording', 
                'equipment_request', 'production_planning', 'producer_final_review',
                'program_manager_review', 'distribution_manager_qc'
            ];
            $this->deadlines()->whereIn('role', $legacyRoles)->delete();
        }


        foreach ($rolesToGenerate as $role => $label) {
            $days = $deadlineDays[$role] ?? 7;
            
            $this->deadlines()->updateOrCreate(
                ['role' => $role],
                [
                    'deadline_date' => $airDate->copy()->subDays($days),
                    'description' => "Deadline {$label}",
                    'auto_generated' => true,
                    'created_by' => auth()->id() ?? 1
                ]
            );
        }

        // Notifikasi ke semua role yang terkait
        $this->notifyDeadlineCreation();

        // Auto-assign team members if available
        $this->syncTeamAssignments();
    }

    /**
     * Notify deadline creation to relevant roles
     */
    private function notifyDeadlineCreation()
    {
        // Map deadline role to user role
        // Add mappings for music arranger and sound engineer so they receive notifications
        $roleMapping = [
            'editor' => 'Editor',
            'kreatif' => 'Creative',
            'produksi' => 'Production',
            'musik_arr' => 'Music Arranger',
            'sound_eng' => 'Sound Engineer',
            'quality_control' => 'Quality Control',
            'broadcasting' => 'Broadcasting',
            'promotion' => 'Promotion'
        ];
        
        // Get deadlines for this episode
        $deadlines = $this->deadlines()->get();
        
        foreach ($deadlines as $deadline) {
            $userRole = $roleMapping[$deadline->role] ?? null;
            
            if ($userRole) {
                $users = User::where('role', $userRole)->get();
                foreach ($users as $user) {
                    \App\Models\Notification::create([
                        'user_id' => $user->id,
                        'type' => 'deadline_created',
                        'title' => 'Deadline Baru Dibuat',
                        'message' => "Deadline baru untuk episode '{$this->title}' telah dibuat",
                        'data' => [
                            'episode_id' => $this->id,
                            'episode_title' => $this->title,
                            'role' => $deadline->role,
                            'deadline_date' => $deadline->deadline_date->format('Y-m-d')
                        ]
                    ]);
                }
            }
        }
    }

    /**
     * Update deadline if needed
     */
    public function updateDeadline($role, $newDeadlineDate, $reason = null)
    {
        $deadline = $this->deadlines()->where('role', $role)->first();
        
        if ($deadline) {
            $deadline->update([
                'deadline_date' => $newDeadlineDate,
                'updated_by' => auth()->id(),
                'update_reason' => $reason,
                'updated_at' => now()
            ]);

            // Notifikasi ke role yang terkait
            $users = User::where('role', $role)->get();
            foreach ($users as $user) {
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'type' => 'deadline_updated',
                    'title' => 'Deadline Diperbarui',
                    'message' => "Deadline untuk episode '{$this->title}' telah diperbarui",
                    'data' => [
                        'episode_id' => $this->id,
                        'episode_title' => $this->title,
                        'role' => $role,
                        'new_deadline' => $newDeadlineDate,
                        'reason' => $reason
                    ]
                ]);
            }
        }
    }

    /**
     * Relationship dengan Media Files
     */
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    /**
     * Relationship dengan Music Arrangements
     */
    public function musicArrangements(): HasMany
    {
        return $this->hasMany(MusicArrangement::class);
    }

    /**
     * Relationship dengan Creative Works
     */
    public function creativeWorks(): HasMany
    {
        return $this->hasMany(CreativeWork::class);
    }

    /**
     * Relationship dengan Creative Work tunggal (Latest)
     */
    public function creativeWork(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CreativeWork::class)->latestOfMany();
    }

    /**
     * Relationship dengan Production Equipment
     */
    public function productionEquipment(): HasMany
    {
        return $this->hasMany(ProductionEquipment::class);
    }

    /**
     * Relationship dengan Produksi Works
     */
    public function produksiWorks(): HasMany
    {
        return $this->hasMany(ProduksiWork::class);
    }



    /**
     * Relationship dengan Sound Engineer Recordings
     */
    public function soundEngineerRecordings(): HasMany
    {
        return $this->hasMany(SoundEngineerRecording::class);
    }

    /**
     * Relationship dengan Sound Engineer Editings
     */
    public function soundEngineerEditings(): HasMany
    {
        return $this->hasMany(SoundEngineerEditing::class);
    }

    /**
     * Relationship dengan Editor Works
     */
    public function editorWorks(): HasMany
    {
        return $this->hasMany(EditorWork::class);
    }

    /**
     * Relationship dengan Design Grafis Works
     */
    public function designGrafisWorks(): HasMany
    {
        return $this->hasMany(DesignGrafisWork::class);
    }

    /**
     * Relationship dengan Promotion Materials
     */
    public function promotionMaterials(): HasMany
    {
        return $this->hasMany(PromotionMaterial::class);
    }

    /**
     * Relationship dengan Broadcasting Schedules
     */
    public function broadcastingSchedules(): HasMany
    {
        return $this->hasMany(BroadcastingSchedule::class);
    }

    /**
     * Relationship dengan Broadcasting Works (Program Musik - upload YouTube/Website)
     */
    public function broadcastingWorks(): HasMany
    {
        return $this->hasMany(BroadcastingWork::class);
    }

    /**
     * Relationship dengan Promotion Works (Program Musik - BTS, sharing, dll)
     */
    public function promotionWorks(): HasMany
    {
        return $this->hasMany(PromotionWork::class);
    }

    /**
     * Relationship dengan Editor Promosi Works
     */
    public function editorPromosiWorks(): HasMany
    {
        return $this->hasMany(EditorPromosiWork::class);
    }

    /**
     * Relationship dengan Quality Control Works
     */
    public function qualityControlWorks(): HasMany
    {
        return $this->hasMany(QualityControlWork::class);
    }

    /**
     * Relationship dengan Quality Controls (Old version)
     */
    public function qualityControls(): HasMany
    {
        return $this->hasMany(QualityControl::class);
    }

    /**
     * Relationship dengan Budgets
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Relationship dengan Team Assignments
     */
    public function teamAssignments(): HasMany
    {
        return $this->hasMany(ProductionTeamAssignment::class, 'episode_id');
    }

    /**
     * Get deadline untuk role tertentu
     */
    public function getDeadlineForRole(string $role): ?Deadline
    {
        return $this->deadlines()->where('role', $role)->first();
    }

    /**
     * Get overdue deadlines
     */
    public function getOverdueDeadlines()
    {
        return $this->deadlines()
            ->where('deadline_date', '<', now())
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->get();
    }

    /**
     * Check if all deadlines completed
     */
    public function allDeadlinesCompleted(): bool
    {
        return $this->deadlines()
            ->where('is_completed', false)
            ->where('status', '!=', 'cancelled')
            ->count() === 0;
    }

    /**
     * Scope untuk episode yang akan datang
     */
    public function scopeUpcoming($query)
    {
        return $query->where('air_date', '>', now())
            ->where('status', '!=', 'aired');
    }

    /**
     * Scope untuk episode yang sudah tayang
     */
    public function scopeAired($query)
    {
        return $query->where('status', 'aired');
    }

    /**
     * Scope untuk episode yang overdue
     */
    public function scopeOverdue($query)
    {
        return $query->where('air_date', '<', now())
            ->where('status', '!=', 'aired');
    }

    /**
     * Scope berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope berdasarkan workflow state
     */
    public function scopeByWorkflowState($query, $state)
    {
        return $query->where('current_workflow_state', $state);
    }

    /**
     * Sync deadlines with program team assignments (Smart Sync)
     */
    public function syncTeamAssignments()
    {
        $program = $this->program;
        if (!$program) return false;

        $teamId = $this->production_team_id ?? $program->production_team_id;
        if (!$teamId) return false;

        $team = ProductionTeam::with('members')->find($teamId);
        if (!$team) return false;

        // Core mappings from Program and Team
        $assignments = [
            'program_manager' => $program->manager_program_id,
            'producer' => $team->producer_id,
        ];

        // Member mappings from ProductionTeamMember
        foreach ($team->members as $member) {
            if (!$member->is_active) continue;

            $role = $member->role;
            $userId = $member->user_id;

            // Map team role to workflow deadline roles
            switch ($role) {
                case 'creative':
                case 'kreatif':
                    $assignments['kreatif'] = $userId;
                    break;
                case 'musik_arr':
                case 'music_arranger':
                    $assignments['musik_arr'] = $userId;
                    $assignments['musik_arr_song'] = $userId;
                    $assignments['musik_arr_lagu'] = $userId;
                    break;
                case 'sound_eng':
                case 'sound_engineer':
                    $assignments['sound_eng'] = $userId;
                    break;
                case 'editor':
                    $assignments['editor'] = $userId;
                    if (!isset($assignments['promotion'])) {
                        $assignments['promotion'] = $userId;
                    }
                    if (!isset($assignments['editor_promosi'])) {
                        $assignments['editor_promosi'] = $userId;
                    }
                    break;
                case 'production':
                case 'produksi':
                    $assignments['tim_setting_coord'] = $userId;
                    $assignments['tim_syuting_coord'] = $userId;
                    $assignments['tim_vocal_coord'] = $userId;
                    $assignments['produksi'] = $userId;
                    break;
                case 'producer':
                    $assignments['producer'] = $userId;
                    $assignments['producer_acc_song'] = $userId;
                    $assignments['producer_acc_lagu'] = $userId;
                    $assignments['producer_creative'] = $userId;
                    break;
                case 'art_set_design':
                    $assignments['art_set_design'] = $userId;
                    break;
                case 'design_grafis':
                    $assignments['design_grafis'] = $userId;
                    break;
                case 'editor_promosi':
                    $assignments['editor_promosi'] = $userId;
                    $assignments['promotion'] = $userId;
                    break;
            }
        }

        // Apply assignments only if currently NULL (Smart Sync - Preserve work)
        foreach ($assignments as $role => $userId) {
            if (!$userId) continue;
            
            $this->deadlines()
                ->where('role', $role)
                ->whereNull('assigned_user_id') 
                ->update(['assigned_user_id' => $userId]);
        }
        
        // 5. PARALLEL TASK PROPAGATION: Ensure work records exist for the assigned users
        // Use the actual assigned users from the deadlines table (respects manual overrides)
        $actualAssignments = $this->deadlines()
            ->whereNotNull('assigned_user_id')
            ->pluck('assigned_user_id', 'role')
            ->toArray();

        $this->propagateAssignmentsToWorkRecords($actualAssignments);

        return true;
    }

    /**
     * Propagate assignments to respective work tables (CreativeWork, EditorWork, etc)
     * So they appear in role-specific dashboards.
     */
    protected function propagateAssignmentsToWorkRecords(array $assignments): void
    {
        foreach ($assignments as $role => $userId) {
            if (!$userId) continue;

            try {
                switch ($role) {
                    case 'kreatif':
                        \App\Models\CreativeWork::firstOrCreate(
                            ['episode_id' => $this->id],
                            ['created_by' => $userId, 'status' => 'draft']
                        );
                        break;

                    case 'editor':
                        \App\Models\EditorWork::firstOrCreate(
                            ['episode_id' => $this->id],
                            ['created_by' => $userId, 'status' => 'draft']
                        );
                        break;

                    case 'musik_arr':
                    case 'musik_arr_song':
                    case 'musik_arr_lagu':
                        \App\Models\MusicArrangement::firstOrCreate(
                            ['episode_id' => $this->id],
                            ['created_by' => $userId, 'status' => 'draft']
                        );
                        break;

                    case 'promotion':
                        \App\Models\PromotionWork::firstOrCreate(
                            ['episode_id' => $this->id, 'work_type' => 'bts_photo'],
                            ['created_by' => $userId, 'status' => 'planning', 'title' => 'BTS & Promo Material']
                        );
                        break;

                    case 'editor_promosi':
                        \App\Models\PromotionWork::firstOrCreate(
                            ['episode_id' => $this->id, 'work_type' => 'highlight_ig'],
                            ['created_by' => $userId, 'status' => 'planning', 'title' => 'Social Media Highlight']
                        );
                        break;

                    case 'design_grafis':
                        \App\Models\DesignGrafisWork::firstOrCreate(
                            ['episode_id' => $this->id],
                            ['created_by' => $userId, 'status' => 'planning']
                        );
                        break;

                    case 'broadcasting':
                        \App\Models\BroadcastingWork::firstOrCreate(
                            ['episode_id' => $this->id],
                            ['created_by' => $userId, 'status' => 'pending']
                        );
                        break;

                    case 'quality_control':
                        \App\Models\QualityControlWork::firstOrCreate(
                            ['episode_id' => $this->id],
                            ['created_by' => $userId, 'status' => 'pending']
                        );
                        break;

                    case 'tim_vocal_coord':
                        \App\Models\ProduksiWork::firstOrCreate(
                            ['episode_id' => $this->id, 'role' => 'tim_vocal_coord'],
                            ['created_by' => $userId, 'status' => 'pending']
                        );
                        break;

                    case 'tim_setting_coord':
                        \App\Models\ProduksiWork::firstOrCreate(
                            ['episode_id' => $this->id, 'role' => 'tim_setting_coord'],
                            ['created_by' => $userId, 'status' => 'pending']
                        );
                        break;

                    case 'tim_syuting_coord':
                        \App\Models\ProduksiWork::firstOrCreate(
                            ['episode_id' => $this->id, 'role' => 'tim_syuting_coord'],
                            ['created_by' => $userId, 'status' => 'pending']
                        );
                        break;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to propagate assignment for role {$role}: " . $e->getMessage());
            }
        }
    }
}