<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\Episode;
use App\Models\MediaFile;
use App\Models\Notification;
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
            
            if ($user->role !== 'Editor Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = PromotionWork::with(['episode', 'createdBy'])
                ->where('created_by', $user->id);

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
            
            if ($user->role !== 'Editor Promosi') {
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
                'title', 'description', 'content_plan', 'talent_data',
                'location_data', 'equipment_needed', 'shooting_date',
                'shooting_time', 'shooting_notes', 'status'
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
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|mimes:mp4,avi,mov,jpg,jpeg,png,gif|max:1024000' // 1GB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadedFiles = [];
            $filePaths = [];

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

            // Update work with file paths
            $work->update([
                'file_paths' => $filePaths,
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
            
            if ($user->role !== 'Editor Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episodeId = $request->get('episode_id');
            $sourceRole = $request->get('source_role'); // 'editor' or 'promosi'

            $query = MediaFile::with(['episode', 'uploadedBy'])
                ->where('episode_id', $episodeId);

            if ($sourceRole === 'editor') {
                // Ambil file dari Editor (main editor)
                $query->where(function($q) {
                    $q->where('file_type', 'editor')
                      ->orWhere('file_type', 'editor_work');
                })
                ->whereHas('uploadedBy', function($q) {
                    $q->where('role', 'Editor');
                });

                // Juga ambil dari EditorWork jika ada
                $editorWorks = \App\Models\EditorWork::where('episode_id', $episodeId)
                    ->whereIn('status', ['completed', 'approved'])
                    ->get();

                $files = $query->orderBy('created_at', 'desc')->get();

                // Convert EditorWork to file-like structure
                $editorWorkFiles = $editorWorks->map(function($work) {
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

            } elseif ($sourceRole === 'promosi' || !$sourceRole) {
                // Default: Ambil file dari Promosi (BTS)
                $query->where('file_type', 'promotion')
                      ->whereHas('uploadedBy', function($q) {
                          $q->where('role', 'Promosi');
                      });

                $allFiles = $query->orderBy('created_at', 'desc')->get();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid source_role. Use "editor" or "promosi".'
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
            
            if ($user->role !== 'Editor Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_works' => PromotionWork::where('created_by', $user->id)->count(),
                'completed_works' => PromotionWork::where('created_by', $user->id)
                    ->where('status', 'completed')->count(),
                'in_progress_works' => PromotionWork::where('created_by', $user->id)
                    ->where('status', 'in_progress')->count(),
                'pending_works' => PromotionWork::where('created_by', $user->id)
                    ->where('status', 'draft')->count(),
                'works_by_type' => PromotionWork::where('created_by', $user->id)
                    ->selectRaw('work_type, count(*) as count')
                    ->groupBy('work_type')
                    ->get(),
                'recent_works' => PromotionWork::where('created_by', $user->id)
                    ->with(['episode'])
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
     * Ajukan ke QC - Editor Promosi submit file locations ke QC
     * POST /api/live-tv/editor-promosi/works/{id}/submit-to-qc
     */
    public function submitToQC(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Editor Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = \App\Models\EditorPromosiWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            if (!$work->file_paths || empty($work->file_paths)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload files before submitting to QC'
                ], 400);
            }

            // Create or update QualityControlWork
            $qcWork = \App\Models\QualityControlWork::updateOrCreate(
                [
                    'episode_id' => $work->episode_id,
                    'qc_type' => 'main_episode'
                ],
                [
                    'title' => "QC Work - Episode {$work->episode->episode_number}",
                    'description' => "File dari Editor Promosi untuk QC",
                    'editor_promosi_file_locations' => array_map(function($path) {
                        return [
                            'file_path' => $path,
                            'file_name' => basename($path),
                            'source' => 'editor_promosi'
                        ];
                    }, $work->file_paths),
                    'status' => 'pending',
                    'created_by' => $user->id
                ]
            );

            // Notify Quality Control
            $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
            foreach ($qcUsers as $qcUser) {
                Notification::create([
                    'user_id' => $qcUser->id,
                    'type' => 'qc_work_assigned',
                    'title' => 'Tugas QC Baru',
                    'message' => "Editor Promosi telah mengajukan file untuk QC Episode {$work->episode->episode_number}.",
                    'data' => [
                        'qc_work_id' => $qcWork->id,
                        'episode_id' => $work->episode_id,
                        'editor_promosi_work_id' => $work->id
                    ]
                ]);
            }

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
