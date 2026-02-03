<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastingWork;
use App\Models\Episode;
use App\Models\Notification;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BroadcastingController extends Controller
{
    /**
     * Get Broadcasting works for current user
     * GET /api/live-tv/broadcasting/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Optimize query with eager loading
            $query = BroadcastingWork::with([
                'episode.program',
                'episode.program.productionTeam',
                'createdBy'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            // Get works for current user or all preparing works (available for acceptance)
            $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('status', 'preparing'); // Preparing works are available for any Broadcasting to accept
            });

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Broadcasting works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving broadcasting works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new Broadcasting work
     * POST /api/live-tv/broadcasting/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:youtube_upload,website_upload,playlist_update,schedule_update,metadata_update,thumbnail_upload,description_update,main_episode',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::create([
                'episode_id' => $request->episode_id,
                'created_by' => $user->id,
                'work_type' => $request->work_type,
                'title' => $request->title,
                'description' => $request->description,
                'metadata' => $request->metadata,
                'status' => 'preparing'
            ]);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Broadcasting work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating broadcasting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Broadcasting work by ID
     * GET /api/live-tv/broadcasting/works/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::with([
                'episode.program',
                'episode.program.productionTeam',
                'createdBy'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $work,
                'message' => 'Broadcasting work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving broadcasting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Broadcasting work
     * PUT /api/live-tv/broadcasting/works/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this work.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array',
                'status' => 'sometimes|in:preparing,pending,uploading,processing,published,scheduled,failed,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work->update($request->only([
                'title', 'description', 'metadata', 'status'
            ]));

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Broadcasting work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating broadcasting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload file
     * POST /api/live-tv/broadcasting/schedules/{id}/upload
     */
    public function upload(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload files for this work.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:mp4,avi,mov|max:10240000' // 10GB max
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
            $filePath = $file->storeAs("broadcasting/{$work->id}", $fileName, 'public');

            $work->update([
                'video_file_path' => $filePath
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'File uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish work
     * POST /api/live-tv/broadcasting/schedules/{id}/publish
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to publish this work.'
                ], 403);
            }

            if (!$work->youtube_url && !$work->website_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload to YouTube or website before publishing.'
                ], 400);
            }

            $work->markAsPublished();

            // Notify Manager Program
            $episode = $work->episode;
            if ($episode->program && $episode->program->manager_program_id) {
                Notification::create([
                    'user_id' => $episode->program->manager_program_id,
                    'type' => 'broadcasting_published',
                    'title' => 'Episode Dipublikasikan',
                    'message' => "Episode {$episode->episode_number} telah dipublikasikan oleh Broadcasting.",
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'episode_id' => $episode->id,
                        'youtube_url' => $work->youtube_url,
                        'website_url' => $work->website_url
                    ]
                ]);
            }

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work published successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error publishing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule playlist
     * POST /api/live-tv/broadcasting/schedules/{id}/schedule-playlist
     */
    public function schedulePlaylist(Request $request, int $id): JsonResponse
    {
        return $this->scheduleWorkPlaylist($request, $id);
    }

    /**
     * Get statistics
     * GET /api/live-tv/broadcasting/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_works' => BroadcastingWork::where('created_by', $user->id)->count(),
                'published_works' => BroadcastingWork::where('created_by', $user->id)
                    ->where('status', 'published')->count(),
                'uploading_works' => BroadcastingWork::where('created_by', $user->id)
                    ->where('status', 'uploading')->count(),
                'scheduled_works' => BroadcastingWork::where('created_by', $user->id)
                    ->where('status', 'scheduled')->count(),
                'preparing_works' => BroadcastingWork::where('status', 'preparing')->count(),
                'works_by_type' => BroadcastingWork::where('created_by', $user->id)
                    ->selectRaw('work_type, count(*) as count')
                    ->groupBy('work_type')
                    ->get(),
                'recent_works' => BroadcastingWork::where('created_by', $user->id)
                    ->with(['episode'])
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
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Broadcasting accept work
     * POST /api/live-tv/broadcasting/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = BroadcastingWork::with(['episode'])->findOrFail($id);

            // Check if work can be accepted (status should be preparing)
            if ($work->status !== 'preparing') {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be accepted. Current status: {$work->status}. Work must be in 'preparing' status."
                ], 400);
            }

            // Update work status to preparing and assign to user
            $work->update([
                'status' => 'preparing',
                'created_by' => $user->id
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'broadcasting_work_accepted',
                    'title' => 'Broadcasting Work Accepted',
                    'message' => "Broadcasting {$user->name} telah menerima tugas untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'episode_id' => $episode->id,
                        'broadcasting_user_id' => $user->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now proceed with broadcasting tasks.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ke YouTube dengan SEO
     * POST /api/live-tv/broadcasting/works/{id}/upload-youtube
     */
    public function uploadYouTube(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'youtube_url' => 'required|url',
                'youtube_video_id' => 'nullable|string|max:50',
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:5000',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'thumbnail_path' => 'nullable|string',
                'category_id' => 'nullable|string|max:50',
                'privacy_status' => 'nullable|in:private,public,unlisted'
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
                    'message' => 'Unauthorized to upload YouTube for this work.'
                ], 403);
            }

            // Update work with YouTube data and SEO metadata
            $metadata = $work->metadata ?? [];
            $metadata['youtube'] = [
                'title' => $request->title,
                'description' => $request->description,
                'tags' => $request->tags ?? [],
                'category_id' => $request->category_id,
                'privacy_status' => $request->privacy_status ?? 'public',
                'uploaded_at' => now()->toDateTimeString()
            ];

            // Use thumbnail from request or from work if available
            $thumbnailPath = $request->thumbnail_path ?? $work->thumbnail_path;

            $work->update([
                'title' => $request->title, // Update title sesuai SEO
                'description' => $request->description,
                'youtube_url' => $request->youtube_url,
                'youtube_video_id' => $request->youtube_video_id ?? $this->extractYouTubeVideoId($request->youtube_url),
                'thumbnail_path' => $thumbnailPath,
                'metadata' => $metadata,
                'status' => 'uploading'
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'broadcasting_youtube_uploaded',
                    'title' => 'Video Uploaded ke YouTube',
                    'message' => "Broadcasting telah mengupload Episode {$episode->episode_number} ke YouTube.",
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'episode_id' => $episode->id,
                        'youtube_url' => $request->youtube_url,
                        'title' => $request->title
                    ]
                ]);
            }

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'YouTube upload completed successfully. Video has been uploaded with SEO optimization.'
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
            
            if (!$user || $user->role !== 'Broadcasting') {
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

            $work = BroadcastingWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload website for this work.'
                ], 403);
            }

            // Update work with website URL
            $metadata = $work->metadata ?? [];
            $metadata['website'] = [
                'url' => $request->website_url,
                'uploaded_at' => now()->toDateTimeString()
            ];

            $work->update([
                'website_url' => $request->website_url,
                'metadata' => $metadata
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'broadcasting_website_uploaded',
                    'title' => 'Video Uploaded ke Website',
                    'message' => "Broadcasting telah mengupload Episode {$episode->episode_number} ke website.",
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'episode_id' => $episode->id,
                        'website_url' => $request->website_url
                    ]
                ]);
            }

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Website upload completed successfully.'
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
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'youtube_url' => 'required|url',
                'youtube_video_id' => 'nullable|string|max:50'
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
                    'message' => 'Unauthorized to input YouTube link for this work.'
                ], 403);
            }

            // Extract video ID if not provided
            $videoId = $request->youtube_video_id ?? $this->extractYouTubeVideoId($request->youtube_url);

            // Update work with YouTube link
            $work->update([
                'youtube_url' => $request->youtube_url,
                'youtube_video_id' => $videoId
            ]);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'YouTube link input successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error inputting YouTube link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Masukan ke Jadwal Playlist
     * POST /api/live-tv/broadcasting/works/{id}/schedule-work-playlist
     */
    public function scheduleWorkPlaylist(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'playlist_data' => 'required|array',
                'scheduled_time' => 'nullable|date',
                'playlist_name' => 'nullable|string|max:255',
                'playlist_position' => 'nullable|integer|min:0'
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
                    'message' => 'Unauthorized to schedule playlist for this work.'
                ], 403);
            }

            // Update work with playlist data
            $playlistData = array_merge($work->playlist_data ?? [], [
                'playlist_name' => $request->playlist_name,
                'playlist_position' => $request->playlist_position,
                'scheduled_at' => $request->scheduled_time ? now()->parse($request->scheduled_time)->toDateTimeString() : null,
                'added_at' => now()->toDateTimeString()
            ]);

            $work->update([
                'playlist_data' => array_merge($playlistData, $request->playlist_data),
                'scheduled_time' => $request->scheduled_time ? now()->parse($request->scheduled_time) : $work->scheduled_time,
                'status' => 'scheduled'
            ]);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work scheduled to playlist successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error scheduling playlist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan
     * POST /api/live-tv/broadcasting/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Broadcasting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'completion_notes' => 'nullable|string|max:1000',
                'youtube_url' => 'nullable|url',
                'website_url' => 'nullable|url'
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
                    'message' => 'Unauthorized to complete this work.'
                ], 403);
            }

            // Update URLs if provided in the request
            if ($request->has('youtube_url')) {
                $work->youtube_url = $request->youtube_url;
                $work->youtube_video_id = $this->extractYouTubeVideoId($request->youtube_url);
            }
            if ($request->has('website_url')) {
                $work->website_url = $request->website_url;
            }

            // Validate that YouTube or Website URL is set
            if (!$work->youtube_url && !$work->website_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload to YouTube or website before completing work.'
                ], 400);
            }

            // Update work status to completed/published
            $work->update([
                'status' => 'published',
                'published_time' => now(),
                'metadata' => array_merge($work->metadata ?? [], [
                    'completion_notes' => $request->completion_notes,
                    'completed_at' => now()->toDateTimeString(),
                    'completed_by' => $user->id
                ])
            ]);

            // Notify Manager Program
            $episode = $work->episode;
            if ($episode->program && $episode->program->manager_program_id) {
                Notification::create([
                    'user_id' => $episode->program->manager_program_id,
                    'type' => 'broadcasting_work_completed',
                    'title' => 'Pekerjaan Broadcasting Selesai',
                    'message' => "Broadcasting telah menyelesaikan pekerjaan untuk Episode {$episode->episode_number}. Video telah dipublikasikan.",
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'episode_id' => $episode->id,
                        'youtube_url' => $work->youtube_url,
                        'website_url' => $work->website_url,
                        'completion_notes' => $request->completion_notes
                    ]
                ]);
            }

            // Auto-create PromotionWork untuk sharing tasks
            $promosiUsers = \App\Models\User::where('role', 'Promotion')->get();
            
            if ($promosiUsers->isNotEmpty()) {
                // Create PromotionWork untuk Share Facebook
                $shareFacebookWork = \App\Models\PromotionWork::firstOrCreate(
                    [
                        'episode_id' => $episode->id,
                        'work_type' => 'share_facebook'
                    ],
                    [
                        'title' => "Share Link Website ke Facebook - Episode {$episode->episode_number}",
                        'description' => "Share link website Episode {$episode->episode_number} ke Facebook. YouTube URL dan Website URL sudah tersedia.",
                        'status' => 'planning',
                        'created_by' => $promosiUsers->first()->id,
                        'social_media_links' => [
                            'youtube_url' => $work->youtube_url,
                            'website_url' => $work->website_url,
                            'thumbnail_path' => $work->thumbnail_path
                        ]
                    ]
                );

                // Create PromotionWork untuk Share WA Group
                $shareWAGroupWork = \App\Models\PromotionWork::firstOrCreate(
                    [
                        'episode_id' => $episode->id,
                        'work_type' => 'share_wa_group'
                    ],
                    [
                        'title' => "Share ke Grup Promosi WA - Episode {$episode->episode_number}",
                        'description' => "Share link Episode {$episode->episode_number} ke grup Promosi WA. YouTube URL dan Website URL sudah tersedia.",
                        'status' => 'planning',
                        'created_by' => $promosiUsers->first()->id,
                        'social_media_links' => [
                            'youtube_url' => $work->youtube_url,
                            'website_url' => $work->website_url,
                            'thumbnail_path' => $work->thumbnail_path
                        ]
                    ]
                );

                // Update existing Story IG dan Reels Facebook dengan YouTube & Website URL jika ada
                \App\Models\PromotionWork::where('episode_id', $episode->id)
                    ->whereIn('work_type', ['story_ig', 'reels_facebook'])
                    ->get()
                    ->each(function($promoWork) use ($work) {
                        $socialLinks = $promoWork->social_media_links ?? [];
                        $socialLinks['youtube_url'] = $work->youtube_url;
                        $socialLinks['website_url'] = $work->website_url;
                        $promoWork->update(['social_media_links' => $socialLinks]);
                    });

                // Notify Promosi dengan YouTube URL dan Website URL untuk sharing
                $notifications = [];
                $now = now();
                foreach ($promosiUsers as $promosiUser) {
                    $notifications[] = [
                        'user_id' => $promosiUser->id,
                        'type' => 'broadcasting_published_promosi_sharing',
                        'title' => 'Video Dipublikasikan - Siap untuk Sharing',
                        'message' => "Broadcasting telah mempublikasikan Episode {$episode->episode_number}. YouTube URL dan Website URL sudah tersedia. PromotionWork untuk Share Facebook dan Share WA Group sudah dibuat. Silakan share ke Facebook, Story IG, Reels Facebook, dan grup Promosi WA.",
                        'data' => json_encode([
                            'broadcasting_work_id' => $work->id,
                            'episode_id' => $episode->id,
                            'youtube_url' => $work->youtube_url,
                            'website_url' => $work->website_url,
                            'thumbnail_path' => $work->thumbnail_path,
                            'title' => $work->title,
                            'description' => $work->description,
                            'share_facebook_work_id' => $shareFacebookWork->id,
                            'share_wa_group_work_id' => $shareWAGroupWork->id
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notifications)) {
                    \App\Models\Notification::insert($notifications);
                }
            }

            // Update Episode status if needed
            // You can add episode status update logic here if required

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Broadcasting work completed successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to extract YouTube video ID from URL
     */
    private function extractYouTubeVideoId(string $url): ?string
    {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1] ?? null;
            }
        }

        return null;
    }
}
