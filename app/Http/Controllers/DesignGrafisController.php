<?php

namespace App\Http\Controllers;

use App\Models\ProgramEpisode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Design Grafis Controller
 * 
 * Workflow:
 * 1. Receive foto talent dari Promosi
 * 2. Receive file produksi dari Produksi
 * 3. Buat thumbnail (YT & BTS)
 * 4. Upload hasil ke sistem
 */
class DesignGrafisController extends Controller
{
    /**
     * Get episodes pending thumbnail
     * GET /api/design-grafis/episodes/pending
     */
    public function getPendingEpisodes(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular', 'promosi'])
                ->where('status', 'post_production')
                ->where(function($q) {
                    $q->whereNull('thumbnail_youtube')
                      ->orWhereNull('thumbnail_bts');
                });

            // Filter by program
            if ($request->has('program_regular_id')) {
                $query->where('program_regular_id', $request->program_regular_id);
            }

            $episodes = $query->orderBy('air_date', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes pending thumbnail retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific episode for design
     * GET /api/design-grafis/episodes/{id}
     */
    public function getEpisode(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::with([
                'programRegular',
                'promosi',
                'produksi'
            ])->findOrFail($id);

            // Get assets available
            $assets = [
                'talent_photos' => $episode->promosi->talent_photos ?? [],
                'bts_photos' => $episode->promosi->bts_photos ?? [],
                'production_files' => $episode->raw_file_urls ?? []
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'assets' => $assets
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
     * Receive assets dari Promosi & Produksi
     * POST /api/design-grafis/episodes/{id}/receive-assets
     */
    public function receiveAssets(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'talent_photo_urls' => 'nullable|array',
                'talent_photo_urls.*' => 'url',
                'bts_photo_urls' => 'nullable|array',
                'bts_photo_urls.*' => 'url',
                'production_file_urls' => 'nullable|array',
                'production_file_urls.*' => 'url',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Store assets info
            $episode->update([
                'design_assets_talent_photos' => $request->talent_photo_urls,
                'design_assets_bts_photos' => $request->bts_photo_urls,
                'design_assets_production_files' => $request->production_file_urls,
                'design_assets_received_at' => now(),
                'design_assets_received_by' => auth()->id(),
                'design_assets_notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Assets received successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error receiving assets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload thumbnail YouTube
     * POST /api/design-grafis/episodes/{id}/upload-thumbnail-youtube
     */
    public function uploadThumbnailYouTube(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'thumbnail_file' => 'required|file|mimes:jpg,jpeg,png|max:2048', // Max 2MB
                'thumbnail_url' => 'nullable|url',
                'design_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload file to storage
            if ($request->hasFile('thumbnail_file')) {
                $path = $request->file('thumbnail_file')->store('thumbnails/youtube', 'public');
                $url = Storage::url($path);
            } else {
                $url = $request->thumbnail_url;
            }

            // Update episode
            $episode->update([
                'thumbnail_youtube' => $url,
                'thumbnail_youtube_uploaded_at' => now(),
                'thumbnail_youtube_uploaded_by' => auth()->id(),
                'thumbnail_youtube_notes' => $request->design_notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'YouTube thumbnail uploaded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error uploading thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload thumbnail BTS
     * POST /api/design-grafis/episodes/{id}/upload-thumbnail-bts
     */
    public function uploadThumbnailBTS(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'thumbnail_file' => 'required|file|mimes:jpg,jpeg,png|max:2048',
                'thumbnail_url' => 'nullable|url',
                'design_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload file to storage
            if ($request->hasFile('thumbnail_file')) {
                $path = $request->file('thumbnail_file')->store('thumbnails/bts', 'public');
                $url = Storage::url($path);
            } else {
                $url = $request->thumbnail_url;
            }

            // Update episode
            $episode->update([
                'thumbnail_bts' => $url,
                'thumbnail_bts_uploaded_at' => now(),
                'thumbnail_bts_uploaded_by' => auth()->id(),
                'thumbnail_bts_notes' => $request->design_notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'BTS thumbnail uploaded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error uploading thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete design work
     * POST /api/design-grafis/episodes/{id}/complete
     */
    public function completeDesign(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            // Validate both thumbnails are uploaded
            if (empty($episode->thumbnail_youtube)) {
                return response()->json([
                    'success' => false,
                    'message' => 'YouTube thumbnail belum diupload'
                ], 400);
            }

            if (empty($episode->thumbnail_bts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'BTS thumbnail belum diupload'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mark design work as completed
            $episode->update([
                'design_completed_at' => now(),
                'design_completed_by' => auth()->id(),
                'design_completion_notes' => $request->completion_notes
            ]);

            // Notify Broadcasting (mereka butuh thumbnail untuk upload)
            // TODO: Implement notification

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Design work completed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error completing design: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my design tasks
     * GET /api/design-grafis/my-tasks
     */
    public function getMyTasks(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $tasks = [
                'pending_youtube_thumbnail' => ProgramEpisode::where('status', 'post_production')
                    ->whereNull('thumbnail_youtube')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'pending_bts_thumbnail' => ProgramEpisode::where('status', 'post_production')
                    ->whereNull('thumbnail_bts')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'pending_completion' => ProgramEpisode::where('status', 'post_production')
                    ->whereNotNull('thumbnail_youtube')
                    ->whereNotNull('thumbnail_bts')
                    ->whereNull('design_completed_at')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'recent_completed' => ProgramEpisode::whereNotNull('design_completed_at')
                    ->where('design_completed_by', $user->id)
                    ->orderBy('design_completed_at', 'desc')
                    ->limit(10)
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

    /**
     * Get statistics
     * GET /api/design-grafis/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'pending_youtube' => ProgramEpisode::whereNull('thumbnail_youtube')->count(),
                'pending_bts' => ProgramEpisode::whereNull('thumbnail_bts')->count(),
                'completed_today' => ProgramEpisode::whereDate('design_completed_at', today())->count(),
                'completed_this_week' => ProgramEpisode::whereBetween('design_completed_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
                'completed_this_month' => ProgramEpisode::whereMonth('design_completed_at', now()->month)
                    ->whereYear('design_completed_at', now()->year)
                    ->count()
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
}

