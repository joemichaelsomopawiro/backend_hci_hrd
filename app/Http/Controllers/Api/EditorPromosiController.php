<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\Episode;
use App\Models\MediaFile;
use App\Models\Notification;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;


class EditorPromosiController extends Controller
{
    /**
     * Get Editor Promosi works for current user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            if ($user->role !== 'Editor Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get all works that are either:
            // 1. Created by current user, OR
            // 2. In draft/planning status (available for acceptance) for any Editor Promosi user
            $query = PromotionWork::with(['episode.program', 'createdBy', 'reviewedBy'])
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereIn('status', ['draft', 'planning']); // Draft/planning works are available for any Editor Promosi to accept
                });

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Editor Promosi works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Editor Promosi works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new Editor Promosi work
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            if ($user->role !== 'Editor Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }


            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:bts_video,bts_photo,highlight_ig,highlight_facebook,highlight_tv,iklan_episode_tv,story_ig,reels_facebook,tiktok,website_content',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'content_plan' => 'nullable|string',
                'talent_data' => 'nullable|array',
                'location_data' => 'nullable|array',
                'equipment_needed' => 'nullable|array',
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable|date_format:H:i',
                'shooting_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::create([
                'episode_id' => $request->episode_id,
                'created_by' => $user->id,
                'work_type' => $request->work_type,
                'title' => $request->title,
                'description' => $request->description,
                'content_plan' => $request->content_plan,
                'talent_data' => $request->talent_data,
                'location_data' => $request->location_data,
                'equipment_needed' => $request->equipment_needed,
                'shooting_date' => $request->shooting_date,
                'shooting_time' => $request->shooting_time,
                'shooting_notes' => $request->shooting_notes,
                'status' => 'planning'
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($work, 'created');

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Editor Promosi work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating Editor Promosi work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Editor Promosi work by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $work = PromotionWork::with(['episode', 'createdBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Editor Promosi work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Editor Promosi work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Editor Promosi work
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id && $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this work.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'content_plan' => 'nullable|string',
                'talent_data' => 'nullable|array',
                'location_data' => 'nullable|array',
                'equipment_needed' => 'nullable|array',
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable|date_format:H:i',
                'shooting_notes' => 'nullable|string',
                'status' => 'sometimes|in:draft,in_progress,completed,approved,rejected'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work->update($request->only([
                'title',
                'description',
                'content_plan',
                'talent_data',
                'location_data',
                'equipment_needed',
                'shooting_date',
                'shooting_time',
                'shooting_notes',
                'status'
            ]));

            // Notify on status change
            if ($request->has('status')) {
                $this->notifyRelatedRoles($work, 'status_changed');
            }

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Editor Promosi work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating Editor Promosi work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload edited content files
     */
    public function uploadFiles(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload files for this work.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'files' => 'nullable|array',
                'files.*' => 'nullable|file|mimes:mp4,avi,mov,jpg,jpeg,png,gif|max:1024000', // 1GB max
                'file_links' => 'nullable|array',
                'file_links.*' => 'nullable|url|max:2048' // Each link must be valid URL
            ]);

            // Require either files or file_links
            if (
                (!$request->hasFile('files') || empty($request->file('files'))) &&
                (!$request->has('file_links') || empty($request->file_links))
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either files or file_links array is required.'
                ], 422);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadedFiles = [];
            $filePaths = $work->file_paths ?? [];

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs("editor_promosi/{$work->id}", $fileName, 'public');

                    $uploadedFiles[] = [
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'uploaded_at' => now()
                    ];

                    $filePaths[] = $filePath;

                    // Create MediaFile record
                    MediaFile::create([
                        'episode_id' => $work->episode_id,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'file_type' => 'editor_promosi',
                        'uploaded_by' => $user->id,
                        'metadata' => [
                            'promotion_work_id' => $work->id,
                            'work_type' => $work->work_type,
                            'original_name' => $file->getClientOriginalName()
                        ]
                    ]);


                }
            }

            // Handle file_links (new: external storage links)
            $fileLinks = $work->file_links ?? [];
            if ($request->has('file_links') && is_array($request->file_links)) {
                foreach ($request->file_links as $link) {
                    $fileLinks[] = [
                        'file_link' => $link,
                        'uploaded_at' => now()->toDateTimeString(),
                        'uploaded_by' => $user->id
                    ];
                }
            }

            // Update work with file paths and links
            $work->update([
                'file_paths' => $filePaths,
                'file_links' => $fileLinks,
                'status' => 'completed'
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($work, 'files_uploaded');

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->load(['episode', 'createdBy']),
                    'uploaded_files' => $uploadedFiles
                ],
                'message' => 'Edited content files uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading edited content files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get source files from Promosi team or Editor (main editor)
     */
    public function getSourceFiles(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            if ($user->role !== 'Editor Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episodeId = $request->get('episode_id');
            $sourceRole = $request->get('source_role'); // 'editor' or 'promotion'

            $query = MediaFile::with(['episode', 'uploadedBy'])
                ->where('episode_id', $episodeId);

            if ($sourceRole === 'editor') {
                // Ambil file dari Editor (main editor)
                $query->where(function ($q) {
                    $q->where('file_type', 'editor')
                        ->orWhere('file_type', 'editor_work');
                })
                    ->whereHas('uploadedBy', function ($q) {
                        $q->where('role', 'Editor');
                    });

                // Juga ambil dari EditorWork jika ada
                $editorWorks = \App\Models\EditorWork::where('episode_id', $episodeId)
                    ->whereIn('status', ['completed', 'approved'])
                    ->get();

                $files = $query->orderBy('created_at', 'desc')->get();

                // Convert EditorWork to file-like structure
                $editorWorkFiles = $editorWorks->map(function ($work) {
                    return [
                        'id' => 'editor_work_' . $work->id,
                        'file_name' => $work->file_name,
                        'file_path' => $work->file_path,
                        'file_size' => $work->file_size,
                        'mime_type' => $work->mime_type,
                        'file_type' => 'editor_work',
                        'work_type' => $work->work_type,
                        'uploaded_by' => $work->created_by,
                        'uploaded_at' => $work->created_at,
                        'episode_id' => $work->episode_id
                    ];
                });

                $allFiles = $files->merge($editorWorkFiles);

            } elseif ($sourceRole === 'promotion' || $sourceRole === 'promosi' || !$sourceRole) { // Support both for backward compatibility
                // Default: Ambil file dari Promotion (BTS)
                $query->where('file_type', 'promotion')
                    ->whereHas('uploadedBy', function ($q) {
                        $q->where('role', 'Promotion');
                    });

                $allFiles = $query->orderBy('created_at', 'desc')->get();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid source_role. Use "editor" or "promotion".'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $allFiles,
                'message' => 'Source files retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving source files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Editor Promosi statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }


            if ($user->role !== 'Editor Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $statusStats = PromotionWork::where('created_by', $user->id)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $stats = [
                'total_works' => $statusStats->sum(),
                'completed_works' => $statusStats->get('completed', 0),
                'in_progress_works' => $statusStats->get('in_progress', 0),
                'pending_works' => $statusStats->get('draft', 0),
                'works_by_type' => PromotionWork::where('created_by', $user->id)
                    ->selectRaw('work_type, count(*) as count')
                    ->groupBy('work_type')
                    ->get(),
                'recent_works' => PromotionWork::where('created_by', $user->id)
                    ->with(['episode.program'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Editor Promosi statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch source files from Editor and Promosi (BTS) for a work
     */
    private function fetchSourceFilesForWork(int $episodeId): array
    {
        $sourceFiles = [
            'editor_files' => [],
            'bts_files' => [],
            'fetched_at' => now()->toDateTimeString()
        ];

        // Get files from Editor (main editor work)
        $editorWorks = \App\Models\EditorWork::where('episode_id', $episodeId)
            ->whereIn('status', ['completed', 'approved'])
            ->get();

        foreach ($editorWorks as $editorWork) {
            if ($editorWork->file_path) {
                $sourceFiles['editor_files'][] = [
                    'editor_work_id' => $editorWork->id,
                    'file_path' => $editorWork->file_path,
                    'file_name' => $editorWork->file_name,
                    'file_size' => $editorWork->file_size,
                    'mime_type' => $editorWork->mime_type,
                    'work_type' => $editorWork->work_type
                ];
            }
        }

        // Get files from Promosi (BTS video dan foto talent)
        $promotionWorks = PromotionWork::where('episode_id', $episodeId)
            ->whereIn('work_type', ['bts_video', 'bts_photo'])
            ->whereIn('status', ['editing', 'review', 'approved', 'published'])
            ->get();

        foreach ($promotionWorks as $promotionWork) {
            if ($promotionWork->file_paths) {
                $filePaths = $promotionWork->file_paths;
                if (is_string($filePaths)) {
                    $filePaths = json_decode($filePaths, true);
                }

                if (!is_array($filePaths)) {
                    $filePaths = [];
                }

                if (is_array($filePaths)) {
                    foreach ($filePaths as $file) {
                        // Filter hanya BTS video (bukan talent photos)
                        if (is_array($file)) {
                            if (isset($file['type']) && $file['type'] === 'bts_video') {
                                $sourceFiles['bts_files'][] = [
                                    'promotion_work_id' => $promotionWork->id,
                                    'work_type' => $promotionWork->work_type,
                                    'file_type' => $file['type'],
                                    'file_path' => $file['file_path'] ?? $file['url'] ?? null,
                                    'file_name' => $file['file_name'] ?? basename($file['file_path'] ?? ''),
                                    'file_size' => $file['file_size'] ?? null,
                                    'mime_type' => $file['mime_type'] ?? null,
                                    'url' => $file['url'] ?? null
                                ];
                            } elseif (isset($file['file_path']) && !isset($file['type'])) {
                                // Fallback: jika tidak ada type tapi ada file_path, assume BTS video
                                $sourceFiles['bts_files'][] = [
                                    'promotion_work_id' => $promotionWork->id,
                                    'work_type' => $promotionWork->work_type,
                                    'file_path' => $file['file_path'],
                                    'file_name' => $file['file_name'] ?? basename($file['file_path']),
                                    'file_size' => $file['file_size'] ?? null,
                                    'mime_type' => $file['mime_type'] ?? null
                                ];
                            }
                        } elseif (is_string($file)) {
                            // Handle simple string paths
                            $sourceFiles['bts_files'][] = [
                                'promotion_work_id' => $promotionWork->id,
                                'work_type' => $promotionWork->work_type,
                                'file_path' => $file,
                                'file_name' => basename($file)
                            ];
                        }
                    }
                }
            }
        }

        return $sourceFiles;
    }

    /**
     * Notify related roles about Editor Promosi work
     */
    private function notifyRelatedRoles(PromotionWork $work, string $action): void
    {
        $messages = [
            'created' => "New editor promosi work '{$work->title}' has been created",
            'status_changed' => "Editor promosi work '{$work->title}' status changed to {$work->status}",
            'files_uploaded' => "Edited content files for '{$work->title}' have been uploaded"
        ];

        // Notify Producer
        $producers = \App\Models\User::where('role', 'Producer')->get();
        foreach ($producers as $producer) {
            Notification::create([
                'title' => 'Editor Promosi Work ' . ucfirst($action),
                'message' => $messages[$action] ?? "Editor promosi work '{$work->title}' {$action}",
                'type' => 'editor_promosi_' . $action,
                'user_id' => $producer->id,
                'episode_id' => $work->episode_id
            ]);
        }

        // Notify Quality Control
        $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
        foreach ($qcUsers as $qcUser) {
            Notification::create([
                'title' => 'Editor Promosi Work ' . ucfirst($action),
                'message' => $messages[$action] ?? "Editor promosi work '{$work->title}' {$action}",
                'type' => 'editor_promosi_' . $action,
                'user_id' => $qcUser->id,
                'episode_id' => $work->episode_id
            ]);
        }
    }

    /**
     * Terima Pekerjaan - Editor Promosi accept work
     * POST /api/live-tv/roles/editor-promosi/works/{id}/accept-work
     */
    public function acceptWork(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Editor Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::findOrFail((int) $id);

            // Check if work can be accepted (draft, planning, editing, atau rejected jika perlu revisi)
            if (!in_array($work->status, ['draft', 'planning', 'editing', 'rejected', 'review'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be accepted. Current status: {$work->status}. Work must be in draft, planning, editing, rejected, or review status."
                ], 400);
            }

            // Auto-fetch source files dari Editor dan Promosi (BTS)
            $sourceFiles = $this->fetchSourceFilesForWork($work->episode_id);

            // Reset review fields jika ini resubmission setelah reject
            $updateData = [
                'status' => 'editing',
                'created_by' => $user->id,
                'file_paths' => array_merge($work->file_paths ?? [], [
                    'source_files' => $sourceFiles,
                    'accepted_at' => now()->toDateTimeString(),
                    'accepted_by' => $user->id
                ])
            ];

            // Reset review fields jika status adalah rejected atau review (resubmission)
            if (in_array($work->status, ['rejected', 'review'])) {
                $updateData['review_notes'] = null;
                $updateData['reviewed_by'] = null;
                $updateData['reviewed_at'] = null;
            }

            $work->update($updateData);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam ?? null;
            $producer = $productionTeam ? $productionTeam->producer : null;

            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_promosi_work_accepted',
                    'title' => 'Editor Promosi Work Accepted',
                    'message' => "Editor Promosi {$user->name} telah menerima pekerjaan {$work->work_type} untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'promotion_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'work_type' => $work->work_type,
                        'editor_promosi_id' => $user->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. Source files from Editor and BTS have been fetched. You can now start editing.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajukan ke QC - Editor Promosi submit file locations ke QC
     * POST /api/live-tv/roles/editor-promosi/works/{id}/submit-to-qc
     */
    public function submitToQC(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($user->role !== 'Editor Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::findOrFail((int) $id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            if ((!$work->file_paths || empty($work->file_paths)) && (!$work->file_links || empty($work->file_links))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload files or provide links before submitting to QC'
                ], 400);
            }

            // Validasi status harus editing atau completed
            if (!in_array($work->status, ['editing', 'completed', 'review'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work must be in 'editing', 'completed', or 'review' status before submitting to QC. Current status: {$work->status}"
                ], 400);
            }

            // Map work_type to qc_type
            $qcTypeMap = [
                'bts_video' => 'bts_video',
                'iklan_episode_tv' => 'advertisement_tv',
                'highlight_ig' => 'highlight_ig',
                'highlight_tv' => 'highlight_tv',
                'highlight_facebook' => 'highlight_facebook'
            ];

            $qcType = $qcTypeMap[$work->work_type] ?? 'bts_video'; // Default fallback

            // Prepare file locations with promotion_work_id
            $fileLocations = [];

            // Process file_paths (physical files)
            if (is_array($work->file_paths)) {
                foreach ($work->file_paths as $file) {
                    if (is_array($file) && isset($file['file_path'])) {
                        $fileLocations[] = [
                            'promotion_work_id' => $work->id,
                            'file_path' => $file['file_path'],
                            'file_name' => $file['file_name'] ?? basename($file['file_path']),
                            'file_size' => $file['file_size'] ?? null,
                            'mime_type' => $file['mime_type'] ?? null,
                            'work_type' => $work->work_type,
                            'source' => 'editor_promosi',
                            'submitted_at' => now()->toDateTimeString()
                        ];
                    } elseif (is_string($file)) {
                        $fileLocations[] = [
                            'promotion_work_id' => $work->id,
                            'file_path' => $file,
                            'file_name' => basename($file),
                            'work_type' => $work->work_type,
                            'source' => 'editor_promosi',
                            'submitted_at' => now()->toDateTimeString()
                        ];
                    }
                }
            }

            // Process file_links (external links)
            if (is_array($work->file_links)) {
                foreach ($work->file_links as $link) {
                    if (is_array($link) && isset($link['file_link'])) {
                        $fileLocations[] = [
                            'promotion_work_id' => $work->id,
                            'file_link' => $link['file_link'],
                            'file_path' => $link['file_link'], // Store link in file_path for QC access
                            'file_name' => 'Visual Link',
                            'work_type' => $work->work_type,
                            'source' => 'editor_promosi',
                            'is_link' => true,
                            'submitted_at' => now()->toDateTimeString()
                        ];
                    } elseif (is_string($link)) {
                        $fileLocations[] = [
                            'promotion_work_id' => $work->id,
                            'file_link' => $link,
                            'file_path' => $link,
                            'file_name' => 'Visual Link',
                            'work_type' => $work->work_type,
                            'source' => 'editor_promosi',
                            'is_link' => true,
                            'submitted_at' => now()->toDateTimeString()
                        ];
                    }
                }
            }

            // Check if QC work already exists for this work type
            $existingQCWork = \App\Models\QualityControlWork::where('episode_id', $work->episode_id)
                ->where('qc_type', $qcType)
                ->where('status', '!=', 'approved')
                ->first();

            if (!$existingQCWork) {
                // Create new QualityControlWork
                $qcUsers = \App\Models\User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                $qcWork = \App\Models\QualityControlWork::create([
                    'episode_id' => $work->episode_id,
                    'qc_type' => $qcType,
                    'title' => "QC {$work->title}",
                    'description' => "Quality Control untuk {$work->work_type} dari Editor Promosi",
                    'editor_promosi_file_locations' => $fileLocations, // Keep for specific history
                    'files_to_check' => $fileLocations, // Add for main visibility
                    'status' => 'pending',
                    'created_by' => $qcUsers->isNotEmpty() ? $qcUsers->first()->id : $user->id
                ]);

                // Notify Quality Control and related managers
                foreach ($qcUsers as $qcUser) {
                    Notification::create([
                        'user_id' => $qcUser->id,
                        'type' => 'editor_promosi_submitted_to_qc',
                        'title' => 'Editor Promosi Work Submitted to QC',
                        'message' => "Editor Promosi telah mengajukan {$work->work_type} untuk QC Episode {$work->episode->episode_number}.",
                        'data' => [
                            'promotion_work_id' => $work->id,
                            'qc_work_id' => $qcWork->id,
                            'episode_id' => $work->episode_id,
                            'work_type' => $work->work_type,
                            'qc_type' => $qcType
                        ]
                    ]);
                }
            } else {
                // Update existing QC work with latest editor promosi files
                $existingEditorPromosiFiles = $existingQCWork->editor_promosi_file_locations ?? [];
                $existingEditorPromosiFiles = array_merge($existingEditorPromosiFiles, $fileLocations);
                $existingFilesToCheck = $existingQCWork->files_to_check ?? [];
                $existingFilesToCheck = array_merge($existingFilesToCheck, $fileLocations);

                $existingQCWork->update([
                    'editor_promosi_file_locations' => $existingEditorPromosiFiles,
                    'files_to_check' => $existingFilesToCheck,
                    'status' => 'pending' // Reset to pending for re-review
                ]);

                // Notify Quality Control
                $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
                foreach ($qcUsers as $qcUser) {
                    Notification::create([
                        'user_id' => $qcUser->id,
                        'type' => 'editor_promosi_resubmitted_to_qc',
                        'title' => 'Editor Promosi Work Resubmitted to QC',
                        'message' => "Editor Promosi telah mengajukan ulang {$work->work_type} untuk QC Episode {$work->episode->episode_number}.",
                        'data' => [
                            'promotion_work_id' => $work->id,
                            'qc_work_id' => $existingQCWork->id,
                            'episode_id' => $work->episode_id,
                            'work_type' => $work->work_type
                        ]
                    ]);
                }
                $qcWork = $existingQCWork;
            }

            // Update PromotionWork status
            $work->update([
                'status' => 'review' // Status review, menunggu QC
            ]);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => [
                    'qc_work' => $qcWork->fresh(['episode', 'createdBy']),
                    'editor_promosi_work' => $work->fresh(['episode', 'createdBy'])
                ],
                'message' => 'Files submitted to QC successfully. Quality Control has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting to QC: ' . $e->getMessage()
            ], 500);
        }
    }
}
