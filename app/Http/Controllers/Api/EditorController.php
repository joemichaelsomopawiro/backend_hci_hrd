<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditorWork;
use App\Models\Episode;
use App\Models\ProduksiWork;
use App\Models\ShootingRunSheet;
use App\Models\SoundEngineerEditing;
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

class EditorController extends Controller
{
    /**
     * Get all editor works
     * GET /api/live-tv/editor/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = EditorWork::with(['episode.program', 'createdBy', 'reviewedBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Show only current user's works
            if ($request->boolean('my_works', false)) {
                $query->where('created_by', $user->id);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Editor works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving editor works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new editor work
     * POST /api/live-tv/editor/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:main_episode,bts,highlight_ig,highlight_tv,highlight_facebook,advertisement',
                'editing_notes' => 'nullable|string|max:5000',
                'source_files' => 'nullable|array'
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
            // Checks if previous episode's work was reassigned, and reverts to original user if yes
            $assignedUserId = WorkAssignmentService::getNextAssignee(
                EditorWork::class,
                $episode->program_id,
                $episode->episode_number,
                $request->work_type,  // Work type filter
                $user->id             // Fallback to current user
            );

            $work = EditorWork::create([
                'episode_id' => $request->episode_id,
                'work_type' => $request->work_type,
                'editing_notes' => $request->editing_notes,
                'source_files' => $request->source_files,
                'status' => 'draft',
                'file_complete' => false,
                'created_by' => $assignedUserId,         // AUTO-ASSIGNED (may be different from current user)
                'originally_assigned_to' => null,         // Reset for new task
                'was_reassigned' => false                 // Reset for new task
            ]);

            // Audit logging
            ControllerSecurityHelper::logCreate($work, $request->all(), $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Editor work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific editor work
     * GET /api/live-tv/editor/works/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with([
                'episode.program',
                'createdBy',
                'reviewedBy'
            ])->findOrFail($id);

            // Get production files
            $produksiWork = ProduksiWork::where('episode_id', $work->episode_id)
                ->where('status', 'completed')
                ->first();

            // Get approved audio files (from Sound Engineer Editing)
            $approvedAudio = SoundEngineerEditing::where('episode_id', $work->episode_id)
                ->where('status', 'approved')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work,
                    'produksi_files' => $produksiWork ? [
                        'shooting_files' => $produksiWork->shooting_files,
                        'shooting_file_links' => $produksiWork->shooting_file_links,
                        'run_sheet_id' => $produksiWork->run_sheet_id
                    ] : null,
                    'approved_audio' => $approvedAudio ? [
                        'id' => $approvedAudio->id,
                        'final_file_path' => $approvedAudio->final_file_path,
                        'editing_notes' => $approvedAudio->editing_notes
                    ] : null
                ],
                'message' => 'Editor work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept work
     * POST /api/live-tv/editor/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if (!in_array($work->status, ['draft', 'editing'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be accepted. Current status: {$work->status}"
                ], 400);
            }

            $work->update([
                'status' => 'editing',
                'created_by' => $user->id
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_work_accepted',
                    'title' => 'Editor Work Accepted',
                    'message' => "Editor {$user->name} telah menerima pekerjaan editing untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_id' => $user->id
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], ['status' => 'editing', 'created_by' => $user->id], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now check file completeness and proceed with editing.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check file completeness
     * POST /api/live-tv/editor/works/{id}/check-file-completeness
     */
    public function checkFileCompleteness(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Get production files
            $produksiWork = ProduksiWork::where('episode_id', $work->episode_id)
                ->where('status', 'completed')
                ->first();

            // Get approved audio
            $approvedAudio = SoundEngineerEditing::where('episode_id', $work->episode_id)
                ->where('status', 'approved')
                ->first();

            // Check completeness
            $hasProductionFiles = $produksiWork && (!empty($produksiWork->shooting_files) || !empty($produksiWork->shooting_file_links));
            // Check audio from SoundEngineerEditing (support both file_path and file_link)
            $hasAudio = $approvedAudio && (!empty($approvedAudio->final_file_path) || !empty($approvedAudio->final_file_link));
            $isComplete = $hasProductionFiles && $hasAudio;

            // Update work dengan source files lengkap
            $sourceFiles = $work->source_files ?? [];
            $sourceFiles['produksi_files_available'] = $hasProductionFiles;
            $sourceFiles['audio_available'] = $hasAudio;
            $sourceFiles['checked_at'] = now()->toDateTimeString();
            $sourceFiles['checked_by'] = $user->id;
            
            // Jika audio available, tambahkan info audio ke source_files
            if ($hasAudio && $approvedAudio) {
                $sourceFiles['approved_audio'] = [
                    'editing_id' => $approvedAudio->id,
                    'final_file_path' => $approvedAudio->final_file_path, // Backward compatibility
                    'final_file_link' => $approvedAudio->final_file_link, // New: External storage link
                    'editing_notes' => $approvedAudio->editing_notes,
                    'approved_at' => $approvedAudio->approved_at?->toDateTimeString()
                ];
            }
            
            // Jika produksi files available, tambahkan info produksi
            if ($hasProductionFiles && $produksiWork) {
                $sourceFiles['produksi_work'] = [
                    'produksi_work_id' => $produksiWork->id,
                    'shooting_files' => $produksiWork->shooting_files, // Backward compatibility
                    'shooting_file_links' => $produksiWork->shooting_file_links // New: External storage links
                ];
            }
            
            $work->update([
                'file_complete' => $isComplete,
                'source_files' => $sourceFiles
            ]);

            $message = $isComplete 
                ? 'Files are complete. You can proceed with editing.' 
                : 'Files are not complete. Please report missing files to Producer.';

            if (!$isComplete) {
                $missingFiles = [];
                if (!$hasProductionFiles) {
                    $missingFiles[] = 'Production shooting files';
                }
                if (!$hasAudio) {
                    $missingFiles[] = 'Approved audio file from Sound Engineer';
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'work' => $work->fresh(['episode']),
                        'file_complete' => false,
                        'missing_files' => $missingFiles,
                        'has_production_files' => $hasProductionFiles,
                        'has_audio' => $hasAudio
                    ],
                    'message' => $message
                ]);
            }

            // If complete, automatically proceed to process work
            $work->update(['status' => 'editing']);

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode']),
                    'file_complete' => true,
                    'has_production_files' => $hasProductionFiles,
                    'has_audio' => $hasAudio
                ],
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking file completeness: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report missing files to Producer
     * POST /api/live-tv/editor/works/{id}/report-missing-files
     */
    public function reportMissingFiles(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'missing_files' => 'required|array|min:1',
                'missing_files.*.file_type' => 'required|string',
                'missing_files.*.description' => 'required|string|max:1000',
                'missing_files.*.notes' => 'nullable|string|max:2000',
                'notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Update work with missing files report
            $work->update([
                'file_notes' => ($work->file_notes ? $work->file_notes . "\n\n" : '') . 
                    "[Missing Files Report - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->notes ?? '') . "\n" .
                    json_encode($request->missing_files, JSON_PRETTY_PRINT),
                'file_complete' => false,
                'status' => 'editing' // Tetap editing, tapi file tidak lengkap
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_missing_files_reported',
                    'title' => 'Editor Melaporkan File Kurang',
                    'message' => "Editor {$user->name} melaporkan file yang kurang atau perlu perbaikan untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'missing_files' => $request->missing_files,
                        'notes' => $request->notes,
                        'editor_id' => $user->id
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], [
                'file_notes' => $work->file_notes,
                'file_complete' => false
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Missing files reported to Producer successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting missing files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process work (start editing)
     * POST /api/live-tv/editor/works/{id}/process-work
     */
    public function processWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'editing') {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be processed. Current status: {$work->status}. Please accept work first."
                ], 400);
            }

            if (!$work->file_complete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Files are not complete. Please check file completeness or report missing files first.'
                ], 400);
            }

            // Update status (already editing, but this confirms processing has started)
            $work->update([
                'status' => 'editing',
                'editing_notes' => ($work->editing_notes ? $work->editing_notes . "\n\n" : '') .
                    "[Processing Started - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->notes ?? 'Processing started')
            ]);

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], ['status' => 'editing'], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work processing started successfully. You can now proceed with editing.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get run sheet (catatan syuting)
     * GET /api/live-tv/editor/episodes/{id}/run-sheet
     */
    public function getRunSheet(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = Episode::findOrFail($id);

            // Get run sheet from produksi work
            $produksiWork = ProduksiWork::where('episode_id', $episode->id)
                ->where('status', 'completed')
                ->with('runSheet')
                ->first();

            if (!$produksiWork || !$produksiWork->runSheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Run sheet not found for this episode'
                ], 404);
            }

            $runSheet = $produksiWork->runSheet;

            return response()->json([
                'success' => true,
                'data' => [
                    'run_sheet' => $runSheet,
                    'episode' => [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title
                    ],
                    'produksi_work' => [
                        'id' => $produksiWork->id,
                        'status' => $produksiWork->status,
                        'shooting_files' => $produksiWork->shooting_files
                    ]
                ],
                'message' => 'Run sheet retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving run sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update editor work (with file upload support)
     * PUT /api/live-tv/editor/works/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'editing_notes' => 'nullable|string|max:5000',
                'file' => 'nullable|file|mimes:mp4,avi,mov,mkv|max:1024000', // Max 1GB (backward compatibility)
                'file_link' => 'nullable|url|max:2048', // New: External storage link
                'file_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldData = $work->toArray();
            $updateData = [];

            // Handle file upload (backward compatibility)
            if ($request->hasFile('file')) {
                $uploadedFile = FileUploadHelper::validateVideoFile($request->file('file'), 1000); // Max 1GB
                
                // Delete old file if exists
                if ($work->file_path && Storage::disk('public')->exists($work->file_path)) {
                    Storage::disk('public')->delete($work->file_path);
                }

                $updateData = array_merge($updateData, [
                    'file_path' => $uploadedFile['file_path'],
                    'file_name' => $uploadedFile['file_name'],
                    'file_size' => $uploadedFile['file_size'],
                    'mime_type' => $uploadedFile['mime_type']
                ]);

                // Audit logging for file upload
                ControllerSecurityHelper::logFileOperation(
                    'upload',
                    $uploadedFile['mime_type'],
                    $uploadedFile['original_name'],
                    $uploadedFile['file_size'],
                    $work,
                    $request
                );
            }
            
            // Handle file_link (new: external storage link)
            if ($request->has('file_link')) {
                $updateData['file_link'] = $request->file_link;
            }

            // Update other fields
            if ($request->has('editing_notes')) {
                $updateData['editing_notes'] = $request->editing_notes;
            }

            if ($request->has('file_notes')) {
                $updateData['file_notes'] = $request->file_notes;
            }

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
                'message' => $request->hasFile('file') 
                    ? 'File uploaded and work updated successfully. File path has been saved to system.'
                    : 'Work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input file links (manual input)
     * POST /api/live-tv/editor/works/{id}/input-file-links
     */
    public function inputFileLinks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_links' => 'required|array|min:1',
                'file_links.*.url' => 'required|url',
                'file_links.*.file_name' => 'required|string|max:255',
                'file_links.*.file_size' => 'nullable|integer',
                'file_links.*.mime_type' => 'nullable|string|max:100',
                'file_links.*.type' => 'nullable|string|max:50' // video, audio, etc
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Update work with file links
            $existingSourceFiles = $work->source_files ?? [];
            $updateData = [
                'source_files' => array_merge($existingSourceFiles, [
                    'manual_file_links' => $request->file_links,
                    'links_added_at' => now()->toDateTimeString(),
                    'links_added_by' => $user->id
                ])
            ];

            // If no file_path exists, use first link as file_path
            if (!$work->file_path && !empty($request->file_links)) {
                $firstLink = $request->file_links[0];
                $updateData['file_path'] = $firstLink['url'];
                $updateData['file_name'] = $firstLink['file_name'] ?? 'External File';
                $updateData['file_size'] = $firstLink['file_size'] ?? null;
                $updateData['mime_type'] = $firstLink['mime_type'] ?? null;
            }

            $work->update($updateData);

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], $updateData, $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'File links added successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding file links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit work to Producer
     * POST /api/live-tv/editor/works/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'editing' && $work->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be submitted. Current status: {$work->status}"
                ], 400);
            }

            // Validate if file is uploaded or file_link is provided
            if (!$work->file_path && !$work->file_link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload edited file or provide file_link before submitting.'
                ], 400);
            }

            // Update work
            $work->update([
                'status' => 'completed',
                'editing_notes' => ($work->editing_notes ? $work->editing_notes . "\n\n" : '') .
                    "[Submitted - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->submission_notes ?? '')
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_work_submitted',
                    'title' => 'Editor Work Submitted',
                    'message' => "Editor {$user->name} telah submit hasil editing untuk Episode {$episode->episode_number}. Mohon review.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_path' => $work->file_path, // Backward compatibility
                        'file_link' => $work->file_link, // New: External storage link
                        'submission_notes' => $request->submission_notes
                    ]
                ]);
            }

            // Notify Manager Broadcasting and Distribution Manager for QC
            $qcManagers = \App\Models\User::whereIn('role', ['Manager Broadcasting', 'Distribution Manager'])->get();
            foreach ($qcManagers as $qcManager) {
                Notification::create([
                    'user_id' => $qcManager->id,
                    'type' => 'editor_work_submitted_to_qc',
                    'title' => 'Materi Episode Siap QC',
                    'message' => "Editor {$user->name} telah submit hasil editing untuk Episode {$episode->episode_number}. Produk siap untuk dilakukan Quality Control.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_link' => $work->file_link,
                        'submission_notes' => $request->submission_notes
                    ]
                ]);
            }

            // Auto-create PromotionWork for Editor Promosi (multiple work types)
            $editorPromosiWorkTypes = [
                'bts_video' => 'Edit Video BTS',
                'highlight_ig' => 'Buat Highlight Episode IG',
                'highlight_tv' => 'Buat Highlight Episode TV',
                'highlight_facebook' => 'Buat Highlight Episode Facebook',
                'iklan_episode_tv' => 'Edit Iklan Episode TV'
            ];

            $createdPromotionWorks = [];

            foreach ($editorPromosiWorkTypes as $workType => $titlePrefix) {
                $existingPromotionWork = \App\Models\PromotionWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $workType)
                    ->first();

                if (!$existingPromotionWork) {
                    $promotionWork = \App\Models\PromotionWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => $workType,
                        'title' => "{$titlePrefix} - Episode {$episode->episode_number}",
                        'description' => "Editing task untuk {$titlePrefix}. File referensi dari Editor sudah tersedia.",
                        'status' => 'editing', // Siap untuk diterima Editor Promosi
                        'file_paths' => [
                            'editor_work_id' => $work->id,
                            'editor_file_path' => $work->file_path, // Backward compatibility
                            'editor_file_link' => $work->file_link, // New: External storage link
                            'editor_file_name' => $work->file_name,
                            'available' => true,
                            'fetched_at' => now()->toDateTimeString()
                        ],
                        'created_by' => $user->id // Assign ke Editor yang submit (akan diganti saat Editor Promosi accept)
                    ]);
                    $createdPromotionWorks[] = $promotionWork;
                } else {
                    // Update existing PromotionWork dengan file terbaru dari Editor
                    $existingFilePaths = $existingPromotionWork->file_paths ?? [];
                    $existingPromotionWork->update([
                        'file_paths' => array_merge($existingFilePaths, [
                            'editor_work_id' => $work->id,
                            'editor_file_path' => $work->file_path, // Backward compatibility
                            'editor_file_link' => $work->file_link, // New: External storage link
                            'editor_file_name' => $work->file_name,
                            'updated_at' => now()->toDateTimeString()
                        ])
                    ]);
                    $createdPromotionWorks[] = $existingPromotionWork;
                }
            }

            // Notify Editor Promosi - File dari Editor sudah tersedia
            $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
            $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
            $promosiNotifications = [];
            $now = now();

            foreach ($editorPromosiUsers as $editorPromosiUser) {
                $promosiNotifications[] = [
                    'user_id' => $editorPromosiUser->id,
                    'type' => 'editor_files_available',
                    'title' => 'File Editor Tersedia',
                    'message' => "Editor telah submit hasil editing untuk Episode {$episode->episode_number}. PromotionWork untuk BTS, Highlight, dan Iklan TV sudah dibuat.",
                    'data' => json_encode([
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_file_path' => $work->file_path, // Backward compatibility
                        'editor_file_link' => $work->file_link, // New: External storage link
                        'promotion_works' => array_map(function($pw) {
                            return [
                                'id' => $pw->id,
                                'work_type' => $pw->work_type,
                                'title' => $pw->title
                            ];
                        }, $createdPromotionWorks)
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            if (!empty($promosiNotifications)) {
                Notification::insert($promosiNotifications);
            }

            // Auto-create QualityControlWork untuk QC
            $existingQCWork = \App\Models\QualityControlWork::where('episode_id', $work->episode_id)
                ->where('qc_type', 'main_episode')
                ->first();

            if (!$existingQCWork) {
                $qcWork = \App\Models\QualityControlWork::create([
                    'episode_id' => $work->episode_id,
                    'qc_type' => 'main_episode',
                    'title' => "QC Work - Episode {$episode->episode_number}",
                    'description' => "File dari Editor untuk QC. Editor telah submit hasil editing.",
                    'files_to_check' => [
                        [
                            'editor_work_id' => $work->id,
                            'file_path' => $work->file_path, // Backward compatibility
                            'file_link' => $work->file_link, // New: External storage link
                            'file_name' => $work->file_name,
                            'file_size' => $work->file_size,
                            'mime_type' => $work->mime_type,
                            'source' => 'editor',
                            'submitted_at' => now()->toDateTimeString()
                        ]
                    ],
                    'status' => 'pending',
                    'created_by' => $user->id // Assign ke Editor yang submit (akan diganti saat QC accept)
                ]);

                // Notify Quality Control and related managers
                $qcUsers = \App\Models\User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                $qcNotifications = [];
                $now = now();

                foreach ($qcUsers as $qcUser) {
                    $qcNotifications[] = [
                        'user_id' => $qcUser->id,
                        'type' => 'qc_work_assigned',
                        'title' => 'Tugas QC Baru',
                        'message' => "Editor telah mengajukan file untuk QC Episode {$episode->episode_number}.",
                        'data' => json_encode([
                            'qc_work_id' => $qcWork->id,
                            'episode_id' => $work->episode_id,
                            'editor_work_id' => $work->id,
                            'file_path' => $work->file_path, // Backward compatibility
                            'file_link' => $work->file_link // New: External storage link
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($qcNotifications)) {
                    Notification::insert($qcNotifications);
                }
            } else {
                // Update existing QCWork dengan file terbaru dari Editor
                $existingFiles = $existingQCWork->files_to_check ?? [];
                $existingFiles[] = [
                    'editor_work_id' => $work->id,
                    'file_path' => $work->file_path, // Backward compatibility
                    'file_link' => $work->file_link, // New: External storage link
                    'file_name' => $work->file_name,
                    'file_size' => $work->file_size,
                    'mime_type' => $work->mime_type,
                    'source' => 'editor',
                    'updated_at' => now()->toDateTimeString()
                ];
                $existingQCWork->update(['files_to_check' => $existingFiles]);

                // Notify QC jika status masih pending
                if ($existingQCWork->status === 'pending') {
                    $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
                    $qcNotifications = [];
                    $now = now();

                    foreach ($qcUsers as $qcUser) {
                        $qcNotifications[] = [
                            'user_id' => $qcUser->id,
                            'type' => 'qc_work_updated',
                            'title' => 'QC Work Diperbarui',
                            'message' => "Editor telah mengupdate file untuk QC Episode {$episode->episode_number}.",
                            'data' => json_encode([
                                'qc_work_id' => $existingQCWork->id,
                                'episode_id' => $work->episode_id,
                                'editor_work_id' => $work->id
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    if (!empty($qcNotifications)) {
                        Notification::insert($qcNotifications);
                    }
                }
            }

            // ✨ PARALLEL NOTIFICATIONS ✨
            // Editor submits work → Notify Producer, Editor Promosi, and QC simultaneously
            $episode = $work->episode;
            $program = $episode->program;

            // 1. Notify Producer (single user)
            $productionTeam = $program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_work_submitted',
                    'title' => 'Editor Work Submitted',
                    'message' => "Editor {$user->name} telah submit hasil editing untuk Episode {$episode->episode_number}. Mohon review.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_path' => $work->file_path,
                        'file_link' => $work->file_link,
                        'submission_notes' => $request->submission_notes
                    ]
                ]);
            }

            // 2. Send PARALLEL notifications to Editor Promosi and QC
            \App\Services\ParallelNotificationService::notifyRoles(
                ['Editor Promotion', 'Quality Control'],
                [
                    'type' => 'editor_submitted_files_available',
                    'title' => 'File Editor Tersedia',
                    'message' => "Editor telah submit hasil editing untuk Episode {$episode->episode_number}. Files ready for review and further processing.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_file_link' => $work->file_link,
                        'submission_notes' => $request->submission_notes
                    ]
                ],
                $program->id
            );

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], [
                'status' => 'completed',
                'parallel_notifications_sent' => 2 // Editor Promosi, QC
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Editor work submitted successfully. 3 notifications sent (Producer, Editor Promosi, QC).'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved audio files for episode
     * GET /api/live-tv/editor/episodes/{episodeId}/approved-audio
     */
    public function getApprovedAudioFiles(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = Episode::findOrFail($episodeId);

            // Get approved audio files from Sound Engineer Editing
            $approvedAudio = SoundEngineerEditing::where('episode_id', $episodeId)
                ->where('status', 'approved')
                ->with(['soundEngineer', 'recording'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title
                    ],
                    'approved_audio_files' => $approvedAudio->map(function($audio) {
                        return [
                            'id' => $audio->id,
                            'final_file_path' => $audio->final_file_path, // Backward compatibility
                            'final_file_link' => $audio->final_file_link, // New: External storage link
                            'editing_notes' => $audio->editing_notes,
                            'submission_notes' => $audio->submission_notes,
                            'approved_at' => $audio->approved_at,
                            'sound_engineer' => $audio->soundEngineer ? [
                                'id' => $audio->soundEngineer->id,
                                'name' => $audio->soundEngineer->name
                            ] : null
                        ];
                    })
                ],
                'message' => 'Approved audio files retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approved audio files: ' . $e->getMessage()
            ], 500);
        }
    }
}
