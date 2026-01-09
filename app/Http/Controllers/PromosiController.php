<?php

namespace App\Http\Controllers;

use App\Models\PromosiBTS;
use App\Models\MusicSubmission;
use App\Models\ProgramEpisode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PromosiController extends Controller
{
    /**
     * Get BTS assignments for Promosi
     */
    public function getBTSAssignments(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $assignments = PromosiBTS::with(['submission', 'shootingSchedule'])
                ->where('created_by', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $assignments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving BTS assignments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create BTS assignment
     */
    public function createBTS(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'shooting_schedule_id' => 'nullable|exists:shooting_run_sheets,id',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bts = PromosiBTS::create([
                'submission_id' => $request->submission_id,
                'shooting_schedule_id' => $request->shooting_schedule_id,
                'notes' => $request->notes,
                'status' => 'pending',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'BTS assignment created successfully.',
                'data' => $bts->load(['submission', 'shootingSchedule'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating BTS assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload BTS video
     */
    public function uploadBTSVideo($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'bts_video' => 'required|file|mimes:mp4,avi,mov|max:102400' // 100MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('bts_video');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('bts_videos', $filename, 'public');

            $bts->uploadBTSVideo($path, asset('storage/' . $path));

            return response()->json([
                'success' => true,
                'message' => 'BTS video uploaded successfully.',
                'data' => [
                    'bts_video_path' => $path,
                    'bts_video_url' => asset('storage/' . $path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading BTS video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload talent photos
     */
    public function uploadTalentPhotos($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'talent_photos' => 'required|array|min:1',
                'talent_photos.*' => 'file|mimes:jpg,jpeg,png|max:10240' // 10MB max per photo
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $photos = [];
            foreach ($request->file('talent_photos') as $index => $photo) {
                $filename = time() . '_' . $index . '_' . $photo->getClientOriginalName();
                $path = $photo->storeAs('talent_photos', $filename, 'public');
                
                $photos[] = [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'original_name' => $photo->getClientOriginalName(),
                    'size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType()
                ];
            }

            $bts->uploadTalentPhotos($photos);

            return response()->json([
                'success' => true,
                'message' => 'Talent photos uploaded successfully.',
                'data' => [
                    'talent_photos' => $photos
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading talent photos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete BTS work
     */
    public function completeBTS($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            if (!$bts->canBeCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'BTS work cannot be completed. Please ensure BTS video and talent photos are uploaded.'
                ], 400);
            }

            $bts->completeWork();

            return response()->json([
                'success' => true,
                'message' => 'BTS work completed successfully.',
                'data' => $bts->fresh(['submission', 'shootingSchedule'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing BTS work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start BTS work
     */
    public function startBTS($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            if (!$bts->canBeStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'BTS work cannot be started.'
                ], 400);
            }

            $bts->startWork();

            return response()->json([
                'success' => true,
                'message' => 'BTS work started successfully.',
                'data' => $bts->fresh(['submission', 'shootingSchedule'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting BTS work: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // PROGRAM REGULAR WORKFLOW
    // ========================================

    /**
     * Get episodes for promosi (TAHAP 1 - Saat Produksi)
     * GET /api/promosi/episodes/shooting-schedule
     */
    public function getEpisodesWithShootingSchedule(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular'])
                ->whereNotNull('production_date')
                ->whereNull('promosi_bts_completed_at')
                ->where('status', '!=', 'aired');

            // Filter upcoming shootings
            if ($request->get('upcoming', false)) {
                $query->whereDate('production_date', '>=', today());
            }

            $episodes = $query->orderBy('production_date', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes with shooting schedule retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * TAHAP 1: Create BTS & Upload Talent Photos
     * POST /api/promosi/episodes/{id}/create-bts
     */
    public function createBTSForEpisode(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'bts_video_files' => 'nullable|array',
                'bts_video_files.*' => 'file|mimes:mp4,mov,avi|max:512000',
                'bts_video_urls' => 'nullable|array',
                'bts_video_urls.*' => 'url',
                'talent_photo_files' => 'nullable|array',
                'talent_photo_files.*' => 'file|mimes:jpg,jpeg,png|max:10240',
                'talent_photo_urls' => 'nullable|array',
                'talent_photo_urls.*' => 'url',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload BTS videos
            $btsVideoUrls = $request->bts_video_urls ?? [];
            if ($request->hasFile('bts_video_files')) {
                foreach ($request->file('bts_video_files') as $file) {
                    $path = $file->store('promosi/bts-videos', 'public');
                    $btsVideoUrls[] = Storage::url($path);
                }
            }

            // Upload talent photos
            $talentPhotoUrls = $request->talent_photo_urls ?? [];
            if ($request->hasFile('talent_photo_files')) {
                foreach ($request->file('talent_photo_files') as $file) {
                    $path = $file->store('promosi/talent-photos', 'public');
                    $talentPhotoUrls[] = Storage::url($path);
                }
            }

            // Update episode
            $episode->update([
                'promosi_bts_video_urls' => $btsVideoUrls,
                'promosi_talent_photo_urls' => $talentPhotoUrls,
                'promosi_bts_notes' => $request->notes,
                'promosi_bts_completed_at' => now(),
                'promosi_bts_completed_by' => auth()->id()
            ]);

            // Notify Design Grafis (mereka butuh talent photos untuk thumbnail)
            // TODO: Implement notification

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'BTS content uploaded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating BTS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes for highlight creation (TAHAP 2 - Setelah Publikasi)
     * GET /api/promosi/episodes/published
     */
    public function getPublishedEpisodes(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular'])
                ->where('status', 'aired')
                ->whereNotNull('youtube_url')
                ->whereNotNull('website_url')
                ->whereNull('promosi_highlight_completed_at');

            $episodes = $query->orderBy('actual_air_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Published episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * TAHAP 2: Create Highlight Content (IG Story, FB Reels)
     * POST /api/promosi/episodes/{id}/create-highlight
     */
    public function createHighlight(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            // Validate episode is published
            if ($episode->status !== 'aired') {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode must be aired before creating highlight'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'ig_story_files' => 'nullable|array',
                'ig_story_files.*' => 'file|mimes:mp4,mov,jpg,jpeg,png|max:51200',
                'ig_story_urls' => 'nullable|array',
                'ig_story_urls.*' => 'url',
                'fb_reel_files' => 'nullable|array',
                'fb_reel_files.*' => 'file|mimes:mp4,mov|max:102400',
                'fb_reel_urls' => 'nullable|array',
                'fb_reel_urls.*' => 'url',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload IG Story content
            $igStoryUrls = $request->ig_story_urls ?? [];
            if ($request->hasFile('ig_story_files')) {
                foreach ($request->file('ig_story_files') as $file) {
                    $path = $file->store('promosi/ig-stories', 'public');
                    $igStoryUrls[] = Storage::url($path);
                }
            }

            // Upload FB Reel content
            $fbReelUrls = $request->fb_reel_urls ?? [];
            if ($request->hasFile('fb_reel_files')) {
                foreach ($request->file('fb_reel_files') as $file) {
                    $path = $file->store('promosi/fb-reels', 'public');
                    $fbReelUrls[] = Storage::url($path);
                }
            }

            // Update episode
            $episode->update([
                'promosi_ig_story_urls' => $igStoryUrls,
                'promosi_fb_reel_urls' => $fbReelUrls,
                'promosi_highlight_notes' => $request->notes,
                'promosi_highlight_completed_at' => now(),
                'promosi_highlight_completed_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Highlight content uploaded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating highlight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share to social media & upload proof
     * POST /api/promosi/episodes/{id}/share-social-media
     */
    public function shareSocialMedia(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'platform' => 'required|in:facebook,instagram,tiktok,whatsapp',
                'post_url' => 'nullable|url',
                'proof_screenshot_file' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'proof_screenshot_url' => 'nullable|url',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload proof screenshot
            $proofUrl = $request->proof_screenshot_url;
            if ($request->hasFile('proof_screenshot_file')) {
                $path = $request->file('proof_screenshot_file')->store('promosi/proofs', 'public');
                $proofUrl = Storage::url($path);
            }

            // Store sharing info
            $shares = $episode->promosi_social_shares ?? [];
            $shares[] = [
                'platform' => $request->platform,
                'post_url' => $request->post_url,
                'proof_url' => $proofUrl,
                'notes' => $request->notes,
                'shared_at' => now(),
                'shared_by' => auth()->id()
            ];

            $episode->update([
                'promosi_social_shares' => $shares
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Social media share recorded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording share: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my promosi tasks
     * GET /api/promosi/my-tasks
     */
    public function getMyPromosiTasks(Request $request): JsonResponse
    {
        try {
            $tasks = [
                'pending_bts' => ProgramEpisode::whereNotNull('production_date')
                    ->whereNull('promosi_bts_completed_at')
                    ->orderBy('production_date', 'asc')
                    ->get(),
                'pending_highlight' => ProgramEpisode::where('status', 'aired')
                    ->whereNotNull('youtube_url')
                    ->whereNull('promosi_highlight_completed_at')
                    ->orderBy('actual_air_date', 'desc')
                    ->get(),
                'upcoming_shootings' => ProgramEpisode::whereDate('production_date', '>=', today())
                    ->whereDate('production_date', '<=', today()->addDays(7))
                    ->orderBy('production_date', 'asc')
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
     * GET /api/promosi/statistics
     */
    public function getPromosiStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'bts_completed_this_month' => ProgramEpisode::whereNotNull('promosi_bts_completed_at')
                    ->whereMonth('promosi_bts_completed_at', now()->month)
                    ->count(),
                'highlights_created_this_month' => ProgramEpisode::whereNotNull('promosi_highlight_completed_at')
                    ->whereMonth('promosi_highlight_completed_at', now()->month)
                    ->count(),
                'total_social_shares' => DB::table('program_episodes')
                    ->whereNotNull('promosi_social_shares')
                    ->count(),
                'pending_bts' => ProgramEpisode::whereNotNull('production_date')
                    ->whereNull('promosi_bts_completed_at')
                    ->count(),
                'pending_highlights' => ProgramEpisode::where('status', 'aired')
                    ->whereNull('promosi_highlight_completed_at')
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
