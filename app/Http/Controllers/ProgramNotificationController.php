<?php

namespace App\Http\Controllers;

use App\Models\ProgramNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProgramNotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProgramNotification::with(['user', 'program', 'episode', 'schedule'])
                ->where('user_id', auth()->id());

            // Filter berdasarkan type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter berdasarkan read status
            if ($request->has('is_read')) {
                $query->where('is_read', $request->is_read);
            }

            // Filter berdasarkan program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            // Filter berdasarkan episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Search
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|in:deadline,reminder,assignment,status_change,general',
                'user_id' => 'required|exists:users,id',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'schedule_id' => 'nullable|exists:schedules,id',
                'scheduled_at' => 'nullable|date|after:now',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $notification = ProgramNotification::create($request->all());
            $notification->load(['user', 'program', 'episode', 'schedule']);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $notification = ProgramNotification::with(['user', 'program', 'episode', 'schedule'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $notification = ProgramNotification::where('user_id', auth()->id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'message' => 'sometimes|required|string',
                'type' => 'sometimes|required|in:info,warning,success,error,reminder',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'schedule_id' => 'nullable|exists:schedules,id',
                'scheduled_at' => 'nullable|date|after:now',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $notification->update($request->all());
            $notification->load(['user', 'program', 'episode', 'schedule']);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $notification = ProgramNotification::where('user_id', auth()->id())->findOrFail($id);
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $notification = ProgramNotification::where('user_id', auth()->id())->findOrFail($id);
            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking notification as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            ProgramNotification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking all notifications as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $count = ProgramNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $count],
                'message' => 'Unread count retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getUnreadCount: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unread count: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notifications
     */
    public function getUnread(Request $request): JsonResponse
    {
        try {
            $notifications = ProgramNotification::with(['user', 'program', 'episode', 'schedule'])
                ->where('user_id', auth()->id())
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Unread notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unread notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scheduled notifications
     */
    public function getScheduled(Request $request): JsonResponse
    {
        try {
            $notifications = ProgramNotification::with(['user', 'program', 'episode', 'schedule'])
                ->where('user_id', auth()->id())
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Scheduled notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving scheduled notifications: ' . $e->getMessage()
            ], 500);
        }
    }
}
