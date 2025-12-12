<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadHelper
{
    /**
     * Validate dan upload file dengan security checks
     */
    public static function uploadFile(
        UploadedFile $file,
        string $directory,
        array $allowedMimeTypes,
        array $allowedExtensions,
        int $maxSize,
        bool $usePrivateStorage = true
    ): array {
        // 1. Validate MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \Exception('File type tidak diizinkan. MIME type: ' . $mimeType);
        }

        // 2. Validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('File extension tidak diizinkan. Extension: ' . $extension);
        }

        // 3. Validate file size
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size terlalu besar. Max size: ' . ($maxSize / 1024 / 1024) . 'MB');
        }

        // 4. Validate file name (prevent path traversal)
        $originalName = $file->getClientOriginalName();
        if (!SecurityHelper::isSafeFileName($originalName)) {
            throw new \Exception('Nama file tidak valid. Detected path traversal attempt.');
        }

        // 5. Generate safe file name
        $safeFileName = SecurityHelper::generateSafeFileName($originalName);

        // 6. Store file
        $disk = $usePrivateStorage ? 'private' : 'public';
        $path = $file->storeAs($directory, $safeFileName, $disk);

        return [
            'file_path' => $path,
            'file_name' => $safeFileName,
            'original_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'url' => $usePrivateStorage ? null : Storage::disk($disk)->url($path)
        ];
    }

    /**
     * Validate audio file
     */
    public static function validateAudioFile(UploadedFile $file, int $maxSizeMB = 50): array
    {
        $allowedMimeTypes = ['audio/mpeg', 'audio/wav', 'audio/aac', 'audio/x-wav', 'audio/wave'];
        $allowedExtensions = ['mp3', 'wav', 'aac', 'm4a'];
        $maxSize = $maxSizeMB * 1024 * 1024; // Convert to bytes

        return self::uploadFile($file, 'audio', $allowedMimeTypes, $allowedExtensions, $maxSize, true);
    }

    /**
     * Validate video file
     */
    public static function validateVideoFile(UploadedFile $file, int $maxSizeMB = 100): array
    {
        $allowedMimeTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
        $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv'];
        $maxSize = $maxSizeMB * 1024 * 1024;

        return self::uploadFile($file, 'videos', $allowedMimeTypes, $allowedExtensions, $maxSize, true);
    }

    /**
     * Validate image file
     */
    public static function validateImageFile(UploadedFile $file, int $maxSizeMB = 5): array
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/gif'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxSize = $maxSizeMB * 1024 * 1024;

        return self::uploadFile($file, 'images', $allowedMimeTypes, $allowedExtensions, $maxSize, false);
    }

    /**
     * Validate document file
     */
    public static function validateDocumentFile(UploadedFile $file, int $maxSizeMB = 10): array
    {
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $allowedExtensions = ['pdf', 'doc', 'docx'];
        $maxSize = $maxSizeMB * 1024 * 1024;

        return self::uploadFile($file, 'documents', $allowedMimeTypes, $allowedExtensions, $maxSize, true);
    }
}

