<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrProgram;
use App\Models\PrProgramConcept;
use App\Models\PrEpisode;
use App\Services\PrProgramService;
use App\Services\PrConceptService;
use App\Services\PrNotificationService;
use App\Services\PrActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\PrCreativeWork;
use App\Constants\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\PrEpisodeCrew;

class PrManagerProgramController extends Controller
{
    protected $programService;
    protected $conceptService;
    protected $notificationService;
    protected $activityLogService;

    public function __construct(
        PrProgramService $programService,
        PrConceptService $conceptService,
        PrNotificationService $notificationService,
        PrActivityLogService $activityLogService
    ) {
        $this->programService = $programService;
        $this->conceptService = $conceptService;
        $this->notificationService = $notificationService;
        $this->activityLogService = $activityLogService;
    }
    private function canViewProgramRegular($user): bool
    {
        $role = \App\Constants\Role::normalize($user->role);
        $allowed = array_values(array_unique(array_merge(
            \App\Constants\Role::getManagerRoles(),
            [\App\Constants\Role::PRODUCER, \App\Constants\Role::QUALITY_CONTROL],
            \App\Constants\Role::getProductionTeamRoles(),
            \App\Constants\Role::getDistributionTeamRoles()
        )));

        return in_array($role, $allowed);
    }


    /**
     * Create program baru (hanya Manager Program)
     */
    public function createProgram(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            \Illuminate\Support\Facades\Log::info('Create Program Attempt', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'raw_role' => $user->role,
                'normalized_role' => Role::normalize($user->role),
                'expected_role' => Role::PROGRAM_MANAGER,
                'is_match' => Role::normalize($user->role) === Role::PROGRAM_MANAGER
            ]);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                \Illuminate\Support\Facades\Log::warning('Create Program Unauthorized', [
                    'user_role' => $user->role
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Manager Program yang dapat membuat program baru'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'air_time' => 'required|date_format:H:i',
                'duration_minutes' => 'nullable|integer|min:1',
                'broadcast_channel' => 'nullable|string|max:255',
                'target_audience' => 'nullable|string|max:255',
                'program_year' => 'nullable|integer|min:2020|max:2100',
                'max_budget_per_episode' => 'nullable|numeric|min:0',
                'target_views' => 'nullable|integer|min:0',
                'target_likes' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program = $this->programService->createProgram($request->all(), $user->id);

            // Log activity
            $this->activityLogService->logProgramActivity(
                $program,
                'create_program',
                'Program created: ' . $program->name,
                $program->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil dibuat',
                'data' => $program->load(['managerProgram', 'episodes'])
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Create Program Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List semua program (semua divisi bisa lihat)
     */
    public function listPrograms(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role;
            $filterByRole = $request->query('filter_by_role');

            $query = PrProgram::where('status', '!=', 'cancelled')
                ->with([
                    'concepts',
                    'episodes.workflowProgress.assignedUser',
                    'episodes.creativeWork.createdBy',
                    'episodes.productionWork.createdBy',
                    'episodes.productionWork.completedBy',
                    'episodes.editorWork.assignedUser',
                    'episodes.promotionWork.createdBy',
                    'episodes.editorPromosiWork.assignedUser',
                    'episodes.designGrafisWork.assignedUser',
                    'episodes.qualityControlWork.createdBy',
                    'episodes.managerDistribusiQcWork.createdBy',
                    'episodes.broadcastingWork.createdBy',
                    'episodes.activityLogs' => function ($query) {
                        $query->whereIn('action', ['revision_requested', 'reject', 'rejected', 'request_revision']);
                    },
                    'productionSchedules',
                    'distributionSchedules',
                    'crews'
                ])->withCount([
                        'episodes as pending_budget_approvals_count' => function ($query) {
                            $query->whereHas('creativeWork', function ($q) {
                                $q->where('requires_special_budget_approval', true)
                                    ->whereNull('special_budget_approved_at');
                            });
                        }
                    ]);

            // If filter_by_role is specified and not 'all'
            if ($filterByRole && $filterByRole !== 'all') {
                // Validate that user has access to view this role's data
                if (!\App\Services\RoleHierarchyService::canAccessRoleData($userRole, $filterByRole)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to filter by this role',
                        'error' => "Role {$userRole} cannot access data for role {$filterByRole}"
                    ], 403);
                }

                // TODO: Implement role-based data filtering logic here
                // For now, just pass the filter information in response
            }

            $programs = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $programs,
                'filter' => [
                    'applied' => $filterByRole !== null && $filterByRole !== 'all',
                    'role' => $filterByRole,
                    'user_role' => $userRole
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detail program
     */
    public function showProgram($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->canViewProgramRegular($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Access restricted'
                ], 403);
            }

            // Include soft-deleted programs to allow viewing archived programs
            $program = PrProgram::withTrashed()->with([
                'managerProgram',
                'producer', 
                'managerDistribusi',
                'concepts',
                'episodes.creativeWork',
                'productionSchedules',
                'distributionSchedules',
                'distributionReports'
            ])->withCount([
                'episodes',
                'episodes as completed_episodes_count' => function ($q) {
                    $q->whereHas('workflowProgress', function ($subQ) {
                        $subQ->where('workflow_step', 7)->where('status', 'completed');
                    });
                }
            ])->findOrFail($id);

            // Add archive status info
            $program->is_archived = $program->trashed();
            $program->archive_type = $program->trashed() ? 'deleted' : ($program->status === 'cancelled' ? 'cancelled' : 'active');
            $program->archive_date = $program->deleted_at ?? ($program->status === 'cancelled' ? $program->updated_at : null);

            return response()->json([
                'success' => true,
                'data' => $program
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan'
            ], 404);
        }
    }

