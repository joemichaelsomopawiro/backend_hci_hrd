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
     * Terima Pekerjaan - Broadcasting terima pekerjaan
     * POST /api/live-tv/broadcasting/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is pending'
                ], 400);
            }

            $work->update([
                'status' => 'preparing',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now process the work.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ke YouTube dengan SEO metadata
     * POST /api/live-tv/broadcasting/works/{id}/upload-youtube
     */
    public function uploadYouTube(Request $request, int $id): JsonResponse
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
                'title' => 'required|string|max:100',
                'description' => 'required|string|max:5000',
                'tags' => 'required|array|min:1',
                'tags.*' => 'string|max:50',
                'thumbnail_path' => 'required|string',
                'youtube_video_id' => 'nullable|string',
                'youtube_url' => 'nullable|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            // Update work with YouTube metadata
            $work->update([
                'title' => $request->title,
                'description' => $request->description,
                'metadata' => array_merge($work->metadata ?? [], [
                    'youtube' => [
                        'title' => $request->title,
                        'description' => $request->description,
                        'tags' => $request->tags,
                        'thumbnail_path' => $request->thumbnail_path,
                        'uploaded_at' => now()
                    ]
                ]),
                'thumbnail_path' => $request->thumbnail_path,
                'youtube_video_id' => $request->youtube_video_id,
                'youtube_url' => $request->youtube_url,
                'status' => $request->youtube_url ? 'uploading' : 'preparing'
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'YouTube upload metadata saved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading to YouTube: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ke Website
     * POST /api/live-tv/broadcasting/works/{id}/upload-website
     */
    public function uploadWebsite(Request $request, int $id): JsonResponse
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
                'website_url' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            $work->update([
                'website_url' => $request->website_url,
                'metadata' => array_merge($work->metadata ?? [], [
                    'website' => [
                        'url' => $request->website_url,
                        'uploaded_at' => now()
                    ]
                ])
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Website URL saved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading to website: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input Link YouTube ke Sistem
     * POST /api/live-tv/broadcasting/works/{id}/input-youtube-link
     */
    public function inputYouTubeLink(Request $request, int $id): JsonResponse
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
                'youtube_url' => 'required|url',
                'youtube_video_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            $work->update([
                'youtube_url' => $request->youtube_url,
                'youtube_video_id' => $request->youtube_video_id,
                'published_time' => now(),
                'status' => 'published'
            ]);

            // Update episode with YouTube link
            $episode = $work->episode;
            if ($episode) {
                $episode->update([
                    'youtube_url' => $request->youtube_url,
                    'youtube_video_id' => $request->youtube_video_id
                ]);
            }

            // Notify Promosi (setelah Broadcasting selesai)
            $promosiUsers = \App\Models\User::where('role', 'Promotion')->get();
            foreach ($promosiUsers as $promosiUser) {
                Notification::create([
                    'user_id' => $promosiUser->id,
                    'type' => 'broadcasting_completed_promosi_notification',
                    'title' => 'Broadcasting Selesai - Siap untuk Promosi',
                    'message' => "Broadcasting telah menyelesaikan upload untuk Episode {$episode->episode_number}. YouTube: {$request->youtube_url}, Website: {$work->website_url}",
                    'data' => [
                        'episode_id' => $work->episode_id,
                        'broadcasting_work_id' => $work->id,
                        'youtube_url' => $request->youtube_url,
                        'website_url' => $work->website_url
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'YouTube link saved successfully. Promosi has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving YouTube link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule playlist for broadcasting work
     * POST /api/live-tv/roles/broadcasting/works/{id}/schedule-playlist
     */
    public function scheduleWorkPlaylist(Request $request, int $id): JsonResponse
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
                'playlist_date' => 'required|date',
                'playlist_time' => 'required|date_format:H:i:s'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            // Combine date and time into datetime
            $scheduledDateTime = $request->playlist_date . ' ' . $request->playlist_time;

            // Update playlist_data if exists
            $playlistData = $work->playlist_data ?? [];
            $playlistData['scheduled_date'] = $request->playlist_date;
            $playlistData['scheduled_time'] = $request->playlist_time;
            $playlistData['scheduled_datetime'] = $scheduledDateTime;
            $playlistData['updated_at'] = now()->toDateTimeString();

            // Update work with scheduled time
            $work->update([
                'scheduled_time' => $scheduledDateTime,
                'status' => 'scheduled',
                'playlist_data' => $playlistData
            ]);

            // Notify related roles
            $this->notifyWorkPlaylistScheduled($work);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
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
     * Notify related roles about playlist scheduled
     */
    private function notifyWorkPlaylistScheduled(BroadcastingWork $work): void
    {
        // Notify Producer
        $producers = \App\Models\User::where('role', 'Producer')->get();
        foreach ($producers as $producer) {
            Notification::create([
                'user_id' => $producer->id,
                'type' => 'broadcasting_playlist_scheduled',
                'title' => 'Playlist Dijadwalkan',
                'message' => "Playlist untuk Episode {$work->episode->episode_number} telah dijadwalkan pada {$work->scheduled_time->format('d M Y H:i')}.",
                'data' => [
                    'broadcasting_work_id' => $work->id,
                    'episode_id' => $work->episode_id,
                    'scheduled_time' => $work->scheduled_time->toDateTimeString()
                ]
            ]);
        }

        // Notify Manager Program
        $managers = \App\Models\User::where('role', 'Manager Program')->get();
        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'broadcasting_playlist_scheduled',
                'title' => 'Playlist Dijadwalkan',
                'message' => "Playlist untuk Episode {$work->episode->episode_number} telah dijadwalkan pada {$work->scheduled_time->format('d M Y H:i')}.",
                'data' => [
                    'broadcasting_work_id' => $work->id,
                    'episode_id' => $work->episode_id,
                    'scheduled_time' => $work->scheduled_time->toDateTimeString()
                ]
            ]);
        }
    }

    /**
     * Selesaikan Pekerjaan - Broadcasting selesaikan pekerjaan
     * POST /api/live-tv/broadcasting/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            // Validate required fields
            if (!$work->youtube_url || !$work->website_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'YouTube URL and Website URL must be provided before completing work'
                ], 400);
            }

            $work->update([
                'status' => 'completed',
                'published_time' => now()
            ]);

            // Notify Promosi
            $promosiUsers = \App\Models\User::where('role', 'Promotion')->get();
            foreach ($promosiUsers as $promosiUser) {
                Notification::create([
                    'user_id' => $promosiUser->id,
                    'type' => 'broadcasting_work_completed',
                    'title' => 'Broadcasting Work Selesai',
                    'message' => "Broadcasting telah menyelesaikan pekerjaan untuk Episode {$work->episode->episode_number}.",
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'youtube_url' => $work->youtube_url,
                        'website_url' => $work->website_url
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Promosi has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
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













