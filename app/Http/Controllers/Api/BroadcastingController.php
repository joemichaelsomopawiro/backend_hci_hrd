<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastingSchedule;
use App\Models\BroadcastingWork;
use App\Models\Episode;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class BroadcastingController extends Controller
{
    /**
     * Get Broadcasting schedules for current user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = BroadcastingSchedule::with(['episode', 'createdBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->platform);
            }

            $schedules = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Broadcasting schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Broadcasting schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new Broadcasting schedule
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'platform' => 'required|in:youtube,website,tv,instagram,facebook,tiktok',
                'schedule_date' => 'required|date',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'tags' => 'nullable|array',
                'thumbnail_path' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = BroadcastingSchedule::create([
                'episode_id' => $request->episode_id,
                'platform' => $request->platform,
                'schedule_date' => $request->schedule_date,
                'title' => $request->title,
                'description' => $request->description,
                'tags' => $request->tags,
                'thumbnail_path' => $request->thumbnail_path,
                'status' => 'pending',
                'created_by' => $user->id
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($schedule, 'created');

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode', 'createdBy']),
                'message' => 'Broadcasting schedule created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating Broadcasting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Broadcasting schedule by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $schedule = BroadcastingSchedule::with(['episode', 'createdBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Broadcasting schedule retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Broadcasting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Broadcasting schedule
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->created_by !== $user->id && $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this schedule.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'tags' => 'nullable|array',
                'thumbnail_path' => 'nullable|string',
                'status' => 'sometimes|in:pending,scheduled,uploading,uploaded,published,failed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update($request->only([
                'title', 'description', 'tags', 'thumbnail_path', 'status'
            ]));

            // Notify on status change
            if ($request->has('status')) {
                $this->notifyRelatedRoles($schedule, 'status_changed');
            }

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode', 'createdBy']),
                'message' => 'Broadcasting schedule updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating Broadcasting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload content for broadcasting
     */
    public function upload(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload content for this schedule.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:mp4,avi,mov,jpg,jpeg,png|max:1024000' // 1GB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs("broadcasting/{$schedule->id}", $fileName, 'public');

            $schedule->update([
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'uploaded'
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($schedule, 'content_uploaded');

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode', 'createdBy']),
                'message' => 'Content uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish content
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to publish this schedule.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'url' => 'required|url',
                'published_at' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update([
                'url' => $request->url,
                'status' => 'published',
                'published_at' => $request->published_at ?? now()
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($schedule, 'published');

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode', 'createdBy']),
                'message' => 'Content published successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error publishing content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule playlist untuk episode
     * User: "masukkan ke jadwal playlist"
     */
    public function schedulePlaylist(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'playlist_name' => 'required|string|max:255',
                'playlist_items' => 'required|array|min:1',
                'playlist_items.*.title' => 'required|string|max:255',
                'playlist_items.*.duration' => 'nullable|integer',
                'playlist_items.*.order' => 'required|integer|min:1',
                'playlist_items.*.type' => 'required|in:main_episode,bts,highlight,advertisement',
                'scheduled_time' => 'nullable|date|after:now',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this schedule'
                ], 403);
            }

            // Create or update broadcasting work with playlist data
            $work = BroadcastingWork::updateOrCreate(
                ['episode_id' => $schedule->episode_id, 'work_type' => 'playlist'],
                [
                    'created_by' => $user->id,
                    'title' => $request->playlist_name,
                    'description' => $request->notes,
                    'playlist_data' => [
                        'playlist_name' => $request->playlist_name,
                        'items' => $request->playlist_items,
                        'total_items' => count($request->playlist_items),
                        'scheduled_time' => $request->scheduled_time,
                        'created_at' => now()
                    ],
                    'scheduled_time' => $request->scheduled_time,
                    'status' => $request->scheduled_time ? 'scheduled' : 'preparing'
                ]
            );

            // Update schedule if needed
            if ($work->scheduled_time) {
                $schedule->update([
                    'schedule_date' => $work->scheduled_time,
                    'upload_notes' => ($schedule->upload_notes ? $schedule->upload_notes . "\n\n" : '') . 
                              "Playlist scheduled: {$request->playlist_name}"
                ]);
            }

            // Notify related roles
            $this->notifyRelatedRoles($schedule, 'playlist_scheduled');

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => $schedule->load(['episode', 'createdBy']),
                    'playlist' => $work->load(['episode', 'createdBy'])
                ],
                'message' => 'Playlist scheduled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error scheduling playlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Broadcasting statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_schedules' => BroadcastingSchedule::count(),
                'pending_schedules' => BroadcastingSchedule::where('status', 'pending')->count(),
                'scheduled_schedules' => BroadcastingSchedule::where('status', 'scheduled')->count(),
                'uploaded_schedules' => BroadcastingSchedule::where('status', 'uploaded')->count(),
                'published_schedules' => BroadcastingSchedule::where('status', 'published')->count(),
                'failed_schedules' => BroadcastingSchedule::where('status', 'failed')->count(),
                'schedules_by_platform' => BroadcastingSchedule::selectRaw('platform, count(*) as count')
                    ->groupBy('platform')
                    ->get(),
                'recent_schedules' => BroadcastingSchedule::with(['episode'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Broadcasting statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Broadcasting statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify related roles about Broadcasting
     */
    private function notifyRelatedRoles(BroadcastingSchedule $schedule, string $action): void
    {
        $messages = [
            'created' => "New broadcasting schedule for episode {$schedule->episode->episode_number} has been created",
            'status_changed' => "Broadcasting schedule for episode {$schedule->episode->episode_number} status changed to {$schedule->status}",
            'content_uploaded' => "Content for episode {$schedule->episode->episode_number} has been uploaded",
            'published' => "Content for episode {$schedule->episode->episode_number} has been published"
        ];

        // Notify Producer
        $producers = \App\Models\User::where('role', 'Producer')->get();
        foreach ($producers as $producer) {
            Notification::create([
                'title' => 'Broadcasting ' . ucfirst($action),
                'message' => $messages[$action] ?? "Broadcasting {$action}",
                'type' => 'broadcasting_' . $action,
                'user_id' => $producer->id,
                'episode_id' => $schedule->episode_id
            ]);
        }

        // Notify Manager Program
        $managers = \App\Models\User::where('role', 'Manager Program')->get();
        foreach ($managers as $manager) {
            Notification::create([
                'title' => 'Broadcasting ' . ucfirst($action),
                'message' => $messages[$action] ?? "Broadcasting {$action}",
                'type' => 'broadcasting_' . $action,
                'user_id' => $manager->id,
                'episode_id' => $schedule->episode_id
            ]);
        }
    }
}













