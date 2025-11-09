<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\MediaFile;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Upload file for episode
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'file' => 'required|file|max:102400', // 100MB max
            'file_type' => 'required|in:audio,video,image,document,thumbnail,bts,highlight,advertisement',
            'description' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode = Episode::findOrFail($request->episode_id);
            $file = $request->file('file');
            
            $mediaFile = $this->fileUploadService->uploadFile(
                $episode,
                $file,
                $request->file_type,
                $request->description
            );
            
            return response()->json([
                'success' => true,
                'data' => $mediaFile,
                'message' => 'File uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:102400', // 100MB max per file
            'file_type' => 'required|in:audio,video,image,document,thumbnail,bts,highlight,advertisement',
            'description' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $episode = Episode::findOrFail($request->episode_id);
            $files = $request->file('files');
            
            $mediaFiles = $this->fileUploadService->uploadMultipleFiles(
                $episode,
                $files,
                $request->file_type,
                $request->description
            );
            
            return response()->json([
                'success' => true,
                'data' => $mediaFiles,
                'message' => 'Files uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete file
     */
    public function delete(int $id): JsonResponse
    {
        $mediaFile = MediaFile::findOrFail($id);
        
        try {
            $result = $this->fileUploadService->deleteFile($mediaFile);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete file'
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file URL
     */
    public function getUrl(int $id): JsonResponse
    {
        $mediaFile = MediaFile::findOrFail($id);
        $url = $this->fileUploadService->getFileUrl($mediaFile);
        
        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
            'message' => 'File URL retrieved successfully'
        ]);
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnail(int $id): JsonResponse
    {
        $mediaFile = MediaFile::findOrFail($id);
        $url = $this->fileUploadService->getThumbnailUrl($mediaFile);
        
        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
            'message' => 'Thumbnail URL retrieved successfully'
        ]);
    }

    /**
     * Get file statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $episodeId = $request->get('episode_id');
            $statistics = $this->fileUploadService->getFileStatistics($episodeId);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'File statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get file statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get storage usage
     */
    public function getStorageUsage(): JsonResponse
    {
        try {
            $usage = $this->fileUploadService->getStorageUsage();
            
            return response()->json([
                'success' => true,
                'data' => $usage,
                'message' => 'Storage usage retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get storage usage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup orphaned files
     */
    public function cleanupOrphaned(): JsonResponse
    {
        try {
            $deleted = $this->fileUploadService->cleanupOrphanedFiles();
            
            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => $deleted],
                'message' => "{$deleted} orphaned files cleaned up successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup orphaned files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files by episode
     */
    public function getByEpisode(int $episodeId): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($episodeId);
            $files = $episode->mediaFiles()->with('uploadedBy')->get();
            
            return response()->json([
                'success' => true,
                'data' => $files,
                'message' => 'Episode files retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get episode files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files by type
     */
    public function getByType(string $type): JsonResponse
    {
        try {
            $files = MediaFile::where('file_type', $type)
                ->with(['episode', 'uploadedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $files,
                'message' => 'Files by type retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get files by type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get files by status
     */
    public function getByStatus(string $status): JsonResponse
    {
        try {
            $files = MediaFile::where('status', $status)
                ->with(['episode', 'uploadedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $files,
                'message' => 'Files by status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get files by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update file metadata
     */
    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        $mediaFile = MediaFile::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'file_description' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $mediaFile->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $mediaFile,
                'message' => 'File metadata updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update file metadata',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}














