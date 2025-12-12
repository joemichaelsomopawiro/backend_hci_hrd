<?php

namespace App\Helpers;

class SecurityHelper
{
    /**
     * Sanitize string input untuk prevent XSS
     */
    public static function sanitizeString(string $input, bool $allowHtml = false): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        if ($allowHtml) {
            // For HTML content, use HTMLPurifier or strip_tags with allowed tags
            return strip_tags($input, '<p><br><strong><em><ul><ol><li><a>');
        }
        
        // Basic XSS prevention
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate safe file name untuk prevent path traversal
     */
    public static function generateSafeFileName(string $originalName): string
    {
        // Remove path traversal attempts
        $fileName = basename($originalName);
        
        // Remove dangerous characters
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        
        // Limit length
        $fileName = substr($fileName, 0, 255);
        
        // Add timestamp and random string
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        
        return time() . '_' . \Illuminate\Support\Str::random(10) . '_' . $nameWithoutExt . '.' . $extension;
    }

    /**
     * Validate file MIME type
     */
    public static function validateMimeType($file, array $allowedMimeTypes): bool
    {
        $mimeType = $file->getMimeType();
        return in_array($mimeType, $allowedMimeTypes);
    }

    /**
     * Validate file extension
     */
    public static function validateFileExtension($file, array $allowedExtensions): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Check if file name is safe (no path traversal)
     */
    public static function isSafeFileName(string $fileName): bool
    {
        // Check for path traversal attempts
        if (strpos($fileName, '..') !== false) {
            return false;
        }
        
        // Check for directory separators
        if (strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
            return false;
        }
        
        // Check for null bytes
        if (strpos($fileName, "\0") !== false) {
            return false;
        }
        
        return true;
    }

    /**
     * Sanitize array input recursively
     */
    public static function sanitizeArray(array $input, bool $allowHtml = false): array
    {
        array_walk_recursive($input, function (&$value) use ($allowHtml) {
            if (is_string($value)) {
                $value = self::sanitizeString($value, $allowHtml);
            }
        });
        
        return $input;
    }
}

