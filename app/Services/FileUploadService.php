<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\Episode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload file untuk episode
     */
    public function uploadFile(Episode $episode, UploadedFile $file, string $fileType, ?string $description = null): MediaFile
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::uuid() . '.' . $extension;
        $filePath = "episodes/{$episode->id}/{$fileType}/{$fileName}";
        
        // Upload file ke storage
        $file->storeAs("episodes/{$episode->id}/{$fileType}", $fileName, 'public');
        
        // Create media file record
        $mediaFile = MediaFile::create([
            'episode_id' => $episode->id,
            'file_type' => $fileType,
            'file_path' => $filePath,
            'file_name' => $originalName,
            'file_extension' => $extension,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'file_description' => $description,
            'uploaded_by' => auth()->id(),
            'status' => 'uploaded'
        ]);
        
        // Generate thumbnail untuk video/image
        if (in_array($fileType, ['video', 'image'])) {
            $this->generateThumbnail($mediaFile);
        }
        
        return $mediaFile;
    }

    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(Episode $episode, array $files, string $fileType, ?string $description = null): array
    {
        $uploadedFiles = [];
        
        foreach ($files as $file) {
            $uploadedFiles[] = $this->uploadFile($episode, $file, $fileType, $description);
        }
        
        return $uploadedFiles;
    }

    /**
     * Delete file
     */
    public function deleteFile(MediaFile $mediaFile): bool
    {
        // Delete file dari storage
        if (Storage::disk('public')->exists($mediaFile->file_path)) {
            Storage::disk('public')->delete($mediaFile->file_path);
        }
        
        // Delete thumbnail jika ada
        if ($mediaFile->thumbnail_path && Storage::disk('public')->exists($mediaFile->thumbnail_path)) {
            Storage::disk('public')->delete($mediaFile->thumbnail_path);
        }
        
        // Delete record
        return $mediaFile->delete();
    }

    /**
     * Get file URL
     */
    public function getFileUrl(MediaFile $mediaFile): string
    {
        return Storage::disk('public')->url($mediaFile->file_path);
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl(MediaFile $mediaFile): ?string
    {
        if (!$mediaFile->thumbnail_path) return null;
        
        return Storage::disk('public')->url($mediaFile->thumbnail_path);
    }

    /**
     * Generate thumbnail untuk video/image
     */
    private function generateThumbnail(MediaFile $mediaFile): void
    {
        try {
            $filePath = storage_path('app/public/' . $mediaFile->file_path);
            $thumbnailPath = "episodes/{$mediaFile->episode_id}/thumbnails/" . Str::uuid() . '.jpg';
            $fullThumbnailPath = storage_path('app/public/' . $thumbnailPath);
            
            // Create thumbnail directory
            Storage::disk('public')->makeDirectory("episodes/{$mediaFile->episode_id}/thumbnails");
            
            if ($mediaFile->file_type === 'video') {
                // Generate thumbnail dari video (menggunakan FFmpeg jika tersedia)
                $this->generateVideoThumbnail($filePath, $fullThumbnailPath);
            } elseif ($mediaFile->file_type === 'image') {
                // Generate thumbnail dari image
                $this->generateImageThumbnail($filePath, $fullThumbnailPath);
            }
            
            // Update media file dengan thumbnail path
            $mediaFile->update(['thumbnail_path' => $thumbnailPath]);
            
        } catch (\Exception $e) {
            // Log error tapi tidak throw exception
            \Log::error('Failed to generate thumbnail: ' . $e->getMessage());
        }
    }

    /**
     * Generate video thumbnail
     */
    private function generateVideoThumbnail(string $videoPath, string $thumbnailPath): void
    {
        // Check if FFmpeg is available
        $ffmpegPath = config('app.ffmpeg_path', 'ffmpeg');
        
        if (!shell_exec("which {$ffmpegPath}")) {
            throw new \Exception('FFmpeg not found');
        }
        
        // Generate thumbnail dari frame pertama
        $command = "{$ffmpegPath} -i \"{$videoPath}\" -ss 00:00:01 -vframes 1 -q:v 2 \"{$thumbnailPath}\"";
        shell_exec($command);
    }

    /**
     * Generate image thumbnail
     */
    private function generateImageThumbnail(string $imagePath, string $thumbnailPath): void
    {
        // Check if ImageMagick is available
        if (!extension_loaded('imagick')) {
            throw new \Exception('ImageMagick not available');
        }
        
        $imagick = new \Imagick($imagePath);
        $imagick->thumbnailImage(300, 300, true);
        $imagick->writeImage($thumbnailPath);
        $imagick->destroy();
    }

    /**
     * Get file statistics
     */
    public function getFileStatistics(?int $episodeId = null): array
    {
        $query = MediaFile::query();
        
        if ($episodeId) {
            $query->where('episode_id', $episodeId);
        }
        
        $total = $query->count();
        $totalSize = $query->sum('file_size');
        $byType = $query->selectRaw('file_type, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('file_type')
            ->get();
        
        return [
            'total_files' => $total,
            'total_size' => $totalSize,
            'formatted_total_size' => $this->formatFileSize($totalSize),
            'by_type' => $byType->map(function ($item) {
                return [
                    'file_type' => $item->file_type,
                    'count' => $item->count,
                    'total_size' => $item->total_size,
                    'formatted_size' => $this->formatFileSize($item->total_size)
                ];
            })
        ];
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Clean up orphaned files
     */
    public function cleanupOrphanedFiles(): int
    {
        $deleted = 0;
        $mediaFiles = MediaFile::whereDoesntHave('episode')->get();
        
        foreach ($mediaFiles as $mediaFile) {
            if ($this->deleteFile($mediaFile)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Get storage usage
     */
    public function getStorageUsage(): array
    {
        $totalSize = MediaFile::sum('file_size');
        $fileCount = MediaFile::count();
        
        return [
            'total_size' => $totalSize,
            'formatted_size' => $this->formatFileSize($totalSize),
            'file_count' => $fileCount,
            'average_size' => $fileCount > 0 ? $totalSize / $fileCount : 0,
            'formatted_average_size' => $fileCount > 0 ? $this->formatFileSize($totalSize / $fileCount) : '0 B'
        ];
    }
}
