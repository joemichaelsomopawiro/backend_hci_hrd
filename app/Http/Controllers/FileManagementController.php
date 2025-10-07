<?php

namespace App\Http\Controllers;

use App\Models\ProgramFile;
use App\Models\Program;
use App\Models\Episode;
use App\Models\Schedule;
use App\Models\ProgramNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FileManagementController extends Controller
{
    /**
     * Upload file
     */
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:102400', // Max 100MB
                'category' => 'required|in:script,bts_video,bts_photo,thumbnail,production_video,production_photo,edited_video,final_video,audio,other',
                'fileable_type' => 'required|in:Program,Episode,Schedule',
                'fileable_id' => 'required|integer',
                'description' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $category = $request->category;
            $fileableType = $request->fileable_type;
            $fileableId = $request->fileable_id;

            // Verify entity exists
            $entity = $this->getEntity($fileableType, $fileableId);
            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Entity not found'
                ], 404);
            }

            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Determine storage path based on category
            $storagePath = $this->getStoragePath($category, $fileableType, $fileableId);
            $fullPath = $file->storeAs($storagePath, $filename, 'public');

            // Create file record
            $programFile = ProgramFile::create([
                'filename' => $filename,
                'original_name' => $originalName,
                'file_path' => $fullPath,
                'file_type' => $this->getFileType($file->getMimeType()),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'category' => $category,
                'description' => $request->description,
                'uploaded_by' => Auth::id(),
                'fileable_type' => $fileableType,
                'fileable_id' => $fileableId,
                'status' => 'uploaded',
                'metadata' => [
                    'upload_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'upload_timestamp' => now()->toISOString()
                ]
            ]);

            // Notify relevant team members
            $this->notifyFileUpload($programFile, $entity);

            return response()->json([
                'success' => true,
                'data' => $programFile->load('uploader'),
                'message' => 'File uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files for entity
     */
    public function getFiles(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            $query = ProgramFile::where('fileable_type', $entityType)
                ->where('fileable_id', $entityId)
                ->with('uploader');

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by file type
            if ($request->has('file_type')) {
                $query->where('file_type', $request->file_type);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $files = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $files,
                'message' => 'Files retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download file
     */
    public function downloadFile(string $id): JsonResponse
    {
        try {
            $file = ProgramFile::findOrFail($id);
            
            if (!Storage::disk('public')->exists($file->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on storage'
                ], 404);
            }

            $downloadUrl = asset('storage/' . $file->file_path);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => $file->original_name,
                    'file_size' => $file->file_size,
                    'mime_type' => $file->mime_type
                ],
                'message' => 'Download URL generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating download URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete file
     */
    public function deleteFile(string $id): JsonResponse
    {
        try {
            $file = ProgramFile::findOrFail($id);
            
            // Check if user has permission to delete
            $user = Auth::user();
            if ($file->uploaded_by !== $user->id && !in_array($user->role, ['Manager', 'Program Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this file'
                ], 403);
            }

            // Delete from storage
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }

            // Update status to deleted
            $file->update(['status' => 'deleted']);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update file metadata
     */
    public function updateFile(Request $request, string $id): JsonResponse
    {
        try {
            $file = ProgramFile::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'description' => 'nullable|string|max:1000',
                'category' => 'sometimes|in:script,bts_video,bts_photo,thumbnail,production_video,production_photo,edited_video,final_video,audio,other'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file->update($request->only(['description', 'category']));

            return response()->json([
                'success' => true,
                'data' => $file->load('uploader'),
                'message' => 'File updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file statistics
     */
    public function getFileStatistics(Request $request, string $entityType = null, int $entityId = null): JsonResponse
    {
        try {
            $query = ProgramFile::where('status', '!=', 'deleted');
            
            if ($entityType && $entityId) {
                $query->where('fileable_type', $entityType)
                      ->where('fileable_id', $entityId);
            }
            
            $files = $query;

            $statistics = [
                'total_files' => $files->count(),
                'total_size' => $files->sum('file_size'),
                'files_by_category' => $files->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category'),
                'files_by_type' => $files->selectRaw('file_type, COUNT(*) as count')
                    ->groupBy('file_type')
                    ->pluck('count', 'file_type'),
                'recent_uploads' => ProgramFile::where('status', '!=', 'deleted')
                    ->when($entityType && $entityId, function($query) use ($entityType, $entityId) {
                        return $query->where('fileable_type', $entityType)
                                   ->where('fileable_id', $entityId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'original_name', 'category', 'created_at'])
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'File statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk upload files
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'files' => 'required|array|max:10',
                'files.*' => 'required|file|max:102400',
                'category' => 'required|in:script,bts_video,bts_photo,thumbnail,production_video,production_photo,edited_video,final_video,audio,other',
                'fileable_type' => 'required|in:Program,Episode,Schedule',
                'fileable_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadedFiles = [];
            $failedFiles = [];

            foreach ($request->file('files') as $file) {
                try {
                    $result = $this->uploadSingleFile($file, $request->category, $request->fileable_type, $request->fileable_id);
                    $uploadedFiles[] = $result;
                } catch (\Exception $e) {
                    $failedFiles[] = [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'uploaded_files' => $uploadedFiles,
                    'failed_files' => $failedFiles,
                    'total_uploaded' => count($uploadedFiles),
                    'total_failed' => count($failedFiles)
                ],
                'message' => 'Bulk upload completed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error in bulk upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get entity by type and ID
     */
    private function getEntity(string $entityType, int $entityId)
    {
        switch ($entityType) {
            case 'Program':
                return Program::find($entityId);
            case 'Episode':
                return Episode::find($entityId);
            case 'Schedule':
                return Schedule::find($entityId);
            default:
                return null;
        }
    }

    /**
     * Get storage path based on category and entity
     */
    private function getStoragePath(string $category, string $entityType, int $entityId): string
    {
        $basePath = 'program-files';
        $entityPath = strtolower($entityType) . 's/' . $entityId;
        
        return $basePath . '/' . $entityPath . '/' . $category;
    }

    /**
     * Get file type from MIME type
     */
    private function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ])) {
            return 'document';
        } else {
            return 'other';
        }
    }

    /**
     * Upload single file (helper for bulk upload)
     */
    private function uploadSingleFile($file, string $category, string $fileableType, int $fileableId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        $storagePath = $this->getStoragePath($category, $fileableType, $fileableId);
        $fullPath = $file->storeAs($storagePath, $filename, 'public');

        $programFile = ProgramFile::create([
            'filename' => $filename,
            'original_name' => $originalName,
            'file_path' => $fullPath,
            'file_type' => $this->getFileType($file->getMimeType()),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'category' => $category,
            'uploaded_by' => Auth::id(),
            'fileable_type' => $fileableType,
            'fileable_id' => $fileableId,
            'status' => 'uploaded'
        ]);

        return $programFile->toArray();
    }

    /**
     * Notify file upload
     */
    private function notifyFileUpload(ProgramFile $file, $entity): void
    {
        $entityName = $entity->name ?? $entity->title ?? 'Unknown';
        
        // Notify team members based on file category
        $notifyRoles = $this->getNotifyRolesForCategory($file->category);
        
        foreach ($notifyRoles as $role) {
            $users = \App\Models\User::where('role', $role)->get();
            
            foreach ($users as $user) {
                ProgramNotification::create([
                    'title' => 'New File Uploaded',
                    'message' => "New {$file->category} file uploaded for {$entityName}: {$file->original_name}",
                    'type' => 'file_uploaded',
                    'user_id' => $user->id,
                    'program_id' => $entity->program_id ?? $entity->id
                ]);
            }
        }
    }

    /**
     * Get roles to notify based on file category
     */
    private function getNotifyRolesForCategory(string $category): array
    {
        $notifyMap = [
            'script' => ['Producer', 'Manager', 'Program Manager'],
            'bts_video' => ['Producer', 'Manager', 'Program Manager'],
            'bts_photo' => ['Producer', 'Manager', 'Program Manager'],
            'thumbnail' => ['Producer', 'Manager', 'Program Manager'],
            'production_video' => ['Producer', 'Editor', 'Manager', 'Program Manager'],
            'production_photo' => ['Producer', 'Editor', 'Manager', 'Program Manager'],
            'edited_video' => ['Producer', 'Manager', 'Program Manager'],
            'final_video' => ['Producer', 'Manager', 'Program Manager']
        ];

        return $notifyMap[$category] ?? ['Manager', 'Program Manager'];
    }
}