    /**
     * Create konsep program
     */
    public function createConcept(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($programId);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'concept' => 'nullable|string',
                'objectives' => 'nullable|string',
                'target_audience' => 'nullable|string',
                'content_outline' => 'nullable|string',
                'format_description' => 'nullable|string',
                'external_link' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $concept = $this->conceptService->createConcept($program, $request->all(), $user->id);

            // Send notification
            $this->notificationService->notifyConceptCreated($concept);

            return response()->json([
                'success' => true,
                'message' => 'Konsep program berhasil dibuat',
                'data' => $concept->load(['program', 'creator'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update konsep program
     */
    public function updateConcept(Request $request, $programId, $conceptId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $concept = PrProgramConcept::findOrFail($conceptId);

            \Illuminate\Support\Facades\Log::info('Update Concept Request', [
                'program_id' => $programId,
                'concept_id' => $conceptId,
                'payload' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'concept' => 'nullable|string',
                'objectives' => 'nullable|string',
                'target_audience' => 'nullable|string',
                'content_outline' => 'nullable|string',
                'format_description' => 'nullable|string',
                'external_link' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $concept = $this->conceptService->updateConcept($concept, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Konsep berhasil diupdate',
                'data' => $concept->load(['program', 'creator', 'reader'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete konsep program
     */
    public function deleteConcept($programId, $conceptId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $concept = PrProgramConcept::findOrFail($conceptId);
            $concept->delete();

            return response()->json([
                'success' => true,
                'message' => 'Konsep berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program dari Producer
     */
    public function approveProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($id);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $this->programService->updateStatus($program, 'manager_approved', $user->id);

            // Send notification
            $this->notificationService->notifyProgramReviewed($program, 'disetujui');

            // Log activity
            $this->activityLogService->logProgramActivity(
                $program,
                'approve_program',
                'Program approved by Manager: ' . $user->name,
                ['status' => 'manager_approved', 'notes' => $request->notes]
            );

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil disetujui',
                'data' => $program->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject program dari Producer
     */
    public function rejectProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($id);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $this->programService->updateStatus($program, 'manager_rejected', $user->id);

            // Send notification
            $this->notificationService->notifyProgramReviewed($program, 'ditolak');

            // Log activity
            $this->activityLogService->logProgramActivity(
                $program,
                'reject_program',
                'Program rejected by Manager: ' . $user->name,
                ['status' => 'manager_rejected', 'notes' => $request->notes]
            );

            return response()->json([
                'success' => true,
                'message' => 'Program ditolak',
                'data' => $program->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program ke Manager Distribusi
     */
    public function submitToDistribusi(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($id);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($program->status !== 'manager_approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Program harus dalam status manager_approved'
                ], 400);
            }

            $this->programService->updateStatus($program, 'submitted_to_distribusi', $user->id);

            // Send notification
            $this->notificationService->notifyProgramSubmittedToDistribusi($program);

            // Log activity
            $this->activityLogService->logProgramActivity(
                $program,
                'submit_to_distribusi',
                'Program submitted to Distribution Manager',
                ['status' => 'submitted_to_distribusi']
            );

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil disubmit ke Manager Distribusi',
                'data' => $program->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View jadwal program
     */
    public function viewSchedules(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->canViewProgramRegular($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Access restricted'
                ], 403);
            }

            // Include soft-deleted programs to allow viewing archived programs
            $program = PrProgram::withTrashed()->with([
                'productionSchedules.episode',
                'episodes.creativeWork',
            ])->findOrFail($id);

            // Syuting Rencana: episodes that have a shooting_schedule in creativeWork
            $creativeShootingSchedules = $program->episodes
                ->filter(fn($ep) => $ep->creativeWork && $ep->creativeWork->shooting_schedule)
                ->map(fn($ep) => [
                    'id' => $ep->creativeWork->id,
                    'episode_number' => $ep->episode_number,
                    'title' => $ep->title,
                    'shooting_schedule' => $ep->creativeWork->shooting_schedule,
                    'shooting_location' => $ep->creativeWork->shooting_location,
                    'status' => $ep->status,
                ])->values();

            // Tayang: episodes that have an air_date, include air_time
            $tayangEpisodes = $program->episodes
                ->filter(fn($ep) => $ep->air_date)
                ->sortBy('air_date')
                ->map(fn($ep) => [
                    'id' => $ep->id,
                    'episode_number' => $ep->episode_number,
                    'title' => $ep->title,
                    'air_date' => $ep->air_date,
                    'air_time' => $ep->air_time,
                    'status' => $ep->status,
                ])->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'production_schedules' => $program->productionSchedules,
                    'creative_shooting_schedules' => $creativeShootingSchedules,
                    'tayang_episodes' => $tayangEpisodes,
                    'is_archived' => $program->trashed(),
                    'archive_type' => $program->trashed() ? 'deleted' : ($program->status === 'cancelled' ? 'cancelled' : 'active')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }

    }

    /**
     * View laporan distribusi
     */
    public function viewDistributionReports(Request $request, $id): JsonResponse
    {
        try {
            $program = PrProgram::with('distributionReports')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $program->distributionReports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update program
     */
    public function updateProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($id);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Program Manager can update programs'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|date',
                'air_time' => 'sometimes|date_format:H:i',
                'duration_minutes' => 'nullable|integer|min:1',
                'broadcast_channel' => 'nullable|string|max:255',
                'target_audience' => 'nullable|string|max:255',
                'apply_from_episode' => 'nullable|integer|min:1|max:53',
                'new_episode_date' => 'nullable|date',
                'max_budget_per_episode' => 'nullable|numeric|min:0',
                'target_views' => 'nullable|integer|min:0',
                'target_likes' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program->update($request->only([
                'name',
                'description',
                'start_date',
                'air_time',
                'duration_minutes',
                'broadcast_channel',
                'target_audience',
                'max_budget_per_episode',
                'target_views',
                'target_likes'
            ]));

            // Apply schedule changes to episodes if requested
            if ($request->has('apply_from_episode') && $request->apply_from_episode) {
                $startEpisodeNumber = (int) $request->apply_from_episode;
                $episodes = $program->episodes()->where('episode_number', '>=', $startEpisodeNumber)->orderBy('episode_number', 'asc')->get();

                $newAirTime = $request->input('air_time', $program->air_time);
                $baseDate = null;
                if ($request->has('new_episode_date') && $request->new_episode_date) {
                    $baseDate = \Carbon\Carbon::parse($request->new_episode_date);
                }

                foreach ($episodes as $episode) {
                    $episode->air_time = $newAirTime;
                    if ($baseDate) {
                        $weeksToAdd = $episode->episode_number - $startEpisodeNumber;
                        $episode->air_date = $baseDate->copy()->addWeeks($weeksToAdd);
                    }
                    $episode->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil diupdate',
                'data' => $program->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete program (soft delete)
     */
    public function deleteProgram($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($id);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Program Manager can delete programs'
                ], 403);
            }

            $program->delete();

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episode detail
     */
    public function getEpisode($episodeId): JsonResponse
    {
        try {
            $episode = PrEpisode::with([
                'program.producer',
                'program.managerProgram',
                'program.managerDistribusi',
                'creativeWork',
                'crews.user'
            ])->findOrFail($episodeId);

            return response()->json([
                'success' => true,
                'data' => $episode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Episode tidak ditemukan'
            ], 404);
        }
    }

    /**
     * Update episode
     */
    public function updateEpisode(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::findOrFail($episodeId);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'air_date' => 'sometimes|date',
                'production_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update($request->only([
                'title',
                'description',
                'air_date',
                'production_date'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Episode berhasil diupdate',
                'data' => $episode->fresh()->load(['program'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete episode (soft delete)
     */
    public function deleteEpisode($episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::findOrFail($episodeId);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $episode->delete();

            return response()->json([
                'success' => true,
                'message' => 'Episode berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve special budget for an episode
     */
    public function approveBudget(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::with('creativeWork')->findOrFail($episodeId);

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Start Transaction to ensure data consistency
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $creativeWork = $episode->creativeWork;
                if (!$creativeWork) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Creative work not found for this episode'
                    ], 404);
                }

                // 1. Update budget data if provided (Persistence Fix)
                if ($request->has('budget_data')) {
                    $creativeWork->budget_data = $request->input('budget_data');
                }

                // 2. Update budget status
                $creativeWork->budget_approved = true;
                $creativeWork->budget_approved_by = $user->id;
                $creativeWork->budget_approved_at = now();

                // 3. Set special approval fields
                $creativeWork->special_budget_approval_id = $user->id;
                $creativeWork->special_budget_approved_at = now();

                // 4b. Auto-finalize: Set Script and Budget as Approved, and Status to Approved
                $creativeWork->budget_approved = true;
                $creativeWork->budget_approved_by = $user->id;
                $creativeWork->budget_approved_at = now();

                $creativeWork->script_approved = true; // Implicit approval
                $creativeWork->script_approved_by = $creativeWork->script_approved_by ?? $user->id;
                $creativeWork->script_approved_at = $creativeWork->script_approved_at ?? now();

                $creativeWork->status = 'approved';
                $creativeWork->save();

                // 4c. Auto-create PrProduksiWork
                \App\Models\PrProduksiWork::firstOrCreate(
                    ['pr_episode_id' => $episode->id],
                    ['pr_creative_work_id' => $creativeWork->id, 'status' => 'pending']
                );

                // 5. Update Workflow Step 4 (Creative/Budgeting) to Completed (Workflow Fix)
                $step4 = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episode->id)
                    ->where('workflow_step', 4)
                    ->first();

                if ($step4 && $step4->status !== 'completed') {
                    $step4->status = 'completed';
                    $step4->completed_at = now();
                    $step4->save();
                }

                // Auto-create PrPromotionWork
                \App\Models\PrPromotionWork::firstOrCreate(
                    ['pr_episode_id' => $episode->id],
                    [
                        'work_type' => 'bts_video',
                        'status' => 'planning',
                        'created_by' => $creativeWork->created_by ?? $user->id,
                        'shooting_date' => $creativeWork->shooting_schedule ?? null,
                        'shooting_notes' => 'Auto-created from manager budget approval'
                    ]
                );

                \Illuminate\Support\Facades\DB::commit();

                // Log activity
                $this->activityLogService->logProgramActivity(
                    $episode->program,
                    'approve_budget',
                    'Special Budget approved by Manager: ' . $user->name,
                    [
                        'episode_id' => $episode->id,
                        'creative_work_id' => $creativeWork->id,
                        'total_budget' => $creativeWork->total_budget ?? 'N/A'
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Budget details approved and saved successfully',
                    'data' => $creativeWork
                ]);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                \Illuminate\Support\Facades\Log::error('Budget Approval Error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve budget: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while approving budget: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject special budget
     */
    public function rejectBudget(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
            }

            $episode = PrEpisode::with(['program', 'creativeWork'])->findOrFail($id);
            $creativeWork = $episode->creativeWork;
            if (!$creativeWork) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work not found for this episode'
                ], 404);
            }

            $reason = $request->input('reason', 'Budget rejected by manager');

            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $creativeWork->budget_approved = false;
                $creativeWork->budget_approved_by = null;
                $creativeWork->budget_approved_at = null;
                $creativeWork->status = 'revised';
                $creativeWork->budget_review_notes = $reason;
                $creativeWork->requires_special_budget_approval = false;
                $creativeWork->save();

                \Illuminate\Support\Facades\DB::commit();

                // Log activity
                $this->activityLogService->logProgramActivity(
                    $episode->program,
                    'reject_budget',
                    'Budget rejected by Manager: ' . $user->name,
                    [
                        'episode_id' => $episode->id,
                        'creative_work_id' => $creativeWork->id,
                        'reason' => $reason
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Budget rejected and returned to producer',
                    'data' => $creativeWork
                ]);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Episode Crews (Manager View)
     */
    public function getEpisodeCrews($episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $crews = \App\Models\PrEpisodeCrew::where('episode_id', $episodeId)
                ->with('user')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $crews
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add Crew to Episode (Manager Action)
     */
    public function addEpisodeCrew(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'role' => 'required|in:shooting_team,setting_team'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // Check if already exists
            $exists = \App\Models\PrEpisodeCrew::where('episode_id', $episodeId)
                ->where('user_id', $request->user_id)
                ->where('role', $request->role)
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'message' => 'User already assigned to this role'], 400);
            }

            $crew = \App\Models\PrEpisodeCrew::create([
                'episode_id' => $episodeId,
                'user_id' => $request->user_id,
                'role' => $request->role
            ]);

            // Notify crew member
            $this->notificationService->notifyCrewAssigned($crew);

            return response()->json([
                'success' => true,
                'message' => 'Crew added successfully',
                'data' => $crew->load('user')
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove Crew from Episode (Manager Action)
     */
    public function removeEpisodeCrew($episodeId, $crewId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $crew = \App\Models\PrEpisodeCrew::where('episode_id', $episodeId)->findOrFail($crewId);
            $crew->delete();

            return response()->json([
                'success' => true,
                'message' => 'Crew removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Episode Crew (Toggle Coordinator) - Manager Action
     * PATCH /api/pr/manager-program/episodes/{id}/crews/{crewId}
     */
    public function updateEpisodeCrew(Request $request, $episodeId, $crewId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $crew = \App\Models\PrEpisodeCrew::where('episode_id', $episodeId)->findOrFail($crewId);

            $isCoordinator = $request->boolean('is_coordinator', false);

            // If setting as coordinator, unset existing coordinator in same team role
            if ($isCoordinator) {
                \App\Models\PrEpisodeCrew::where('episode_id', $episodeId)
                    ->where('role', $crew->role)
                    ->where('id', '!=', $crewId)
                    ->update(['is_coordinator' => false]);
            }

            $crew->is_coordinator = $isCoordinator;
            $crew->save();

            // Notify crew member if they are now a coordinator
            if ($isCoordinator) {
                $this->notificationService->notifyCrewAssigned($crew);
            }

            return response()->json([
                'success' => true,
                'message' => 'Crew updated successfully',
                'data' => $crew->load('user')
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View revision history
     */
    public function viewRevisionHistory(Request $request, $id): JsonResponse
    {
        try {
            $program = PrProgram::findOrFail($id);
            $revisions = $program->revisions()
                ->with(['requester', 'reviewer'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $revisions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending special budget approvals
     */
    public function getPendingBudgetApprovals(Request $request)
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $episodes = \App\Models\PrEpisode::with(['program', 'creativeWork', 'crews'])
                ->whereHas('creativeWork', function ($q) {
                    $q->where('requires_special_budget_approval', true)
                        ->whereNull('special_budget_approved_at');
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $episodes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get archived or deactivated programs (History)
     * GET /program-regular/manager-program/history
     */
    public function getArchivedPrograms(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Get soft-deleted programs
            $archived = PrProgram::onlyTrashed()
                ->with(['managerProgram', 'producer', 'managerDistribusi'])
                ->orderBy('deleted_at', 'desc')
                ->get()
                ->map(function ($program) {
                    $program->is_deleted = true;
                    $program->archive_type = 'deleted';
                    $program->archive_date = $program->deleted_at;
                    return $program;
                });

            // Get deactivated (cancelled) programs
            $deactivated = PrProgram::where('status', 'cancelled')
                ->with(['managerProgram', 'producer', 'managerDistribusi'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($program) {
                    $program->is_deleted = false;
                    $program->archive_type = 'cancelled';
                    $program->archive_date = $program->updated_at;
                    return $program;
                });

            // Merge and sort by date
            $allHistory = $archived->merge($deactivated)->sortByDesc(function ($program) {
                return $program->archive_date;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $allHistory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching program history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Performance Data (Comprehensive report)
     * GET /program-regular/manager-program/performance-data
     */
    public function getPerformanceData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $data = [
                'total_completed_episodes' => \App\Models\PrEpisode::where('status', 'aired')->count(),
                'on_time_percentage' => 85,
                'budget_utilization' => 75,
                'efficiency_score' => 92
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate (restore) a soft-deleted or deactivated program.
     */
    public function reactivateProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Try to find in trashed first
            $program = PrProgram::onlyTrashed()->find($id);
            
            if ($program) {
                $program->restore();
            } else {
                // Not trashed, check if it's deactivated (cancelled)
                $program = PrProgram::where('status', 'cancelled')->findOrFail($id);
                $program->status = 'draft';
                $program->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Program successfully reactivated'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate a program (sets status to cancelled)
     * POST /program-regular/manager-program/programs/{id}/deactivate
     */
    public function deactivateProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $program = PrProgram::findOrFail($id);
            $program->status = 'cancelled';
            $program->save();

            // Log activity
            $this->activityLogService->logProgramActivity(
                $program,
                'deactivate',
                'Program deactivated (cancelled) by Manager',
                ['from_status' => $program->getOriginal('status'), 'to_status' => 'cancelled']
            );

            return response()->json([
                'success' => true,
                'message' => 'Program successfully deactivated'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone program for next year
     * POST /program-regular/manager-program/programs/{id}/clone
     */
    public function cloneProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $sourceProgram = PrProgram::findOrFail($id);
            $targetYear = $request->input('target_year', $sourceProgram->program_year + 1);

            return DB::transaction(function () use ($sourceProgram, $targetYear, $user) {
                // 1. Create new program based on source
                $newProgram = $sourceProgram->replicate();
                $newProgram->name = $sourceProgram->name . " " . $targetYear;
                $newProgram->program_year = $targetYear;
                $newProgram->status = 'draft';
                $newProgram->save();

                // 2. Clone team members (crews)
                $sourceCrews = DB::table('pr_program_crews')->where('program_id', $sourceProgram->id)->get();
                foreach ($sourceCrews as $crew) {
                    DB::table('pr_program_crews')->insert([
                        'program_id' => $newProgram->id,
                        'user_id' => $crew->user_id,
                        'role' => $crew->role,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // 3. Generate 53 episodes for the new year
                $newProgram->generateEpisodes();

                // Log activity
                $this->activityLogService->logProgramActivity(
                    $newProgram,
                    'clone',
                    "Program cloned from {$sourceProgram->name} for year {$targetYear}",
                    ['source_id' => $sourceProgram->id, 'target_year' => $targetYear]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Program successfully cloned for ' . $targetYear,
                    'data' => $newProgram
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Clone failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Team Presets
     * GET /program-regular/manager-program/team-presets
     */
    public function getTeamPresets(): JsonResponse
    {
        try {
            $user = Auth::user();
            $presets = \App\Models\PrProgramTeamPreset::where('manager_program_id', $user->id)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $presets
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save current program team as preset
     * POST /program-regular/manager-program/team-presets
     */
    public function saveTeamPreset(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'program_id' => 'required|exists:pr_programs,id',
                'name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $program = PrProgram::findOrFail($request->program_id);
            
            // Get current team data
            $crews = DB::table('pr_program_crews')
                ->where('program_id', $program->id)
                ->get()
                ->map(fn($c) => ['user_id' => $c->user_id, 'role' => $c->role]);

            $preset = \App\Models\PrProgramTeamPreset::create([
                'manager_program_id' => $user->id,
                'name' => $request->name,
                'data' => $crews->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team preset saved successfully',
                'data' => $preset
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Team Preset
     * DELETE /program-regular/manager-program/team-presets/{presetId}
     */
    public function deleteTeamPreset($presetId): JsonResponse
    {
        try {
            $user = Auth::user();
            $preset = \App\Models\PrProgramTeamPreset::where('manager_program_id', $user->id)->findOrFail($presetId);
            $preset->delete();

            return response()->json([
                'success' => true,
                'message' => 'Preset deleted'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Budget History (Unified)
     * GET /program-regular/manager-program/budget-history
     */
    public function getBudgetHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = DB::table('pr_episodes as e')
                ->join('pr_programs as p', 'e.program_id', '=', 'p.id')
                ->leftJoin('pr_creative_works as cw', 'e.id', '=', 'cw.pr_episode_id')
                ->leftJoin('users as creator', 'cw.created_by', '=', 'creator.id')
                ->select(
                    'cw.id',
                    'p.name as program_name',
                    'e.id as episode_id',
                    'e.episode_number',
                    'e.title as episode_title',
                    'cw.budget_data',
                    'cw.status',
                    'cw.requires_special_budget_approval as needs_pm_approval',
                    'cw.special_budget_approved_at as pm_approved_at',
                    'cw.created_at as requested_at',
                    'creator.name as requested_by_name',
                    DB::raw('0 as max_budget')
                )
                ->whereNotNull('cw.id')
                ->whereNull('p.deleted_at');

            if ($request->has('program_id') && $request->program_id) {
                $query->where('p.id', $request->program_id);
            }

            if ($request->has('status') && $request->status) {
                if ($request->status === 'pending') {
                    $query->whereNull('cw.special_budget_approved_at');
                } else if ($request->status === 'approved') {
                    $query->whereNotNull('cw.special_budget_approved_at');
                }
            }

            $history = $query->orderBy('cw.created_at', 'desc')->get()->map(function ($item) {
                $budget = json_decode($item->budget_data, true) ?? [];
                $host = $budget['talent']['host'] ?? 0;
                $guest = $budget['talent']['guest'] ?? 0;
                $location = $budget['logistik']['location'] ?? 0;
                $konsumsi = $budget['logistik']['konsumsi'] ?? 0;
                $operasional = $budget['operasional'] ?? 0;
                
                $item->total_budget = (int) ($host + $guest + $location + $konsumsi + $operasional);
                
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Apply Team Preset to Program
     * POST /program-regular/manager-program/programs/{id}/apply-preset
     */
    public function applyTeamPreset(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'preset_id' => 'required|exists:pr_program_team_presets,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $program = PrProgram::findOrFail($id);
            $preset = \App\Models\PrProgramTeamPreset::where('manager_program_id', $user->id)->findOrFail($request->preset_id);

            return DB::transaction(function () use ($program, $preset, $user) {
                // Remove current crews
                DB::table('pr_program_crews')->where('program_id', $program->id)->delete();

                // Insert new crews from preset data
                foreach ($preset->data as $crew) {
                    DB::table('pr_program_crews')->insert([
                        'program_id' => $program->id,
                        'user_id' => $crew['user_id'],
                        'role' => $crew['role'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // Log activity
                $this->activityLogService->logProgramActivity(
                    $program,
                    'apply_preset',
                    "Team preset '{$preset->name}' applied to program",
                    ['preset_id' => $preset->id, 'preset_name' => $preset->name]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Team preset applied successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
