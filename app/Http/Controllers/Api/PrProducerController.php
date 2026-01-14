<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrProgram;
use App\Models\PrProgramConcept;
use App\Models\PrEpisode;
use App\Models\PrProductionSchedule;
use App\Models\PrProgramFile;
use App\Services\PrConceptService;
use App\Services\PrProductionService;
use App\Services\PrNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PrProducerController extends Controller
{
    protected $conceptService;
    protected $productionService;
    protected $notificationService;

    public function __construct(
        PrConceptService $conceptService,
        PrProductionService $productionService,
        PrNotificationService $notificationService
    ) {
        $this->conceptService = $conceptService;
        $this->productionService = $productionService;
        $this->notificationService = $notificationService;
    }

    /**
     * List konsep untuk approval
     */
    public function listConceptsForApproval(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $concepts = $this->conceptService->getConceptsForApproval($user->id)
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $concepts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve konsep
     */
    public function approveConcept(Request $request, $conceptId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $concept = PrProgramConcept::findOrFail($conceptId);
            $concept = $this->conceptService->approveConcept($concept, $user->id, $request->notes ?? null);
            
            // Send notification
            $this->notificationService->notifyConceptReviewed($concept, 'disetujui');

            return response()->json([
                'success' => true,
                'message' => 'Konsep berhasil disetujui',
                'data' => $concept->load(['program', 'approver'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject konsep
     */
    public function rejectConcept(Request $request, $conceptId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Producer') {
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

            $concept = PrProgramConcept::findOrFail($conceptId);
            $concept = $this->conceptService->rejectConcept($concept, $user->id, $request->notes);
            
            // Send notification
            $this->notificationService->notifyConceptReviewed($concept, 'ditolak');

            return response()->json([
                'success' => true,
                'message' => 'Konsep ditolak',
                'data' => $concept->load(['program', 'rejector'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create jadwal produksi
     */
    public function createProductionSchedule(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($programId);

            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'nullable|exists:pr_episodes,id',
                'scheduled_date' => 'required|date',
                'scheduled_time' => 'nullable|date_format:H:i',
                'schedule_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = $this->productionService->createProductionSchedule(
                $program,
                $request->all(),
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produksi berhasil dibuat',
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
     * Update status episode (produksi/editing)
     */
    public function updateEpisodeStatus(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::findOrFail($episodeId);

            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:production,editing,ready_for_review',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = $this->productionService->updateEpisodeStatus(
                $episode,
                $request->status,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Status episode berhasil diupdate',
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
     * Upload file setelah editing
     */
    public function uploadFile(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::findOrFail($episodeId);

            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:102400', // Max 100GB
                'category' => 'required|in:raw_footage,edited_video,thumbnail,script,rundown,other',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $path = $file->store('program-regular/files', 'public');

            $programFile = PrProgramFile::create([
                'program_id' => $episode->program_id,
                'episode_id' => $episode->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'category' => $request->category,
                'uploaded_by' => $user->id,
                'description' => $request->description
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diupload',
                'data' => $programFile->load(['program', 'episode', 'uploader'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program ke Manager Program
     */
    public function submitToManager(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($programId);

            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Check if all episodes are ready
            $episodesNotReady = $program->episodes()
                ->whereNotIn('status', ['ready_for_review', 'manager_approved', 'aired'])
                ->count();

            if ($episodesNotReady > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masih ada episode yang belum siap untuk review'
                ], 400);
            }

            // Submit all episodes
            foreach ($program->episodes as $episode) {
                if ($episode->status === 'ready_for_review') {
                    $this->productionService->submitForReview($episode);
                }
            }

            $program->update(['status' => 'submitted_to_manager']);
            
            // Send notification
            $this->notificationService->notifyProgramSubmitted($program);

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil disubmit ke Manager Program',
                'data' => $program->fresh()->load(['episodes'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update production schedule
     */
    public function updateProductionSchedule(Request $request, $scheduleId): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = PrProductionSchedule::findOrFail($scheduleId);

            if ($user->role !== 'Producer' || $schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'scheduled_date' => 'sometimes|date',
                'scheduled_time' => 'nullable|date_format:H:i',
                'schedule_notes' => 'nullable|string',
                'status' => 'sometimes|in:draft,confirmed,in_progress,completed,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update($request->only([
                'scheduled_date', 'scheduled_time', 'schedule_notes', 'status'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produksi berhasil diupdate',
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
     * Delete production schedule
     */
    public function deleteProductionSchedule($scheduleId): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = PrProductionSchedule::findOrFail($scheduleId);

            if ($user->role !== 'Producer' || $schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produksi berhasil dihapus'
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

            if ($user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'production_date' => 'nullable|date',
                'production_notes' => 'nullable|string',
                'editing_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update($request->only([
                'title', 'description', 'production_date', 'production_notes', 'editing_notes'
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
     * Delete episode
     */
    public function deleteEpisode($episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $episode = PrEpisode::findOrFail($episodeId);

            if ($user->role !== 'Producer') {
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
     * View jadwal tayang (distribution schedules)
     */
    public function viewDistributionSchedules(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::with(['distributionSchedules'])->findOrFail($programId);

            return response()->json([
                'success' => true,
                'data' => $program->distributionSchedules
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
    public function viewDistributionReports(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::with(['distributionReports'])->findOrFail($programId);

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
