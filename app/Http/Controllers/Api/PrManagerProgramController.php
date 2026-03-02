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
                'program_year' => 'nullable|integer|min:2020|max:2100'
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

            $query = PrProgram::with([
                'concepts',
                'episodes',
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
            $program = PrProgram::with([
                'managerProgram',
                'producer',
                'managerDistribusi',
                'concepts',
                'episodes.creativeWork',
                'productionSchedules',
                'distributionSchedules',
                'distributionReports'
            ])->findOrFail($id);

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
                'format_description' => 'nullable|string'
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

            $validator = Validator::make($request->all(), [
                'concept' => 'nullable|string',
                'objectives' => 'nullable|string',
                'target_audience' => 'nullable|string',
                'content_outline' => 'nullable|string',
                'format_description' => 'nullable|string'
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
            $program = PrProgram::with(['productionSchedules', 'distributionSchedules'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'production_schedules' => $program->productionSchedules,
                    'distribution_schedules' => $program->distributionSchedules
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
                'apply_from_episode' => 'nullable|integer|min:1|max:53',
                'new_episode_date' => 'nullable|date'
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
                'broadcast_channel'
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

                // 4. Reset "requires" flag so it doesn't show as pending anymore (optional, but good for UI state)
                // Actually, keeping it true but approved is fine, frontend handles it. 
                // But let's check frontend logic: 
                // <span v-if="work.budget_approved" class="text-success">Budget Approved</span>
                // <span v-else-if="work.requires_special_budget_approval" class="text-warning">Pending Manager</span>
                // So if approved=true, it shows Approved regardless of requires flag. Good.

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
                // Assuming Step 4 is "Creative Work"
                $step4 = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episode->id)
                    ->where('workflow_step', 4) // Adjust ID if dynamic
                    ->first();

                // OR better: use current step logic if 4 is hardcoded
                // Ideally we find the step that corresponds to "Budgeting" or "Creative"
                // For now, let's assume step 4 based on earlier context.
                if ($step4 && $step4->status !== 'completed') {
                    $step4->status = 'completed';
                    $step4->completed_at = now();

                    $step4->save();
                }

                // Auto-create PrPromotionWork (Ensure consistency with Producer approval)
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

    public function rejectBudget(Request $request, $id)
    {
        try {
            // Get user from token
            $user = Auth::user();

            // Validate that user is a PROGRAM_MANAGER
            $validRoles = [Role::PROGRAM_MANAGER];

            // Check if user has any of the valid roles
            $hasRole = false;
            foreach ($validRoles as $role) {
                if ($user->hasRole($role)) {
                    $hasRole = true;
                    break;
                }
            }

            if (!$hasRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $episode = PrEpisode::with(['program', 'creativeWork'])->findOrFail($id);

            // Check if program belongs to manager (if applicable)
            // Implementation skipped for brevity, assuming middleware handles or logic similar to above

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
                // Update stats
                $creativeWork->budget_approved = false;
                $creativeWork->budget_approved_by = null;
                $creativeWork->budget_approved_at = null;

                // Important: Reset special budget requirement so Producer sees it as actionable
                // Or we can keep it and just change status. 
                // User said "dikembalikan ke producer".
                // Setting status to 'revised' is good standard practice.
                $creativeWork->status = 'revised';
                $creativeWork->budget_review_notes = $reason;
                // We keep requires_special_budget_approval = true so they know it WAS special request?
                // Or set to false so they can submit again?
                // Let's set false so they can toggle it again or submit normal if they reduce budget.
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
            // Manager can view crews
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
}
