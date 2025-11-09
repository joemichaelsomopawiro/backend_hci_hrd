<?php

namespace App\Http\Controllers;

use App\Models\ProgramEpisode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Broadcasting Controller
 * 
 * Workflow:
 * 1. Receive file dari QC
 * 2. Receive thumbnail dari Desain Grafis
 * 3. Input metadata SEO (judul, deskripsi, tag)
 * 4. Upload ke YouTube dan Website
 * 5. Input link YouTube ke sistem
 * 6. Mark sebagai selesai (status: aired)
 */
class BroadcastingController extends Controller
{
    /**
     * Get episodes ready for broadcasting (passed QC)
     * GET /api/broadcasting/episodes/ready
     */
    public function getReadyEpisodes(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular', 'qc'])
                ->where('status', 'ready_to_air')
                ->whereHas('qc', function ($q) {
                    $q->where('status', 'approved');
                });

            // Filter by program
            if ($request->has('program_regular_id')) {
                $query->where('program_regular_id', $request->program_regular_id);
            }

            // Sort by air_date
            $episodes = $query->orderBy('air_date', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes ready for broadcasting retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific episode for broadcasting
     * GET /api/broadcasting/episodes/{id}
     */
    public function getEpisode(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::with([
                'programRegular',
                'qc',
                'editorWork',
                'designGrafisWork.thumbnails'
            ])->findOrFail($id);

            // Check if episode is ready for broadcasting
            if ($episode->status !== 'ready_to_air') {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode is not ready for broadcasting yet'
                ], 400);
            }

            // Get QC approval info
            $qcInfo = $episode->qc()->where('status', 'approved')->first();

            // Get thumbnail from design grafis
            $thumbnail = $episode->designGrafisWork->thumbnails()->where('type', 'youtube')->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'qc_approval' => $qcInfo,
                    'thumbnail' => $thumbnail,
                    'video_file' => $episode->editorWork->final_file_url ?? null
                ],
                'message' => 'Episode details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update metadata SEO untuk episode
     * PUT /api/broadcasting/episodes/{id}/metadata
     */
    public function updateMetadata(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'seo_title' => 'required|string|max:255',
                'seo_description' => 'required|string|max:5000',
                'seo_tags' => 'required|array',
                'seo_tags.*' => 'string|max:50',
                'youtube_category' => 'nullable|string|max:100',
                'youtube_privacy' => 'nullable|in:public,unlisted,private'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update episode metadata
            $episode->update([
                'seo_title' => $request->seo_title,
                'seo_description' => $request->seo_description,
                'seo_tags' => $request->seo_tags,
                'youtube_category' => $request->youtube_category,
                'youtube_privacy' => $request->youtube_privacy ?? 'public',
                'metadata_updated_at' => now(),
                'metadata_updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Metadata updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating metadata: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload to YouTube (placeholder - perlu YouTube API integration)
     * POST /api/broadcasting/episodes/{id}/upload-youtube
     */
    public function uploadToYouTube(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'video_file_url' => 'required|url',
                'thumbnail_url' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // TODO: Implement YouTube API upload
            // For now, just store the URL
            
            $episode->update([
                'youtube_upload_status' => 'uploading',
                'youtube_upload_started_at' => now(),
                'youtube_upload_by' => auth()->id()
            ]);

            // Simulate upload process (replace with real YouTube API)
            // This should be done via queue/job for long-running uploads

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'YouTube upload started'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading to YouTube: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input link YouTube setelah upload selesai
     * POST /api/broadcasting/episodes/{id}/youtube-link
     */
    public function setYouTubeLink(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'youtube_url' => 'required|url',
                'youtube_video_id' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update([
                'youtube_url' => $request->youtube_url,
                'youtube_video_id' => $request->youtube_video_id,
                'youtube_uploaded_at' => now(),
                'youtube_upload_status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'YouTube link saved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving YouTube link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload to Website
     * POST /api/broadcasting/episodes/{id}/upload-website
     */
    public function uploadToWebsite(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'website_url' => 'required|url',
                'website_publish_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update([
                'website_url' => $request->website_url,
                'website_published_at' => $request->website_publish_date ?? now(),
                'website_published_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode,
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
     * Mark episode sebagai selesai broadcasting (aired)
     * POST /api/broadcasting/episodes/{id}/complete
     */
    public function completeBroadcast(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            // Validasi semua step sudah selesai
            if (empty($episode->youtube_url)) {
                return response()->json([
                    'success' => false,
                    'message' => 'YouTube URL belum diinput'
                ], 400);
            }

            if (empty($episode->website_url)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Website URL belum diinput'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'broadcast_notes' => 'nullable|string|max:1000',
                'actual_air_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update episode status to aired
            $episode->update([
                'status' => 'aired',
                'broadcast_notes' => $request->broadcast_notes,
                'actual_air_date' => $request->actual_air_date ?? now(),
                'broadcast_completed_at' => now(),
                'broadcast_completed_by' => auth()->id()
            ]);

            // Create notification to Manager Distribusi
            // TODO: Implement notification system

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode aired successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error completing broadcast: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get broadcasting history/statistics
     * GET /api/broadcasting/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_aired' => ProgramEpisode::where('status', 'aired')->count(),
                'aired_this_month' => ProgramEpisode::where('status', 'aired')
                    ->whereMonth('actual_air_date', now()->month)
                    ->whereYear('actual_air_date', now()->year)
                    ->count(),
                'ready_to_broadcast' => ProgramEpisode::where('status', 'ready_to_air')->count(),
                'uploading' => ProgramEpisode::where('youtube_upload_status', 'uploading')->count(),
                'recent_aired' => ProgramEpisode::where('status', 'aired')
                    ->orderBy('actual_air_date', 'desc')
                    ->limit(10)
                    ->get(),
                'pending_broadcast' => ProgramEpisode::where('status', 'ready_to_air')
                    ->orderBy('air_date', 'asc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my tasks (for broadcaster user)
     * GET /api/broadcasting/my-tasks
     */
    public function getMyTasks(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $tasks = [
                'pending_metadata' => ProgramEpisode::where('status', 'ready_to_air')
                    ->whereNull('metadata_updated_at')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'pending_youtube_upload' => ProgramEpisode::where('status', 'ready_to_air')
                    ->whereNotNull('metadata_updated_at')
                    ->whereNull('youtube_url')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'pending_website_upload' => ProgramEpisode::where('status', 'ready_to_air')
                    ->whereNotNull('youtube_url')
                    ->whereNull('website_url')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'pending_completion' => ProgramEpisode::where('status', 'ready_to_air')
                    ->whereNotNull('youtube_url')
                    ->whereNotNull('website_url')
                    ->whereNull('broadcast_completed_at')
                    ->orderBy('air_date', 'asc')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'Tasks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tasks: ' . $e->getMessage()
            ], 500);
        }
    }
}

