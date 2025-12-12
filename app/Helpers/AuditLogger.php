<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Log user action untuk audit trail
     */
    public static function log(string $action, $resource = null, array $data = [], ?Request $request = null): void
    {
        $user = auth()->user();
        
        $logData = [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_role' => $user?->role,
            'action' => $action,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->id ?? null,
            'data' => $data,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'timestamp' => now()->toDateTimeString()
        ];

        Log::channel('audit')->info('User Action', $logData);
    }

    /**
     * Log critical action (approve, reject, delete, etc)
     */
    public static function logCritical(string $action, $resource = null, array $data = [], ?Request $request = null): void
    {
        $logData = array_merge([
            'severity' => 'critical',
            'action' => $action,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->id ?? null,
        ], $data);

        self::log($action, $resource, $logData, $request);
    }

    /**
     * Log file upload
     */
    public static function logFileUpload(string $fileType, string $fileName, int $fileSize, $resource = null, ?Request $request = null): void
    {
        self::log('file_upload', $resource, [
            'file_type' => $fileType,
            'file_name' => $fileName,
            'file_size' => $fileSize,
        ], $request);
    }

    /**
     * Log authentication events
     */
    public static function logAuth(string $event, bool $success = true, ?string $reason = null, ?Request $request = null): void
    {
        $user = auth()->user();
        
        Log::channel('audit')->info('Authentication Event', [
            'event' => $event,
            'success' => $success,
            'user_id' => $user?->id,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}

