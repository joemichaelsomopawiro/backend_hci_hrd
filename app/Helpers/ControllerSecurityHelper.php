<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ControllerSecurityHelper
{
    /**
     * Log CRUD operation untuk audit trail
     */
    public static function logCrud(string $action, $resource, array $data = [], ?Request $request = null): void
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

        Log::channel('audit')->info('CRUD Operation', $logData);
    }

    /**
     * Log create operation
     */
    public static function logCreate($resource, array $data = [], ?Request $request = null): void
    {
        $resourceType = $resource ? class_basename(get_class($resource)) : 'unknown';
        self::logCrud("{$resourceType}_created", $resource, $data, $request);
    }

    /**
     * Log update operation
     */
    public static function logUpdate($resource, array $oldData = [], array $newData = [], ?Request $request = null): void
    {
        $resourceType = $resource ? class_basename(get_class($resource)) : 'unknown';
        self::logCrud("{$resourceType}_updated", $resource, [
            'old_data' => $oldData,
            'new_data' => $newData,
            'changed_fields' => array_keys($newData)
        ], $request);
    }

    /**
     * Log delete operation
     */
    public static function logDelete($resource, array $data = [], ?Request $request = null): void
    {
        $resourceType = $resource ? class_basename(get_class($resource)) : 'unknown';
        self::logCrud("{$resourceType}_deleted", $resource, $data, $request);
    }

    /**
     * Log approval operation
     */
    public static function logApproval(string $action, $resource, array $data = [], ?Request $request = null): void
    {
        $resourceType = $resource ? class_basename(get_class($resource)) : 'unknown';
        AuditLogger::logCritical("{$resourceType}_{$action}", $resource, $data, $request);
    }

    /**
     * Log file operation
     */
    public static function logFileOperation(string $operation, string $fileType, string $fileName, int $fileSize, $resource = null, ?Request $request = null): void
    {
        AuditLogger::logFileUpload($fileType, $fileName, $fileSize, $resource, $request);
        self::logCrud("file_{$operation}", $resource, [
            'file_type' => $fileType,
            'file_name' => $fileName,
            'file_size' => $fileSize
        ], $request);
    }
}

