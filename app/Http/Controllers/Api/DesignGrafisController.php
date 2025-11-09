<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DesignGrafisWork;
use App\Models\PromotionWork;
use App\Models\Episode;
use App\Models\MediaFile;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DesignGrafisController extends Controller
{
    /**
     * Get Design Grafis works for current user
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
            
            if ($user->role !== 'Design Grafis') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = DesignGrafisWork::with(['episode', 'createdBy'])
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
                'message' => 'Design Grafis works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Design Grafis works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new Design Grafis work
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Design Grafis') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:thumbnail_youtube,thumbnail_bts,graphics_ig,graphics_facebook,banner_website',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'design_brief' => 'nullable|string',
                'brand_guidelines' => 'nullable|string',
                'color_scheme' => 'nullable|string',
                'dimensions' => 'nullable|string',
                'file_format' => 'nullable|string',
                'deadline' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = DesignGrafisWork::create([
                'episode_id' => $request->episode_id,
                'created_by' => $user->id,
                'work_type' => $request->work_type,
                'title' => $request->title,
                'description' => $request->description,
                'design_brief' => $request->design_brief,
                'brand_guidelines' => $request->brand_guidelines,
                'color_scheme' => $request->color_scheme,
                'dimensions' => $request->dimensions,
                'file_format' => $request->file_format,
                'deadline' => $request->deadline,
                'status' => 'draft'
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($work, 'created');

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Design Grafis work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating Design Grafis work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Design Grafis work by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $work = DesignGrafisWork::with(['episode', 'createdBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Design Grafis work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Design Grafis work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Design Grafis work
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = DesignGrafisWork::findOrFail($id);

            if ($work->created_by !== $user->id && $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this work.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'design_brief' => 'nullable|string',
                'brand_guidelines' => 'nullable|string',
                'color_scheme' => 'nullable|string',
                'dimensions' => 'nullable|string',
                'file_format' => 'nullable|string',
                'deadline' => 'nullable|date',
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
                'title', 'description', 'design_brief', 'brand_guidelines',
                'color_scheme', 'dimensions', 'file_format', 'deadline', 'status'
            ]));

            // Notify on status change
            if ($request->has('status')) {
                $this->notifyRelatedRoles($work, 'status_changed');
            }

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Design Grafis work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating Design Grafis work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload design files
     */
    public function uploadFiles(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $work = DesignGrafisWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload files for this work.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|mimes:jpg,jpeg,png,psd,ai,svg|max:102400' // 100MB max
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
                $filePath = $file->storeAs("design_grafis/{$work->id}", $fileName, 'public');
                
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
                    'file_type' => 'design_grafis',
                    'uploaded_by' => $user->id,
                    'metadata' => [
                        'design_work_id' => $work->id,
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
                'message' => 'Design files uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading design files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files from other roles (Promosi, Produksi)
     */
    public function getSharedFiles(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Design Grafis') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episodeId = $request->get('episode_id');
            $sourceRole = $request->get('source_role'); // 'promosi' or 'produksi'

            $query = MediaFile::with(['episode', 'uploadedBy'])
                ->where('episode_id', $episodeId);

            if ($sourceRole === 'promosi') {
                $query->where('file_type', 'promotion')
                      ->whereHas('uploadedBy', function($q) {
                          $q->where('role', 'Promosi');
                      });
            } elseif ($sourceRole === 'produksi') {
                $query->where('file_type', 'production')
                      ->whereHas('uploadedBy', function($q) {
                          $q->where('role', 'Produksi');
                      });
            }

            $files = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $files,
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
     * Get Design Grafis statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Design Grafis') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_works' => DesignGrafisWork::where('created_by', $user->id)->count(),
                'completed_works' => DesignGrafisWork::where('created_by', $user->id)
                    ->where('status', 'completed')->count(),
                'in_progress_works' => DesignGrafisWork::where('created_by', $user->id)
                    ->where('status', 'in_progress')->count(),
                'pending_works' => DesignGrafisWork::where('created_by', $user->id)
                    ->where('status', 'draft')->count(),
                'works_by_type' => DesignGrafisWork::where('created_by', $user->id)
                    ->selectRaw('work_type, count(*) as count')
                    ->groupBy('work_type')
                    ->get(),
                'recent_works' => DesignGrafisWork::where('created_by', $user->id)
                    ->with(['episode'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Design Grafis statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify related roles about Design Grafis work
     */
    private function notifyRelatedRoles(DesignGrafisWork $work, string $action): void
    {
        $messages = [
            'created' => "New design work '{$work->title}' has been created",
            'status_changed' => "Design work '{$work->title}' status changed to {$work->status}",
            'files_uploaded' => "Design files for '{$work->title}' have been uploaded"
        ];

        // Notify Producer
        $producers = \App\Models\User::where('role', 'Producer')->get();
        foreach ($producers as $producer) {
            Notification::create([
                'title' => 'Design Grafis Work ' . ucfirst($action),
                'message' => $messages[$action] ?? "Design work '{$work->title}' {$action}",
                'type' => 'design_grafis_' . $action,
                'user_id' => $producer->id,
                'episode_id' => $work->episode_id
            ]);
        }

        // Notify Quality Control
        $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
        foreach ($qcUsers as $qcUser) {
            Notification::create([
                'title' => 'Design Grafis Work ' . ucfirst($action),
                'message' => $messages[$action] ?? "Design work '{$work->title}' {$action}",
                'type' => 'design_grafis_' . $action,
                'user_id' => $qcUser->id,
                'episode_id' => $work->episode_id
            ]);
        }
    }
}