<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class PrNotificationController extends Controller
{
    /**
     * Get unread notification count
     * GET /api/pr/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $count = Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of notifications
     * GET /api/pr/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $query = Notification::where('user_id', $user->id);

            // Filter optional
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     * PUT /api/pr/notifications/{id}/read
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $notification = Notification::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            $notification->update([
                'status' => 'read',
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     * PUT /api/pr/notifications/mark-all-read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->update([
                    'status' => 'read',
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
