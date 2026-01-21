<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrProgram;
use App\Models\PrProgramConcept;
use App\Models\PrEpisode;
use App\Models\PrDistributionSchedule;
use App\Models\PrDistributionReport;
use App\Services\PrDistributionService;
use App\Services\PrNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Constants\Role;

class PrManagerDistribusiController extends Controller
{
    protected $distributionService;
    protected $notificationService;

    public function __construct(
        PrDistributionService $distributionService,
        PrNotificationService $notificationService
    ) {
        $this->distributionService = $distributionService;
        $this->notificationService = $notificationService;
    }

    /**
     * List program untuk distribusi
     */
    public function listProgramsForDistribusi(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $programs = PrProgram::with(['managerProgram', 'producer', 'episodes'])
                ->whereIn('status', ['submitted_to_distribusi', 'distribusi_approved', 'scheduled'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $programs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify program
     */
    public function verifyProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($id);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'verified' => 'required|boolean',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program = $this->distributionService->verifyProgram(
                $program,
                $request->verified,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => $request->verified ? 'Program berhasil diverifikasi' : 'Program ditolak',
                'data' => $program
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create jadwal tayang
     */
    public function createDistributionSchedule(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($programId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'nullable|exists:pr_episodes,id',
                'schedule_date' => 'required|date',
                'schedule_time' => 'required|date_format:H:i',
                'channel' => 'nullable|string|max:255',
                'schedule_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = $this->distributionService->createDistributionSchedule(
                $program,
                $request->all(),
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Jadwal tayang berhasil dibuat',
                'data' => $schedule->load(['program', 'episode', 'creator'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark episode as aired
     */
    public function markAsAired(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::findOrFail($episodeId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $episode = $this->distributionService->markAsAired($episode);

            return response()->json([
                'success' => true,
                'message' => 'Episode berhasil ditandai sebagai sudah tayang',
                'data' => $episode->load(['program'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create laporan distribusi
     */
    public function createDistributionReport(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($programId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'nullable|exists:pr_episodes,id',
                'report_title' => 'required|string|max:255',
                'report_content' => 'required|string',
                'distribution_data' => 'nullable|array',
                'analytics_data' => 'nullable|array',
                'report_period_start' => 'nullable|date',
                'report_period_end' => 'nullable|date|after_or_equal:report_period_start'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $report = $this->distributionService->createDistributionReport(
                $program,
                $request->all(),
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Laporan distribusi berhasil dibuat',
                'data' => $report->load(['program', 'episode', 'creator'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List laporan distribusi
     */
    public function listDistributionReports(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $filters = $request->only(['program_id', 'status']);
            $reports = $this->distributionService->getDistributionReports($filters)
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View shooting schedule per episode
     */
    public function viewShootingSchedule(Request $request, $episodeId): JsonResponse
    {
        try {
            $episode = PrEpisode::with(['productionSchedules'])->findOrFail($episodeId);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'shooting_schedules' => $episode->productionSchedules
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
     * View program files
     */
    public function viewProgramFiles(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::with(['files'])->findOrFail($programId);

            return response()->json([
                'success' => true,
                'data' => $program->files
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update distribution schedule
     */
    public function updateDistributionSchedule(Request $request, $scheduleId): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = PrDistributionSchedule::findOrFail($scheduleId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER || $schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'schedule_date' => 'sometimes|date',
                'schedule_time' => 'sometimes|date_format:H:i',
                'channel' => 'nullable|string|max:255',
                'schedule_notes' => 'nullable|string',
                'status' => 'sometimes|in:draft,confirmed,scheduled,aired,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update($request->only([
                'schedule_date',
                'schedule_time',
                'channel',
                'schedule_notes',
                'status'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Jadwal tayang berhasil diupdate',
                'data' => $schedule->fresh()->load(['program', 'episode'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete distribution schedule
     */
    public function deleteDistributionSchedule($scheduleId): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = PrDistributionSchedule::findOrFail($scheduleId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER || $schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal tayang berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update distribution report
     */
    public function updateDistributionReport(Request $request, $reportId): JsonResponse
    {
        try {
            $user = Auth::user();
            $report = PrDistributionReport::findOrFail($reportId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER || $report->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'report_title' => 'sometimes|string|max:255',
                'report_content' => 'sometimes|string',
                'distribution_data' => 'nullable|array',
                'analytics_data' => 'nullable|array',
                'status' => 'sometimes|in:draft,published,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $report->update($request->only([
                'report_title',
                'report_content',
                'distribution_data',
                'analytics_data',
                'status'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Laporan distribusi berhasil diupdate',
                'data' => $report->fresh()->load(['program', 'episode'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete distribution report
     */
    public function deleteDistributionReport($reportId): JsonResponse
    {
        try {
            $user = Auth::user();
            $report = PrDistributionReport::findOrFail($reportId);

            if (Role::normalize($user->role) !== Role::DISTRIBUTION_MANAGER || $report->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Laporan distribusi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View konsep program
     */
    public function viewProgramConcept(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::with(['concepts.creator', 'concepts.approver', 'concepts.rejector'])->findOrFail($programId);

            return response()->json([
                'success' => true,
                'data' => [
                    'program' => $program,
                    'concepts' => $program->concepts
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
     * View jadwal produksi
     */
    public function viewProductionSchedules(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::with(['productionSchedules.episode', 'productionSchedules.creator'])->findOrFail($programId);

            return response()->json([
                'success' => true,
                'data' => $program->productionSchedules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View revision history
     */
    public function viewRevisionHistory(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::findOrFail($programId);
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
}
