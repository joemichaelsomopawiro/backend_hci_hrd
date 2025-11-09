<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\Episode;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class FileSharingController extends Controller
{
    /**
     * Share files between roles
     */
    public function shareFiles(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'target_roles' => 'required|array|min:1',
                'target_roles.*' => 'required|string|in:Design Grafis,Editor Promosi,Quality Control,Broadcasting',
                'file_ids' => 'nullable|array',
                'file_ids.*' => 'required|exists:media_files,id',
                'message' => 'nullable|string|max:500',
                'priority' => 'nullable|in:low,medium,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sharedFiles = [];

            // If file_ids provided, share specific files
            if (!empty($request->file_ids)) {
                $files = MediaFile::whereIn('id', $request->file_ids)->get();
                
                foreach ($files as $file) {
                    // Create shared file record
                    $sharedFile = MediaFile::create([
                        'episode_id' => $request->episode_id,
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'file_type' => 'shared',
                        'uploaded_by' => $user->id,
                        'metadata' => array_merge($file->metadata ?? [], [
                            'shared_by' => $user->id,
                            'shared_at' => now(),
                            'target_roles' => $request->target_roles,
                            'message' => $request->message,
                            'priority' => $request->priority ?? 'medium',
                            'original_file_id' => $file->id
                        ])
                    ]);

                    $sharedFiles[] = $sharedFile;
                }
            } else {
                // If no specific files, just create a sharing notification
                $sharedFiles = [];
            }

            // Notify target roles
            $this->notifyTargetRoles($request->target_roles, $sharedFiles, $request->episode_id, $request->message);

            return response()->json([
                'success' => true,
                'data' => $sharedFiles,
                'message' => 'Files shared successfully with target roles'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sharing files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shared files for current user's role
     */
    public function getSharedFiles(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = MediaFile::with(['episode', 'uploadedBy'])
                ->where('file_type', 'shared')
                ->whereJsonContains('metadata->target_roles', $user->role);

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->whereJsonContains('metadata->priority', $request->priority);
            }

            // Filter by shared date
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $files = $query->orderBy('created_at', 'desc')->paginate(20);

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
     * Get files from specific role
     */
    public function getFilesFromRole(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'source_role' => 'required|string|in:Promosi,Produksi,Design Grafis,Editor Promosi',
                'file_type' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = MediaFile::with(['episode', 'uploadedBy'])
                ->where('episode_id', $request->episode_id)
                ->whereHas('uploadedBy', function($q) use ($request) {
                    $q->where('role', $request->source_role);
                });

            if ($request->has('file_type')) {
                $query->where('file_type', $request->file_type);
            }

            $files = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $files,
                'message' => "Files from {$request->source_role} retrieved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving files from role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download shared file
     */
    public function downloadFile(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = MediaFile::findOrFail($id);

            // Check if user has access to this file
            if ($file->file_type === 'shared') {
                $targetRoles = $file->metadata['target_roles'] ?? [];
                if (!in_array($user->role, $targetRoles) && $file->uploaded_by !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to access this file'
                    ], 403);
                }
            } elseif ($file->uploaded_by !== $user->id && $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to access this file'
                ], 403);
            }

            if (!Storage::disk('public')->exists($file->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileUrl = Storage::disk('public')->url($file->file_path);

            return response()->json([
                'success' => true,
                'data' => [
                    'file' => $file,
                    'download_url' => $fileUrl
                ],
                'message' => 'File download URL generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file sharing statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $stats = [
                'files_shared_by_me' => MediaFile::where('uploaded_by', $user->id)
                    ->where('file_type', 'shared')
                    ->count(),
                'files_shared_to_me' => MediaFile::where('file_type', 'shared')
                    ->whereJsonContains('metadata->target_roles', $user->role)
                    ->count(),
                'files_by_role' => MediaFile::where('file_type', 'shared')
                    ->selectRaw('JSON_EXTRACT(metadata, "$.target_roles") as roles, count(*) as count')
                    ->groupBy('roles')
                    ->get(),
                'recent_shared_files' => MediaFile::where('file_type', 'shared')
                    ->whereJsonContains('metadata->target_roles', $user->role)
                    ->with(['episode', 'uploadedBy'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'File sharing statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file sharing statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify target roles about shared files
     */
    private function notifyTargetRoles(array $targetRoles, array $files, int $episodeId, ?string $message): void
    {
        $fileNames = collect($files)->pluck('file_name')->join(', ');
        
        foreach ($targetRoles as $role) {
            $users = \App\Models\User::where('role', $role)->get();
            
            foreach ($users as $user) {
                Notification::create([
                    'title' => 'Files Shared',
                    'message' => "Files have been shared with you: {$fileNames}" . ($message ? " - {$message}" : ''),
                    'type' => 'file_shared',
                    'user_id' => $user->id,
                    'episode_id' => $episodeId
                ]);
            }
        }
    }
}
