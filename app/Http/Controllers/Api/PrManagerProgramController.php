<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrProgram;
use App\Models\PrProgramConcept;
use App\Models\PrEpisode;
use App\Services\PrProgramService;
use App\Services\PrConceptService;
use App\Services\PrNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Constants\Role;

class PrManagerProgramController extends Controller
{
    protected $programService;
    protected $conceptService;
    protected $notificationService;

    public function __construct(
        PrProgramService $programService,
        PrConceptService $conceptService,
        PrNotificationService $notificationService
    ) {
        $this->programService = $programService;
        $this->conceptService = $conceptService;
        $this->notificationService = $notificationService;
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
            $filters = $request->only(['status', 'program_year', 'manager_program_id', 'producer_id', 'search']);
            $programs = $this->programService->getPrograms($filters, Auth::user())
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
                'episodes',
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

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER || $program->manager_program_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'concept' => 'required|string',
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

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER || $program->manager_program_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|date',
                'air_time' => 'sometimes|date_format:H:i',
                'duration_minutes' => 'nullable|integer|min:1',
                'broadcast_channel' => 'nullable|string|max:255'
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

            if (Role::normalize($user->role) !== Role::PROGRAM_MANAGER || $program->manager_program_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
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
}
