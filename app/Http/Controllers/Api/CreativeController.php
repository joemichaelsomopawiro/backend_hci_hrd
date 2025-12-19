<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreativeWork;
use App\Models\Episode;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreativeController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Get creative works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = CreativeWork::with([
                'episode.program', 
                'episode.musicArrangements' => function($q) {
                    $q->whereIn('status', ['arrangement_approved', 'approved'])
                      ->orderBy('reviewed_at', 'desc')
                      ->limit(1);
                },
                'createdBy', 
                'reviewedBy'
            ]);
            
            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by creator - default to current user if Creative role
            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            } elseif ($user->role === 'Creative') {
                $query->where('created_by', $user->id);
            }

            // Filter untuk "Terima Pekerjaan" - hanya creative work dengan status draft
            if ($request->has('ready_for_work') && $request->ready_for_work == 'true') {
                $query->where('status', 'draft');
            }
            
            $works = $query->orderBy('created_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve creative works',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative work by ID
     */
    public function show(int $id): JsonResponse
    {
        // Use cache for frequently accessed data
        $work = \App\Helpers\QueryOptimizer::remember(
            \App\Helpers\QueryOptimizer::getCacheKey('CreativeWork', $id),
            300, // 5 minutes
            function () use ($id) {
                return CreativeWork::with([
                    'episode.program.managerProgram',
                    'episode.program.productionTeam.members.user',
                    'episode.musicArrangements' => function($q) {
                        $q->whereIn('status', ['arrangement_approved', 'approved'])
                          ->orderBy('reviewed_at', 'desc')
                          ->limit(1);
                    },
                    'createdBy',
                    'reviewedBy'
                ])->findOrFail($id);
            }
        );
        
        return response()->json([
            'success' => true,
            'data' => $work,
            'message' => 'Creative work retrieved successfully'
        ]);
    }

    /**
     * Create creative work
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'script_content' => 'nullable|string',
            'storyboard_data' => 'nullable|array',
            'budget_data' => 'nullable|array',
            'recording_schedule' => 'nullable|date',
            'shooting_schedule' => 'nullable|date',
            'shooting_location' => 'nullable|string|max:255',
            'created_by' => 'required|exists:users,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work = CreativeWork::create([
                'episode_id' => $request->episode_id,
                'script_content' => $request->script_content,
                'storyboard_data' => $request->storyboard_data,
                'budget_data' => $request->budget_data,
                'recording_schedule' => $request->recording_schedule,
                'shooting_schedule' => $request->shooting_schedule,
                'shooting_location' => $request->shooting_location,
                'status' => 'draft',
                'created_by' => $request->created_by
            ]);
            
            // Audit logging
            ControllerSecurityHelper::logCreate($work, [
                'episode_id' => $work->episode_id,
                'status' => 'draft'
            ], $request);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update creative work
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'script_content' => 'nullable|string',
            'storyboard_data' => 'nullable|array',
            'budget_data' => 'nullable|array',
            'recording_schedule' => 'nullable|date',
            'shooting_schedule' => 'nullable|date',
            'shooting_location' => 'nullable|string|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit creative work for review
     */
    public function submit(int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        if ($work->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft works can be submitted'
            ], 400);
        }
        
        try {
            $work->submitForReview();
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work submitted for review successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve creative work
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        if ($work->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted works can be approved'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'review_notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->approve(auth()->id(), $request->review_notes);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject creative work
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $work = CreativeWork::findOrFail($id);
        
        if ($work->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted works can be rejected'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $work->reject(auth()->id(), $request->rejection_reason);
            
            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Creative work rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative works by episode
     */
    public function getByEpisode(int $episodeId): JsonResponse
    {
        try {
            $works = CreativeWork::where('episode_id', $episodeId)
                ->with(['createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works by episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get creative works by episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative works by status
     */
    public function getByStatus(string $status): JsonResponse
    {
        try {
            $works = CreativeWork::where('status', $status)
                ->with(['episode', 'createdBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Creative works by status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get creative works by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget summary
     */
    public function getBudgetSummary(int $id): JsonResponse
    {
        try {
            $work = CreativeWork::findOrFail($id);
            $budgetSummary = [
                'total_budget' => $work->total_budget,
                'formatted_budget' => 'Rp ' . number_format($work->total_budget, 0, ',', '.'),
                'budget_data' => $work->formatted_budget_data
            ];
            
            return response()->json([
                'success' => true,
                'data' => $budgetSummary,
                'message' => 'Budget summary retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get budget summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Creative terima pekerjaan setelah arrangement approved
     * User: "Terima Pekerjaan"
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = CreativeWork::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow accept work if status is draft
            if ($work->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is draft'
                ], 400);
            }

            // Change status to in_progress
            $oldStatus = $work->status;
            $work->update([
                'status' => 'in_progress'
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('creative_work_accepted', $work, [
                'episode_id' => $work->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'in_progress'
            ], $request);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now start working on script, storyboard, schedules, and budget.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan - Creative selesaikan setelah selesai semua tugas
     * User: "Selesaikan Pekerjaan"
     * Includes: script, storyboard, recording schedule, shooting schedule, location, budget
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'script_content' => 'nullable|string',
                'storyboard_data' => 'nullable|array',
                'recording_schedule' => 'nullable|string', // Use string to be more flexible with ISO formats
                'shooting_schedule' => 'nullable|string',
                'shooting_location' => 'nullable|string|max:255',
                'budget_data' => 'nullable|array',
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                Log::warning('Creative Complete Work Validation Failed:', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all(),
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the work, allow any Creative user to complete it if they are in the same team
            // but for now, let's keep it simple: any user with role 'Creative' can update.
            $work = CreativeWork::where('id', $id)->firstOrFail();
            
            if (strtolower($user->role) !== 'creative') {
                 return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Creative role can complete this work.'
                ], 403);
            }

            // Only allow complete if status is in_progress
            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is in_progress'
                ], 400);
            }

            // Update all fields and auto-submit
            $work->update([
                'script_content' => $request->script_content,
                'storyboard_data' => $request->storyboard_data,
                'recording_schedule' => $request->recording_schedule,
                'shooting_schedule' => $request->shooting_schedule,
                'shooting_location' => $request->shooting_location,
                'budget_data' => $request->budget_data,
                'status' => 'submitted', // Auto-submit untuk Producer review
                'review_notes' => $request->completion_notes ? 
                    ($work->review_notes ? $work->review_notes . "\n\n" : '') . "Completion notes: " . $request->completion_notes 
                    : $work->review_notes
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'creative_work_submitted',
                    'title' => 'Creative Work Selesai',
                    'message' => "Creative {$user->name} telah menyelesaikan creative work untuk Episode {$episode->episode_number}. Script, storyboard, schedules, dan budget telah disubmit untuk review.",
                    'data' => [
                        'creative_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'completion_notes' => $request->completion_notes
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Creative work has been submitted for Producer review.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revisi Creative Work setelah budget ditolak
     * PUT /api/live-tv/roles/creative/works/{id}/revise
     */
    public function reviseCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'script_content' => 'nullable|string',
                'storyboard_data' => 'nullable|array',
                'budget_data' => 'nullable|array',
                'recording_schedule' => 'nullable|date',
                'shooting_schedule' => 'nullable|date',
                'shooting_location' => 'nullable|string|max:255',
                'revision_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = CreativeWork::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow revise if status is rejected or revised
            if (!in_array($work->status, ['rejected', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be revised when status is rejected or revised'
                ], 400);
            }

            // Update fields
            $updateData = [];
            if ($request->has('script_content')) $updateData['script_content'] = $request->script_content;
            if ($request->has('storyboard_data')) $updateData['storyboard_data'] = $request->storyboard_data;
            if ($request->has('budget_data')) $updateData['budget_data'] = $request->budget_data;
            if ($request->has('recording_schedule')) $updateData['recording_schedule'] = $request->recording_schedule;
            if ($request->has('shooting_schedule')) $updateData['shooting_schedule'] = $request->shooting_schedule;
            if ($request->has('shooting_location')) $updateData['shooting_location'] = $request->shooting_location;

            // Reset review fields
            $updateData['script_approved'] = null;
            $updateData['storyboard_approved'] = null;
            $updateData['budget_approved'] = null;
            $updateData['script_review_notes'] = null;
            $updateData['storyboard_review_notes'] = null;
            $updateData['budget_review_notes'] = null;
            $updateData['requires_special_budget_approval'] = false;
            $updateData['special_budget_reason'] = null;
            $updateData['special_budget_approval_id'] = null;
            $updateData['status'] = 'revised';

            if ($request->revision_notes) {
                $updateData['review_notes'] = ($work->review_notes ? $work->review_notes . "\n\n" : '') . 
                    "[Revisi] " . $request->revision_notes;
            }

            $work->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work revised successfully. You can now resubmit to Producer.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error revising creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resubmit Creative Work setelah revisi
     * POST /api/live-tv/roles/creative/works/{id}/resubmit
     */
    public function resubmitCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = CreativeWork::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow resubmit if status is revised
            if ($work->status !== 'revised') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be resubmitted when status is revised'
                ], 400);
            }

            // Validate required fields
            if (!$work->script_content || !$work->storyboard_data || !$work->budget_data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete script, storyboard, and budget before resubmitting'
                ], 400);
            }

            // Resubmit
            $oldStatus = $work->status;
            $work->update([
                'status' => 'submitted'
            ]);

            // Audit logging
            ControllerSecurityHelper::logApproval('creative_work_resubmitted', $work, [
                'episode_id' => $work->episode_id,
                'old_status' => $oldStatus,
                'new_status' => 'submitted'
            ], $request);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'creative_work_resubmitted',
                    'title' => 'Creative Work Diresubmit',
                    'message' => "Creative {$user->name} telah meresubmit creative work untuk Episode {$episode->episode_number} setelah revisi.",
                    'data' => [
                        'creative_work_id' => $work->id,
                        'episode_id' => $work->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Creative work resubmitted successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resubmitting creative work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Storyboard file
     * POST /api/live-tv/roles/creative/works/{id}/upload-storyboard
     */
    public function uploadStoryboard(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Creative') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Cari file apa saja yang dikirim (tidak terpaku pada key 'file')
            $file = null;
            $allFiles = $request->allFiles();
            if (!empty($allFiles)) {
                $file = reset($allFiles); // Ambil file pertama yang ditemukan
            }

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['file' => ['No file was uploaded.']]
                ], 422);
            }

            // Validasi manual terhadap file yang ditemukan
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['file' => ['File must be an image (jpg, jpeg, png) or PDF.']]
                ], 422);
            }

            if ($file->getSize() > 10240 * 1024) { // 10MB
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['file' => ['File size exceeds 10MB limit.']]
                ], 422);
            }

            $work = CreativeWork::findOrFail($id);
            $episode = $work->episode;

            $mediaFile = $this->fileUploadService->uploadFile(
                $episode,
                $file,
                'image',
                "Storyboard for Episode {$episode->episode_number}"
            );

            // Update storyboard_data with file info
            $storyboardData = $work->storyboard_data ?? [];
            if (!is_array($storyboardData)) {
                $storyboardData = [];
            }
            
            $storyboardData['file_path'] = $mediaFile->file_path;
            $storyboardData['file_name'] = $mediaFile->file_name;
            $storyboardData['file_url'] = $mediaFile->file_url;
            $storyboardData['uploaded_at'] = now()->toDateTimeString();

            $work->update([
                'storyboard_data' => $storyboardData
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'createdBy']),
                    'media_file' => $mediaFile
                ],
                'message' => 'Storyboard uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading storyboard: ' . $e->getMessage()
            ], 500);
        }
    }
}














