<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrCreativeWork;
use App\Models\PrEpisode;
use App\Models\Notification;
use App\Models\PrEpisodeWorkflowProgress;
use App\Constants\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\PrProgramCrew;
use App\Models\PrProgramFile;
use App\Services\PrNotificationService;
use App\Services\PrActivityLogService;

class PrCreativeController extends Controller
{
    protected $notificationService;
    protected $activityLogService;

    public function __construct(PrNotificationService $notificationService, PrActivityLogService $activityLogService)
    {
        $this->notificationService = $notificationService;
        $this->activityLogService = $activityLogService;
    }
    /**
     * Get episodes available for creating new creative work
     */
    public function getAvailableEpisodes(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Relaxed check for 'kreatif' or 'Creative'
            if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $isSuperAdmin = $request->boolean('all_programs') && Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER]);

            $existingEpisodeIds = PrCreativeWork::pluck('pr_episode_id')->toArray();

            $query = PrEpisode::whereNotIn('id', $existingEpisodeIds)
                ->whereHas('program', function ($q) {
                    $q->where('read_by_producer', true);
                })
                ->with('program');

            if (!$isSuperAdmin && !Role::inArray($user->role, [Role::DISTRIBUTION_MANAGER])) {
                $query->where(function ($q) use ($user) {
                    // Producer of the program
                    $q->whereHas('program', function ($pq) use ($user) {
                        $pq->where('producer_id', $user->id);
                    })
                    // Assigned to program crew
                    ->orWhereHas('program.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });
                });
            }

            $episodes = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Available episodes retrieved successfully'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative works for current user (Creative role)
     * GET /api/pr/creative/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $isSuperAdmin = $request->boolean('all_programs') && Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER]);

            // Start query with episode relationship for program filtering
            $query = PrCreativeWork::with(['episode.program', 'createdBy']);

            if (!$isSuperAdmin && !Role::inArray($user->role, [Role::DISTRIBUTION_MANAGER])) {
                $query->where(function ($q) use ($user) {
                    // 1. Producer of the program
                    $q->whereHas('episode.program', function ($pq) use ($user) {
                        $pq->where('producer_id', $user->id);
                    });
                    
                    // 2. Assigned to program crew
                    $q->orWhereHas('episode.program.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });

                    // 3. Assigned to episode crew
                    $q->orWhereHas('episode.crews', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id);
                    });
                });
            }

            // Filter by status (only if not empty)
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by assigned user
            if ($request->boolean('my_works', false)) {
                $query->where('created_by', $user->id);
            }

            // Check for 'all' parameter to disable pagination
            if ($request->has('all') && $request->boolean('all')) {
                $works = $query->orderBy('created_at', 'desc')->get();
            } else {
                $works = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));
            }

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works retrieved successfully'
            ]);

        } catch (\Throwable $e) {
            file_put_contents(base_path('public/debug_log.txt'), $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving creative works: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    }

    /**
     * Get specific creative work
     * GET /api/pr/creative/works/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = PrCreativeWork::with(['episode.program', 'createdBy'])->findOrFail($id);

            if (!$this->checkWorkAuthorization($work, $user)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access. You are not assigned to this program.'], 403);
            }
            $work->load([
                'reviewedBy',
                'scriptApprovedBy',
                'budgetApprovedBy',
                'specialBudgetApprover'
            ]);

            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create creative work for PR episode
     * POST /api/pr/creative/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'pr_episode_id' => 'required|exists:pr_episodes,id',
                'episode_title' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if creative work already exists for this episode
            $existing = PrCreativeWork::where('pr_episode_id', $request->pr_episode_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work already exists for this episode',
                    'data' => $existing
                ], 400);
            }

            $data = $request->only([
                'pr_episode_id',
                'script_content',
                'storyboard_data',
                'budget_data',
                'recording_schedule',
                'shooting_schedule',
                'shooting_location',
                'setup_schedule',
                'talent_data',
                'requires_special_budget_approval'
            ]);
            $data['status'] = 'draft';
            $data['created_by'] = $user->id;

            $work = PrCreativeWork::create($data);

            // Update Episode Title if provided
            if ($request->filled('episode_title')) {
                $episode = PrEpisode::find($request->pr_episode_id);
                if ($episode) {
                    $episode->title = $request->episode_title;
                    $episode->save();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Creative work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept work - Creative accepts assignment
     * POST /api/pr/creative/works/{id}/accept-work
     */
    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::findOrFail($id);

            if ($work->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is draft'
                ], 400);
            }

            $work->update([
                'status' => 'in_progress',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now start working on script, storyboard, and budget.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update creative work (script, storyboard, budget, schedules)
     * PUT /api/pr/creative/works/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isProgramManager = Role::inArray($user->role, [Role::PROGRAM_MANAGER]);
            $isProducer = Role::inArray($user->role, [Role::PRODUCER]);
            $isCreative = strtolower($user->role) === 'kreatif' || strtolower($user->role) === 'creative';
            $isSuperRole = $isProgramManager || $isProducer;

            if (!$user || (!$isCreative && !$isSuperRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::findOrFail($id);

            // If not a super role (PM or Producer), enforce ownership
            if (!$isSuperRole && $work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // If not a super role (PM or Producer), enforce status check
            if (!$isSuperRole && !in_array($work->status, ['draft', 'in_progress', 'rejected', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work cannot be updated in current status: ' . $work->status
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'script_content' => 'nullable|string',
                'storyboard_data' => 'nullable|array',
                'budget_data' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'shooting_schedule' => 'nullable|date',
                'shooting_location' => 'nullable|string|max:500',
                'setup_schedule' => 'nullable|date',
                'talent_data' => 'nullable|array',
                'requires_special_budget_approval' => 'boolean',
                'episode_title' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'script_content',
                'storyboard_data',
                'budget_data',
                'recording_schedule',
                'shooting_schedule',
                'shooting_location',
                'setup_schedule',
                'talent_data',
                'requires_special_budget_approval'
            ]);

            $work->update($updateData);

            // Update Episode Title if provided
            if ($request->filled('episode_title')) {
                $episode = $work->episode;
                if ($episode) {
                    $episode->title = $request->episode_title;
                    $episode->save();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete and submit work to Producer for review
     * POST /api/pr/creative/works/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::with('episode.program.producer')->findOrFail($id);

            $isSuperRole = Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER]);
            if (!$isSuperRole && $work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if (!in_array($work->status, ['draft', 'in_progress', 'revised', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be submitted when draft, in_progress, revised or rejected'
                ], 400);
            }

            // Validate required fields.
            // Note: budget_data can be an array/object (even all-zeros), so use is_null() instead
            // of PHP's falsy check (!$x) which would incorrectly flag an empty array/object as missing.
            
            // Check if script exists either as text or uploaded file
            $hasScriptText = !empty($work->script_content);
            $hasScriptFile = \App\Models\PrProgramFile::where('episode_id', $work->pr_episode_id)
                ->where('category', 'script')
                ->exists();
            $missingScript = !$hasScriptText && !$hasScriptFile;

            $missingBudget   = is_null($work->budget_data);
            $missingSchedule = empty($work->shooting_schedule);

            if ($missingScript || $missingBudget || $missingSchedule) {
                $missingFields = [];
                if ($missingScript) $missingFields[] = "Naskah/Script (Teks atau File PDF)";
                if ($missingBudget) $missingFields[] = "Rencana Anggaran (Budget)";
                if ($missingSchedule) $missingFields[] = "Jadwal Shooting";

                $fieldsList = implode(', ', $missingFields);

                return response()->json([
                    'success' => false,
                    'message' => "Gagal submit. Harap lengkapi bidang berikut: $fieldsList",
                    'missing' => [
                        'script_content'   => $missingScript,
                        'budget_data'      => $missingBudget,
                        'shooting_schedule' => $missingSchedule
                    ]
                ], 400);
            }

            $work->update([
                'status' => 'submitted',
                'reviewed_at' => null, // Reset review
                'review_notes' => null
            ]);

            // Notify Producer via Service
            $this->notificationService->notifyCreativeWorkSubmitted($work);

            // Log Activity
            $this->activityLogService->logEpisodeActivity(
                $work->episode,
                'submit_creative',
                "Creative script/naskah submitted for review",
                ['step' => 3, 'work_id' => $work->id]
            );

            // Automate Workflow Step 3 Completion: Creative Submits to Producer
            $workflowProgress = PrEpisodeWorkflowProgress::where('episode_id', $work->pr_episode_id)
                ->where('workflow_step', 3)
                ->first();

            if ($workflowProgress) {
                $workflowProgress->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'assigned_user_id' => $user->id
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work submitted successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting creative work: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Upload file (Script, etc.)
     * POST /api/pr/creative/episodes/{id}/files
     */
    public function uploadFile(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();

            $isSuperRole = Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER]);
            $isCreative = strtolower($user->role ?? '') === 'kreatif' || strtolower($user->role ?? '') === 'creative';

            if (!$user || (!$isCreative && !$isSuperRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = PrEpisode::with('program')->findOrFail($episodeId);
            $program = $episode->program;

            // Check assignment (bypass for Program Manager and Producer)
            if (!$isSuperRole) {
                $isAssigned = \App\Models\PrProgramCrew::where('user_id', $user->id)
                    ->where('program_id', $program->id)
                    ->whereIn('role', ['kreatif', 'Creative', 'creative'])
                    ->exists();

                if (!$isAssigned) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized. You are not assigned to this program.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:pdf|max:104857600', // Max 100MB, PDF only
                'category' => 'required|in:raw_footage,edited_video,thumbnail,script,rundown,other',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Only PDF files are allowed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $path = $file->store('program-regular/files', 'public');

            $programFile = \App\Models\PrProgramFile::create([
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

            // If it is a script, we might want to return the URL for easy access
            $fileUrl = asset('storage/' . $path);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diupload',
                'data' => $programFile->load(['program', 'episode', 'uploader']),
                'file_url' => $fileUrl
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files for an episode
     * GET /api/pr/creative/episodes/{id}/files
     */
    public function getFiles(Request $request, $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check authorization
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $files = \App\Models\PrProgramFile::where('episode_id', $episodeId)
                ->with('uploader')
                ->orderBy('created_at', 'desc')
                ->get();

            // Add full URL
            $files->transform(function ($file) {
                $file->file_url = asset('storage/' . $file->file_path);
                return $file;
            });

            return response()->json([
                'success' => true,
                'data' => $files
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching files: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Delete file
     * DELETE /api/pr/creative/episodes/{id}/files/{fileId}
     */
    public function deleteFile(Request $request, $episodeId, $fileId): JsonResponse
    {
        try {
            $user = Auth::user();

            $isSuperRole = Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER]);
            $isCreative = strtolower($user->role ?? '') === 'kreatif' || strtolower($user->role ?? '') === 'creative';

            if (!$user || (!$isCreative && !$isSuperRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $file = \App\Models\PrProgramFile::where('id', $fileId)
                ->where('episode_id', $episodeId)
                ->firstOrFail();

            // Check if user is the uploader (bypass for Program Manager and Producer)
            if (!$isSuperRole && $file->uploaded_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only delete files you uploaded.'
                ], 403);
            }

            // Create path relative to storage/app/public
            // The file_path in DB is like 'program-regular/files/filename.pdf'
            // Storage::disk('public')->delete() expects path relative to public root
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($file->file_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($file->file_path);
            }

            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting file: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get creative highlights (drafts and rejected works)
     * GET /api/pr/creative/highlights
     */
    public function getHighlights(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $isSuperAdmin = $request->boolean('all_programs') && Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::PRODUCER]);

            $draftsQuery = \App\Models\PrCreativeWork::with(['episode.program'])
                ->where('status', 'draft')
                ->orderBy('updated_at', 'desc');

            $rejectedQuery = \App\Models\PrCreativeWork::with(['episode.program'])
                ->where('status', 'rejected')
                ->orderBy('updated_at', 'desc');

            if (!$isSuperAdmin) {
                $draftsQuery->where('created_by', $user->id);
                $rejectedQuery->where('created_by', $user->id);
            }

            // Get latest drafts
            $drafts = $draftsQuery->take(5)->get();

            // Get rejected works
            $rejected = $rejectedQuery->take(5)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'active_drafts' => $drafts,
                    'rejected_works' => $rejected
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to check if user is authorized for a Creative Work
     */
    private function checkWorkAuthorization($work, $user): bool
    {
        if (!$user) return false;

        // 1. Administrative roles
        if (Role::inArray($user->role, [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER])) {
            return true;
        }

        // 2. Producer (Only their own programs)
        if (Role::inArray($user->role, [Role::PRODUCER])) {
            return $work->episode && $work->episode->program && $work->episode->program->producer_id === $user->id;
        }

        // 3. Program Crew Assignment (Matches Creative staff)
        $isCrew = \App\Models\PrProgramCrew::where('user_id', $user->id)
            ->where('program_id', $work->episode->program_id)
            ->exists();
        if ($isCrew) return true;

        // 4. Episode Crew Assignment
        $isEpisodeCrew = \App\Models\PrEpisodeCrew::where('user_id', $user->id)
            ->where('episode_id', $work->pr_episode_id)
            ->exists();
        if ($isEpisodeCrew) return true;

        return false;
    }
}
