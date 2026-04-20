<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\Episode;
use App\Models\EditorWork;
use App\Models\ProduksiWork;
use App\Models\MediaFile;
use App\Models\Notification;
use App\Models\User;
use App\Models\QualityControlWork;
use App\Helpers\QueryOptimizer;
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;
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
            $role = strtolower($user->role ?? '');
            $allowedRoles = ['editor promotion', 'editor promosi', 'promotion editor', 'promotion', 'promosi'];

            $isAuthorized = MusicProgramAuthorization::canUserPerformTask($user, null, 'Editor Promotion') || MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$user || !$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get all works that are either:
            // 1. Created by current user (Accepted or manual), OR
            // 2. Unclaimed auto-created works (created by non-Editor Promosi roles) in specific statuses
            $query = PromotionWork::with(['episode.program', 'createdBy', 'reviewedBy'])
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhere(function ($sub) {
                            // Show unclaimed auto-created works
                            $sub->whereIn('status', ['draft', 'shooting', 'editing', 'review', 'rejected', 'completed', 'approved', 'published'])
                                ->whereHas('createdBy', function ($roleQuery) {
                                    $roleQuery->whereNotIn('role', ['Editor Promotion', 'Editor Promosi', 'Promotion Editor', 'Promotion', 'Promosi']);
                                });
                        })
                        ->orWhere(function ($sub) {
                            // NEW: Allow all Promotion/Editor Promotion users to see works in 'planning' status
                            // This ensures auto-created sharing tasks (assigned to first user) are visible to the whole team until accepted.
                            $sub->where('status', 'planning');
                        });
                });

            // Filter by status
            if ($request->has('status')) {
                $status = $request->status;
                if (str_contains($status, ',')) {
                    $query->whereIn('status', explode(',', $status));
                } else {
                    $query->where('status', $status);
                }
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            // Add explicit creator_role for frontend safety (handles naming collisions)
            collect($works->items())->each(function ($work) {
                $work->creator_role = $work->createdBy->role ?? '';
            });

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

            $isAuthorized = MusicProgramAuthorization::canUserPerformTask($user, null, 'Editor Promotion') || MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$user || !$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }


            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:bts_video,bts_photo,highlight_ig,highlight_facebook,highlight_tv,iklan_episode_tv,story_ig,reels_facebook,tiktok,website_content,whatsapp_story',
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

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Editor Promotion')) {
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

            $isEditor = in_array($user->role, ['Editor Promotion', 'Editor Promosi', 'Promotion Editor']);
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Editor Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload files for this work.'
                ], 403);
            }

            // Auto-assign to current user if it's an editor (eliminates need for separate accept-work step)
            // Auto-assign to current user if it's an editor (eliminates need for separate accept-work step)
            if ($isEditor && $work->created_by !== $user->id) {
                $work->created_by = $user->id;
                // Transition to editing status if starting from early stages
                if (in_array($work->status, ['draft', 'planning'])) {
                    $work->status = 'editing';
                }
                $work->save();
            }

            $validator = Validator::make($request->all(), [
                'files' => 'nullable|array',
                'files.*' => 'nullable|file|mimes:mp4,avi,mov,jpg,jpeg,png,gif|max:1024000', // 1GB max
                'file_links' => 'nullable|array',
                'file_links.*.url' => 'required|url|max:2048',
                'file_links.*.type' => 'required|string|max:50',
                'file_links.*.label' => 'nullable|string|max:255'
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

            // Enforce minimum 2 links for reels_facebook
            if ($work->work_type === 'reels_facebook') {
                $linkCount = 0;
                if ($request->has('file_links') && is_array($request->file_links)) {
                    $linkCount = count(array_filter($request->file_links, function($link) {
                        $url = is_array($link) ? ($link['url'] ?? $link['file_link'] ?? null) : $link;
                        return !empty($url);
                    }));
                }
                
                if ($linkCount < 2) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Upload Reels Facebook minimal 2 link. Silakan tambah link lainnya.'
                    ], 422);
                }
            }

            $uploadedFiles = [];
            $filePaths = $work->file_paths ?? [];

            // Physical file upload removed
            if ($request->hasFile('files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical file uploads are disabled. Please use the file_links array for URL submissions.'
                ], 405);
            }

            // Handle file_links (Full replacement to support updates/deletes from frontend)
            $fileLinks = [];
            if ($request->has('file_links') && is_array($request->file_links)) {
                foreach ($request->file_links as $linkData) {
                    $url = is_array($linkData) ? ($linkData['url'] ?? $linkData['file_link'] ?? null) : $linkData;
                    if (!$url) continue;

                    $fileLinks[] = [
                        'file_link' => $url,
                        'type' => $linkData['type'] ?? 'other',
                        'label' => $linkData['label'] ?? null,
                        'uploaded_at' => $linkData['uploaded_at'] ?? now()->toDateTimeString(),
                        'uploaded_by' => $linkData['uploaded_by'] ?? $user->id
                    ];
                }
            }

            // Sync metadata into file_paths
            $filePaths = $work->file_paths ?? [];
            if ($request->has('duration') || $request->has('resolution')) {
                $filePaths['result_metadata'] = [
                    'duration' => $request->duration,
                    'resolution' => $request->resolution,
                    'updated_at' => now()->toDateTimeString()
                ];
            }

            // Update work with file paths, links, and description
            $work->update([
                'file_paths' => $filePaths,
                'file_links' => $fileLinks,
                'description' => $request->notes ?? $work->description,
                'status' => 'review'
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

            $isAuthorized = MusicProgramAuthorization::canUserPerformTask($user, null, 'Editor Promotion') || MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$user || !$isAuthorized) {
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
            $role = strtolower($user->role ?? '');
            $allowedRoles = ['editor promotion', 'editor promosi', 'promotion editor', 'promotion', 'promosi'];

            $isAuthorized = MusicProgramAuthorization::canUserPerformTask($user, null, 'Editor Promotion') || MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$user || !$isAuthorized) {
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
            if ($editorWork->file_path || $editorWork->file_link) {
                $sourceFiles['editor_files'][] = [
                    'editor_work_id' => $editorWork->id,
                    'file_path' => $editorWork->file_path,
                    'file_link' => $editorWork->file_link,
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
            ->whereIn('status', ['editing', 'review', 'approved', 'published', 'completed']) // Include completed
            ->get();

        foreach ($promotionWorks as $promotionWork) {
            // Check file_paths
            $allFiles = [];
            
            if ($promotionWork->file_paths) {
                $filePaths = $promotionWork->file_paths ?? [];
                if (is_array($filePaths)) {
                    foreach ($filePaths as $file) {
                        if (is_array($file)) {
                            $allFiles[] = $file;
                        } elseif (is_string($file)) {
                            $allFiles[] = ['file_path' => $file, 'type' => 'bts_video']; // Default to bts_video if string
                        }
                    }
                }
            }

            // Check file_links
            if ($promotionWork->file_links) {
                $fileLinks = $promotionWork->file_links ?? [];
                if (is_array($fileLinks)) {
                    foreach ($fileLinks as $link) {
                        if (is_array($link)) {
                            $allFiles[] = $link;
                        }
                    }
                }
            }

            // Legacy fields fallback
            if ($promotionWork->bts_video_path) {
                $allFiles[] = ['file_path' => $promotionWork->bts_video_path, 'type' => 'bts_video', 'source' => 'legacy_path'];
            }
            if ($promotionWork->bts_video_link) {
                $allFiles[] = ['file_path' => $promotionWork->bts_video_link, 'type' => 'bts_video', 'source' => 'legacy_link'];
            }

            foreach ($allFiles as $file) {
                // Filter hanya BTS video (bukan talent photos)
                if (isset($file['type']) && $file['type'] === 'bts_video') {
                    $sourceFiles['bts_files'][] = [
                        'promotion_work_id' => $promotionWork->id,
                        'work_type' => $promotionWork->work_type,
                        'file_type' => $file['type'],
                        'file_path' => $file['file_path'] ?? $file['url'] ?? $file['file_link'] ?? null,
                        'file_name' => $file['file_name'] ?? basename($file['file_path'] ?? ''),
                        'file_size' => $file['file_size'] ?? null,
                        'mime_type' => $file['mime_type'] ?? null,
                        'url' => $file['url'] ?? $file['file_link'] ?? null
                    ];
                }
            }
        }

        // Get files from Produksi (Shooting results for highlights)
        $produksiWork = \App\Models\ProduksiWork::where('episode_id', $episodeId)
            ->where('status', 'completed')
            ->first();

        if ($produksiWork) {
            $sourceFiles['production_files'] = [
                'produksi_work_id' => $produksiWork->id,
                'shooting_files' => $produksiWork->shooting_files,
                'shooting_file_links' => $produksiWork->shooting_file_links,
                'run_sheet_id' => $produksiWork->run_sheet_id
            ];
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

            $isAuthorized = MusicProgramAuthorization::canUserPerformTask($user, null, 'Editor Promotion') || MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$user || !$isAuthorized) {
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

            if (!$user || !in_array($user->role, ['Editor Promotion', 'Editor Promosi', 'Promotion Editor'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::findOrFail((int) $id);

            $isEditor = in_array($user->role, ['Editor Promotion', 'Editor Promosi', 'Promotion Editor']);

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $work, 'Editor Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to submit files for this work.'
                ], 403);
            }

            // Ensure ownership is set if it's an editor
            if ($isEditor && $work->created_by !== $user->id) {
                $work->created_by = $user->id;
                $work->save();
            }

            if ((!$work->file_paths || empty($work->file_paths)) && (!$work->file_links || empty($work->file_links))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload files or provide links before submitting to QC'
                ], 400);
            }

            // Allow submission from various statuses to enable re-submissions and corrections
            if (!in_array($work->status, ['editing', 'review', 'completed', 'approved', 'published'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work must be in a valid status for submission. Current status: {$work->status}"
                ], 400);
            }

            // Consolidate ALL Editor Promosi tasks for an episode into a single QC Card
            // Using 'bts_video' as the technical type to match database ENUM allowed values
            $qcType = 'bts_video';
            $episodeNumber = $work->episode->episode_number ?? 'X';
            $qcTitle = "Materi Promosi - Episode {$episodeNumber}";

            // Whitelist for Editor Promosi outputs ONLY
            $allowedKeys = [
                'bts_video', 'bts_photo',
                'iklan_episode_tv', 'advertisement_tv',
                'highlight_ig', 'highlight_tv', 'highlight_facebook', 'highlight_face', 'highlight_fb',
                'story_ig', 'reels_facebook', 'tiktok', 'website_content', 'promotion'
            ];

            // Professional labels for QC items
            $materialLabels = [
                'bts_video' => 'Video BTS',
                'bts_photo' => 'Foto BTS',
                'iklan_episode_tv' => 'Iklan Episode TV',
                'advertisement_tv' => 'Iklan Episode TV',
                'highlight_ig' => 'Highlight Episode IG',
                'highlight_tv' => 'Highlight Episode TV',
                'highlight_facebook' => 'Highlight Episode Face',
                'highlight_face' => 'Highlight Episode Face',
                'highlight_fb' => 'Highlight Episode Face',
                'story_ig' => 'Highlight Story IG',
                'reels_facebook' => 'Highlight Reels FB',
                'tiktok' => 'TikTok Content',
                'website_content' => 'Website Content'
            ];

            // Get all works for this episode to ensure we don't miss anything that was already submitted or is ready
            $allWorks = PromotionWork::where('episode_id', $work->episode_id)
                ->whereIn('status', ['editing', 'review', 'completed', 'approved', 'published'])
                ->get();

            // Prepare file locations from ALL works with prioritization
            $groupedLocations = [];
            $seenUrls = [];

            foreach ($allWorks as $w) {
                // Identity the role of the creator
                $userRole = $w->createdBy->role ?? '';
                $isFromEditor = in_array($userRole, ['Editor Promotion', 'Editor Promosi', 'Promotion Editor']);

                // 1. Process file_paths (Physical files)
                if (is_array($w->file_paths)) {
                    foreach ($w->file_paths as $key => $value) {
                        if (!in_array($key, $allowedKeys) || !is_string($value)) {
                            continue;
                        }

                        $uniqueUrlKey = $value . '_' . $key;
                        if (in_array($uniqueUrlKey, $seenUrls)) continue;
                        $seenUrls[] = $uniqueUrlKey;

                        $label = $materialLabels[$key] ?? str_replace(['_', '-'], ' ', ucwords($key));
                        $label = str_replace('QC ', '', $label); // Strip any existing redundant QC prefix
                        $sourcePrefix = $isFromEditor ? 'Final: ' : 'Source: ';

                        $location = [
                            'promotion_work_id' => $w->id,
                            'file_path' => $value,
                            'file_name' => $sourcePrefix . $label,
                            'work_type' => $key,
                            'source' => $isFromEditor ? 'editor_promosi' : 'shooting_team',
                            'is_link' => false,
                            'submitted_at' => now()->toDateTimeString()
                        ];

                        // Prioritization: Editor version replaces Shooting version for the same type in the checklist
                        if ($isFromEditor || !isset($groupedLocations[$key])) {
                            $groupedLocations[$key] = $location;
                        }
                    }
                }

                // 2. Process file_links (External links)
                if (is_array($w->file_links) && !empty($w->file_links)) {
                    $isList = array_key_exists(0, $w->file_links);
                    $links = $isList ? $w->file_links : [$w->file_links];

                    foreach ($links as $link) {
                        if (!is_array($link)) continue;
                        
                        $linkVal = $link['file_link'] ?? $link['url'] ?? null;
                        if (!$linkVal) continue;

                        $typeKey = $link['type'] ?? $w->work_type;
                        if ($typeKey === 'other' || !in_array($typeKey, $allowedKeys)) {
                            $typeKey = $w->work_type;
                        }

                        $uniqueUrlKey = $linkVal . '_' . $typeKey;
                        if (in_array($uniqueUrlKey, $seenUrls)) continue;
                        $seenUrls[] = $uniqueUrlKey;
                        
                        if (in_array($typeKey, $allowedKeys)) {
                            $label = $materialLabels[$typeKey] ?? ($materialLabels[$w->work_type] ?? 'External File');
                            $label = str_replace('QC ', '', $label);
                            $sourcePrefix = $isFromEditor ? 'Final: ' : 'Source: ';

                            $location = [
                                'promotion_work_id' => $w->id,
                                'file_link' => $linkVal,
                                'file_path' => $linkVal,
                                'file_name' => $sourcePrefix . $label,
                                'work_type' => $typeKey,
                                'source' => $isFromEditor ? 'editor_promosi' : 'shooting_team',
                                'is_link' => true,
                                'submitted_at' => now()->toDateTimeString()
                            ];

                            // Prioritization
                            if ($isFromEditor || !isset($groupedLocations[$typeKey])) {
                                $groupedLocations[$typeKey] = $location;
                            }
                        }
                    }
                }
            }

            $fileLocations = array_values($groupedLocations);

            if (empty($fileLocations)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada file promosi yang relevan untuk diajukan ke QC.'
                ], 400);
            }

            // Check for existing QC work
            $existingQCWork = QualityControlWork::where('episode_id', $work->episode_id)
                ->where('qc_type', $qcType)
                ->first();

            if (!$existingQCWork) {
                // Create new QualityControlWork
                $qcWork = QualityControlWork::create([
                    'episode_id' => $work->episode_id,
                    'qc_type' => $qcType,
                    'title' => $qcTitle,
                    'description' => "Quality Control Materi Promosi dari Editor Promosi",
                    'editor_promosi_file_locations' => $fileLocations,
                    'files_to_check' => $fileLocations,
                    'status' => 'pending',
                    'created_by' => $user->id
                ]);

                // Notify Quality Control and related managers
                $qcUsers = User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                foreach ($qcUsers as $qcUser) {
                    Notification::create([
                        'user_id' => $qcUser->id,
                        'type' => 'editor_promosi_submitted_to_qc',
                        'title' => 'Editor Promosi Work Submitted to QC',
                        'message' => "Editor Promosi telah mengajukan materi promosi untuk QC Episode {$work->episode->episode_number}.",
                        'data' => [
                            'promotion_work_id' => $work->id,
                            'qc_work_id' => $qcWork->id,
                            'episode_id' => $work->episode_id,
                            'work_type' => 'promosi_material',
                            'qc_type' => $qcType
                        ]
                    ]);
                }
            } else {
                // Update existing QC work with latest editor promosi files (All materials for the episode)
                // Update existing QC work with latest editor promosi files (All materials for the episode)
                $existingQCWork->update([
                    'editor_promosi_file_locations' => $fileLocations,
                    'files_to_check' => $fileLocations,
                    'qc_checklist' => null, // Clear old checklist to force a fresh review
                    'quality_score' => 0, // Reset score
                    'status' => 'pending' // Reset to pending for re-review
                ]);

                // Also notify again on resubmission
                $qcUsers = User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                foreach ($qcUsers as $qcUser) {
                    Notification::create([
                        'user_id' => $qcUser->id,
                        'type' => 'editor_promosi_submitted_to_qc',
                        'title' => 'Editor Promosi Work Updated',
                        'message' => "Editor Promosi telah memperbarui materi promosi untuk QC Episode {$work->episode->episode_number}.",
                        'data' => [
                            'promotion_work_id' => $work->id,
                            'qc_work_id' => $existingQCWork->id,
                            'episode_id' => $work->episode_id,
                            'work_type' => 'promosi_material',
                            'qc_type' => $qcType
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
