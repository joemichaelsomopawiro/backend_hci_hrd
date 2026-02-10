<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrCreativeWork;
use App\Models\PrEpisode;
use App\Models\Notification;
use App\Models\PrEpisodeWorkflowProgress;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\PrProgramCrew;
use App\Models\PrProgramFile;
use App\Services\PrNotificationService;

class PrCreativeController extends Controller
{
    protected $notificationService;

    public function __construct(PrNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Get episodes available for creating new creative work
     */
    public function getAvailableEpisodes(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Relaxed check for 'kreatif' or 'Creative'
            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get programs where current user is assigned as Kreatif crew
            // Checking for both 'kreatif' and 'Creative' roles in crew assignment
            $assignedProgramIds = PrProgramCrew::where('user_id', $user->id)
                ->whereIn('role', ['kreatif', 'Creative', 'creative'])
                ->pluck('program_id')
                ->toArray();

            // Get episodes from assigned programs that don't have a creative work yet
            $existingEpisodeIds = PrCreativeWork::pluck('pr_episode_id')->toArray();

            $episodes = PrEpisode::whereIn('program_id', $assignedProgramIds)
                ->whereNotIn('id', $existingEpisodeIds)
                ->whereHas('program', function ($q) {
                    $q->where('read_by_producer', true);
                })
                ->with('program')
                ->orderBy('created_at', 'desc')
                ->get();

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

            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get programs where current user is assigned as Kreatif crew
            $assignedProgramIds = \App\Models\PrProgramCrew::where('user_id', $user->id)
                ->whereIn('role', ['kreatif', 'Creative', 'creative'])
                ->pluck('program_id')
                ->toArray();

            // Start query with episode relationship for program filtering
            $query = PrCreativeWork::with(['episode.program', 'createdBy'])
                ->whereHas('episode', function ($q) use ($assignedProgramIds) {
                    $q->whereIn('program_id', $assignedProgramIds);
                });

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
            $work = PrCreativeWork::with([
                'episode.program',
                'createdBy',
                'reviewedBy',
                'scriptApprovedBy',
                'budgetApprovedBy',
                'specialBudgetApprover'
            ])->findOrFail($id);

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

            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
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

            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
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
            $isProducer = strtolower($user->role) === 'producer' || $user->role === 'Producer';
            $isCreative = strtolower($user->role) === 'kreatif' || strtolower($user->role) === 'creative';

            if (!$user || (!$isCreative && !$isProducer)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::findOrFail($id);

            // If not Producer, enforce ownership
            if (!$isProducer && $work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // If not Producer, enforce status check
            if (!$isProducer && !in_array($work->status, ['draft', 'in_progress', 'rejected', 'revised'])) {
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

            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::with('episode.program.producer')->findOrFail($id);

            if ($work->created_by !== $user->id) {
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

            // Validate required fields
            if (!$work->script_content || !$work->budget_data || !$work->shooting_schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete required fields: script_content, budget_data, and shooting_schedule before submitting',
                    'missing' => [
                        'script_content' => !$work->script_content,
                        'budget_data' => !$work->budget_data,
                        'shooting_schedule' => !$work->shooting_schedule
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

            // Automate Workflow Step 3 Completion: Creative Submits to Producer
            $workflowProgress = PrEpisodeWorkflowProgress::where('episode_id', $work->pr_episode_id)
                ->where('workflow_step', 3)
                ->first();

            if ($workflowProgress) {
                $workflowProgress->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    // 'completed_by' => $user->id // Optional, if column exists
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

            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = PrEpisode::with('program')->findOrFail($episodeId);
            $program = $episode->program;

            // Check assignment
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

            if (!$user || (strtolower($user->role) !== 'kreatif' && strtolower($user->role) !== 'creative')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $file = \App\Models\PrProgramFile::where('id', $fileId)
                ->where('episode_id', $episodeId)
                ->firstOrFail();

            // Check if user is the uploader
            if ($file->uploaded_by !== $user->id) {
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

            // Get latest drafts (status 'draft')
            $drafts = \App\Models\PrCreativeWork::with(['episode.program'])
                ->where('created_by', $user->id)
                ->where('status', 'draft')
                ->orderBy('updated_at', 'desc')
                ->take(3)
                ->get();

            // Get rejected works (status 'rejected')
            $rejected = \App\Models\PrCreativeWork::with(['episode.program'])
                ->where('created_by', $user->id)
                ->where('status', 'rejected')
                ->orderBy('updated_at', 'desc')
                ->take(5)
                ->get();

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
}
