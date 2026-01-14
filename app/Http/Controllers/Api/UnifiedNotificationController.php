<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnifiedNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Unified Notification Controller
 * 
 * Controller untuk menampilkan semua notifikasi dari berbagai sumber
 * dalam satu endpoint yang sama berdasarkan role user
 */
class UnifiedNotificationController extends Controller
{
    protected $unifiedNotificationService;

    public function __construct(UnifiedNotificationService $unifiedNotificationService)
    {
        $this->unifiedNotificationService = $unifiedNotificationService;
    }

    /**
     * Get all notifications for current user
     * GET /api/live-tv/unified-notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $filters = [
                'status' => $request->get('status'), // unread, read, all
                'type' => $request->get('type'),
                'priority' => $request->get('priority'),
                'limit' => $request->get('limit', 50),
            ];

            $notifications = $this->unifiedNotificationService->getAllNotificationsForUser(
                $user->id,
                $filters
            );

            // Get statistics
            $unreadCount = $this->unifiedNotificationService->getUnreadCount($user->id);
            $totalCount = count($notifications);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $notifications,
                    'statistics' => [
                        'total' => $totalCount,
                        'unread' => $unreadCount,
                        'read' => $totalCount - $unreadCount
                    ]
                ],
                'message' => 'Notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in UnifiedNotificationController@index: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     * GET /api/live-tv/unified-notifications/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $count = $this->unifiedNotificationService->getUnreadCount($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ],
                'message' => 'Unread count retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in UnifiedNotificationController@unreadCount: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     * POST /api/live-tv/unified-notifications/{id}/read
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Format: source_id (e.g., "main_123", "program_456", "leave_789")
            $result = $this->unifiedNotificationService->markAsRead($id, $user->id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or already read'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     * POST /api/live-tv/unified-notifications/mark-all-read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $count = $this->unifiedNotificationService->markAllAsRead($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'marked_count' => $count
                ],
                'message' => "{$count} notifications marked as read successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications by type
     * GET /api/live-tv/unified-notifications/by-type/{type}
     */
    public function getByType(string $type, Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $filters = [
                'type' => $type,
                'status' => $request->get('status'),
                'limit' => $request->get('limit', 50),
            ];

            $notifications = $this->unifiedNotificationService->getAllNotificationsForUser(
                $user->id,
                $filters
            );

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => "Notifications of type '{$type}' retrieved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications by type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

