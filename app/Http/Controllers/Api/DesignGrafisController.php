<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DesignGrafisWork;
use App\Models\Episode;
use App\Models\ProduksiWork;
use App\Models\PromotionWork;
use App\Models\Notification;
use App\Helpers\FileUploadHelper;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use App\Services\WorkAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DesignGrafisController extends Controller
{
    /**
     * Get all design grafis works
     * GET /api/live-tv/design-grafis/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $allowedRoles = ['graphic design', 'graphic designer', 'design grafis', 'graphis design'];

            if (!$user || !in_array($role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = DesignGrafisWork::with(['episode.program', 'createdBy', 'reviewedBy']);

            // If it's a Graphic Designer role, show works assigned to them OR works with no assignment (pending)
            // Unless 'my_works' is specifically requested
            if (!$request->boolean('all_works', false)) {
                $query->where(function($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereNull('assigned_to');
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $statuses = explode(',', $request->status);
                if (count($statuses) > 1) {
                    $query->whereIn('status', $statuses);
                } else {
                    $query->where('status', $request->status);
                }
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Show only current user's assigned works
            if ($request->boolean('my_works', false)) {
                $query->where('assigned_to', $user->id);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Design grafis works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving design grafis works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new design grafis work
     * POST /api/live-tv/design-grafis/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:thumbnail_youtube,thumbnail_bts,graphics_ig,graphics_facebook,banner_website',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:5000',
                'design_brief' => 'nullable|string|max:5000',
                'brand_guidelines' => 'nullable|string|max:5000',
                'color_scheme' => 'nullable|string|max:100',
                'dimensions' => 'nullable|string|max:100',
                'file_format' => 'nullable|string|max:50',
                'deadline' => 'nullable|date',
                'platform' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get episode details for auto-assignment logic
            $episode = Episode::with('program')->findOrFail($request->episode_id);

            // AUTO-ASSIGNMENT LOGIC: Use WorkAssignmentService to determine assignee
            $assignedUserId = WorkAssignmentService::getNextAssignee(
                DesignGrafisWork::class,
                $episode->program_id,
                $episode->episode_number,
                $request->work_type,  // Work type filter
                $user->id
            );

            $work = DesignGrafisWork::create([
                'episode_id' => $request->episode_id,
                'work_type' => $request->work_type,
                'title' => $request->title,
                'description' => $request->description,
                'design_brief' => $request->design_brief,
                'brand_guidelines' => $request->brand_guidelines,
                'color_scheme' => $request->color_scheme,
                'dimensions' => $request->dimensions,
                'file_format' => $request->file_format,
                'deadline' => $request->deadline,
                'platform' => $request->platform,
                'status' => 'draft',
                'created_by' => $assignedUserId,           // AUTO-ASSIGNED
                'originally_assigned_to' => null,           // Reset
                'was_reassigned' => false                   // Reset
            ]);

            // Auto-fetch source files from Produksi and Promosi
            $this->fetchSourceFiles($work);

            // Audit logging
            ControllerSecurityHelper::logCreate($work, $request->all(), $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Design grafis work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating design grafis work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific design grafis work
     * GET /api/live-tv/design-grafis/works/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = DesignGrafisWork::with(['episode.program', 'createdBy', 'reviewedBy'])->findOrFail($id);

            // Refresh and save latest source files from Produksi and Promosi
            $this->fetchSourceFiles($work);
            $work->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work,
                    'source_files' => $work->source_files // Use refreshed data
                ],
                'message' => 'Design grafis work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving design grafis work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept work
     * POST /api/live-tv/design-grafis/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if (!in_array($work->status, ['draft', 'designing'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be accepted. Current status: {$work->status}"
                ], 400);
            }

            // Fetch latest source files
            $this->fetchSourceFiles($work);

            $work->update([
                'status' => 'in_progress',
                'assigned_to' => $user->id
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;

            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'design_grafis_work_accepted',
                    'title' => 'Design Grafis Work Accepted',
                    'message' => "Design Grafis {$user->name} telah menerima pekerjaan untuk Episode {$episode->episode_number}. Work type: {$work->work_type}",
                    'data' => [
                        'design_grafis_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'work_type' => $work->work_type,
                        'designer_id' => $user->id
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], ['status' => 'in_progress', 'created_by' => $user->id], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. Source files have been fetched. You can now proceed with design work.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shared files from Produksi and Promosi
     * GET /api/live-tv/design-grafis/shared-files
     */
    public function getSharedFiles(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episodeId = $request->episode_id;
            $sourceFiles = $this->getSourceFiles($episodeId);

            return response()->json([
                'success' => true,
                'data' => $sourceFiles,
                'message' => 'Shared files retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving shared files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload thumbnail YouTube
     * POST /api/live-tv/design-grafis/works/{id}/upload-thumbnail-youtube
     */
    public function uploadThumbnailYouTube(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_link' => 'required|url',
                'design_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }


            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if ($work->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->work_type !== 'thumbnail_youtube') {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is for YouTube thumbnail only. Work type: ' . $work->work_type
                ], 400);
            }

            $allowedStatuses = ['in_progress', 'completed', 'reviewed', 'revision_needed'];
            if (!in_array($work->status, $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Work must be in progress or under review to upload thumbnail. Current status: {$work->status}"
                ], 400);
            }

            // Link-based implementation (Strict Link Only)
            $uploadedFile = [
                'file_path' => $request->file_link,
                'file_name' => 'External Link',
                'file_size' => 0,
                'mime_type' => 'url',
                'original_name' => 'External Link'
            ];
            
            // Delete old physical file if exists
            if ($work->file_path && !filter_var($work->file_path, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($work->file_path)) {
                Storage::disk('public')->delete($work->file_path);
            }

            // Update file paths (support multiple files)
            $filePaths = $work->file_paths ?? [];
            if ($uploadedFile) {
                $filePaths[] = array_merge($uploadedFile, [
                    'type' => 'thumbnail_youtube',
                    'uploaded_at' => now()->toDateTimeString(),
                    'uploaded_by' => $user->id
                ]);

                $work->update([
                    'file_path' => $uploadedFile['file_path'], // Main file path
                    'file_name' => $uploadedFile['file_name'],
                    'file_size' => $uploadedFile['file_size'],
                    'mime_type' => $uploadedFile['mime_type'],
                    'file_paths' => $filePaths,
                    'design_notes' => ($work->design_notes ? $work->design_notes . "\n\n" : '') .
                        "[YouTube Thumbnail Uploaded/Linked - " . now()->format('Y-m-d H:i:s') . "]\n" .
                        ($request->design_notes ?? '')
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logFileOperation(
                'upload',
                $uploadedFile['mime_type'],
                $uploadedFile['original_name'] ?? 'External Link',
                $uploadedFile['file_size'],
                $work,
                $request
            );

            // Automate task completion and QC setup
            $this->finalizeCompletion($work, $user, $request->design_notes ?? '');

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'YouTube thumbnail uploaded and task completed successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading YouTube thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload thumbnail BTS
     * POST /api/live-tv/design-grafis/works/{id}/upload-thumbnail-bts
     */
    public function uploadThumbnailBTS(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_link' => 'required|url',
                'design_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Link-only policy enforced

            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if ($work->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->work_type !== 'thumbnail_bts') {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is for BTS thumbnail only. Work type: ' . $work->work_type
                ], 400);
            }

            $allowedStatuses = ['in_progress', 'completed', 'reviewed', 'revision_needed'];
            if (!in_array($work->status, $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Work must be in progress or under review to upload thumbnail. Current status: {$work->status}"
                ], 400);
            }

            // Link-based implementation (Strict Link Only)
            $uploadedFile = [
                'file_path' => $request->file_link,
                'file_name' => 'External Link',
                'file_size' => 0,
                'mime_type' => 'url',
                'original_name' => 'External Link'
            ];

            // Delete old physical file if exists
            if ($work->file_path && !filter_var($work->file_path, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($work->file_path)) {
                Storage::disk('public')->delete($work->file_path);
            }

            // Update file paths (support multiple files)
            $filePaths = $work->file_paths ?? [];
            if ($uploadedFile) {
                $filePaths[] = array_merge($uploadedFile, [
                    'type' => 'thumbnail_bts',
                    'uploaded_at' => now()->toDateTimeString(),
                    'uploaded_by' => $user->id
                ]);

                $work->update([
                    'file_path' => $uploadedFile['file_path'], // Main file path
                    'file_name' => $uploadedFile['file_name'],
                    'file_size' => $uploadedFile['file_size'],
                    'mime_type' => $uploadedFile['mime_type'],
                    'file_paths' => $filePaths,
                    'design_notes' => ($work->design_notes ? $work->design_notes . "\n\n" : '') .
                        "[BTS Thumbnail Uploaded/Linked - " . now()->format('Y-m-d H:i:s') . "]\n" .
                        ($request->design_notes ?? '')
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logFileOperation(
                'upload',
                $uploadedFile['mime_type'],
                $uploadedFile['original_name'] ?? 'External Link',
                $uploadedFile['file_size'],
                $work,
                $request
            );

            // Automate task completion and QC setup
            $this->finalizeCompletion($work, $user, $request->design_notes ?? '');

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'BTS thumbnail uploaded and task completed successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading BTS thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update design grafis work
     * PUT /api/live-tv/design-grafis/works/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if ($work->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:5000',
                'design_brief' => 'nullable|string|max:5000',
                'design_notes' => 'nullable|string|max:5000',
                'design_specifications' => 'nullable|string|max:5000',
                'color_scheme' => 'nullable|string|max:100',
                'dimensions' => 'nullable|string|max:100',
                'file_format' => 'nullable|string|max:50',
                'deadline' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldData = $work->toArray();
            $updateData = $request->only([
                'title',
                'description',
                'design_brief',
                'design_notes',
                'design_specifications',
                'color_scheme',
                'dimensions',
                'file_format',
                'deadline'
            ]);

            if (!empty($updateData)) {
                $work->update($updateData);
            }

            // Audit logging
            if (!empty($updateData)) {
                ControllerSecurityHelper::logUpdate($work, $oldData, $updateData, $request);
            }

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Design grafis work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating design grafis work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete work
     * POST /api/live-tv/design-grafis/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'completion_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if ($work->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be completed. Current status: {$work->status}"
                ], 400);
            }

            // Validate if thumbnail has been uploaded
            if (!$work->file_path && (!$work->file_paths || empty($work->file_paths))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload thumbnail file before completing work.'
                ], 400);
            }

            // Automate completion logic
            $this->finalizeCompletion($work, $user, $request->completion_notes);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Producer and Quality Control have been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload files (generic)
     * POST /api/live-tv/design-grafis/works/{id}/upload-files
     */
    public function uploadFiles(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'files' => 'nullable|array|min:1',
                'files.*' => 'required_with:files|file|mimes:jpg,jpeg,png,webp,psd,ai|max:10240', // Max 10MB per file
                'file_links' => 'nullable|array|min:1',
                'file_links.*' => 'required_with:file_links|url',
                'design_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$request->file_links) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['file_links' => ['Please provide at least one file link.']]
                ], 422);
            }

            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if ($work->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Physical file upload processing removed

            // Process file links
            $uploadedFiles = [];
            $filePaths = $work->file_paths ?? [];
            if ($request->file_links) {
                foreach ($request->file_links as $link) {
                    $linkData = [
                        'file_path' => $link,
                        'file_name' => 'External Link',
                        'file_size' => 0,
                        'mime_type' => 'url',
                        'original_name' => 'External Link',
                        'type' => $work->work_type,
                        'uploaded_at' => now()->toDateTimeString(),
                        'uploaded_by' => $user->id
                    ];
                    $filePaths[] = $linkData;
                    $uploadedFiles[] = $linkData;
                }
            }

            // Update main file path if empty or set to first uploaded file
            if (!$work->file_path && !empty($uploadedFiles)) {
                $work->update([
                    'file_path' => $uploadedFiles[0]['file_path'],
                    'file_name' => $uploadedFiles[0]['file_name'],
                    'file_size' => $uploadedFiles[0]['file_size'],
                    'mime_type' => $uploadedFiles[0]['mime_type']
                ]);
            }

            $work->update([
                'file_paths' => $filePaths,
                'design_notes' => ($work->design_notes ? $work->design_notes . "\n\n" : '') .
                    "[Files Uploaded/Linked - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->design_notes ?? '')
            ]);

            // Audit logging
            // Physical file audit logging removed
            if ($request->file_links) {
                ControllerSecurityHelper::logFileOperation(
                    'upload',
                    'url',
                    'External links (' . count($request->file_links) . ')',
                    0,
                    $work,
                    $request
                );
            }

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'createdBy']),
                    'uploaded_files' => $uploadedFiles
                ],
                'message' => 'Files uploaded successfully. File paths have been saved to system.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit to QC (optional - jika diperlukan)
     * POST /api/live-tv/design-grafis/works/{id}/submit-to-qc
     */
    public function submitToQC(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Graphic Design', 'Graphic Designer', 'Design Grafis', 'Graphis Design'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = DesignGrafisWork::with(['episode'])->findOrFail($id);

            if ($work->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Allow submit / resubmit to QC from several end-states
            $allowedStatuses = ['completed', 'reviewed', 'revision_needed'];
            if (!in_array($work->status, $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Work must be in one of the following statuses before submitting to QC: completed, reviewed, revision_needed. Current status: {$work->status}"
                ], 400);
            }

            // Map work_type to qc_type
            $qcTypeMap = [
                'thumbnail_youtube' => 'thumbnail_yt',
                'thumbnail_bts' => 'thumbnail_bts'
            ];

            $qcType = $qcTypeMap[$work->work_type] ?? 'thumbnail_yt'; // Default fallback

            // Check if QC work already exists for this design grafis work
            $existingQCWork = \App\Models\QualityControlWork::where('episode_id', $work->episode_id)
                ->where('qc_type', $qcType)
                ->where('status', '!=', 'approved')
                ->first();

            if (!$existingQCWork) {
                // Auto-create QualityControlWork
                $qcUsers = \App\Models\User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                if ($qcUsers->isNotEmpty()) {
                    // Build file list from current Design Grafis work (supports multi-link)
                    $designFiles = $this->prepareFilesToCheck($work);

                    $qcWork = \App\Models\QualityControlWork::create([
                        'episode_id' => $work->episode_id,
                        'qc_type' => $qcType,
                        'title' => "QC {$work->title}",
                        'description' => "Quality Control untuk {$work->work_type} dari Design Grafis",
                        'files_to_check' => $designFiles,
                        'design_grafis_file_locations' => $designFiles,
                        'status' => 'pending',
                        'created_by' => $qcUsers->first()->id
                    ]);

                    // Notify QC users and related managers
                    $notifications = [];
                    $now = now();
                    foreach ($qcUsers as $qcUser) {
                        $notifications[] = [
                            'user_id' => $qcUser->id,
                            'type' => 'design_grafis_submitted_to_qc',
                            'title' => 'Design Grafis Work Submitted to QC',
                            'message' => "Design Grafis telah mengajukan {$work->work_type} untuk QC Episode {$work->episode->episode_number}.",
                            'data' => json_encode([
                                'design_grafis_work_id' => $work->id,
                                'qc_work_id' => $qcWork->id,
                                'episode_id' => $work->episode_id,
                                'work_type' => $work->work_type,
                                'qc_type' => $qcType
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    if (!empty($notifications)) {
                        Notification::insert($notifications);
                    }
                }
            } else {
                // Update existing QC work with latest design grafis files (support resubmit)
                $existingDesignFiles = $existingQCWork->design_grafis_file_locations ?? [];
                if (is_string($existingDesignFiles)) {
                    $decoded = json_decode($existingDesignFiles, true);
                    $existingDesignFiles = is_array($decoded) ? $decoded : [];
                }

                $existingFilesToCheck = $existingQCWork->files_to_check ?? [];
                if (is_string($existingFilesToCheck)) {
                    $decodedFiles = json_decode($existingFilesToCheck, true);
                    $existingFilesToCheck = is_array($decodedFiles) ? $decodedFiles : [];
                }

                $newLocations = $this->prepareFilesToCheck($work);

                $mergedDesignFiles = array_merge($existingDesignFiles, $newLocations);
                $mergedFilesToCheck = array_merge($existingFilesToCheck, $newLocations);

                $existingQCWork->update([
                    'design_grafis_file_locations' => $mergedDesignFiles,
                    'files_to_check' => $mergedFilesToCheck,
                    'status' => 'pending' // Reset to pending for re-review
                ]);

                // Notify QC users and related managers
                $qcUsers = \App\Models\User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                $notifications = [];
                $now = now();

                foreach ($qcUsers as $qcUser) {
                    $notifications[] = [
                        'user_id' => $qcUser->id,
                        'type' => 'design_grafis_resubmitted_to_qc',
                        'title' => 'Design Grafis Work Resubmitted to QC',
                        'message' => "Design Grafis telah mengajukan ulang {$work->work_type} untuk QC Episode {$work->episode->episode_number}.",
                        'data' => json_encode([
                            'design_grafis_work_id' => $work->id,
                            'qc_work_id' => $existingQCWork->id,
                            'episode_id' => $work->episode_id,
                            'work_type' => $work->work_type
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notifications)) {
                    Notification::insert($notifications);
                }
            }

            // Update status
            $work->update([
                'status' => 'reviewed' // Status reviewed, menunggu QC
            ]);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work submitted to QC successfully. QC team has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting to QC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics
     * GET /api/live-tv/design-grafis/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $role = strtolower($user->role ?? '');
            $allowedRoles = ['graphic design', 'graphic designer', 'design grafis', 'graphis design'];

            if (!$user || !in_array($role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $userId = $user->id;

            // Statiskit untuk pekerjaan yang di-assign ke user ini
            $statusStats = DesignGrafisWork::where('assigned_to', $userId)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $typeStats = DesignGrafisWork::where('assigned_to', $userId)
                ->selectRaw('work_type, count(*) as count')
                ->groupBy('work_type')
                ->pluck('count', 'work_type');

            // Hitung juga yang belum di-assign (untuk Inbox)
            $pendingUnassigned = DesignGrafisWork::whereNull('assigned_to')
                ->whereIn('status', ['draft', 'pending'])
                ->count();

            $stats = [
                'total_works' => $statusStats->sum(),
                'pending_tasks' => $pendingUnassigned, // Digunakan untuk Inbox alert
                'draft' => $statusStats->get('draft', 0),
                'in_progress' => $statusStats->get('in_progress', 0), // Status correct from model
                'completed' => $statusStats->get('completed', 0),
                'pending_approval' => $statusStats->get('reviewed', 0), // Map reviewed to pending_approval
                'approved' => $statusStats->get('approved', 0),
                'by_work_type' => [
                    'thumbnail_youtube' => $typeStats->get('thumbnail_youtube', 0),
                    'thumbnail_bts' => $typeStats->get('thumbnail_bts', 0),
                    'graphics_ig' => $typeStats->get('graphics_ig', 0),
                    'graphics_facebook' => $typeStats->get('graphics_facebook', 0),
                    'banner_website' => $typeStats->get('banner_website', 0)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch source files from Produksi and Promosi
     */
    private function fetchSourceFiles(DesignGrafisWork $work): void
    {
        $sourceFiles = $this->getSourceFiles($work->episode_id);
        $work->update(['source_files' => $sourceFiles]);
    }

    /**
     * Get source files from Produksi and Promosi
     */
    private function getSourceFiles(int $episodeId): array
    {
        $sourceFiles = [
            'produksi_files' => [],
            'promosi_files' => [],
            'fetched_at' => now()->toDateTimeString()
        ];

        // Get files from Produksi
        $produksiWork = ProduksiWork::where('episode_id', $episodeId)
            ->whereIn('status', ['in_progress', 'completed'])
            ->first();

        if ($produksiWork && (!empty($produksiWork->shooting_files) || !empty($produksiWork->shooting_file_links))) {
            $links = $produksiWork->shooting_file_links;
            if (is_string($links)) {
                $links = explode(',', $links);
            }

            // Ensure we return an array of objects for the frontend if it was just primitive strings
            $formattedLinks = [];
            if (is_array($links)) {
                foreach ($links as $link) {
                    if (is_string($link)) {
                        $formattedLinks[] = [
                            'url' => $link,
                            'label' => 'Shooting Result',
                            'type' => 'video'
                        ];
                    } else {
                        $formattedLinks[] = $link;
                    }
                }
            }

            $sourceFiles['produksi_files'] = [
                'produksi_work_id' => $produksiWork->id,
                'files' => $produksiWork->shooting_files,
                'file_links' => $formattedLinks,
                'status' => $produksiWork->status,
                'available' => true
            ];
        } else {
            $sourceFiles['produksi_files'] = [
                'available' => false,
                'message' => 'Production files not available yet'
            ];
        }

        // Get files from Promosi (talent photos & BTS reference)
        $promotionWorks = PromotionWork::where('episode_id', $episodeId)
            ->whereIn('status', ['editing', 'review', 'approved', 'published', 'completed'])
            ->get();

        $talentPhotos = [];
        $btsVideos = [];

        foreach ($promotionWorks as $work) {
            // Check file_links
            $links = is_string($work->file_links) ? json_decode($work->file_links, true) : ($work->file_links ?? []);
            if (is_array($links)) {
                foreach ($links as $link) {
                    if (is_array($link)) {
                        $type = $link['type'] ?? 'other';
                        if ($type === 'talent_photo') {
                            $talentPhotos[] = $link;
                        } elseif ($type === 'bts_video') {
                            $btsVideos[] = $link;
                        } else {
                            // Fallback for other linked files from promotion
                            $talentPhotos[] = array_merge(['type' => 'other_promotion_file'], $link);
                        }
                    } elseif (is_string($link)) {
                        $talentPhotos[] = [
                            'file_link' => $link,
                            'type' => 'promotion_link',
                            'label' => 'Promotion Link'
                        ];
                    }
                }
            }

            // Check file_paths
            $paths = is_string($work->file_paths) ? json_decode($work->file_paths, true) : ($work->file_paths ?? []);
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (is_array($path)) {
                        $type = $path['type'] ?? 'other';
                        if ($type === 'talent_photo') {
                            $talentPhotos[] = $path;
                        } elseif ($type === 'bts_video') {
                            $btsVideos[] = $path;
                        } else {
                            $talentPhotos[] = array_merge(['type' => 'other_promotion_file'], $path);
                        }
                    }
                }
            }
            
            // Legacy fallbacks
            if ($work->bts_video_link) $btsVideos[] = ['file_link' => $work->bts_video_link, 'url' => $work->bts_video_link, 'type' => 'bts_video', 'source' => 'legacy'];
            if ($work->bts_video_path) $btsVideos[] = ['file_path' => $work->bts_video_path, 'type' => 'bts_video', 'source' => 'legacy'];
        }

        if (!empty($talentPhotos) || !empty($btsVideos)) {
            $sourceFiles['promosi_files'] = [
                'talent_photos' => $talentPhotos,
                'bts_videos' => $btsVideos,
                'available' => true
            ];
        } else {
            $sourceFiles['promosi_files'] = [
                'available' => false,
                'message' => 'Promotion materials (photos/BTS) not available yet'
            ];
        }

        return $sourceFiles;
    }

    /**
     * Helper to prepare files_to_check array from DesignGrafisWork
     */
    private function prepareFilesToCheck($work)
    {
        $files = [];

        // Priority 1: Use file_paths array if available (contains multiple files/links)
        if (!empty($work->file_paths) && is_array($work->file_paths)) {
            foreach ($work->file_paths as $file) {
                // Handle both array structure from uploadFiles and potential raw strings
                $path = is_array($file) ? ($file['file_path'] ?? null) : $file;
                $name = is_array($file) ? ($file['file_name'] ?? 'Attached File') : 'Attached File';
                $isLink = is_array($file) ? ($file['mime_type'] === 'url') : false;

                if ($path) {
                    $files[] = [
                        'design_grafis_work_id' => $work->id,
                        'file_path' => $path,
                        'file_name' => $name,
                        'work_type' => $work->work_type,
                        'is_link' => $isLink,
                        'submitted_at' => now()->toDateTimeString()
                    ];
                }
            }
        }

        // Priority 2: Fallback to single file_path if files array is empty but file_path exists
        if (empty($files) && $work->file_path) {
            $files[] = [
                'design_grafis_work_id' => $work->id,
                'file_path' => $work->file_path,
                'file_name' => $work->file_name,
                'work_type' => $work->work_type,
                'is_link' => false,
                'submitted_at' => now()->toDateTimeString()
            ];
        }

        return $files;
    }
    /**
     * Finalize task completion: update status, notify producer, and setup QC.
     */
    private function finalizeCompletion($work, $user, $notes = '')
    {
        // Update work status and notes
        $work->update([
            'status' => 'completed',
            'design_notes' => ($work->design_notes ? $work->design_notes . "\n\n" : '') .
                "[Completed - " . now()->format('Y-m-d H:i:s') . "]\n" .
                ($notes ?? '')
        ]);

        // Notify Producer
        $episode = $work->episode;
        if ($episode) {
            $productionTeam = $episode->program->productionTeam ?? null;
            $producer = $productionTeam ? $productionTeam->producer : null;

            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'design_grafis_work_completed',
                    'title' => 'Design Grafis Work Completed',
                    'message' => "Design Grafis {$user->name} telah menyelesaikan pekerjaan {$work->work_type} untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'design_grafis_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'work_type' => $work->work_type,
                        'file_path' => $work->file_path,
                        'completion_notes' => $notes
                    ]
                ]);
            }
        }

        // Audit logging
        ControllerSecurityHelper::logUpdate($work, [], ['status' => 'completed'], request());

        // Create Quality Control Work
        $qcTypeMap = [
            'thumbnail_youtube' => 'thumbnail_yt',
            'thumbnail_bts' => 'thumbnail_bts',
            'graphics_ig' => 'highlight_ig',
            'graphics_facebook' => 'highlight_facebook'
        ];

        if (isset($qcTypeMap[$work->work_type])) {
            $qcType = $qcTypeMap[$work->work_type];

            // Check if QC work already exists
            $existingQCWork = \App\Models\QualityControlWork::where('episode_id', $work->episode_id)
                ->where('qc_type', $qcType)
                ->first();

            if (!$existingQCWork) {
                $qcUsers = \App\Models\User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();

                $qcWork = \App\Models\QualityControlWork::create([
                    'episode_id' => $work->episode_id,
                    'qc_type' => $qcType,
                    'title' => "QC {$work->title}",
                    'description' => "Quality Control untuk {$work->work_type} dari Design Grafis",
                    'files_to_check' => $this->prepareFilesToCheck($work),
                    'design_grafis_file_locations' => $this->prepareFilesToCheck($work),
                    'status' => 'pending',
                    'created_by' => $qcUsers->isNotEmpty() ? $qcUsers->first()->id : $user->id
                ]);

                // Notify QC
                foreach ($qcUsers as $qcUser) {
                    Notification::create([
                        'user_id' => $qcUser->id,
                        'type' => 'design_grafis_submitted_to_qc',
                        'title' => 'Design Grafis Work Submitted to QC',
                        'message' => "Design Grafis telah mengajukan {$work->work_type} untuk QC Episode {$episode->episode_number}.",
                        'data' => [
                            'design_grafis_work_id' => $work->id,
                            'qc_work_id' => $qcWork->id,
                            'episode_id' => $work->episode_id,
                            'work_type' => $work->work_type,
                            'qc_type' => $qcType
                        ]
                    ]);
                }
            } else {
                // Update existing QC work
                $existingLocations = $existingQCWork->design_grafis_file_locations ?? [];
                $newLocations = $this->prepareFilesToCheck($work);

                // Merge and unique based on file_path
                $existingLocations = array_merge($existingLocations, $newLocations);

                // Update files_to_check as well
                $existingFilesToCheck = $existingQCWork->files_to_check ?? [];
                $existingFilesToCheck = array_merge($existingFilesToCheck, $newLocations);

                $existingQCWork->update([
                    'design_grafis_file_locations' => $existingLocations,
                    'status' => 'pending'
                ]);
            }
        }
    }
}
